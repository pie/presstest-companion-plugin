<?php
/**
 * Base class for integrations with no-op defaults.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Integrations;

/**
 * Extend this and override only what the integration needs.
 */
abstract class Abstract_Integration implements Integration {

	/**
	 * No hooks by default.
	 *
	 * @return void
	 */
	public function register_hooks(): void {}

	/**
	 * No cleanup handlers by default.
	 *
	 * @return array<string, array{priority: int, callback: callable}>
	 */
	public function get_cleanup_handlers(): array {
		return array();
	}

	/**
	 * No factories by default.
	 *
	 * @return array<string, callable>
	 */
	public function get_factories(): array {
		return array();
	}

	/**
	 * Always ready by default.
	 *
	 * @return string[]
	 */
	public function preflight(): array {
		return array();
	}
}
