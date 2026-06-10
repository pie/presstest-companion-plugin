<?php
/**
 * Scheduled test runner.
 *
 * Handles registering, managing, and executing scheduled Presstest runs.
 * Uses Action Scheduler when available (preferred for reliability), falling
 * back to WP-Cron for sites that don't have it installed.
 *
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

/** The WP action hook name fired on each scheduled run. */
const PRESSTEST_CRON_HOOK = 'presstest_companion_run_scheduled_tests';

add_filter( 'cron_schedules', __NAMESPACE__ . '\add_weekly_cron_schedule' ); // phpcs:ignore WordPress.WP.CronInterval
add_action( PRESSTEST_CRON_HOOK, __NAMESPACE__ . '\run_presstest_scheduled_test' );
add_action( 'update_option_presstest_companion_cron_enabled', __NAMESPACE__ . '\sync_presstest_cron' );
add_action( 'update_option_presstest_companion_cron_schedule', __NAMESPACE__ . '\sync_presstest_cron' );

/**
 * Returns the available schedule options.
 *
 * Each entry has a translated label and an interval in seconds so the caller
 * doesn't need to maintain a separate mapping.
 *
 * @since 1.0.0
 * @return array<string, array{label: string, interval: int}>
 */
function get_cron_schedule_options(): array {
	return array(
		'hourly'     => array(
			'label'    => __( 'Hourly', 'presstest-companion' ),
			'interval' => HOUR_IN_SECONDS,
		),
		'twicedaily' => array(
			'label'    => __( 'Twice Daily', 'presstest-companion' ),
			'interval' => 12 * HOUR_IN_SECONDS,
		),
		'daily'      => array(
			'label'    => __( 'Daily', 'presstest-companion' ),
			'interval' => DAY_IN_SECONDS,
		),
		'weekly'     => array(
			'label'    => __( 'Weekly', 'presstest-companion' ),
			'interval' => WEEK_IN_SECONDS,
		),
	);
}

/**
 * Register a weekly WP-Cron interval so the 'weekly' schedule slug is valid
 * when Action Scheduler is not present.
 *
 * @since 1.0.0
 * @param array $schedules Existing WP-Cron schedules.
 * @return array
 */
function add_weekly_cron_schedule( array $schedules ): array {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'presstest-companion' ),
		);
	}
	return $schedules;
}

/**
 * Whether Action Scheduler is available on this site.
 *
 * @since 1.0.0
 * @return bool
 */
function has_action_scheduler(): bool {
	return function_exists( 'as_schedule_recurring_action' );
}

/**
 * Schedule the recurring test run.
 *
 * Uses Action Scheduler when available; falls back to WP-Cron.
 * Always call unschedule_presstest_cron() first when changing the interval.
 *
 * @since 1.0.0
 * @param string $schedule_slug One of: hourly, twicedaily, daily, weekly.
 * @return void
 */
function schedule_presstest_cron( string $schedule_slug ): void {
	$options = get_cron_schedule_options();

	if ( ! isset( $options[ $schedule_slug ] ) ) {
		return;
	}

	if ( has_action_scheduler() ) {
		as_schedule_recurring_action(
			time(),
			$options[ $schedule_slug ]['interval'],
			PRESSTEST_CRON_HOOK,
			array(),
			'presstest-companion'
		);
		return;
	}

	wp_schedule_event( time(), $schedule_slug, PRESSTEST_CRON_HOOK );
}

/**
 * Remove all scheduled test run events from both Action Scheduler and WP-Cron.
 *
 * Cleans up both schedulers so switching between them leaves no orphaned events.
 *
 * @since 1.0.0
 * @return void
 */
function unschedule_presstest_cron(): void {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( PRESSTEST_CRON_HOOK, array(), 'presstest-companion' );
	}

	wp_clear_scheduled_hook( PRESSTEST_CRON_HOOK );
}

/**
 * Synchronise the scheduled event with the current settings.
 *
 * Hooked to option updates for the enabled flag and the schedule slug.
 * Always unschedules first so that changing the interval takes effect immediately.
 *
 * @since 1.0.0
 * @return void
 */
function sync_presstest_cron(): void {
	$enabled  = (bool) get_option( 'presstest_companion_cron_enabled', false );
	$schedule = sanitize_key( get_option( 'presstest_companion_cron_schedule', 'daily' ) );

	unschedule_presstest_cron();

	if ( true === $enabled ) {
		schedule_presstest_cron( $schedule );
	}
}

/**
 * Execute the scheduled test run.
 *
 * Reads all cron-specific settings from options and dispatches a request to
 * the Presstest server. Silently exits if any required value is missing or
 * the feature has been disabled since the event was last scheduled.
 *
 * @since 1.0.0
 * @return void
 */
function run_presstest_scheduled_test() {
	if ( ! (bool) get_option( 'presstest_companion_cron_enabled', false ) ) {
		return;
	}

	$url     = esc_url_raw( get_option( 'presstest_companion_cron_url', '' ) );
	$tests   = sanitize_text_field( get_option( 'presstest_companion_cron_tests', '' ) );
	$browser = sanitize_key( get_option( 'presstest_companion_cron_browser', 'chromium' ) );
	$user_id = absint( get_option( 'presstest_companion_cron_user_id', 0 ) );

	if ( '' === $url || '' === $tests || 0 === $user_id ) {
		return;
	}

	dispatch_presstest_request( $url, $tests, $browser, $user_id );
}
