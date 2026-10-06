<?php
/**
 * REST routes for test sessions.
 *
 * Runner routes (authenticated by the site's report secret, X-Presstest-Token):
 *   POST   /sessions                    — start a session
 *   DELETE /sessions/{id}               — end a session and clean up (also accepts session credentials)
 *
 * Test routes (authenticated by session credentials, X-Presstest-Session: <id>.<token>):
 *   GET    /sessions/{id}/preflight     — check integrations are safe to test
 *   POST   /sessions/{id}/fixtures/{type} — create test data
 *   POST   /sessions/{id}/login         — log a session test user in (sets auth cookies)
 *   GET    /sessions/{id}/emails        — emails captured during the session
 *
 * Admin routes (manage_options):
 *   GET    /sessions                    — recent sessions for the settings screen
 *   POST   /sessions/purge              — end every unfinished session now
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Rest;

use PIE\PresstestCompanion\TestSessions\Cleaner;
use PIE\PresstestCompanion\TestSessions\Integrations\Integration_Registry;
use PIE\PresstestCompanion\TestSessions\Notices;
use PIE\PresstestCompanion\TestSessions\Session;
use PIE\PresstestCompanion\TestSessions\Session_Context;
use PIE\PresstestCompanion\TestSessions\Session_Repository;
use PIE\PresstestCompanion\TestSessions\Test_Data_Settings;

/**
 * Registers and handles the session routes.
 */
class Sessions_Controller {

	/**
	 * REST namespace shared with the plugin's other routes.
	 */
	const NAMESPACE = 'presstest-companion/v1';

	/**
	 * Session data access.
	 *
	 * @var Session_Repository
	 */
	private Session_Repository $repository;

	/**
	 * Registered integrations.
	 *
	 * @var Integration_Registry
	 */
	private Integration_Registry $registry;

	/**
	 * Session cleanup.
	 *
	 * @var Cleaner
	 */
	private Cleaner $cleaner;

	/**
	 * Sets up the controller.
	 *
	 * @param Session_Repository   $repository Session data access.
	 * @param Integration_Registry $registry   Registered integrations.
	 * @param Cleaner              $cleaner    Session cleanup.
	 */
	public function __construct( Session_Repository $repository, Integration_Registry $registry, Cleaner $cleaner ) {
		$this->repository = $repository;
		$this->registry   = $registry;
		$this->cleaner    = $cleaner;
	}

	/**
	 * Registers every session route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$id = '(?P<id>\d+)';

		register_rest_route(
			self::NAMESPACE,
			'/sessions',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'has_report_token' ),
					'args'                => array(
						'label' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_sessions' ),
					'permission_callback' => array( $this, 'is_admin' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/purge',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'purge' ),
				'permission_callback' => array( $this, 'is_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/' . $id,
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'end' ),
				'permission_callback' => array( $this, 'can_end' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/' . $id . '/preflight',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'preflight' ),
				'permission_callback' => array( $this, 'has_session_credentials' ),
				'args'                => array(
					'integrations' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/' . $id . '/fixtures/(?P<type>[a-z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_fixture' ),
				'permission_callback' => array( $this, 'has_session_credentials' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/' . $id . '/login',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'login' ),
				'permission_callback' => array( $this, 'has_session_credentials' ),
				'args'                => array(
					'user_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sessions/' . $id . '/emails',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'emails' ),
				'permission_callback' => array( $this, 'has_session_credentials' ),
				'args'                => array(
					'to'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'subject' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Whether the request carries this site's report secret.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return bool
	 */
	public function has_report_token( \WP_REST_Request $request ): bool {
		$provided = (string) $request->get_header( 'x_presstest_token' );
		$secret   = (string) get_option( 'presstest_companion_report_secret', '' );

		return '' !== $secret && hash_equals( $secret, $provided );
	}

	/**
	 * Whether the request carries open credentials for the session in the URL.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return bool
	 */
	public function has_session_credentials( \WP_REST_Request $request ): bool {
		$session = Session_Context::current();
		return null !== $session && (int) $request['id'] === $session->get_id();
	}

