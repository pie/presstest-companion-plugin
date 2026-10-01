<?php
/**
 * Database tables for test sessions.
 *
 * Three tables back the session system:
 *   presstest_sessions         — one row per test run (status, expiry, cleanup summary).
 *   presstest_session_objects  — everything a session created, so it can be removed.
 *   presstest_session_emails   — emails captured (and blocked) during a session.
 *
 * Installed on activation and upgraded in place when SCHEMA_VERSION changes, so
 * sites that update the plugin without reactivating it still get new tables.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

/**
 * Creates and upgrades the session tables.
 */
class Schema {

	/**
	 * Bump whenever a table definition below changes.
	 */
	const SCHEMA_VERSION = '2';

	/**
	 * Option storing the installed schema version.
	 */
	const VERSION_OPTION = 'presstest_companion_sessions_db_version';

	/**
	 * Installs or upgrades the tables if the stored version is out of date.
	 *
	 * Cheap enough to run on every request: a single autoloaded option read.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( self::SCHEMA_VERSION !== get_option( self::VERSION_OPTION, '' ) ) {
			self::install();
		}
	}

	/**
	 * Creates or updates all session tables via dbDelta().
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		$charset  = $wpdb->get_charset_collate();
		$sessions = self::sessions_table();
		$objects  = self::objects_table();
		$emails   = self::emails_table();

		// dbDelta() is whitespace-sensitive: two spaces after PRIMARY KEY, one
		// field per line, and KEY rather than INDEX.
		$sql = "CREATE TABLE {$sessions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			label varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			ended_at datetime DEFAULT NULL,
			summary longtext DEFAULT NULL,
			active_requests int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY status_expires (status, expires_at)
		) {$charset};
		CREATE TABLE {$objects} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned NOT NULL,
			object_type varchar(50) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			data longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_object (session_id, object_type, object_id),
			KEY object_lookup (object_type, object_id)
		) {$charset};
		CREATE TABLE {$emails} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned NOT NULL,
			recipients text NOT NULL,
			subject text NOT NULL,
			message longtext NOT NULL,
			headers text NOT NULL,
			attachments text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Full name of the sessions table.
	 *
	 * @return string Table name including the site prefix.
	 */
	public static function sessions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'presstest_sessions';
	}

	/**
	 * Full name of the session objects table.
	 *
	 * @return string Table name including the site prefix.
	 */
	public static function objects_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'presstest_session_objects';
	}

	/**
	 * Full name of the captured emails table.
	 *
	 * @return string Table name including the site prefix.
	 */
	public static function emails_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'presstest_session_emails';
	}
}
