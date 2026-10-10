<?php
/**
 * Admin notices for integrations that failed their preflight check.
 *
 * When a test run finds, for example, a payment gateway in live mode, the
 * tests are stopped and a notice stays on the dashboard for administrators
 * until a later preflight for that integration passes.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

/**
 * Stores and renders preflight failure notices.
 */
class Notices {

	/**
	 * Option holding active notices keyed by integration slug.
	 */
	const OPTION = 'presstest_companion_preflight_notices';

	/**
	 * Registers the admin notice output.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	/**
	 * Records (or replaces) the notice for an integration.
	 *
	 * @param string   $slug     Integration slug.
	 * @param string   $name     Integration display name.
	 * @param string[] $problems Problems found by the preflight check.
	 * @return void
	 */
	public static function set( string $slug, string $name, array $problems ): void {
		$notices          = self::all();
		$notices[ $slug ] = array(
			'name'     => $name,
			'problems' => array_values( $problems ),
			'time'     => time(),
		);
		update_option( self::OPTION, $notices, false );
	}

	/**
	 * Removes the notice for an integration, e.g. once its preflight passes.
	 *
	 * @param string $slug Integration slug.
	 * @return void
	 */
	public static function clear( string $slug ): void {
		$notices = self::all();

		if ( isset( $notices[ $slug ] ) ) {
			unset( $notices[ $slug ] );
			update_option( self::OPTION, $notices, false );
		}
	}

	/**
	 * Active notices keyed by integration slug.
	 *
	 * @return array<string, array{name: string, problems: string[], time: int}>
	 */
	public static function all(): array {
		$notices = get_option( self::OPTION, array() );
		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Outputs a notice per failing integration for administrators.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( self::all() as $notice ) {
			echo '<div class="notice notice-error"><p><strong>';
			echo esc_html(
				sprintf(
					/* translators: 1: integration name, 2: date and time. */
					__( 'Presstest stopped %1$s tests on %2$s because the site is not safe to test:', 'presstest-companion' ),
					$notice['name'],
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $notice['time'] )
				)
			);
			echo '</strong></p><ul style="list-style: disc; padding-left: 1.5em;">';
			foreach ( $notice['problems'] as $problem ) {
				echo '<li>' . esc_html( $problem ) . '</li>';
			}
			echo '</ul><p>' . esc_html__( 'This notice clears automatically once a test run finds the problem fixed.', 'presstest-companion' ) . '</p></div>';
		}
	}
}
