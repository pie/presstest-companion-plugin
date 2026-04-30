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
