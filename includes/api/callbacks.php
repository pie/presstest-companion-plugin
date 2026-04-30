<?php
/**
 * REST API callback functions.
 *
 * @link       https://pie.co.de
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes/api
 */

namespace PIE\PresstestCompanion;

/**
 * Proxy a test run request to the Presstest server.
 *
 * Keeps the API key server-side — it is never sent to or exposed in the browser.
 * The WordPress user ID is resolved from the session rather than accepted as a
 * parameter, preventing one user from triggering runs attributed to another.
 *
 * @since 2.0.0
 * @param \WP_REST_Request $request Incoming REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function trigger_test_run( \WP_REST_Request $request ) {
	$server_url = get_option( 'presstest_companion_server_url', '' );
	$api_key    = get_option( 'presstest_companion_api_key', '' );

	if ( '' === $server_url || '' === $api_key ) {
		return new \WP_Error(
			'presstest_not_configured',
			__( 'Presstest server is not configured. Please update the plugin settings.', 'presstest-companion' ),
			array( 'status' => 500 )
		);
	}

	$response = wp_remote_post(
		trailingslashit( $server_url ) . 'api.php',
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-API-Key'    => $api_key,
			),
			'body'    => wp_json_encode(
				array(
					'url'           => $request->get_param( 'url' ),
					'user_id'       => get_current_user_id(),
					'tests'         => $request->get_param( 'tests' ),
					'browser'       => $request->get_param( 'browser' ),
					// Sent to the Presstest server so it can authenticate its callback
					// POST back to this site's /report endpoint via X-Presstest-Token.
					// Resolved here server-side — never exposed to the browser.
					'report_secret' => get_option( 'presstest_companion_report_secret', '' ),
				)
			),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new \WP_Error(
			'presstest_request_failed',
			$response->get_error_message(),
			array( 'status' => 502 )
		);
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $status_code ) {
		$message = isset( $body['error'] ) ? $body['error'] : __( 'Unknown error from Presstest server.', 'presstest-companion' );
		return new \WP_Error( 'presstest_error', $message, array( 'status' => $status_code ) );
	}

	return rest_ensure_response( $body );
}

/**
 * Save a test report into the database.
 *
 * @since 2.0.0
 * @param \WP_REST_Request $request Incoming REST request.
 * @return bool True on success, false on failure.
 */
function save_report( \WP_REST_Request $request ) {
	global $wpdb;

	$domain  = esc_url_raw( $request->get_param( 'domain' ) );
	$browser = sanitize_text_field( $request->get_param( 'browser' ) );
	$user_id = absint( $request->get_param( 'user_id' ) );
	$report  = $request->get_param( 'report' );

	$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prefix . 'presstest_reports',
		array(
			'domain'  => $domain,
			'browser' => $browser,
			'user_id' => $user_id,
			'date'    => current_time( 'mysql' ),
			'report'  => $report,
		)
	);

	return 1 === $inserted;
}

/**
 * Get all test reports for the currently authenticated user.
 *
 * @since 2.0.0
 * @param \WP_REST_Request $request Incoming REST request.
 * @return array
 */
function get_reports( \WP_REST_Request $request ) {
	global $wpdb;

	$user_id = get_current_user_id();
	$table   = $wpdb->prefix . 'presstest_reports';

	return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY date DESC", $user_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'OBJECT'
	);
}
