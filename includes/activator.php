<?php
/**
 * Fired during plugin activation.
 *
 * @link       https://pie.co.de
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

/**
 * Runs once on plugin activation.
 *
 * @since 2.0.0
 * @return void
 */
function activate() {
	update_option( 'woocommerce_queue_flush_rewrite_rules', 'true' );
	create_database_tables();
	generate_report_secret();
	require_once PRESSTEST_COMPANION_FILE_PATH . 'includes/cron.php';
	sync_presstest_cron();
}

/**
 * Generate a cryptographically random report secret on first activation.
 *
 * The secret authenticates callbacks from the Presstest server — it is sent
 * with every test request and validated by the /report REST endpoint via the
 * X-Presstest-Token header. Never overwrites an existing value so the secret
 * survives deactivation/reactivation cycles.
 *
 * @since 2.0.0
 * @return void
 */
function generate_report_secret() {
	if ( '' !== get_option( 'presstest_companion_report_secret', '' ) ) {
		return;
	}
	update_option( 'presstest_companion_report_secret', bin2hex( random_bytes( 32 ) ) );
}

/**
 * Create the custom database table for storing test reports.
 *
 * Uses dbDelta() which handles CREATE TABLE idempotently — safe to call on
 * every activation (e.g. re-activation after update).
 *
 * @since 2.0.0
 * @return void
 */
function create_database_tables() {
	global $wpdb;

	$charset = $wpdb->get_charset_collate();
	$table   = $wpdb->prefix . 'presstest_reports';

	$sql = "CREATE TABLE {$table} (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		user_id bigint(20) NOT NULL,
		domain varchar(255) DEFAULT '' NOT NULL,
		browser varchar(100) DEFAULT '' NOT NULL,
		date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		report longtext NOT NULL,
		PRIMARY KEY (id)
	) {$charset}";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
