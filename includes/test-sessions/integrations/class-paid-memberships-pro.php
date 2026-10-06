<?php
/**
 * Paid Memberships Pro integration: orders, membership history,
 * subscriptions, payment safety, and a membership fixture.
 *
 * Payment safety mirrors the WooCommerce integration: preflight() reports a
 * live payment gateway, and during session requests a paid checkout is
 * refused before the gateway is contacted. Free levels can still be tested.
 *
 * @package PIE\PresstestCompanion\TestSessions
 * @since   1.3.0
 */

namespace PIE\PresstestCompanion\TestSessions\Integrations;

use PIE\PresstestCompanion\TestSessions\Session;
use PIE\PresstestCompanion\TestSessions\Session_Context;
use PIE\PresstestCompanion\TestSessions\Tracker;

/**
 * Tracking, cleanup, safety checks, and fixtures for PMPro.
 */
class Paid_Memberships_Pro extends Abstract_Integration {

	/**
	 * Gateways that never take payment: "Testing Only" and "Pay by Check".
	 */
	const SAFE_GATEWAYS = array( '', 'check' );

	/**
	 * Integration slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'pmpro';
	}

	/**
	 * Integration display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Paid Memberships Pro';
	}

	/**
	 * Whether PMPro is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return defined( 'PMPRO_VERSION' );
	}

	/**
	 * Registers order and membership tracking and the checkout guard.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'pmpro_added_order', array( $this, 'track_order' ) );
		add_action( 'pmpro_after_change_membership_level', array( $this, 'track_membership' ), 10, 2 );
		add_filter( 'pmpro_checkout_checks', array( $this, 'block_unsafe_checkout' ), PHP_INT_MAX );
	}

	/**
	 * Records a new membership order.
	 *
	 * @param \MemberOrder $order The order.
	 * @return void
	 */
	public function track_order( $order ): void {
		if ( is_object( $order ) && isset( $order->id ) ) {
			Tracker::record( 'pmpro_order', (int) $order->id );
		}
	}

	/**
	 * Records membership changes for test users, so their membership history
	 * and subscriptions are removed with them. Changes to real users are
	 * never recorded.
	 *
	 * @param int $level_id New level ID (0 when cancelled).
	 * @param int $user_id  User whose membership changed.
	 * @return void
	 */
	public function track_membership( $level_id, $user_id ): void {
		if ( true === Tracker::is_session_object( 'user', (int) $user_id ) ) {
			Tracker::record( 'pmpro_member', (int) $user_id );
		}
	}

	/**
	 * Refuses a paid checkout during a session if the gateway is not safe.
	 *
	 * @param bool $should_continue Whether checkout should continue.
	 * @return bool
	 */
	public function block_unsafe_checkout( $should_continue ) {
		global $pmpro_level;

		if ( null === Session_Context::current() || true !== $should_continue || ! is_object( $pmpro_level ) || true === pmpro_isLevelFree( $pmpro_level ) ) {
			return $should_continue;
		}

		$problems = $this->preflight();

		if ( array() === $problems ) {
			return $should_continue;
		}

		pmpro_setMessage( __( 'Presstest blocked this checkout: ', 'presstest-companion' ) . implode( ' ', $problems ), 'pmpro_error' );

		return false;
	}

	/**
	 * Reports a payment gateway that could take a real payment.
	 *
	 * @return string[]
	 */
	public function preflight(): array {
		$gateway     = (string) get_option( 'pmpro_gateway', '' );
		$environment = (string) get_option( 'pmpro_gateway_environment', '' );

		if ( in_array( $gateway, self::SAFE_GATEWAYS, true ) || 'sandbox' === $environment ) {
			return array();
		}

		return array(
			sprintf(
				/* translators: %s: gateway slug. */
				__( 'The %s payment gateway is in live mode. Set Memberships > Settings > Payment Gateway to the Sandbox/Testing environment on this site.', 'presstest-companion' ),
				$gateway
			),
		);
	}

	/**
	 * Orders first; membership rows after the user is deleted (PMPro's own
	 * delete_user handler may still write to them).
	 *
	 * @return array<string, array{priority: int, callback: callable}>
	 */
	public function get_cleanup_handlers(): array {
		return array(
			'pmpro_order'  => array(
				'priority' => 10,
				'callback' => array( $this, 'delete_order' ),
			),
			'pmpro_member' => array(
				'priority' => 95,
				'callback' => array( $this, 'delete_membership_data' ),
			),
		);
	}

