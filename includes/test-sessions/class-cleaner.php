<?php
/**
 * Removes everything a test session created.
 *
 * Cleanup runs inside the session (Session_Context::run_as) so that:
 *   - emails triggered while deleting (cancellation notices etc.) are blocked;
 *   - anything created as a side effect is recorded and removed in a later pass.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

use PIE\PresstestCompanion\TestSessions\Integrations\Integration_Registry;

/**
 * Runs integration cleanup handlers over a session's recorded objects.
 */
class Cleaner {

	/**
	 * Maximum passes over the objects table. Each pass picks up objects
	 * recorded by the previous one; three is plenty in practice.
	 */
	const MAX_PASSES = 3;

	/**
	 * Returned by a cleanup handler that deliberately left an object in place,
	 * e.g. a shared term real content still uses.
	 */
	const KEPT = 'kept';

	/**
	 * Default seconds to wait for in-flight session requests before cleaning.
	 * Filterable via presstest_companion_cleanup_wait.
	 */
	const DEFAULT_WAIT = 30;

	/**
	 * Session data access.
	 *
	 * @var Session_Repository
	 */
	private Session_Repository $repository;

	/**
	 * Source of cleanup handlers.
	 *
	 * @var Integration_Registry
	 */
	private Integration_Registry $registry;

	/**
	 * Sets up the cleaner.
	 *
	 * @param Session_Repository   $repository Session data access.
	 * @param Integration_Registry $registry   Source of cleanup handlers.
	 */
	public function __construct( Session_Repository $repository, Integration_Registry $registry ) {
		$this->repository = $repository;
		$this->registry   = $registry;
	}

	/**
	 * Cleans up a session and marks it finished.
	 *
	 * @param Session $session      Session to clean.
	 * @param string  $final_status Session::STATUS_ENDED or Session::STATUS_EXPIRED.
	 * @return array<string, mixed>|null Cleanup summary, or null if another
	 *                                   process is already cleaning this session.
	 */
	public function clean( Session $session, string $final_status ): ?array {
		if ( false === $this->repository->transition( $session->get_id(), Session::STATUS_ACTIVE, Session::STATUS_CLEANING ) ) {
			return null;
		}

		// Large sessions can take a while; don't let a short limit leave data behind.
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}

		// The session is closed to new requests now; let any still running
		// finish, or they could recreate data after it has been deleted.
		Session_Context::release();
		$this->wait_for_requests( $session->get_id() );

		$summary = Session_Context::run_as( $session, fn(): array => $this->purge( $session ) );

		$summary['emails_captured'] = $this->repository->delete_emails( $session->get_id() );

		$this->repository->finish( $session->get_id(), $final_status, $summary );

		return $summary;
	}

	/**
	 * Cleans up every unfinished session now, including any left mid-cleanup
	 * by a crash or timeout. Used by the admin purge and on deactivation.
	 *
	 * @return int Number of sessions cleaned.
	 */
	public function clean_unfinished(): int {
		$cleaned = 0;

		foreach ( $this->repository->unfinished_ids() as $session_id ) {
			// Put a session stuck in "cleaning" back to active so it can be claimed.
			$this->repository->transition( $session_id, Session::STATUS_CLEANING, Session::STATUS_ACTIVE );
			$session = $this->repository->find( $session_id );

			if ( null !== $session && null !== $this->clean( $session, Session::STATUS_ENDED ) ) {
				++$cleaned;
			}
		}

		return $cleaned;
	}

	/**
	 * Deletes the session's objects in handler priority order.
	 *
	 * @param Session $session Session being cleaned.
	 * @return array{deleted: array<string, int>, kept: array<string, int>, failed: string[], unhandled: array<string, int>}
	 */
	private function purge( Session $session ): array {
		$handlers  = $this->registry->cleanup_handlers();
		$deleted   = array();
		$kept      = array();
		$failed    = array();
		$unhandled = array();
		$attempted = array();

		for ( $pass = 1; $pass <= self::MAX_PASSES; $pass++ ) {
			$objects = array_filter(
				$this->repository->objects( $session->get_id() ),
				fn( array $record ): bool => ! isset( $attempted[ $record['id'] ] )
			);

			if ( array() === $objects ) {
				break;
			}

			usort(
				$objects,
				fn( array $a, array $b ): int => self::priority( $handlers, $a['type'] ) <=> self::priority( $handlers, $b['type'] )
			);

			$done = array();

			foreach ( $objects as $record ) {
				$attempted[ $record['id'] ] = true;
				$type                       = $record['type'];

				if ( ! isset( $handlers[ $type ] ) ) {
					$unhandled[ $type ] = ( $unhandled[ $type ] ?? 0 ) + 1;
					$done[]             = $record['id'];
					continue;
				}

				try {
					$result = call_user_func( $handlers[ $type ]['callback'], $record['object_id'], $record['data'], $session );
					$error  = 'handler returned false';
				} catch ( \Throwable $e ) {
					$result = false;
					$error  = $e->getMessage();
				}

				if ( self::KEPT === $result ) {
					$kept[ $type ] = ( $kept[ $type ] ?? 0 ) + 1;
				} elseif ( true === $result ) {
					$deleted[ $type ] = ( $deleted[ $type ] ?? 0 ) + 1;
				} else {
					$failed[] = sprintf( '%s #%d: %s', $type, $record['object_id'], $error );
				}

				// Failed objects are forgotten too: retrying won't help, and the
				// summary records them for the admin to follow up.
				$done[] = $record['id'];
			}

			$this->repository->forget_objects( $done );
		}

		return array(
			'deleted'   => $deleted,
			'kept'      => $kept,
			'failed'    => $failed,
			'unhandled' => $unhandled,
		);
	}

	/**
	 * Waits until no requests are running inside the session, up to a limit.
	 *
	 * A request killed without running shutdown (e.g. a PHP-FPM timeout)
	 * never decrements its count, hence the limit rather than waiting forever.
	 *
	 * @param int $session_id Session ID.
	 * @return void
	 */
	private function wait_for_requests( int $session_id ): void {
		$deadline = microtime( true ) + (int) apply_filters( 'presstest_companion_cleanup_wait', self::DEFAULT_WAIT );

		while ( 0 < $this->repository->active_requests( $session_id ) && microtime( true ) < $deadline ) {
			usleep( 250000 );
		}
	}

	/**
	 * Priority for an object type; unknown types sort last.
	 *
	 * @param array<string, array{priority: int, callback: callable}> $handlers Cleanup handlers.
	 * @param string                                                  $type     Object type.
	 * @return int
	 */
	private static function priority( array $handlers, string $type ): int {
		return isset( $handlers[ $type ] ) ? (int) $handlers[ $type ]['priority'] : PHP_INT_MAX;
	}
}
