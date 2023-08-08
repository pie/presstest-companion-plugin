<?php

/**
 * Fired during plugin activation
 * 
 * @link       https://pie.co.de
 * @since      1.0.0
 *
 * @package    PIE\TestingPlatform
 * @subpackage PIE\TestingPlatform/includes
 */

namespace PIE\TestingPlatform;

/**
 * Runs once on plugin activation
 *
 * @since    1.0.0
 */
function activate() {

	update_option( 'woocommerce_queue_flush_rewrite_rules', 'true' );
	create_database_tables();

}

/**
 * Add custom db table for storing reports
 *

 * @return void
 */
function create_database_tables() {

	global $wpdb;
	$table = $wpdb->prefix . 'pie_testing_platform_reports'; 

	if ( $wpdb->get_var( "show tables like '$table'" ) != $table ) {

		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			domain varchar(100) DEFAULT '' NOT NULL,
			browser varchar(100) DEFAULT '' NOT NULL,
			date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			report longtext NOT NULL,
			PRIMARY KEY (id)
		) $charset";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

	}

}
