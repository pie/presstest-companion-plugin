<?php
/**
 * WooCommerce integration: orders, refunds, cart sessions, payment safety,
 * and product/coupon fixtures.
 *
 * Payment safety is enforced twice:
 *   - preflight() reports any enabled gateway that isn't verifiably in test
 *     mode, so checkout tests stop before they start and the admin is told;
 *   - during session requests, unverified gateways are removed from the
 *     available list, so even a test that skips preflight cannot pay with one.
 *
 * Products and coupons are posts, so the WordPress integration records and
 * removes them; WooCommerce's own delete hooks clear its lookup tables.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Integrations;

use Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore as CustomersDataStore;
use PIE\PresstestCompanion\TestSessions\Email_Capture;
use PIE\PresstestCompanion\TestSessions\Session;
use PIE\PresstestCompanion\TestSessions\Session_Context;
use PIE\PresstestCompanion\TestSessions\Tracker;

/**
 * Tracking, cleanup, safety checks, and fixtures for WooCommerce.
 */
class WooCommerce extends Abstract_Integration implements Owns_Post_Types, Provides_Test_Context {

	/**
	 * Gateways that never take payment online.
	 */
	const OFFLINE_GATEWAYS = array( 'bacs', 'cheque', 'cod' );

	/**
	 * Gateway settings that switch test mode on when set to "yes".
	 */
	const TEST_MODE_SETTINGS = array( 'testmode', 'test_mode', 'sandbox', 'sandbox_mode', 'enable_sandbox' );

	/**
	 * Values of an "environment" setting that mean test mode.
	 */
	const TEST_ENVIRONMENTS = array( 'sandbox', 'test', 'testing', 'staging', 'development' );

	/**
	 * Orders being saved for the first time, keyed by spl_object_id().
	 *
	 * @var array<int, true>
	 */
	private array $new_orders = array();

	/**
	 * Order post types — orders with posts-based storage, refunds, and the
	 * placeholder posts HPOS keeps. Listed directly (not via
	 * wc_get_order_types()) so ownership holds while WooCommerce is inactive.
	 *
	 * @return array<string, string>
	 */
	public function get_owned_post_types(): array {
		return array(
			'shop_order'           => 'wc_order',
			'shop_order_refund'    => 'wc_order',
			'shop_order_placehold' => 'wc_order',
		);
	}

	/**
	 * Store settings tests must not assume: the configured page URLs (stores
	 * can move the cart, checkout and account pages, and rename endpoints),
	 * and where the store is and sells to, so checkout tests can enter an
	 * address the store accepts.
	 *
	 * @return array<string, mixed>
	 */
	public function get_test_context(): array {
		return array(
			'cart_url'          => wc_get_cart_url(),
			'checkout_url'      => wc_get_checkout_url(),
			'account_url'       => wc_get_page_permalink( 'myaccount' ),
			'orders_url'        => wc_get_account_endpoint_url( 'orders' ),
			'base_country'      => WC()->countries->get_base_country(),
			'allowed_countries' => array_keys( WC()->countries->get_allowed_countries() ),
		);
	}

	/**
	 * Integration slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'woocommerce';
	}

	/**
	 * Integration display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'WooCommerce';
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Registers order tracking and the payment and email guards.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// woocommerce_new_order is not fired for checkout drafts, which the
		// Checkout block creates as soon as the page loads — so creation is
		// detected directly: an order without an ID when its save starts.
		add_action( 'woocommerce_before_order_object_save', array( $this, 'flag_new_order' ) );
		add_action( 'woocommerce_after_order_object_save', array( $this, 'track_new_order' ) );
		add_action( 'woocommerce_refund_created', array( $this, 'track_order' ) );
		add_action( 'shutdown', array( $this, 'track_cart_session' ), 1 );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_unsafe_gateways' ), PHP_INT_MAX );
		add_filter( 'woocommerce_defer_transactional_emails', array( $this, 'send_emails_immediately' ), PHP_INT_MAX );
	}

	/**
	 * Notes an order that is about to be saved for the first time.
	 *
	 * @param \WC_Order $order Order being saved.
	 * @return void
	 */
	public function flag_new_order( $order ): void {
		if ( $order instanceof \WC_Abstract_Order && 0 === $order->get_id() ) {
			$this->new_orders[ spl_object_id( $order ) ] = true;
		}
	}

