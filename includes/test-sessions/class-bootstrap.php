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
	 * Refuses test traffic whose session is no longer open.
	 *
	 * A request carrying session credentials is test traffic. If its session
	 * has ended, expired, or started cleaning (or the credentials are
	 * invalid), letting it through as an ordinary visitor would bypass the
	 * payment guards, leave whatever it creates untracked, and send real
	 * emails — e.g. a checkout the browser submitted just as the run ended.
	 * So it is refused before WordPress or any plugin handles it.
	 *
	 * Called as the plugin file loads, before other plugins' hooks run. The
	 * runner's own session routes (authenticated by the report secret) are
	 * let through so it can always end and clean up a session.
	 *
	 * @return void
	 */
	public static function guard_request(): void {
		if ( false === Session_Context::has_credentials() || null !== Session_Context::current() || true === self::is_runner_request() ) {
			return;
		}

		$message = 'This request belongs to a Presstest test session that has ended or expired, so it was blocked to avoid creating untracked test data or sending real emails.';

		if ( true === self::is_json_request() ) {
			wp_send_json(
				array(
					'code'    => 'presstest_session_closed',
					'message' => $message,
					'data'    => array( 'status' => 403 ),
				),
				403
			);
		}

		wp_die( esc_html( $message ), 'Presstest', array( 'response' => 403 ) );
	}

	/**
	 * Whether this is the runner starting or ending a session with this
	 * site's report secret — the only test traffic allowed through without
	 * open session credentials, so it can always clean up.
	 *
	 * Both the secret and the route are checked here: the guard runs before
	 * WordPress routes the request, so a non-REST request (e.g. a checkout)
	 * would never reach the REST permission check that validates the secret.
	 *
	 * @return bool
	 */
	private static function is_runner_request(): bool {
		$secret = (string) get_option( 'presstest_companion_report_secret', '' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared with hash_equals() only.
		$token = isset( $_SERVER['HTTP_X_PRESSTEST_TOKEN'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_PRESSTEST_TOKEN'] ) : '';

		if ( '' === $secret || ! hash_equals( $secret, $token ) ) {
			return false;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		$route  = untrailingslashit( self::rest_route() );

		return ( 'POST' === $method && '/presstest-companion/v1/sessions' === $route )
			|| ( 'DELETE' === $method && 1 === preg_match( '#^/presstest-companion/v1/sessions/\d+$#', $route ) );
	}

	/**
	 * The REST route this request will be served as, worked out the way
	 * WordPress routes it: the rest_route query parameter (which WordPress
	 * serves as a REST request whatever the path), or a path made of the
	 * site's home path, the REST prefix, and the route.
	 *
	 * @return string The route, e.g. "/presstest-companion/v1/sessions/12", or "" if this isn't a REST request.
	 */
	private static function rest_route(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only routing, matched against fixed routes.
		if ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ) {
			return '/' . ltrim( wp_unslash( $_GET['rest_route'] ), '/' );
		}

		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		// phpcs:enable

		$base = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) ) . '/' . rest_get_url_prefix();

		return 0 === strpos( $path, $base . '/' ) ? substr( $path, strlen( $base ) ) : '';
	}

	/**
	 * Whether the client expects a JSON response (REST API, AJAX, fetch).
	 *
	 * @return bool
	 */
	private static function is_json_request(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched against fixed strings.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		return wp_is_json_request() || false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, 'rest_route=' ) || wp_doing_ajax();
	}

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
	 * Cleans up sessions the runner never ended (past their expiry time),
	 * retries sessions whose last cleanup left data behind, releases cleanups
	 * that died part-way, and prunes old session history.
	 *
	 * @return void
	 */
	public static function expire_sessions(): void {
		$repository = new Session_Repository();
		$cleaner    = new Cleaner( $repository, self::registry() );

		$repository->release_stuck_cleanups( Cleaner::STALE_CLAIM_AGE );

		foreach ( $repository->cleanup_due_ids() as $session_id ) {
			$session = $repository->find( $session_id );

			if ( null !== $session ) {
				$cleaner->clean( $session, Session::STATUS_ACTIVE === $session->get_status() ? Session::STATUS_EXPIRED : Session::STATUS_ENDED );
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
