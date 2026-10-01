<?php
/**
 * Database access for test sessions, their objects, and captured emails.
 *
 * All SQL for the session system lives here so the rest of the code works with
 * Session objects and plain arrays.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

// Custom tables: there is nothing to cache across requests, and the only
// interpolated values are table names from Schema — all data goes through
// $wpdb->prepare() placeholders.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

/**
 * Reads and writes the session tables.
 */
class Session_Repository {

	/**
	 * How long a session stays open before the expiry job cleans it up.
	 * Filterable via presstest_companion_session_ttl.
	 */
	const DEFAULT_TTL = 2 * HOUR_IN_SECONDS;

	/**
	 * How long ended sessions are kept for the admin history view.
	 */
	const HISTORY_RETENTION = 30 * DAY_IN_SECONDS;

	/**
	 * Creates a new active session.
	 *
	 * @param string $label Description of the run, e.g. "wordpress-core (chromium)".
	 * @return array{session: Session, token: string} The session and its plain token —
	 *                                               the token is only ever available here.
	 */
	public function create( string $label ): array {
		global $wpdb;

		$token = bin2hex( random_bytes( 32 ) );
		$ttl   = (int) apply_filters( 'presstest_companion_session_ttl', self::DEFAULT_TTL );
		$now   = time();

		$wpdb->insert(
			Schema::sessions_table(),
			array(
				'token_hash' => self::hash_token( $token ),
				'status'     => Session::STATUS_ACTIVE,
				'label'      => mb_substr( $label, 0, 255 ),
				'created_at' => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', $now + $ttl ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return array(
			'session' => $this->find( (int) $wpdb->insert_id ),
			'token'   => $token,
		);
	}

	/**
	 * Finds a session by ID.
	 *
	 * @param int $id Session ID.
	 * @return Session|null Null if no such session exists.
	 */
	public function find( int $id ): ?Session {
		global $wpdb;

		$table = Schema::sessions_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return null !== $row ? new Session( $row ) : null;
	}

	/**
	 * Finds an open session whose token matches.
	 *
	 * @param int    $id    Session ID.
	 * @param string $token Plain session token.
	 * @return Session|null Null if the session doesn't exist, isn't open, or the token is wrong.
	 */
	public function find_open_by_credentials( int $id, string $token ): ?Session {
		global $wpdb;

		$table = Schema::sessions_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		if ( null === $row || ! hash_equals( (string) $row->token_hash, self::hash_token( $token ) ) ) {
			return null;
		}

		$session = new Session( $row );
		return $session->is_open() ? $session : null;
	}

	/**
	 * Atomically moves a session from one status to another.
	 *
	 * Used to claim a session for cleanup: only one caller can win the
	 * active → cleaning transition, so cleanup never runs twice concurrently.
	 *
	 * @param int    $id   Session ID.
	 * @param string $from Expected current status.
	 * @param string $to   New status.
	 * @return bool True if this call made the transition.
	 */
	public function transition( int $id, string $from, string $to ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			Schema::sessions_table(),
			array( 'status' => $to ),
			array(
				'id'     => $id,
				'status' => $from,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		return 1 === $updated;
	}

	/**
	 * Marks a session finished and stores its cleanup summary.
	 *
	 * @param int                  $id      Session ID.
	 * @param string               $status  Session::STATUS_ENDED or Session::STATUS_EXPIRED.
	 * @param array<string, mixed> $summary Cleanup result.
	 * @return void
	 */
	public function finish( int $id, string $status, array $summary ): void {
		global $wpdb;

		$wpdb->update(
			Schema::sessions_table(),
			array(
				'status'   => $status,
				'ended_at' => gmdate( 'Y-m-d H:i:s' ),
				'summary'  => wp_json_encode( $summary ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Counts a request as running inside a session.
	 *
	 * @param int $id Session ID.
	 * @return void
	 */
	public function begin_request( int $id ): void {
		global $wpdb;

		$table = Schema::sessions_table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET active_requests = active_requests + 1 WHERE id = %d", $id ) );
	}

	/**
	 * Marks a request inside a session as finished.
	 *
	 * @param int $id Session ID.
	 * @return void
	 */
	public function end_request( int $id ): void {
		global $wpdb;

		$table = Schema::sessions_table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET active_requests = GREATEST( active_requests - 1, 0 ) WHERE id = %d", $id ) );
	}

	/**
	 * Number of requests currently running inside a session.
	 *
	 * @param int $id Session ID.
	 * @return int
	 */
	public function active_requests( int $id ): int {
		global $wpdb;

		$table = Schema::sessions_table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT active_requests FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Stored cleanup summary of a finished session.
	 *
	 * @param int $id Session ID.
	 * @return array<string, mixed>|null Null while the session is unfinished.
	 */
	public function summary( int $id ): ?array {
		global $wpdb;

		$table   = Schema::sessions_table();
		$summary = $wpdb->get_var( $wpdb->prepare( "SELECT summary FROM {$table} WHERE id = %d", $id ) );

		return null !== $summary ? json_decode( $summary, true ) : null;
	}

	/**
	 * IDs of active sessions past their expiry time.
	 *
	 * @return int[]
	 */
	public function expired_ids(): array {
		global $wpdb;

		$table = Schema::sessions_table();
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = %s AND expires_at < %s",
				Session::STATUS_ACTIVE,
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * IDs of all sessions still holding test data (active or mid-cleanup).
	 *
	 * @return int[]
	 */
	public function unfinished_ids(): array {
		global $wpdb;

		$table = Schema::sessions_table();
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status IN ( %s, %s )",
				Session::STATUS_ACTIVE,
				Session::STATUS_CLEANING
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Most recent sessions with object counts, for the admin view.
	 *
	 * @param int $limit Maximum rows to return.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 20 ): array {
		global $wpdb;

		$sessions = Schema::sessions_table();
		$objects  = Schema::objects_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.status, s.label, s.created_at, s.expires_at, s.ended_at, s.summary, COUNT( o.id ) AS object_count
				FROM {$sessions} s LEFT JOIN {$objects} o ON o.session_id = s.id
				GROUP BY s.id ORDER BY s.id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return array_map(
			function ( array $row ): array {
				$row['id']           = (int) $row['id'];
				$row['object_count'] = (int) $row['object_count'];
				$row['summary']      = null !== $row['summary'] ? json_decode( $row['summary'], true ) : null;
				return $row;
			},
			$rows
		);
	}

	/**
	 * Deletes finished sessions older than the retention period.
	 *
	 * @return void
	 */
	public function prune_history(): void {
		global $wpdb;

		$table = Schema::sessions_table();
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN ( %s, %s ) AND created_at < %s",
				Session::STATUS_ENDED,
				Session::STATUS_EXPIRED,
				gmdate( 'Y-m-d H:i:s', time() - self::HISTORY_RETENTION )
			)
		);
	}

	/**
	 * Records an object as created by a session. Recording the same object
	 * twice is a no-op.
	 *
	 * @param int        $session_id Session ID.
	 * @param string     $type       Object type, matching a registered cleanup handler.
	 * @param int        $object_id  Object ID.
	 * @param array|null $data       Extra data the cleanup handler needs, if any.
	 * @return void
	 */
	public function record_object( int $session_id, string $type, int $object_id, ?array $data = null ): void {
		global $wpdb;

		$table = Schema::objects_table();
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} ( session_id, object_type, object_id, data, created_at ) VALUES ( %d, %s, %d, %s, %s )",
				$session_id,
				$type,
				$object_id,
				null !== $data ? wp_json_encode( $data ) : '',
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Whether a session recorded a given object.
	 *
	 * @param int    $session_id Session ID.
	 * @param string $type       Object type.
	 * @param int    $object_id  Object ID.
	 * @return bool
	 */
	public function has_object( int $session_id, string $type, int $object_id ): bool {
		global $wpdb;

		$table = Schema::objects_table();
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$table} WHERE session_id = %d AND object_type = %s AND object_id = %d",
				$session_id,
				$type,
				$object_id
			)
		);

		return null !== $found;
	}

	/**
	 * Objects recorded by a session.
	 *
	 * @param int $session_id Session ID.
	 * @return array<int, array{id: int, type: string, object_id: int, data: array|null}>
	 */
	public function objects( int $session_id ): array {
		global $wpdb;

		$table = Schema::objects_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, object_type, object_id, data FROM {$table} WHERE session_id = %d ORDER BY id ASC", $session_id ),
			ARRAY_A
		);

		return array_map(
			fn( array $row ): array => array(
				'id'        => (int) $row['id'],
				'type'      => (string) $row['object_type'],
				'object_id' => (int) $row['object_id'],
				'data'      => '' !== $row['data'] ? json_decode( $row['data'], true ) : null,
			),
			$rows
		);
	}

	/**
	 * Removes object records that have been cleaned up.
	 *
	 * @param int[] $record_ids Primary keys from the objects table.
	 * @return void
	 */
	public function forget_objects( array $record_ids ): void {
		global $wpdb;

		if ( array() === $record_ids ) {
			return;
		}

		$table        = Schema::objects_table();
		$placeholders = implode( ', ', array_fill( 0, count( $record_ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ( {$placeholders} )", $record_ids ) );
	}

	/**
	 * Stores an email captured during a session.
	 *
	 * @param int                  $session_id Session ID.
	 * @param array<string, mixed> $email      Normalised email: recipients, subject, message, headers, attachments.
	 * @return void
	 */
	public function record_email( int $session_id, array $email ): void {
		global $wpdb;

		$wpdb->insert(
			Schema::emails_table(),
			array(
				'session_id'  => $session_id,
				'recipients'  => wp_json_encode( $email['recipients'] ),
				'subject'     => $email['subject'],
				'message'     => $email['message'],
				'headers'     => wp_json_encode( $email['headers'] ),
				'attachments' => wp_json_encode( $email['attachments'] ),
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Emails captured during a session, oldest first.
	 *
	 * @param int $session_id Session ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function emails( int $session_id ): array {
		global $wpdb;

		$table = Schema::emails_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE session_id = %d ORDER BY id ASC", $session_id ),
			ARRAY_A
		);

		return array_map(
			fn( array $row ): array => array(
				'id'          => (int) $row['id'],
				'recipients'  => json_decode( $row['recipients'], true ),
				'subject'     => $row['subject'],
				'message'     => $row['message'],
				'headers'     => json_decode( $row['headers'], true ),
				'attachments' => json_decode( $row['attachments'], true ),
				'created_at'  => $row['created_at'],
			),
			$rows
		);
	}

	/**
	 * Deletes a session's captured emails.
	 *
	 * @param int $session_id Session ID.
	 * @return int Number of emails deleted.
	 */
	public function delete_emails( int $session_id ): int {
		global $wpdb;

		return (int) $wpdb->delete( Schema::emails_table(), array( 'session_id' => $session_id ), array( '%d' ) );
	}

	/**
	 * Hashes a session token for storage and comparison.
	 *
	 * Tokens are 256 bits of randomness, so a fast hash is sufficient — there
	 * is nothing to brute-force.
	 *
	 * @param string $token Plain token.
	 * @return string SHA-256 hex digest.
	 */
	private static function hash_token( string $token ): string {
		return hash( 'sha256', $token );
	}
}