	/**
	 * Sessions can be ended by the runner (report secret) or from within.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return bool
	 */
	public function can_end( \WP_REST_Request $request ): bool {
		return $this->has_report_token( $request ) || $this->has_session_credentials( $request );
	}

	/**
	 * Whether the current user may manage Presstest.
	 *
	 * @return bool
	 */
	public function is_admin(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	// Runner routes
	// -------------------------------------------------------------------------

	/**
	 * Starts a session. Refused unless test data is enabled in the settings.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start( \WP_REST_Request $request ) {
		if ( false === Test_Data_Settings::is_enabled() ) {
			return new \WP_Error(
				'presstest_test_data_disabled',
				__( 'Test data is disabled on this site. Enable it in Presstest > Settings to run logged-in tests.', 'presstest-companion' ),
				array( 'status' => 403 )
			);
		}

		$created     = $this->repository->create( (string) $request['label'] );
		$session     = $created['session'];
		$credentials = Session_Context::format_credentials( $session->get_id(), $created['token'] );

		return new \WP_REST_Response(
			array_merge(
				$session->to_array(),
				array(
					'token'  => $created['token'],
					'cookie' => array(
						'name'  => Session_Context::COOKIE,
						'value' => $credentials,
					),
				)
			),
			201
		);
	}

	/**
	 * Ends a session and removes everything it created. Safe to call twice:
	 * a finished session returns its stored summary.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function end( \WP_REST_Request $request ) {
		$session = $this->repository->find( (int) $request['id'] );

		if ( null === $session ) {
			return new \WP_Error( 'presstest_session_not_found', __( 'Session not found.', 'presstest-companion' ), array( 'status' => 404 ) );
		}

		// Null when already finished, or another request is cleaning it right now.
		$summary = Session::STATUS_ACTIVE === $session->get_status() ? $this->cleaner->clean( $session, Session::STATUS_ENDED ) : null;

		return rest_ensure_response(
			array(
				'id'      => $session->get_id(),
				'status'  => $this->repository->find( $session->get_id() )->get_status(),
				'summary' => $summary ?? $this->repository->summary( $session->get_id() ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Test routes
	// -------------------------------------------------------------------------

	/**
	 * Checks the requested integrations (or all active ones) are safe to
	 * test. Failures raise an admin notice; a pass clears it.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function preflight( \WP_REST_Request $request ): \WP_REST_Response {
		$requested = array_filter( array_map( 'trim', explode( ',', (string) $request['integrations'] ) ), fn( string $slug ): bool => '' !== $slug );
		$slugs     = array() !== $requested ? $requested : array_keys( $this->registry->active() );
		$results   = array();

		foreach ( $slugs as $slug ) {
			$integration = $this->registry->get( $slug );

			if ( null === $integration ) {
				/* translators: %s: integration slug. */
				$results[ $slug ] = $this->preflight_result( false, false, array( sprintf( __( 'Unknown integration "%s".', 'presstest-companion' ), $slug ) ) );
				continue;
			}

			if ( false === $integration->is_active() ) {
				/* translators: %s: integration name. */
				$results[ $slug ] = $this->preflight_result( false, false, array( sprintf( __( '%s is not active on this site.', 'presstest-companion' ), $integration->get_name() ) ) );
				continue;
			}

			$problems = $integration->preflight();

			if ( array() === $problems ) {
				Notices::clear( $slug );
			} else {
				Notices::set( $slug, $integration->get_name(), $problems );
			}

