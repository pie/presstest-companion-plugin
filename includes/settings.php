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
 * Returns the browser slugs the Presstest server can run tests in.
 *
 * Single source of truth for validating the browser on both interactive runs
 * and the scheduled test setting. Each slug must match a Playwright project
 * name in the Presstest server's playwright.config.js.
 *
 * @since 1.2.1
 * @return string[] Supported browser slugs.
 */
function get_supported_browsers(): array {
	return array( 'chromium', 'firefox', 'webkit', 'mobile-chrome', 'mobile-safari' );
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
					'enum' => get_supported_browsers(),
				),
			),
		)
	);

	// Test data: whether Presstest may create users, orders, etc. while
	// testing, and which roles its test users may have. Administrator must be
	// opted into explicitly — it is not in the default list.
	register_setting(
		'presstest_companion',
		TestSessions\Test_Data_Settings::ENABLED_OPTION,
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => true,
		)
	);

	register_setting(
		'presstest_companion',
		TestSessions\Test_Data_Settings::ROLES_OPTION,
		array(
			'type'              => 'array',
			'default'           => TestSessions\Test_Data_Settings::DEFAULT_ROLES,
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_test_data_roles',
			'show_in_rest'      => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
		)
	);
}

/**
 * Keeps only role slugs that exist on this site.
 *
 * @since 1.3.0
 * @param mixed $roles Submitted role slugs.
 * @return string[] Valid role slugs.
 */
function sanitize_test_data_roles( $roles ): array {
	if ( ! is_array( $roles ) ) {
		return array();
	}

	return array_values( array_filter( array_map( 'sanitize_key', $roles ), fn( string $role ): bool => null !== get_role( $role ) ) );
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
