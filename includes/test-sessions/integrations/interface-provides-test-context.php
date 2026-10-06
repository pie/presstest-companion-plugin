<?php
/**
 * Optional contract for integrations that give tests site-specific details.
 *
 * Tests run against many sites, so anything a site configures — which page
 * is the checkout, where the account area lives — must come from the site
 * rather than be assumed. An integration implementing this returns those
 * details, and they are included in its preflight result (so tests receive
 * them from the requireReady() call they already make).
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Integrations;

/**
 * Supplies site-specific details tests need.
 */
interface Provides_Test_Context {

	/**
	 * Site-specific details for tests, e.g. configured page URLs.
	 *
	 * Only called while the integration is active.
	 *
	 * @return array<string, mixed>
	 */
	public function get_test_context(): array;
}
