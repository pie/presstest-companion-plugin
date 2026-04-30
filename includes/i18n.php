<?php
/**
 * Define the internationalization functionality.
 *
 * @link       https://pie.co.de
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

/**
 * Load the plugin text domain for translation.
 *
 * @since 2.0.0
 * @return void
 */
function load_plugin_textdomain() {
	\load_plugin_textdomain(
		'presstest-companion',
		false,
		plugin_basename( PRESSTEST_COMPANION_FILE_PATH ) . 'languages'
	);
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\load_plugin_textdomain' );
