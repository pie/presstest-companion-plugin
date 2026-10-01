<?php
/**
 * Read access to the test data settings.
 *
 * The settings themselves are registered in includes/settings.php alongside
 * the plugin's other options.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

/**
 * Typed getters for the test data options.
 */
class Test_Data_Settings {

	/**
	 * Option: whether Presstest may create test data on this site.
	 */
	const ENABLED_OPTION = 'presstest_companion_test_data_enabled';

	/**
	 * Option: roles test users may be created with.
	 */
	const ROLES_OPTION = 'presstest_companion_test_data_roles';

	/**
	 * Roles allowed until an administrator changes the setting. Administrator
	 * is deliberately absent — it must be opted into explicitly.
	 */
	const DEFAULT_ROLES = array( 'subscriber', 'customer' );

	/**
	 * Whether test sessions may be started.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return true === (bool) get_option( self::ENABLED_OPTION, false );
	}

	/**
	 * Roles test users may be created with.
	 *
	 * @return string[]
	 */
	public static function allowed_roles(): array {
		$roles = get_option( self::ROLES_OPTION, self::DEFAULT_ROLES );
		return is_array( $roles ) ? array_values( array_filter( $roles, 'is_string' ) ) : self::DEFAULT_ROLES;
	}
}
