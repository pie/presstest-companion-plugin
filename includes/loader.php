<?php
/**
 * Define core plugin load functionality.
 *
 * @link       https://pie.co.de
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

/**
 * Load plugin.
 *
 * @since 2.0.0
 * @return void
 */
function load_plugin() {
	load_dependencies();
	set_locale();
	define_hooks();
	add_enqueues();
}

/**
 * Load the required dependencies for this plugin.
 *
 * @since 2.0.0
 * @return void
 */
function load_dependencies() {
	require_once PRESSTEST_COMPANION_FILE_PATH . 'includes/i18n.php';
	require_once PRESSTEST_COMPANION_FILE_PATH . 'includes/enqueues.php';
	require_once PRESSTEST_COMPANION_FILE_PATH . 'includes/woocommerce.php';
	require_once PRESSTEST_COMPANION_FILE_PATH . 'includes/settings.php';
	require_once PRESSTEST_COMPANION_FILE_PATH . 'includes/api/endpoints.php';
	require_once PRESSTEST_COMPANION_FILE_PATH . 'includes/api/callbacks.php';
}

/**
 * Define the locale for this plugin for internationalization.
 *
 * @since 2.0.0
 * @return void
 */
function set_locale() {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\load_plugin_textdomain' );
}

/**
 * Register all plugin hooks.
 *
 * @since 2.0.0
 * @return void
 */
function define_hooks() {
	add_action( 'init', __NAMESPACE__ . '\register_wc_endpoints' );
	add_filter( 'query_vars', __NAMESPACE__ . '\add_wc_query_vars' );
	add_filter( 'woocommerce_account_menu_items', __NAMESPACE__ . '\add_custom_my_account_tab' );
	add_action( 'woocommerce_account_presstest_endpoint', __NAMESPACE__ . '\add_custom_tab_content' );
}

/**
 * Enqueue CSS/JS files.
 *
 * @since 2.0.0
 * @return void
 */
function add_enqueues() {
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueues_frontend' );
}
