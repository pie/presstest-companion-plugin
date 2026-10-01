<?php
/**
 * Resolves which test session, if any, the current request belongs to.
 *
 * A request joins a session by presenting "<id>.<token>" in either:
 *   - the X-Presstest-Session header — used by API calls from the test runner;
 *   - the wp-presstest-session cookie — set on the browser, so every page load,
 *     form post, AJAX call, and redirect back from an offsite gateway carries it.
 *
 * The cookie is prefixed "wp-" because managed hosts (Pantheon, WP Engine and
 * others) strip unrecognised cookies before PHP sees them but keep wp-* ones.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

/**
 * Per-request session lookup with an override for cleanup.
 */
class Session_Context {

	/**
	 * Request header carrying session credentials ($_SERVER key form).
	 */
	const HEADER = 'HTTP_X_PRESSTEST_SESSION';

	/**
	 * Cookie carrying session credentials.
	 */
	const COOKIE = 'wp-presstest-session';

	/**
	 * Session resolved from this request's credentials.
	 *
	 * @var Session|null
	 */
	private static ?Session $resolved = null;

	/**
	 * Whether resolution has run for this request.
	 *
	 * @var bool
	 */
	private static bool $has_resolved = false;

	/**
	 * Whether this request is counted in its session's active requests.
	 *
	 * @var bool
	 */
	private static bool $counted = false;

	/**
	 * Session forced by run_as(), taking precedence over the request.
	 *
	 * @var Session|null
	 */
	private static ?Session $override = null;

	/**
	 * The session the current request is acting within.
	 *
	 * @return Session|null Null for ordinary site traffic.
	 */
	public static function current(): ?Session {
		if ( null !== self::$override ) {
			return self::$override;
		}

		if ( false === self::$has_resolved ) {
			self::$has_resolved = true;
			self::$resolved     = self::resolve();
		}

		return self::$resolved;
	}

	/**
	 * Runs a callback as if the request belonged to the given session.
	 *
	 * Cleanup uses this so emails triggered while deleting (e.g. order
	 * cancellation notices) are captured and blocked, and anything created as
	 * a side effect is recorded and removed in the next pass.
	 *
	 * @param Session  $session  Session to act within.
	 * @param callable $callback Work to run.
	 * @return mixed The callback's return value.
	 */
	public static function run_as( Session $session, callable $callback ) {
		$previous       = self::$override;
		self::$override = $session;

		try {
			return $callback();
		} finally {
			self::$override = $previous;
		}
	}

	/**
	 * Stops counting this request as running inside its session.
	 *
	 * Runs on shutdown. Cleanup also calls it, so a request that ends its own
	 * session doesn't wait for itself to finish.
	 *
	 * @return void
	 */
	public static function release(): void {
		if ( true === self::$counted && null !== self::$resolved ) {
			self::$counted = false;
			( new Session_Repository() )->end_request( self::$resolved->get_id() );
		}
	}

	/**
	 * Parses "<id>.<token>" credentials from the header or cookie.
	 *
	 * @return array{id: int, token: string}|null Null if absent or malformed.
	 */
	public static function credentials(): ?array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by the pattern below.
		$raw = isset( $_SERVER[ self::HEADER ] ) ? wp_unslash( $_SERVER[ self::HEADER ] ) : ( isset( $_COOKIE[ self::COOKIE ] ) ? wp_unslash( $_COOKIE[ self::COOKIE ] ) : '' );

		if ( ! is_string( $raw ) || 1 !== preg_match( '/^(\d+)\.([a-f0-9]{64})$/', $raw, $matches ) ) {
			return null;
		}

		return array(
			'id'    => (int) $matches[1],
			'token' => $matches[2],
		);
	}

	/**
	 * Builds the credential string presented by header or cookie.
	 *
	 * @param int    $id    Session ID.
	 * @param string $token Plain session token.
	 * @return string
	 */
	public static function format_credentials( int $id, string $token ): string {
		return $id . '.' . $token;
	}

	/**
	 * Looks up the session for this request's credentials, and counts the
	 * request as running in it until shutdown — so cleanup can wait for
	 * requests still in flight (e.g. a checkout the browser abandoned when a
	 * run crashed) instead of racing them.
	 *
	 * @return Session|null
	 */
	private static function resolve(): ?Session {
		$credentials = self::credentials();

		if ( null === $credentials ) {
			return null;
		}

		$repository = new Session_Repository();
		$session    = $repository->find_open_by_credentials( $credentials['id'], $credentials['token'] );

		if ( null !== $session ) {
			$repository->begin_request( $session->get_id() );
			self::$counted = true;
			// Shutdown functions run even after a fatal error.
			register_shutdown_function( array( self::class, 'release' ) );
		}

		return $session;
	}
}
