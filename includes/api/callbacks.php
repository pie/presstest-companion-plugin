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
        'domain' => $domain,
        'date'   => date( 'Y-m-d H:i:s' ),
        'report' => $report
    ));
    
    return 1 === $response ? true : false;

}

 /**
  * Save report HTML into the database
  *
  * @since    1.0.0
  * @param \WP_REST_Request $request
  * @return array
  */
  function get_report_html( \WP_REST_Request $request ) {

    global $wpdb;

    // Retrieve users domains and convert array to csv of values (each domain wrapped in quotes for query)
    $domains = get_user_meta( get_current_user_id(), '_domains', true ) ? get_user_meta( get_current_user_id(), '_domains', true ) : array();
    array_walk( $domains, fn( &$x ) => $x = "'$x'" );
    $domains = implode( ',', $domains );
    $table   = $wpdb->prefix . 'pie_testing_platform_reports';
    $sql     = $wpdb->prepare( "SELECT * FROM $table WHERE domain IN ($domains) ORDER BY date DESC" );

    $wpdb->show_errors( true );
    $response = $wpdb->get_results( $sql, 'OBJECT' );
    
    return $response;

}