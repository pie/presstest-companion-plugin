<?php
/**
 * All CSS/JS enqueues.
 *
 * @link       https://pie.co.de
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

/**
 * Enqueue scripts and styles on the Presstest admin page.
 *
 * @since 2.0.0
 * @return void
 */
function enqueues_admin() {
	$screen = get_current_screen();

	if ( ! $screen instanceof \WP_Screen || 'toplevel_page_presstest-companion' !== $screen->id ) {
		return;
	}

	$build_path = PRESSTEST_COMPANION_FILE_PATH . 'build/';
	$build_url  = PRESSTEST_COMPANION_ENQUEUE_PATH . 'build/';
	$asset      = require $build_path . 'index.asset.php';
	$user_id    = get_current_user_id();
	$user_meta  = get_user_meta( $user_id, '_presstest_settings', true );

	if ( ! is_array( $user_meta ) ) {
		$user_meta = array();
	}

	wp_enqueue_script(
		'presstest-companion-app',
		$build_url . 'index.js',
		array_merge( $asset['dependencies'], array( 'wp-api' ) ),
		filemtime( $build_path . 'index.js' ),
		true
	);

	wp_localize_script(
		'presstest-companion-app',
		'presstest_companion',
		array(
			'site_url'            => home_url(),
			'server_url'          => PRESSTEST_SERVER_URL,
			'report_url'          => rest_url( 'presstest-companion/v1/report' ),
			'report_token'        => get_option( 'presstest_companion_report_secret', '' ),
			'user_settings'       => $user_meta,
			'tests_run_message'   => __( 'Tests have been queued. Check your reports shortly.', 'presstest-companion' ),
			'tests_error_message' => __( 'Failed to run tests. Please check the server settings.', 'presstest-companion' ),
		)
	);

	wp_enqueue_style(
		'presstest-companion-app',
		$build_url . 'index.css',
		array(),
		filemtime( $build_path . 'index.css' )
	);

	wp_enqueue_style( 'dashicons' );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueues_admin' );
