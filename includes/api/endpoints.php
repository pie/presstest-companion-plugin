<?php
/**
 * Register REST API endpoints required by the plugin.
 *
 * Routes:
 *   POST presstest-companion/v1/run     — proxy test run to the Presstest server
 *   POST presstest-companion/v1/report  — save a report sent from the Presstest server
 *   GET  presstest-companion/v1/reports — retrieve all reports for the current user
 *
 * @link       https://pie.co.de
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes/api
 */

namespace PIE\PresstestCompanion;

/**
 * Register all REST routes.
 *
 * @since 2.0.0
 * @return void
 */
function register_endpoints() {

	// Proxy endpoint — triggers a test run on the Presstest server.
	// Called from the React app; the API key is resolved server-side and never
	// exposed to the browser.
	register_rest_route(
		'presstest-companion/v1',
		'run/',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\trigger_test_run',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
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
						return in_array( $param, array( 'chromium', 'firefox', 'webkit' ), true );
					},
				),
			),
		)
	);

	// Save a test report sent from the Presstest server.
	// Requires authentication via a WordPress Application Password.
	register_rest_route(
		'presstest-companion/v1',
		'report/',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\save_report',
			'permission_callback' => function () {
				return is_user_logged_in();
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
				'user_id' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
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

	// Retrieve all reports for the currently authenticated user.
	register_rest_route(
		'presstest-companion/v1',
		'reports/',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\get_reports',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_endpoints' );

/**
 * Register user meta for persisting frontend settings via the REST API.
 *
 * @since 2.0.0
 * @return void
 */
function register_metafields() {
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
						'domains'          => array( 'type' => 'array' ),
						'selected_domain'  => array( 'type' => 'string' ),
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
add_action( 'rest_api_init', __NAMESPACE__ . '\register_metafields' );
