<?php

/**
 * All CSS/JS enqueues.
 *
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\TestingPlatform
 * @subpackage PIE\TestingPlatform/includes
 */

namespace PIE\TestingPlatform;

/**
 * Enqueue all frontend scripts and styles
 *
 * @since    1.0.0
 */
function enqueues_frontend() {

    $file_path    = PIE_TESTING_PLATFORM_FILE_PATH . '/build/static/';
    $enqueue_path = PIE_TESTING_PLATFORM_ENQUEUE_PATH . '/build/static/';

    // Only enqueue react generated assets if on the WC account page (this may need to be changed if compiling everything)
    if ( is_account_page() ) {
        
        foreach( glob( $file_path . 'js/*.js' ) as $file ) {
            $filename = substr( $file, strrpos( $file, '/' ) + 1 );
            wp_enqueue_script( $filename, $enqueue_path . 'js/' . $filename, array(), filemtime( $file_path . 'js/' . $filename ), true );
            wp_localize_script( $filename, 'my_account_app', array(
                'user_id'                         => get_current_user_id(),
                'username'                        => 'joey@pie.co.de',
                'password'                        => '***REMOVED***',
                'user_settings'                   => get_user_meta( get_current_user_id(), '_presstest_settings', true ) ? get_user_meta( get_current_user_id(), '_presstest_settings', true ) : array(),
                'domain_already_exists_message'   => __( 'Domain has already been added.  Please select it from the dropdown to use it for testing.', 'pie-testing-platform' ),
                'domain_invalid_message'          => __( 'Domain is not a valid URL.', 'pie-testing-platform' ),
                'domain_added_message'            => __( 'Domain has been successfully added to your list.', 'pie-testing-platform' ),
                'domain_not_selected_message'     => __( 'No domain selected.', 'pie-testing-platform' ),
                'domain_removed_message'          => __( 'Selected domain has been successfully removed from your list.', 'pie-testing-platform' ),
                'tests_run_message'               => __( 'Tests were run successfully, see your reports.', 'pie-testing-platform' ),
            ));
        }

        foreach( glob( $file_path . 'css/*.css' ) as $file ) {
            $filename = substr( $file, strrpos( $file, '/' ) + 1 );
            wp_enqueue_style( $filename, $enqueue_path . 'css/' . $filename );
        }

        wp_enqueue_style( 'dashicons' );
        wp_enqueue_script( 'wp-api' );
    }
}