<?php
/**
 * A single test session.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

/**
 * Immutable view of one row in the sessions table.
 */
class Session {

	/**
	 * Session is open: requests carrying its credentials are tracked.
	 */
	const STATUS_ACTIVE = 'active';

	/**
	 * Cleanup is in progress — set atomically so it only ever runs once.
	 */
	const STATUS_CLEANING = 'cleaning';

	/**
	 * Cleanup finished after the runner ended the session.
	 */
	const STATUS_ENDED = 'ended';

	/**
	 * Cleanup finished after the session outlived its expiry time.
	 */
	const STATUS_EXPIRED = 'expired';

	/**
	 * Session ID.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Current status, one of the STATUS_* constants.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Human-readable description of the run, e.g. suites and browser.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Creation time (GMT, MySQL format).
	 *
	 * @var string
	 */
	private string $created_at;

	/**
	 * Expiry time (GMT, MySQL format).
	 *
	 * @var string
	 */
	private string $expires_at;

	/**
	 * Builds a session from a database row.
	 *
	 * @param object $row Row from the sessions table.
	 */
	public function __construct( object $row ) {
		$this->id         = (int) $row->id;
		$this->status     = (string) $row->status;
		$this->label      = (string) $row->label;
		$this->created_at = (string) $row->created_at;
		$this->expires_at = (string) $row->expires_at;
	}

	/**
	 * Session ID.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Current status.
	 *
	 * @return string One of the STATUS_* constants.
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Description of the run.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Creation time.
	 *
	 * @return string GMT datetime in MySQL format.
	 */
	public function get_created_at(): string {
		return $this->created_at;
	}

	/**
	 * Expiry time.
	 *
	 * @return string GMT datetime in MySQL format.
	 */
	public function get_expires_at(): string {
		return $this->expires_at;
	}

	/**
	 * Whether requests may still act within this session.
	 *
	 * @return bool True if active and not yet expired.
	 */
	public function is_open(): bool {
		return self::STATUS_ACTIVE === $this->status && strtotime( $this->expires_at . ' UTC' ) > time();
	}

	/**
	 * Shape returned by the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'status'     => $this->status,
			'label'      => $this->label,
			'created_at' => $this->created_at,
			'expires_at' => $this->expires_at,
		);
	}
}
