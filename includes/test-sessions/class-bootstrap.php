<?php
/**
 * Wires the test session system into WordPress.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

use PIE\PresstestCompanion\TestSessions\Integrations\Integration_Registry;
use PIE\PresstestCompanion\TestSessions\Rest\Sessions_Controller;

/**
 * Entry points for plugin load, activation, deactivation, and cron.
 */
class Bootstrap {

	/**
	 * Hourly cron hook that cleans up sessions the runner never ended.
	 */
	const EXPIRY_HOOK = 'presstest_companion_expire_sessions';

	/**
	 * Integration registry, built once integrations can be registered.
	 *
	 * @var Integration_Registry|null
	 */
	private static ?Integration_Registry $registry = null;

	/**
	 * Hooks the session system in. Called on plugins_loaded.
	 *
	 * @return void
	 */
	public static function init(): void {
		Schema::maybe_upgrade();

		// Join the session (if this request carries credentials) straight
		// away, so the request is counted as in flight from its very start.
		Session_Context::current();

		( new Email_Capture( new Session_Repository() ) )->register();
		Notices::register();

		// After the theme loads, so a theme's functions.php can register
		// integrations too, and before init, when most content is created.
		add_action( 'after_setup_theme', array( self::class, 'register_integration_hooks' ), 100 );
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_action( self::EXPIRY_HOOK, array( self::class, 'expire_sessions' ) );
		add_action( 'init', array( self::class, 'schedule_expiry' ) );
	}

	/**
	 * The integration registry, built on first use.
	 *
	 * @return Integration_Registry
	 */
	public static function registry(): Integration_Registry {
		if ( null === self::$registry ) {
			self::$registry = new Integration_Registry();
		}

		return self::$registry;
	}

	/**
	 * Registers tracking hooks and guards for every active integration.
	 *
	 * @return void
	 */
	public static function register_integration_hooks(): void {
		foreach ( self::registry()->active() as $integration ) {
			$integration->register_hooks();
		}
	}

	/**
	 * Registers the session REST routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$repository = new Session_Repository();

		( new Sessions_Controller( $repository, self::registry(), new Cleaner( $repository, self::registry() ) ) )->register_routes();
	}

	/**
	 * Cleans up sessions past their expiry time, e.g. when the test runner
	 * crashed before ending them, and prunes old session history.
	 *
	 * @return void
	 */
	public static function expire_sessions(): void {
		$repository = new Session_Repository();
		$cleaner    = new Cleaner( $repository, self::registry() );

		foreach ( $repository->expired_ids() as $session_id ) {
			$session = $repository->find( $session_id );

			if ( null !== $session ) {
				$cleaner->clean( $session, Session::STATUS_EXPIRED );
			}
		}

		$repository->prune_history();
	}

	/**
	 * Schedules the expiry job if it isn't already.
	 *
	 * @return void
	 */
	public static function schedule_expiry(): void {
		if ( false === wp_next_scheduled( self::EXPIRY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::EXPIRY_HOOK );
		}
	}

	/**
	 * Creates the tables and schedules the expiry job on activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Schema::install();
		self::schedule_expiry();
	}

	/**
	 * Removes all remaining test data and stops the expiry job on
	 * deactivation, so a deactivated plugin leaves nothing behind.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::EXPIRY_HOOK );

		( new Cleaner( new Session_Repository(), self::registry() ) )->clean_unfinished();
	}
}
