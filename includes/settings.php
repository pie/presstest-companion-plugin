<?php
/**
 * Admin page and settings registration.
 *
 * Registers a top-level Presstest admin menu page that mounts the React app,
 * and exposes the plugin settings via the WP REST API (/wp/v2/settings) so the
 * React app can read and write them without a separate PHP form.
 *
 * @link       https://pie.co.de
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

/**
 * Register the top-level Presstest admin menu page.
 *
 * @since 2.0.0
 * @return void
 */
function add_admin_page() {
	add_menu_page(
		__( 'Presstest', 'presstest-companion' ),
		__( 'Presstest', 'presstest-companion' ),
		'manage_options',
		'presstest-companion',
		__NAMESPACE__ . '\render_admin_page',
		'dashicons-performance',
		80
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_admin_page' );

/**
 * Register plugin settings with the Settings API and expose them via REST.
 *
 * Hooked to `init` (not `admin_init`) so the settings are registered on every
 * request — including REST API calls — which is required for show_in_rest to work.
 * The React Settings tab reads and writes them via the standard /wp/v2/settings endpoint.
 *
 * @since 2.0.0
 * @return void
 */
function register_settings() {
	register_setting(
		'presstest_companion',
		'presstest_companion_api_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
			'show_in_rest'      => true,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register_settings' );

/**
 * Render the admin page — outputs the React app mount point.
 *
 * @since 2.0.0
 * @return void
 */
function render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap"><div id="presstest-admin-app"></div></div>';
}
