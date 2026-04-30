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
 * Enqueue all frontend scripts and styles.
 *
 * Only loads on WooCommerce account pages to avoid polluting other pages.
 *
 * @since 2.0.0
 * @return void
 */
function enqueues_frontend() {
	if ( ! is_account_page() ) {
		return;
	}

	$file_path     = PRESSTEST_COMPANION_FILE_PATH . 'build/static/';
	$enqueue_path  = PRESSTEST_COMPANION_ENQUEUE_PATH . 'build/static/';
	$user_id       = get_current_user_id();
	$user_settings = get_user_meta( $user_id, '_presstest_settings', true );

	if ( ! is_array( $user_settings ) ) {
		$user_settings = array();
	}

	wp_enqueue_script( 'wp-api' );

	foreach ( glob( $file_path . 'js/*.js' ) as $file ) {
		$filename = substr( $file, strrpos( $file, '/' ) + 1 );
		wp_enqueue_script(
			$filename,
			$enqueue_path . 'js/' . $filename,
			array( 'wp-api' ),
			filemtime( $file_path . 'js/' . $filename ),
			true
		);
		wp_localize_script(
			$filename,
			'presstest_companion',
			array(
				'user_id'                       => $user_id,
				'user_settings'                 => $user_settings,
				'domain_already_exists_message' => __( 'Domain has already been added. Please select it from the dropdown to use it for testing.', 'presstest-companion' ),
				'domain_invalid_message'        => __( 'Domain is not a valid URL.', 'presstest-companion' ),
				'domain_added_message'          => __( 'Domain has been successfully added to your list.', 'presstest-companion' ),
				'domain_not_selected_message'   => __( 'No domain selected.', 'presstest-companion' ),
				'domain_removed_message'        => __( 'Selected domain has been successfully removed from your list.', 'presstest-companion' ),
				'tests_run_message'             => __( 'Tests have been queued. Check your reports shortly.', 'presstest-companion' ),
				'tests_error_message'           => __( 'Failed to run tests. Please check your server settings.', 'presstest-companion' ),
			)
		);
	}

	foreach ( glob( $file_path . 'css/*.css' ) as $file ) {
		$filename = substr( $file, strrpos( $file, '/' ) + 1 );
		wp_enqueue_style( $filename, $enqueue_path . 'css/' . $filename );
	}

	wp_enqueue_style( 'dashicons' );
}
