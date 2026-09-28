<?php
/**
 * Register REST API endpoints required by the plugin.
 *
 * Routes:
 *   POST   presstest-companion/v1/run          — proxy test run to the Presstest server
 *   POST   presstest-companion/v1/report        — save a report sent from the Presstest server
 *   GET    presstest-companion/v1/reports       — retrieve all reports
 *   DELETE presstest-companion/v1/reports/{id}  — delete a single report
 *   GET    presstest-companion/v1/schedules     — list the scheduled test intervals
 *
 * Every route except /report is restricted to administrators via
 * can_manage_presstest(); /report is authenticated by the X-Presstest-Token header.
 *
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes/api
 */

namespace PIE\PresstestCompanion;

add_action( 'rest_api_init', __NAMESPACE__ . '\register_endpoints' );
add_action( 'rest_api_init', __NAMESPACE__ . '\register_metafields' );

/**
 * Permission callback for the admin-only routes.
 *
 * Matches the capability required to open the Presstest admin page, so only
 * users who can see the app can trigger runs or read and delete reports.
 *
 * @since 1.2.1
 * @return bool True if the current user can manage Presstest.
 */
function can_manage_presstest(): bool {
	return current_user_can( 'manage_options' );
}

/**
 * Register all REST routes.
 *
 * @since 1.0.0
 * @return void
 */
function register_endpoints(): void {

	// Proxy endpoint — triggers a test run on the Presstest server.
	// Called from the React app; the API key is resolved server-side and never
	// exposed to the browser.
	register_rest_route(
		'presstest-companion/v1',
		'run/',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\trigger_test_run',
			'permission_callback' => __NAMESPACE__ . '\can_manage_presstest',
			'args'                => array(
				'url'     => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return (bool) wp_http_validate_url( $param );
					},
				),
				'tests'   => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_string( $param ) && '' !== $param;
					},
				),
				'browser' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return in_array( $param, get_supported_browsers(), true );
					},
				),
			),
		)
	);

	// Save a test report sent from the Presstest server.
	// Authenticated via X-Presstest-Token header — the secret is generated on
	// plugin activation and sent with every job request from trigger_test_run().
	register_rest_route(
		'presstest-companion/v1',
		'report/',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\save_report',
			'permission_callback' => function () {
				$provided = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_PRESSTEST_TOKEN'] ?? '' ) );
				$secret   = get_option( 'presstest_companion_report_secret', '' );
				return '' !== $secret && hash_equals( $secret, $provided );
			},
			'args'                => array(
				'domain'  => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return (bool) wp_http_validate_url( $param );
					},
				),
				'browser' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_string( $param );
					},
				),
				'report'  => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_string( $param );
					},
				),
			),
		)
	);

	// Retrieve all reports.
	register_rest_route(
		'presstest-companion/v1',
		'reports/',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\get_reports',
			'permission_callback' => __NAMESPACE__ . '\can_manage_presstest',
		)
	);

	// Return the available cron schedule options for the React settings UI.
	register_rest_route(
		'presstest-companion/v1',
		'schedules/',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\get_schedule_options',
			'permission_callback' => __NAMESPACE__ . '\can_manage_presstest',
		)
	);

	// Delete a single report.
	register_rest_route(
		'presstest-companion/v1',
		'reports/(?P<id>\d+)',
		array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => __NAMESPACE__ . '\delete_report',
			'permission_callback' => __NAMESPACE__ . '\can_manage_presstest',
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && absint( $param ) > 0;
					},
				),
			),
		)
	);
}

/**
 * Register user meta for persisting frontend settings via the REST API.
 *
 * @since 1.0.0
 * @return void
 */
function register_metafields(): void {
	register_meta(
		'user',
		'_presstest_settings',
		array(
			'type'          => 'object',
			'single'        => true,
			'show_in_rest'  => array(
				'schema' => array(
					'type'       => 'object',
					'properties' => array(
						'selected_tests'   => array( 'type' => 'array' ),
						'selected_browser' => array( 'type' => 'string' ),
					),
				),
			),
			'auth_callback' => function () {
				return is_user_logged_in();
			},
		)
	);
}
