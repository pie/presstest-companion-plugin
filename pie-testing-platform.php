<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://pie.co.de
 * @since             1.0.0
 * @package           PIE\TestingPlatform
 *
 * @wordpress-plugin
 * Plugin Name:       Testing Platform
 * Plugin URI:        https://pie.co.de
 * Description:       Testing Platform plugin
 * Version:           1.0.0
 * Author:            PIE Web Ltd
 * Author URI:        https://pie.co.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pie-testing-platform
 * Domain Path:       /languages
 */

namespace PIE\TestingPlatform;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once 'definitions.php';

/**
 * The code that runs during plugin activation.
 */
function activate_pie_testing_platform() {
	require_once PIE_TESTING_PLATFORM_FILE_PATH . 'includes/activator.php';
	activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate_pie_testing_platform' );

// /**
//  * The code that runs during plugin deactivation.
//  * This action is documented in includes/class-pie-testing-platform-deactivator.php
//  */
// function deactivate_pie_testing_platform() {
// 	require_once PIE_TESTING_PLATFORM_FILE_PATH . 'includes/class-pie-testing-platform-deactivator.php';
// 	Pie_Testing_Platform_Deactivator::deactivate();
// }

// register_deactivation_hook( __FILE__, 'deactivate_pie_testing_platform' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require PIE_TESTING_PLATFORM_FILE_PATH . 'includes/loader.php';

load_plugin();