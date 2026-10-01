<?php
/**
 * Collects the integrations the session system knows about.
 *
 * Built-in integrations are registered here; site code or other plugins add
 * their own via the presstest_companion_integrations filter:
 *
 *     add_filter( 'presstest_companion_integrations', function ( array $integrations ): array {
 *         $integrations[] = new My_Plugin_Integration();
 *         return $integrations;
 *     } );
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Integrations;

/**
 * Holds every registered integration, keyed by slug.
 */
class Integration_Registry {

	/**
	 * Registered integrations keyed by slug.
	 *
	 * @var array<string, Integration>
	 */
	private array $integrations = array();

	/**
	 * Registers the built-in integrations plus any added by the filter.
	 */
	public function __construct() {
		$integrations = apply_filters(
			'presstest_companion_integrations',
			array(
				new WordPress(),
				new WooCommerce(),
				new Paid_Memberships_Pro(),
				new Action_Scheduler(),
			)
		);

		foreach ( (array) $integrations as $integration ) {
			if ( $integration instanceof Integration ) {
				$this->integrations[ $integration->get_slug() ] = $integration;
			}
		}
	}

	/**
	 * Integrations whose plugin is active on this site.
	 *
	 * @return array<string, Integration>
	 */
	public function active(): array {
		return array_filter( $this->integrations, fn( Integration $integration ): bool => $integration->is_active() );
	}

	/**
	 * Finds an integration by slug, active or not.
	 *
	 * @param string $slug Integration slug.
	 * @return Integration|null
	 */
	public function get( string $slug ): ?Integration {
		return $this->integrations[ $slug ] ?? null;
	}

	/**
	 * Cleanup handlers from all active integrations, keyed by object type.
	 *
	 * @return array<string, array{priority: int, callback: callable}>
	 */
	public function cleanup_handlers(): array {
		$handlers = array();

		foreach ( $this->active() as $integration ) {
			$handlers = array_merge( $handlers, $integration->get_cleanup_handlers() );
		}

		return $handlers;
	}

	/**
	 * Fixture factories from all active integrations, keyed by fixture type.
	 *
	 * @return array<string, callable>
	 */
	public function factories(): array {
		$factories = array();

		foreach ( $this->active() as $integration ) {
			$factories = array_merge( $factories, $integration->get_factories() );
		}

		return $factories;
	}
}
