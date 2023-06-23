<?php

/**
 * API callbacks required by the plugin
 * 
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\TestingPlatform
 * @subpackage PIE\TestingPlatform/includes/api
 */

 namespace PIE\TestingPlatform;

 /**
  * Save report HTML into the database
  *
  * @since    1.0.0
  * @param \WP_REST_Request $request
  * @return void
  */
function save_report_html( \WP_REST_Request $request ) {
    extract($request->get_query_params());
    global $wpdb;
    $response = $wpdb->insert( $wpdb->prefix . 'pie_testing_platform_reports', array( 
        'domain' => $domain,
        'date'   => date( 'Y-m-d H:i:s' ),
        'report' => $report
    ));
    return 1 === $response ? true : false;
}