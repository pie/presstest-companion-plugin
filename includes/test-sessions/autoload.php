<?php
/**
 * Autoloader for the test session classes.
 *
 * Maps WordPress-style class names to WordPress-style file names, e.g.
 *   PIE\PresstestCompanion\TestSessions\Session_Repository
 *     → includes/test-sessions/class-session-repository.php
 *   PIE\PresstestCompanion\TestSessions\Integrations\Integration
 *     → includes/test-sessions/integrations/interface-integration.php
 *
 * New classes are picked up without regenerating anything.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions;

spl_autoload_register(
	function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$parts     = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$file_name = strtolower( str_replace( '_', '-', array_pop( $parts ) ) );
		$directory = __DIR__ . '/' . ( array() !== $parts ? strtolower( implode( '/', $parts ) ) . '/' : '' );

		foreach ( array( 'class', 'interface' ) as $type ) {
			$file = $directory . $type . '-' . $file_name . '.php';

			if ( is_readable( $file ) ) {
				require $file;
				return;
			}
		}
	}
);
