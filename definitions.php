<?php
/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Update it as you release new versions.
 */
if ( ! defined( 'PIE_TESTING_PLATFORM_VERSION' ) ) {
	define( 'PIE_TESTING_PLATFORM_VERSION', '1.0.0' );
}
if ( ! defined( 'PIE_TESTING_PLATFORM_NAME' ) ) {
	define( 'PIE_TESTING_PLATFORM_NAME', 'pie-testing-platform' );
}
if ( ! defined( 'PIE_TESTING_PLATFORM_ENQUEUE_PATH' ) ) {
	define( 'PIE_TESTING_PLATFORM_ENQUEUE_PATH', plugins_url( '/', __FILE__ ) );
}
if ( ! defined( 'PIE_TESTING_PLATFORM_FILE_PATH' ) ) {
	define( 'PIE_TESTING_PLATFORM_FILE_PATH', plugin_dir_path( __FILE__ ) );
}