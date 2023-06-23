<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\TestingPlatform
 * @subpackage PIE\TestingPlatform/includes
 */

 namespace PIE\TestingPlatform;

/**
 * Load the plugin text domain for translation.
 *
 * @since    1.0.0
 */
function load_plugin_textdomain() {

	\load_plugin_textdomain(
		'pie-testing-platform',
		false,
		dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
	);

}
add_action( 'plugins_loaded', __NAMESPACE__ . '\load_plugin_textdomain' );

