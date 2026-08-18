<?php
/**
 * All required plugin definitions.
 *
 * @package PIE\PresstestCompanion
 * @since   1.0.0
 */

if ( ! defined( 'PRESSTEST_COMPANION_VERSION' ) ) {
	define( 'PRESSTEST_COMPANION_VERSION', '1.1.0' );
}
if ( ! defined( 'PRESSTEST_COMPANION_NAME' ) ) {
	define( 'PRESSTEST_COMPANION_NAME', 'presstest-companion' );
}
if ( ! defined( 'PRESSTEST_COMPANION_ENQUEUE_PATH' ) ) {
	define( 'PRESSTEST_COMPANION_ENQUEUE_PATH', plugins_url( '/', __FILE__ ) );
}
if ( ! defined( 'PRESSTEST_COMPANION_FILE_PATH' ) ) {
	define( 'PRESSTEST_COMPANION_FILE_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'PRESSTEST_SERVER_URL' ) ) {
	define( 'PRESSTEST_SERVER_URL', 'https://api.presstest.io' );
}
if ( ! defined( 'PRESSTEST_UPDATE_JSON_URL' ) ) {
	define( 'PRESSTEST_UPDATE_JSON_URL', 'https://api.presstest.io/presstest-companion.json' );
}
