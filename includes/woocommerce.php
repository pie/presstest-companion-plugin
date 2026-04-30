<?php
/**
 * Define the WooCommerce functionality.
 *
 * @link       https://pie.co.de
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

/**
 * Register the custom My Account endpoint.
 *
 * @since 2.0.0
 * @return void
 */
function register_wc_endpoints() {
	add_rewrite_endpoint( 'presstest', EP_ROOT | EP_PAGES );
}

/**
 * Add the endpoint query variable.
 *
 * @since 2.0.0
 * @param array $vars Existing query variables.
 * @return array
 */
function add_wc_query_vars( array $vars = array() ) {
	$vars[] = 'presstest';
	return $vars;
}

/**
 * Add a custom tab to the WooCommerce My Account menu.
 *
 * @since 2.0.0
 * @param array $items Existing menu items.
 * @return array
 */
function add_custom_my_account_tab( array $items = array() ) {
	$items['presstest'] = __( 'Tests', 'presstest-companion' );
	return $items;
}

/**
 * Output the content for the custom My Account tab.
 *
 * @since 2.0.0
 * @return void
 */
function add_custom_tab_content() {
	include_once PRESSTEST_COMPANION_FILE_PATH . 'templates/my-account/main.php';
}
