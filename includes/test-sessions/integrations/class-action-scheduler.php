<?php
/**
 * Action Scheduler integration: background jobs queued during a session.
 *
 * Plugins such as WooCommerce queue jobs for each object a test creates
 * (lookup table updates, analytics imports). Left behind, they run against
 * deleted objects and their logs linger for a month. Any plugin bundling
 * Action Scheduler benefits from this integration.
 *
 * Only one-off and async jobs are recorded. Recurring jobs are site-wide
 * maintenance that merely happened to be scheduled during a test request,
 * so they are never removed.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Integrations;

use PIE\PresstestCompanion\TestSessions\Tracker;

/**
 * Tracking and cleanup for Action Scheduler jobs.
 */
class Action_Scheduler extends Abstract_Integration {

	/**
	 * Integration slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'action-scheduler';
	}

	/**
	 * Integration display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Action Scheduler';
	}

	/**
	 * Whether any active plugin has loaded Action Scheduler.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return class_exists( 'ActionScheduler' );
	}

	/**
	 * Records jobs as they are stored.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'action_scheduler_stored_action', array( $this, 'track_action' ) );
	}

	/**
	 * Records a one-off or async job.
	 *
	 * @param int $action_id Action ID.
	 * @return void
	 */
	public function track_action( $action_id ): void {
		$action = \ActionScheduler::store()->fetch_action( (int) $action_id );

		if ( true === $action->get_schedule()->is_recurring() ) {
			return;
		}

		Tracker::record( 'as_action', (int) $action_id );
	}

	/**
	 * Jobs go last, so ones queued while other objects are being deleted are
	 * picked up in the next cleanup pass.
	 *
	 * @return array<string, array{priority: int, callback: callable}>
	 */
	public function get_cleanup_handlers(): array {
		return array(
			'as_action' => array(
				'priority' => 100,
				'callback' => array( $this, 'delete_action' ),
			),
		);
	}

	/**
	 * Deletes a job. Action Scheduler's own logger removes its log entries.
	 *
	 * @param int $action_id Action ID.
	 * @return bool True once the job is gone.
	 */
	public function delete_action( int $action_id ): bool {
		try {
			\ActionScheduler::store()->delete_action( $action_id );
		} catch ( \InvalidArgumentException $e ) {
			// Thrown when the action no longer exists — nothing to do.
			return true;
		}

		return true;
	}
}
