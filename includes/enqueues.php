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
        }

        foreach( glob( $file_path . 'css/*.css' ) as $file ) {
            $filename = substr( $file, strrpos( $file, '/' ) + 1 );
            wp_enqueue_style( $filename, $enqueue_path . 'css/' . $filename );
        }
    }
}