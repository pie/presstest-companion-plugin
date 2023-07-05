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
            wp_enqueue_script( $filename, $enqueue_path . 'js/' . $filename, array(), false, true );
            wp_localize_script( $filename, 'my_account_app', array(
                'user_id' => get_current_user_id(),
                'domains' => get_user_meta( get_current_user_id(), '_pie_testing_domains', true ) ? get_user_meta( get_current_user_id(), '_pie_testing_domains', true ) : array(),
            ));
        }

        foreach( glob( $file_path . 'css/*.css' ) as $file ) {
            $filename = substr( $file, strrpos( $file, '/' ) + 1 );
            wp_enqueue_style( $filename, $enqueue_path . 'css/' . $filename );
        }

        wp_enqueue_script( 'wp-api' );
    }
}