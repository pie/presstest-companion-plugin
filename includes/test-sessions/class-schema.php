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
	 * Transient set after a failed migration, so a persistent database
	 * problem doesn't re-run dbDelta() on every request.
	 */
	const RETRY_TRANSIENT = 'presstest_companion_sessions_db_retry';

	/**
	 * Seconds to wait before retrying a failed migration.
	 */
	const RETRY_DELAY = 5 * MINUTE_IN_SECONDS;

	/**
	 * Installs or upgrades the tables if the stored version is out of date.
	 *
	 * Cheap enough to run on every request: a single autoloaded option read
	 * (plus a transient read while a failed migration is backing off).
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( self::SCHEMA_VERSION !== get_option( self::VERSION_OPTION, '' ) && false === get_transient( self::RETRY_TRANSIENT ) ) {
			self::install();
		}
	}

	/**
	 * Creates or updates all session tables via dbDelta(), and records the
	 * schema version only once the tables are confirmed to match.
	 *
	 * The dbDelta() function doesn't report failures, so the migration is
	 * confirmed with a dry run: any change it would still make means a CREATE
	 * or ALTER failed (e.g. a permissions or connection error). The version is
	 * then left unrecorded so the migration is retried after RETRY_DELAY.
	 *
	 * @return bool True if the tables match the current schema.
	 */
	public static function install(): bool {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = self::definitions();
		dbDelta( $sql );

		$pending = dbDelta( $sql, false );

		if ( array() !== $pending ) {
			set_transient( self::RETRY_TRANSIENT, 1, self::RETRY_DELAY );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- surfaced in the server error log for whoever investigates.
			error_log( 'Presstest Companion: test session tables could not be created or updated; retrying in ' . self::RETRY_DELAY . 's. Outstanding changes: ' . implode( ' | ', $pending ) );
			return false;
		}

		delete_transient( self::RETRY_TRANSIENT );
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );

		return true;
	}

	/**
	 * CREATE TABLE statements for every session table, in dbDelta() format.
	 *
	 * @return string
	 */
	private static function definitions(): string {
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

		return $sql;
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