	/**
	 * Records an order once its first save has given it an ID. Saves of
	 * existing orders are ignored, so real orders a test edits are never
	 * treated as test data.
	 *
	 * @param \WC_Order $order Saved order.
	 * @return void
	 */
	public function track_new_order( $order ): void {
		$key = spl_object_id( $order );

		if ( isset( $this->new_orders[ $key ] ) ) {
			unset( $this->new_orders[ $key ] );
			$this->track_order( $order->get_id() );
		}
	}

	/**
	 * Records a new order or refund.
	 *
	 * @param int $order_id Order or refund ID.
	 * @return void
	 */
	public function track_order( int $order_id ): void {
		Tracker::record( 'wc_order', $order_id );
	}

	/**
	 * Records the session's cart session row so it doesn't linger for the
	 * usual 48 hours. Runs before WooCommerce saves the session on shutdown.
	 *
	 * @return void
	 */
	public function track_cart_session(): void {
		if ( null === Session_Context::current() || ! isset( WC()->session ) || ! WC()->session instanceof \WC_Session ) {
			return;
		}

		$key = (string) WC()->session->get_customer_id();

		if ( '' !== $key ) {
			// Session keys are strings; the objects table wants an integer ID,
			// so store a stable hash as the ID and the real key as data.
			Tracker::record( 'wc_session', (int) sprintf( '%u', crc32( $key ) ), array( 'key' => $key ) );
		}
	}

	/**
	 * Removes gateways not verified as test or offline during session requests.
	 *
	 * @param array $gateways Available gateways keyed by ID.
	 * @return array
	 */
	public function remove_unsafe_gateways( $gateways ) {
		if ( null === Session_Context::current() || ! is_array( $gateways ) ) {
			return $gateways;
		}

		return array_filter(
			$gateways,
			fn( $gateway ): bool => $gateway instanceof \WC_Payment_Gateway && in_array( $this->gateway_mode( $gateway ), array( 'test', 'offline' ), true )
		);
	}

	/**
	 * Sends transactional emails during the request instead of deferring
	 * them to a background queue, so they're captured with the session.
	 *
	 * @param bool $defer Whether WooCommerce would defer emails.
	 * @return bool
	 */
	public function send_emails_immediately( $defer ) {
		return null !== Session_Context::current() ? false : $defer;
	}

	/**
	 * Reports enabled gateways that could take a real payment.
	 *
	 * @return string[]
	 */
	public function preflight(): array {
		$problems = array();

		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			if ( ! $gateway instanceof \WC_Payment_Gateway || 'yes' !== $gateway->enabled ) {
				continue;
			}

			$label = sprintf( '%s (%s)', wp_strip_all_tags( (string) $gateway->get_method_title() ), $gateway->id );

			switch ( $this->gateway_mode( $gateway ) ) {
				case 'live':
					/* translators: %s: gateway name and ID. */
					$problems[] = sprintf( __( '%s is in live mode. Switch it to test mode, or disable it on this site.', 'presstest-companion' ), $label );
					break;

				case 'unknown':
					$problems[] = sprintf(
						/* translators: %s: gateway name and ID. */
						__( '%s: Presstest cannot confirm it is in test mode. If it is, confirm it with the presstest_companion_gateway_mode filter; otherwise disable it on this site.', 'presstest-companion' ),
						$label
					);
					break;
			}
		}

