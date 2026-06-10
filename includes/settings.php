<?php
/**
 * Admin page and settings registration.
 *
 * Registers a top-level Presstest admin menu page that mounts the React app,
 * and exposes the plugin settings via the WP REST API (/wp/v2/settings) so the
 * React app can read and write them without a separate PHP form.
 *
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

add_action( 'init', __NAMESPACE__ . '\register_settings' );
add_action( 'admin_menu', __NAMESPACE__ . '\add_admin_page' );

/**
 * Register the top-level Presstest admin menu page.
 *
 * @since 1.0.0
 * @return void
 */
function add_admin_page(): void {
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

/**
 * Register plugin settings with the Settings API and expose them via REST.
 *
 * Hooked to `init` (not `admin_init`) so the settings are registered on every
 * request — including REST API calls — which is required for show_in_rest to work.
 * The React Settings tab reads and writes them via the standard /wp/v2/settings endpoint.
 *
 * @since 1.0.0
 * @return void
 */
function register_settings(): void {
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

	register_setting(
		'presstest_companion',
		'presstest_companion_cron_enabled',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
		)
	);

	register_setting(
		'presstest_companion',
		'presstest_companion_cron_schedule',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'default'           => 'daily',
			'show_in_rest'      => array(
				'schema' => array(
					'type' => 'string',
					'enum' => array( 'hourly', 'twicedaily', 'daily', 'weekly' ),
				),
			),
		)
	);

	register_setting(
		'presstest_companion',
		'presstest_companion_cron_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
			'show_in_rest'      => true,
		)
	);

	register_setting(
		'presstest_companion',
		'presstest_companion_cron_tests',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
			'show_in_rest'      => true,
		)
	);

	register_setting(
		'presstest_companion',
		'presstest_companion_cron_browser',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'default'           => 'chromium',
			'show_in_rest'      => array(
				'schema' => array(
					'type' => 'string',
					'enum' => array( 'chromium', 'firefox', 'webkit' ),
				),
			),
		)
	);

	register_setting(
		'presstest_companion',
		'presstest_companion_cron_user_id',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
			'show_in_rest'      => true,
		)
	);
}

/**
 * Render the admin page — outputs the React app mount point.
 *
 * @since 1.0.0
 * @return void
 */
function render_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap"><div id="presstest-admin-app"></div></div>';
}
