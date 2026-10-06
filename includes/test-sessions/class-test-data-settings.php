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

	/**
	 * Whether a user's current roles and directly granted capabilities are all
	 * allowed roles.
	 *
	 * Checked when test users are created and again whenever one is logged
	 * in, because a session's users can come from the site's own registration
	 * forms (which assign whatever role the site or a plugin chooses) and
	 * their privileges can change after creation.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_has_only_allowed_roles( int $user_id ): bool {
		// Roles are cached on the user object; read them fresh.
		clean_user_cache( $user_id );
		$user = new \WP_User( $user_id );

		// $user->caps holds both roles and any directly granted capabilities.
		$granted = array_keys( array_filter( $user->caps ) );

		return array() === array_diff( $granted, self::allowed_roles() );
	}
}