		return $problems;
	}

	/**
	 * Works out whether a gateway could take a real payment.
	 *
	 * Deliberately conservative: anything not positively identified as test
	 * or offline is "unknown" and treated as unsafe. Site code can correct a
	 * gateway Presstest can't read via the presstest_companion_gateway_mode
	 * filter.
	 *
	 * @param \WC_Payment_Gateway $gateway Gateway to check.
	 * @return string One of: test, offline, live, unknown.
	 */
	public function gateway_mode( \WC_Payment_Gateway $gateway ): string {
		$mode = $this->detect_gateway_mode( $gateway );

		/**
		 * Filters the detected mode of a payment gateway.
		 *
		 * @param string              $mode    One of: test, offline, live, unknown.
		 * @param \WC_Payment_Gateway $gateway The gateway.
		 */
		$mode = (string) apply_filters( 'presstest_companion_gateway_mode', $mode, $gateway );

		return in_array( $mode, array( 'test', 'offline', 'live' ), true ) ? $mode : 'unknown';
	}

	/**
	 * Detects a gateway's mode from known plugins and common setting names.
	 *
	 * @param \WC_Payment_Gateway $gateway Gateway to check.
	 * @return string One of: test, offline, live, unknown.
	 */
	private function detect_gateway_mode( \WC_Payment_Gateway $gateway ): string {
		// Cheque, bank transfer and cash on delivery never take payment online.
		if ( in_array( $gateway->id, self::OFFLINE_GATEWAYS, true ) ) {
			return 'offline';
		}

		// WooPayments keeps its mode outside the gateway settings.
		if ( 'woocommerce_payments' === $gateway->id && class_exists( 'WC_Payments' ) && method_exists( 'WC_Payments', 'mode' ) ) {
			return true === \WC_Payments::mode()->is_test() ? 'test' : 'live';
		}

		$settings = is_array( $gateway->settings ) ? $gateway->settings : array();

		// A test-mode switch (e.g. Stripe's "testmode"): on means test, any
		// other value means live. Gateways have one such switch, so the first
		// found decides.
		foreach ( self::TEST_MODE_SETTINGS as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				return in_array( $settings[ $key ], array( 'yes', '1', 1, true ), true ) ? 'test' : 'live';
			}
		}

		// Otherwise an environment setting (e.g. "sandbox" or "production").
		if ( isset( $settings['environment'] ) && is_string( $settings['environment'] ) ) {
			return in_array( strtolower( $settings['environment'] ), self::TEST_ENVIRONMENTS, true ) ? 'test' : 'live';
		}

		// Nothing recognisable: treated as unsafe until the site confirms it
		// with the presstest_companion_gateway_mode filter.
		return 'unknown';
	}

	/**
	 * Orders go first so their stock and coupon usage are restored while the
	 * products and coupons still exist.
	 *
	 * @return array<string, array{priority: int, callback: callable}>
	 */
	public function get_cleanup_handlers(): array {
		return array(
			'wc_order'   => array(
				'priority' => 10,
				'callback' => array( $this, 'delete_order' ),
			),
			'wc_session' => array(
				'priority' => 15,
				'callback' => array( $this, 'delete_cart_session' ),
			),
		);
	}

	/**
	 * Deletes an order as if it never happened: restores stock and coupon
	 * usage, releases held stock, then removes the order and its refunds.
	 * WooCommerce's delete hooks clear the analytics tables.
	 *
	 * @param int $order_id Order or refund ID.
	 * @return bool True once the order is gone.
	 */
	public function delete_order( int $order_id ): bool {
		$order = wc_get_order( $order_id );

		// Already gone (e.g. deleted with its parent order).
		if ( false === $order ) {
			return true;
		}

		$guest_email = '';

		// Refunds are recorded too, but have no stock, coupons or customer of
		// their own, so only a real order goes through these steps.
		if ( $order instanceof \WC_Order ) {
			// Refunds first, so none is left pointing at a deleted order.
			foreach ( $order->get_refunds() as $refund ) {
				$refund->delete( true );
			}

			// Reverse the order's effects while its data still exists.
			$this->restore_stock( $order );
			$this->restore_coupon_usage( $order );
			wc_release_stock_for_order( $order );

			// Note a guest's email now; their analytics record is removed after.
			$guest_email = 0 === $order->get_user_id() ? $order->get_billing_email() : '';
		}

		$order->delete( true );

		$this->delete_guest_customer( $guest_email );

		// delete() doesn't report failure, so confirm the order is gone.
		return false === wc_get_order( $order_id );
	}

	/**
	 * Deletes a cart session row.
	 *
	 * @param int        $id   Hash of the session key (unused).
	 * @param array|null $data Recorded data: array( 'key' => string ).
	 * @return bool False on a database error, so the session row is retried.
	 */
	public function delete_cart_session( int $id, ?array $data ): bool {
		global $wpdb;

		$key = (string) ( $data['key'] ?? '' );

		if ( '' === $key ) {
			return true;
		}

		// False means a database error; 0 rows means the session already expired.
		return false !== $wpdb->delete( $wpdb->prefix . 'woocommerce_sessions', array( 'session_key' => $key ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Puts back stock the order took, if it took any.
	 *
	 * @param \WC_Order $order Order being deleted.
	 * @return void
	 */
	private function restore_stock( \WC_Order $order ): void {
		$data_store = $order->get_data_store();

		if ( true === (bool) $data_store->get_stock_reduced( $order->get_id() ) ) {
			wc_increase_stock_levels( $order );
			$data_store->set_stock_reduced( $order->get_id(), false );
		}
	}

	/**
	 * Reverses the coupon usage counts the order recorded, mirroring
	 * wc_update_coupon_usage_counts() for a cancelled order.
	 *
	 * @param \WC_Order $order Order being deleted.
	 * @return void
	 */
	private function restore_coupon_usage( \WC_Order $order ): void {
		$data_store = $order->get_data_store();

		if ( true !== (bool) $data_store->get_recorded_coupon_usage_counts( $order ) ) {
			return;
		}

		$used_by = 0 !== $order->get_user_id() ? $order->get_user_id() : $order->get_billing_email();

		foreach ( $order->get_coupon_codes() as $code ) {
			if ( '' !== trim( (string) $code ) ) {
				( new \WC_Coupon( $code ) )->decrease_usage_count( $used_by );
			}
		}

		$data_store->set_recorded_coupon_usage_counts( $order, false );

		if ( function_exists( 'wc_release_coupons_for_order' ) ) {
			wc_release_coupons_for_order( $order );
		}
	}

	/**
	 * Removes the analytics customer row a guest checkout created. Only ever
	 * for test addresses, so a real guest customer's record is never touched.
	 *
	 * @param string $email Guest billing email.
	 * @return void
	 */
	private function delete_guest_customer( string $email ): void {
		if ( '@' . Email_Capture::TEST_EMAIL_DOMAIN !== strtolower( (string) strrchr( $email, '@' ) ) || ! class_exists( CustomersDataStore::class ) ) {
			return;
		}

		$customer_id = CustomersDataStore::get_guest_id_by_email( $email );

		if ( false !== $customer_id && null !== $customer_id ) {
			CustomersDataStore::delete_customer( (int) $customer_id );
		}
	}

	/**
	 * Fixture factories for WooCommerce data.
	 *
	 * @return array<string, callable>
	 */
	public function get_factories(): array {
		return array(
			'wc_product' => array( $this, 'create_product' ),
			'wc_coupon'  => array( $this, 'create_coupon' ),
		);
	}

	/**
	 * Creates a simple product.
	 *
	 * Args: name, regular_price (default "10.00"), virtual (default true, so
	 * checkout needs no shipping), stock_quantity (enables stock management
	 * when set).
	 *
	 * @param array   $args    Factory arguments.
	 * @param Session $session Session the product belongs to.
	 * @return array|\WP_Error Product details: id, url, price, add_to_cart_url.
	 */
	public function create_product( array $args, Session $session ) {
		$product = new \WC_Product_Simple();
		$product->set_name( sanitize_text_field( (string) ( $args['name'] ?? sprintf( 'Presstest product %d', $session->get_id() ) ) ) );
		$product->set_status( 'publish' );
		$product->set_regular_price( wc_format_decimal( (string) ( $args['regular_price'] ?? '10.00' ) ) );
		$product->set_virtual( false !== ( $args['virtual'] ?? true ) );

		if ( isset( $args['stock_quantity'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $args['stock_quantity'] );
		}

		$product_id = $product->save();

		if ( 0 === $product_id ) {
			return new \WP_Error( 'presstest_product_failed', __( 'The product could not be created.', 'presstest-companion' ), array( 'status' => 500 ) );
		}

		return array(
			'id'              => $product_id,
			'url'             => get_permalink( $product_id ),
			'price'           => $product->get_price(),
			'add_to_cart_url' => add_query_arg( 'add-to-cart', $product_id, wc_get_cart_url() ),
		);
	}

	/**
	 * Creates a coupon.
	 *
	 * Args: code (random if omitted), discount_type (default "percent"),
	 * amount (default 10).
	 *
	 * @param array   $args    Factory arguments.
	 * @param Session $session Session the coupon belongs to.
	 * @return array|\WP_Error Coupon details: id, code.
	 */
	public function create_coupon( array $args, Session $session ) {
		$code   = wc_format_coupon_code( (string) ( $args['code'] ?? sprintf( 'presstest-%d-%s', $session->get_id(), strtolower( wp_generate_password( 6, false ) ) ) ) );
		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( sanitize_key( (string) ( $args['discount_type'] ?? 'percent' ) ) );
		$coupon->set_amount( wc_format_decimal( (string) ( $args['amount'] ?? '10' ) ) );

		$coupon_id = $coupon->save();

		if ( 0 === $coupon_id ) {
			return new \WP_Error( 'presstest_coupon_failed', __( 'The coupon could not be created.', 'presstest-companion' ), array( 'status' => 500 ) );
		}

		return array(
			'id'   => $coupon_id,
			'code' => $code,
		);
	}
}