	/**
	 * Deletes a membership order and its meta.
	 *
	 * A database error deleting the meta fails the handler, so the order's
	 * record is kept and the meta is retried later (the order itself being
	 * gone already is fine on retry).
	 *
	 * @param int $order_id Order ID.
	 * @return bool True once the order and its meta are gone.
	 */
	public function delete_order( int $order_id ): bool {
		global $wpdb;

		$order = new \MemberOrder( $order_id );

		// MemberOrder leaves id as null/false/"" when no order exists.
		if ( 0 !== absint( $order->id ?? 0 ) ) {
			$order->deleteMe();
		}

		// False means a database error; 0 rows simply means there was no meta.
		if ( false === $wpdb->delete( $wpdb->pmpro_membership_ordermeta, array( 'pmpro_membership_order_id' => $order_id ), array( '%d' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return false;
		}

		$order = new \MemberOrder( $order_id );
		return 0 === absint( $order->id ?? 0 );
	}

	/**
	 * Removes membership history, subscriptions, and discount code uses for a
	 * test user. PMPro keeps these after deleting a user, for reporting.
	 *
	 * Stops at the first database error and returns false, so the record is
	 * kept and the rest is retried later. Subscription meta goes first, so a
	 * retry can still find the subscriptions it belongs to.
	 *
	 * @param int $user_id Test user ID.
	 * @return bool True once all of the user's membership data is gone.
	 */
	public function delete_membership_data( int $user_id ): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->last_error = '';
		$subscription_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->pmpro_subscriptions} WHERE user_id = %d", $user_id ) );

		// get_col() returns an empty array on error too; don't mistake that
		// for "no subscriptions" and orphan their meta.
		if ( '' !== $wpdb->last_error ) {
			return false;
		}

		$deletions = array();

		foreach ( $subscription_ids as $subscription_id ) {
			$deletions[] = array( $wpdb->pmpro_subscriptionmeta, array( 'pmpro_subscription_id' => (int) $subscription_id ) );
		}

		$deletions[] = array( $wpdb->pmpro_subscriptions, array( 'user_id' => $user_id ) );
		$deletions[] = array( $wpdb->pmpro_memberships_users, array( 'user_id' => $user_id ) );
		$deletions[] = array( $wpdb->pmpro_discount_codes_uses, array( 'user_id' => $user_id ) );

		foreach ( $deletions as $deletion ) {
			// False means a database error; 0 rows simply means nothing to delete.
			if ( false === $wpdb->delete( $deletion[0], $deletion[1], array( '%d' ) ) ) {
				return false;
			}
		}
		// phpcs:enable

		return true;
	}

	/**
	 * Fixture factories for PMPro data.
	 *
	 * @return array<string, callable>
	 */
	public function get_factories(): array {
		return array(
			'pmpro_membership' => array( $this, 'give_membership' ),
		);
	}

	/**
	 * Gives a session test user a membership level directly, without a
	 * checkout — for testing member-only content.
	 *
	 * Args: user_id (required, must be a session test user), level_id (required).
	 *
	 * @param array   $args    Factory arguments.
	 * @param Session $session Session the membership belongs to.
	 * @return array|\WP_Error Membership details: user_id, level_id.
	 */
	public function give_membership( array $args, Session $session ) {
		$user_id  = absint( $args['user_id'] ?? 0 );
		$level_id = absint( $args['level_id'] ?? 0 );

		if ( false === Tracker::is_session_object( 'user', $user_id ) ) {
			return new \WP_Error( 'presstest_not_session_user', __( 'Memberships can only be given to test users created in this session.', 'presstest-companion' ), array( 'status' => 403 ) );
		}

		if ( false === pmpro_getLevel( $level_id ) ) {
			return new \WP_Error( 'presstest_unknown_level', __( 'That membership level does not exist.', 'presstest-companion' ), array( 'status' => 400 ) );
		}

		if ( false === pmpro_changeMembershipLevel( $level_id, $user_id ) ) {
			return new \WP_Error( 'presstest_membership_failed', __( 'The membership level could not be given.', 'presstest-companion' ), array( 'status' => 500 ) );
		}

		return array(
			'user_id'  => $user_id,
			'level_id' => $level_id,
		);
	}
}
