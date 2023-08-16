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
        'permission_callback' => function() { return true; },
        'args'     => [
            'domain' => [
                'validate_callback' => function( $param, $request, $key ) { 
                    return wp_http_validate_url( $param );
                }
            ],
            'browser' => [
                'validate_callback' => function( $param, $request, $key ) {
                    return is_string( $param );
                }
            ],
            'user_id' => [
                'validate_callback' => function( $param, $request, $key ) {
                    return is_numeric( $param );
                }
            ],
            'report' => [
                'validate_callback' => function( $param, $request, $key ) {
                    return is_string( $param );
                }
            ],
        ],
    ]);

    register_rest_route( 'pie-testing-platform/v1', 'reports/', [
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\get_report_html',
        'permission_callback' => function() { return true; },
    ]);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_endpoints' );

function register_metafields() {
    register_meta( 'user', '_presstest_settings', [
        'type'         => 'object',
        'single'       => true,
        'show_in_rest' => array(
            'schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'domains'          => array(
                        'type' => 'array',
                    ),
                    'selected_domain'  => array(
                        'type' => 'string',
                    ),
                    'selected_tests'   => array(
                        'type' => 'array',
                    ),
                    'selected_browser' => array(
                        'type' => 'string',
                    ),
                 ),
            ),
        ),
        'auth_callback' => function() { return true; },
    ]);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_metafields' );