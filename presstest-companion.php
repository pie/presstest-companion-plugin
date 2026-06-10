<?php
/**
 * Plugin bootstrap file.
 *
 * @link              https://pie.co.de
 * @since             1.0.0
 * @package           PIE\PresstestCompanion
 *
 * @wordpress-plugin
 * Plugin Name:       Presstest Companion
 * Plugin URI:        https://pie.co.de
 * Description:       Connect your WordPress site to your Presstest server and run automated browser tests from your account dashboard.
 * Version:           1.0.7
 * Author:            PIE Web Ltd
 * Author URI:        https://pie.co.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       presstest-companion
 * Domain Path:       /languages
 */

namespace PIE\PresstestCompanion;

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once 'definitions.php';

/**
 * Runs during plugin activation.
 *
 * @since 1.0.0
 */
function activate_presstest_companion(): void {
	require_once PRESSTEST_COMPANION_FILE_PATH . 'includes/activator.php';
	activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate_presstest_companion' );

/**
 * Runs during plugin deactivation — clears any scheduled test events.
 *
 * @since 1.0.0
 */
function deactivate_presstest_companion(): void {
	require_once PRESSTEST_COMPANION_FILE_PATH . 'includes/cron.php';
	unschedule_presstest_cron();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate_presstest_companion' );

require PRESSTEST_COMPANION_FILE_PATH . 'includes/loader.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\load_plugin' );
