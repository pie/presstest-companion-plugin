<?php

/**
 * Define the WooCommerce functionality
 *
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\TestingPlatform
 * @subpackage PIE\TestingPlatform/includes
 */

namespace PIE\TestingPlatform;

/**
 * Register all of the endpoints related to woocommerce.
 *
 * @since    1.0.0
 */
function register_wc_endpoints() {

    add_rewrite_endpoint( 'pie-testing-platform', EP_ROOT | EP_PAGES );

}

/**
 * Add query vars for woocommerce endpoints.
 *
 * @since    1.0.0
 * @param array $vars
 * @return array
 */
function add_wc_query_vars( array $vars = array() ) {

    $vars[] = 'pie-testing-platform';
	return $vars;

}

/**
 * Add New custom tab on the my account page.
 *
 * @since    1.0.0
 * @param array $items
 * @return array
 */
function add_custom_my_account_tab( array $items = array() ) {

    $items['pie-testing-platform'] = __( 'Tests', PIE_TESTING_PLATFORM_NAME );
	return $items;

}

/**
 * Add content to the new tab.
 * 
 * @since    1.0.0
 * @return void
 */
function add_custom_tab_content() {
	include_once PIE_TESTING_PLATFORM_FILE_PATH . 'templates/my-account/main.php';
}
