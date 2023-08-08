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
 * @return bool
 */
function save_report_html( \WP_REST_Request $request ) {

    global $wpdb;
    extract( $request->get_params() );

    $response = $wpdb->insert( $wpdb->prefix . 'pie_testing_platform_reports', array( 
        'domain'  => $domain,
        'browser' => $browser,
        'user_id' => $user_id,
        'date'    => date( 'Y-m-d H:i:s' ),
        'report'  => $report
    ));
    
    return 1 === $response ? true : false;

}

 /**
  * Get all reports for current user from database
  *
  * @since    1.0.0
  * @param \WP_REST_Request $request
  * @return array
  */
  function get_report_html( \WP_REST_Request $request ) {

    global $wpdb;

    $user_id = get_current_user_id();
    $table   = $wpdb->prefix . 'pie_testing_platform_reports';
    $sql     = $wpdb->prepare( "SELECT * FROM $table WHERE user_id = $user_id ORDER BY date DESC" );

    $wpdb->show_errors( true );
    $response = $wpdb->get_results( $sql, 'OBJECT' );
    
    return $response;

}