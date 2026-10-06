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
	 * Returned by a cleanup handler that must wait for another handler first,
	 * e.g. an order post waiting for the order's own cleanup. The record is
	 * kept for the next attempt, and it isn't reported as a failure.
	 */
	const DEFERRED = 'deferred';

	/**
	 * Default seconds to wait for in-flight session requests before cleaning.
	 * Filterable via presstest_companion_cleanup_wait.
	 */
	const DEFAULT_WAIT = 30;

	/**
	 * Cleanup attempts before a session that keeps leaving data behind stops
	 * being retried hourly (roughly a day). Filterable via
	 * presstest_companion_cleanup_max_attempts.
	 */
	const MAX_ATTEMPTS = 24;

	/**
	 * Seconds after which a cleanup claim is treated as abandoned (e.g. the
	 * request hit a PHP timeout) and released for retry. Well beyond the
	 * 300-second limit a cleanup runs under, so a live cleanup is never
	 * released while it is still working.
	 */
	const STALE_CLAIM_AGE = HOUR_IN_SECONDS;

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
	 * Cleans up a session (active, or left incomplete/failed by an earlier
	 * attempt).
	 *
	 * The session is only marked finished once no records remain. Anything
	 * that couldn't be removed keeps its record and leaves the session
	 * incomplete, so the expiry job retries it — or failed, after
	 * MAX_ATTEMPTS, so only the admin purge retries it.
	 *
	 * @param Session $session      Session to clean.
	 * @param string  $final_status Status once fully cleaned: Session::STATUS_ENDED or Session::STATUS_EXPIRED.
	 * @return array<string, mixed>|null Cleanup summary, or null if the
	 *                                   session isn't cleanable or another
	 *                                   process is already cleaning it.
	 */
	public function clean( Session $session, string $final_status ): ?array {
		if (
			! in_array( $session->get_status(), Session::CLEANABLE_STATUSES, true )
			|| false === $this->repository->claim_for_cleanup( $session->get_id(), $session->get_status() )
		) {
			return null;
		}

		// Large sessions can take a while; don't let a short limit leave data behind.
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}

		// The session is closed to new requests now; let any still running
		// finish, or they could recreate data after it has been deleted.
		// Counts left by killed requests are cleared first so they never
		// hold cleanup up.
		Session_Context::release();
		$this->repository->reset_leaked_requests( $session->get_id(), self::STALE_CLAIM_AGE );

		if ( false === $this->wait_for_requests( $session->get_id() ) ) {
			return $this->defer_for_requests( $session );
		}

		$result   = Session_Context::run_as( $session, fn(): array => $this->purge( $session ) );
		$previous = $this->repository->summary( $session->get_id() ) ?? array();
		$attempts = (int) ( $previous['attempts'] ?? 0 ) + 1;

		// Totals accumulate across attempts; failures describe the latest one.
		$summary = array(
			'deleted'         => self::add_counts( (array) ( $previous['deleted'] ?? array() ), $result['deleted'] ),
			'kept'            => self::add_counts( (array) ( $previous['kept'] ?? array() ), $result['kept'] ),
			'deferred'        => $result['deferred'],
			'failed'          => $result['failed'],
			'unhandled'       => $result['unhandled'],
			'outstanding'     => $this->repository->count_objects( $session->get_id() ),
			'attempts'        => $attempts,
			'emails_captured' => (int) ( $previous['emails_captured'] ?? 0 ) + $this->repository->delete_emails( $session->get_id() ),
		);

		$max_attempts = (int) apply_filters( 'presstest_companion_cleanup_max_attempts', self::MAX_ATTEMPTS );

		if ( 0 === $summary['outstanding'] ) {
			$status = $final_status;
		} else {
			$status = $attempts >= $max_attempts ? Session::STATUS_FAILED : Session::STATUS_INCOMPLETE;
		}

		$this->repository->finish( $session->get_id(), $status, $summary );

		return $summary;
	}

	/**
	 * Cleans up every session that may still hold test data — active,
	 * incomplete, failed, or abandoned mid-cleanup. Used by the admin purge
	 * and on deactivation.
	 *
	 * @return int Number of sessions now fully cleaned.
	 */
	public function clean_unfinished(): int {
		$cleaned = 0;

		// Only abandoned claims are released. A cleanup still running (e.g. the
		// runner ending its session right now) is left to finish, so two
		// cleanups never restore stock or coupons for the same session at once.
		$this->repository->release_stuck_cleanups( self::STALE_CLAIM_AGE );

		foreach ( $this->repository->unfinished_ids() as $session_id ) {
			$session = $this->repository->find( $session_id );
			$summary = null !== $session ? $this->clean( $session, Session::STATUS_ENDED ) : null;

			if ( null !== $summary && 0 === $summary['outstanding'] ) {
				++$cleaned;
			}
		}

		return $cleaned;
	}

	/**
	 * Deletes the session's objects in handler priority order.
	 *
	 * Records are only forgotten once their object is removed (or
	 * deliberately kept). Failed and unhandled objects keep their records, so
	 * a later attempt can retry them — e.g. after a transient database error,
	 * or once a deactivated integration is active again.
	 *
	 * @param Session $session Session being cleaned.
	 * @return array{deleted: array<string, int>, kept: array<string, int>, deferred: array<string, int>, failed: string[], unhandled: array<string, int>}
	 */
	private function purge( Session $session ): array {
		$handlers  = $this->registry->cleanup_handlers();
		$deleted   = array();
		$kept      = array();
		$deferred  = array();
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
					continue;
				}

				try {
					$result = call_user_func( $handlers[ $type ]['callback'], $record['object_id'], $record['data'], $session );
					$error  = 'handler returned false';
				} catch ( \Throwable $e ) {
					$result = false;
					$error  = $e->getMessage();
				}

				if ( self::DEFERRED === $result ) {
					$deferred[ $type ] = ( $deferred[ $type ] ?? 0 ) + 1;
				} elseif ( self::KEPT === $result ) {
					$kept[ $type ] = ( $kept[ $type ] ?? 0 ) + 1;
					$done[]        = $record['id'];
				} elseif ( true === $result ) {
					$deleted[ $type ] = ( $deleted[ $type ] ?? 0 ) + 1;
					$done[]           = $record['id'];
				} else {
					$failed[] = sprintf( '%s #%d: %s', $type, $record['object_id'], $error );
				}
			}

			$this->repository->forget_objects( $done );
		}

		return array(
			'deleted'   => $deleted,
			'kept'      => $kept,
			'deferred'  => $deferred,
			'failed'    => $failed,
			'unhandled' => $unhandled,
		);
	}

	/**
	 * Waits until no requests are running inside the session, up to a limit.
	 *
	 * @param int $session_id Session ID.
	 * @return bool True if every request finished; false if some are still running.
	 */
	private function wait_for_requests( int $session_id ): bool {
		$deadline = microtime( true ) + (int) apply_filters( 'presstest_companion_cleanup_wait', self::DEFAULT_WAIT );

		while ( 0 < $this->repository->active_requests( $session_id ) && microtime( true ) < $deadline ) {
			usleep( 250000 );
		}

		return 0 === $this->repository->active_requests( $session_id );
	}

	/**
	 * Postpones cleanup while requests are still running inside the session.
	 *
	 * Purging now would let those requests create data after the final count.
	 * Instead the session is left incomplete — still closed to new requests,
	 * while the running ones keep recording into it — and the hourly job
	 * tries again. Waiting doesn't count towards MAX_ATTEMPTS.
	 *
	 * @param Session $session Session being cleaned.
	 * @return array<string, mixed> The session's summary, noting the requests still running.
	 */
	private function defer_for_requests( Session $session ): array {
		$summary = array_merge(
			$this->repository->summary( $session->get_id() ) ?? array(),
			array(
				'in_flight'   => $this->repository->active_requests( $session->get_id() ),
				'outstanding' => $this->repository->count_objects( $session->get_id() ),
			)
		);

		$this->repository->finish( $session->get_id(), Session::STATUS_INCOMPLETE, $summary );

		return $summary;
	}

	/**
	 * Adds per-type counts together.
	 *
	 * @param array<string, int> $totals Running totals.
	 * @param array<string, int> $counts Counts to add.
	 * @return array<string, int>
	 */
	private static function add_counts( array $totals, array $counts ): array {
		foreach ( $counts as $type => $count ) {
			$totals[ $type ] = (int) ( $totals[ $type ] ?? 0 ) + (int) $count;
		}

		return $totals;
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
