<?php
/**
 * Admin settings page — configure the Presstest server connection.
 *
 * @link       https://pie.co.de
 * @since      2.0.0
 *
 * @package    PIE\PresstestCompanion
 * @subpackage PIE\PresstestCompanion/includes
 */

namespace PIE\PresstestCompanion;

/**
 * Register the settings page under Settings > Presstest.
 *
 * @since 2.0.0
 * @return void
 */
function add_settings_page() {
	add_options_page(
		__( 'Presstest Companion', 'presstest-companion' ),
		__( 'Presstest', 'presstest-companion' ),
		'manage_options',
		'presstest-companion',
		__NAMESPACE__ . '\render_settings_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_settings_page' );

/**
 * Register plugin settings with the Settings API.
 *
 * @since 2.0.0
 * @return void
 */
function register_settings() {
	register_setting(
		'presstest_companion',
		'presstest_companion_server_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);

	register_setting(
		'presstest_companion',
		'presstest_companion_api_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	add_settings_section(
		'presstest_companion_section',
		__( 'Server Connection', 'presstest-companion' ),
		'__return_false',
		'presstest-companion'
	);

	add_settings_field(
		'presstest_companion_server_url',
		__( 'Server URL', 'presstest-companion' ),
		__NAMESPACE__ . '\render_server_url_field',
		'presstest-companion',
		'presstest_companion_section'
	);

	add_settings_field(
		'presstest_companion_api_key',
		__( 'API Key', 'presstest-companion' ),
		__NAMESPACE__ . '\render_api_key_field',
		'presstest-companion',
		'presstest_companion_section'
	);
}
add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );

/**
 * Render the Server URL settings field.
 *
 * @since 2.0.0
 * @return void
 */
function render_server_url_field() {
	$value = get_option( 'presstest_companion_server_url', '' );
	?>
	<input
		type="url"
		id="presstest_companion_server_url"
		name="presstest_companion_server_url"
		value="<?php echo esc_url( $value ); ?>"
		class="regular-text"
		placeholder="https://your-presstest-server.com"
	/>
	<p class="description"><?php esc_html_e( 'The full URL of your Presstest server (without a trailing slash).', 'presstest-companion' ); ?></p>
	<?php
}

/**
 * Render the API Key settings field.
 *
 * @since 2.0.0
 * @return void
 */
function render_api_key_field() {
	$value = get_option( 'presstest_companion_api_key', '' );
	?>
	<input
		type="password"
		id="presstest_companion_api_key"
		name="presstest_companion_api_key"
		value="<?php echo esc_attr( $value ); ?>"
		class="regular-text"
		autocomplete="new-password"
	/>
	<p class="description"><?php esc_html_e( 'The PRESSTEST_API_KEY value from your Presstest server .env file.', 'presstest-companion' ); ?></p>
	<?php
}

/**
 * Render the settings page.
 *
 * @since 2.0.0
 * @return void
 */
function render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'presstest_companion' );
			do_settings_sections( 'presstest-companion' );
			submit_button( __( 'Save Settings', 'presstest-companion' ) );
			?>
		</form>
	</div>
	<?php
}
