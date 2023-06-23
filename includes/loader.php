<?php

/**
 * Define core plugin load functionality
 *
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\TestingPlatform
 * @subpackage PIE\TestingPlatform/includes
 */

namespace PIE\TestingPlatform;

/**
 * Load plugin
 *
 * @since    1.0.0
 * @return void
 */
function load_plugin() {
    load_dependencies();
    set_locale();
    define_wc_hooks();
    add_enqueues();
}

/**
 * Load the required dependencies for this plugin.
 *
 * @since    1.0.0
 */
function load_dependencies() {

    require_once PIE_TESTING_PLATFORM_FILE_PATH . 'includes/i18n.php';
    require_once PIE_TESTING_PLATFORM_FILE_PATH . 'includes/enqueues.php';
    require_once PIE_TESTING_PLATFORM_FILE_PATH . 'includes/woocommerce.php';
    require_once PIE_TESTING_PLATFORM_FILE_PATH . 'includes/api/endpoints.php';
    require_once PIE_TESTING_PLATFORM_FILE_PATH . 'includes/api/callbacks.php';

}

/**
 * Define the locale for this plugin for internationalization.
 *
 * @since    1.0.0
 */
function set_locale() {

    add_action( 'plugins_loaded', __NAMESPACE__ . '\load_plugin_textdomain' );

}

/**
 * Register all of the hooks related to woocommerce.
 *
 * @since    1.0.0
 */
function define_wc_hooks() {

    add_action( 'init', __NAMESPACE__ . '\register_wc_endpoints' );
    add_filter( 'query_vars', __NAMESPACE__ . '\add_wc_query_vars' );
    add_filter( 'woocommerce_account_menu_items', __NAMESPACE__ . '\add_custom_my_account_tab' );
    add_action( 'woocommerce_account_pie-testing-platform_endpoint', __NAMESPACE__ . '\add_custom_tab_content' );

}

/**
 * Enqueue CSS/JS files
 *
 * @since    1.0.0
 */
function add_enqueues() {

    add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueues_frontend' );

}