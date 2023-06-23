<?php

/**
 * Register and API endpoints required by the plugin
 * 
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\TestingPlatform
 * @subpackage PIE\TestingPlatform/includes/api
 */

 namespace PIE\TestingPlatform;

 function register_endpoints() {

    register_rest_route( 'pie-testing-platform/v1', 'report/', [
        'methods'  => 'POST',
        'callback' => __NAMESPACE__ . '\save_report_html',
        'args'     => [
            'domain' => [ 'validate_callback' => function($param, $request, $key) { return wp_http_validate_url( $param ); } ],
            'report' => [ 'validate_callback' => function($param, $request, $key) { return is_string( $param ); } ],
        ],
    ]);

    register_rest_route( 'pie-testing-platform/v1', 'report/', [
        'methods'  => 'GET',
        'callback' => __NAMESPACE__ . '\get_report_html',
        'args'     => [
            'domain' => [ 'validate_callback' => function($param, $request, $key) { return wp_http_validate_url( $param ); } ],
        ],
    ]);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_endpoints' );