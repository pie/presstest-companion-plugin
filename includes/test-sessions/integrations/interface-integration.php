<?php
/**
 * Contract for a test session integration.
 *
 * An integration teaches the session system about one plugin (or WordPress
 * itself): what to record when it creates things, how to remove them again,
 * whether it is safe to test against (e.g. payment gateways in test mode),
 * and which fixtures tests can request. Supporting a new plugin means writing
 * one class — extend Abstract_Integration and override what you need — and
 * registering it with the presstest_companion_integrations filter.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Integrations;

use PIE\PresstestCompanion\TestSessions\Session;

/**
 * Everything the session system needs from an integration.
 */
interface Integration {

	/**
	 * Unique identifier, used by tests to request preflight checks.
	 *
	 * @return string Lowercase slug, e.g. "woocommerce".
	 */
	public function get_slug(): string;

	/**
	 * Display name for admin notices and messages.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Whether the integrated plugin is installed and active.
	 *
	 * Only active integrations have their hooks registered.
	 *
	 * @return bool
	 */
	public function is_active(): bool;

	/**
	 * Registers tracking hooks (Tracker::record) and any safety guards.
	 *
	 * Hooks must check Session_Context::current() themselves before changing
	 * behaviour, so ordinary site traffic is unaffected.
	 *
	 * @return void
	 */
	public function register_hooks(): void;

	/**
	 * Cleanup handlers keyed by object type.
	 *
	 * Each entry: array( 'priority' => int, 'callback' => callable ). Lower
	 * priorities run first — remove dependants (orders, comments) before what
	 * they depend on (users). The callback receives ( int $object_id,
	 * ?array $data, Session $session ) and returns true once the object is
	 * gone (including when it was already deleted), Cleaner::KEPT when it was
	 * deliberately left in place, Cleaner::DEFERRED when it must wait for
	 * another handler (retried later), or false on failure.
	 *
	 * @return array<string, array{priority: int, callback: callable}>
	 */
	public function get_cleanup_handlers(): array;

	/**
	 * Fixture factories keyed by fixture type.
	 *
	 * Each callback receives ( array $args, Session $session ) and returns the
	 * created object's details as an array, or a WP_Error. Objects created
	 * here are recorded automatically by the tracking hooks, since fixture
	 * requests run inside the session.
	 *
	 * @return array<string, callable>
	 */
	public function get_factories(): array;

	/**
	 * Checks the site is safe to test this integration against.
	 *
	 * @return string[] Problems found; an empty array means ready.
	 */
	public function preflight(): array;
}
