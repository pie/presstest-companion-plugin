<?php
/**
 * Define the internationalization functionality.
 *
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

add_action( 'plugins_loaded', __NAMESPACE__ . '\load_plugin_textdomain' );

/**
 * Load the plugin text domain for translation.
 *
 * @since 1.0.0
 * @return void
 */
function load_plugin_textdomain(): void {
	\load_plugin_textdomain(
		'presstest-companion',
		false,
		plugin_basename( PRESSTEST_COMPANION_FILE_PATH ) . 'languages'
	);
}