			$results[ $slug ] = $this->preflight_result( true, array() === $problems, $problems );
		}

		$ready = array() === array_filter( $results, fn( array $result ): bool => false === $result['ready'] );

		return rest_ensure_response(
			array(
				'ready'        => $ready,
				'integrations' => $results,
			)
		);
	}

	/**
	 * Creates test data with the factory registered for the fixture type.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_fixture( \WP_REST_Request $request ) {
		$type      = (string) $request['type'];
		$factories = $this->registry->factories();

		if ( ! isset( $factories[ $type ] ) ) {
			return new \WP_Error(
				'presstest_unknown_fixture',
				/* translators: 1: fixture type, 2: comma-separated list of fixture types. */
				sprintf( __( 'Unknown fixture type "%1$s". Available: %2$s.', 'presstest-companion' ), $type, implode( ', ', array_keys( $factories ) ) ),
				array( 'status' => 404 )
			);
		}

		$args   = $request->get_json_params();
		$result = call_user_func( $factories[ $type ], is_array( $args ) ? $args : array(), Session_Context::current() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 201 );
	}

	/**
	 * Logs in a test user created in this session — if its current roles are
	 * all allowed — by setting auth cookies on the response. Browser contexts that share the caller's cookie jar (e.g.
	 * Playwright's context.request) are logged in immediately.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function login( \WP_REST_Request $request ) {
		$session = Session_Context::current();
		$user_id = (int) $request['user_id'];

		if ( false === $this->repository->has_object( $session->get_id(), 'user', $user_id ) ) {
			return new \WP_Error( 'presstest_not_session_user', __( 'Only test users created in this session can be logged in.', 'presstest-companion' ), array( 'status' => 403 ) );
		}

		if ( false === get_userdata( $user_id ) ) {
			return new \WP_Error( 'presstest_user_not_found', __( 'That test user no longer exists.', 'presstest-companion' ), array( 'status' => 404 ) );
		}

		// Revalidated on every login, not just at creation: users registered
		// through the site's own forms get whatever role the site assigns, and
		// any test user's privileges may have changed since.
		if ( false === Test_Data_Settings::user_has_only_allowed_roles( $user_id ) ) {
			return new \WP_Error(
				'presstest_role_not_allowed',
				__( 'This test user has a role or capability that is not allowed for test users, so it cannot be logged in. Allow the role in Presstest > Settings > Test data if this is intended.', 'presstest-companion' ),
				array( 'status' => 403 )
			);
		}

		wp_set_auth_cookie( $user_id, false );

		return rest_ensure_response( array( 'user_id' => $user_id ) );
	}

	/**
	 * Emails captured during the session, optionally filtered.
	 *
	 * Filters are case-insensitive substring matches: "to" against any
	 * recipient, "subject" against the subject line.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function emails( \WP_REST_Request $request ): \WP_REST_Response {
		$to      = strtolower( (string) $request['to'] );
		$subject = strtolower( (string) $request['subject'] );
		$emails  = $this->repository->emails( Session_Context::current()->get_id() );

		$emails = array_values(
			array_filter(
				$emails,
				function ( array $email ) use ( $to, $subject ): bool {
					$to_matches      = '' === $to || array() !== array_filter( $email['recipients'], fn( string $recipient ): bool => false !== strpos( strtolower( $recipient ), $to ) );
					$subject_matches = '' === $subject || false !== strpos( strtolower( $email['subject'] ), $subject );
					return $to_matches && $subject_matches;
				}
			)
		);

		return rest_ensure_response( $emails );
	}

	// -------------------------------------------------------------------------
	// Admin routes
	// -------------------------------------------------------------------------

	/**
	 * Recent sessions for the settings screen.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_sessions(): \WP_REST_Response {
		return rest_ensure_response( $this->repository->recent() );
	}

	/**
	 * Ends every unfinished session now, including any left mid-cleanup by a
	 * crash.
	 *
	 * @return \WP_REST_Response
	 */
	public function purge(): \WP_REST_Response {
		return rest_ensure_response( array( 'purged' => $this->cleaner->clean_unfinished() ) );
	}

	/**
	 * Shape of one integration's preflight result.
	 *
	 * @param bool     $active   Whether the integration is active.
	 * @param bool     $ready    Whether it passed.
	 * @param string[] $problems Problems found.
	 * @return array{active: bool, ready: bool, problems: string[]}
	 */
	private function preflight_result( bool $active, bool $ready, array $problems ): array {
		return array(
			'active'   => $active,
			'ready'    => $ready,
			'problems' => array_values( $problems ),
		);
	}
}
