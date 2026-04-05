<?php
defined( 'ABSPATH' ) || exit;

/**
 * Detect unpaid WooCommerce orders that should enter the recovery flow.
 */
final class WCCR_Pending_Order_Detector {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Locale_Resolver_Manager $locale_resolver,
		private WCCR_Settings_Repository $settings_repository
	) {}

	/**
	 * Register order hooks used by the recovery flow.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'link_recovered_order' ), 30, 1 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'capture_failed_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'mark_order_recovered' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'mark_order_recovered' ), 20, 1 );
	}

	/**
	 * Sync stale pending or failed orders into the recovery table.
	 */
	public function sync_stale_pending_orders(): void {
		$settings = $this->settings_repository->get();
		$minutes  = max( 1, absint( $settings['abandon_after_minutes'] ?? 60 ) );
		$orders   = wc_get_orders(
			array(
				'status'       => array( 'pending', 'failed' ),
				'limit'        => 50,
				'orderby'      => 'date',
				'order'        => 'ASC',
				'date_created' => '<=' . gmdate( 'Y-m-d H:i:s', time() - ( $minutes * MINUTE_IN_SECONDS ) ),
				'return'       => 'ids',
			)
		);

		foreach ( $orders as $order_id ) {
			$this->capture_unpaid_order( absint( $order_id ), true );
		}
	}

	/**
	 * Capture a failed order immediately.
	 */
	public function capture_failed_order( int $order_id ): void {
		$this->capture_unpaid_order( $order_id, false );
	}

	/**
	 * Capture an unpaid order if it should be treated as abandoned.
	 */
	private function capture_unpaid_order( int $order_id, bool $respect_age ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! in_array( $order->get_status(), array( 'pending', 'failed' ), true ) ) {
			return;
		}

		if ( absint( $order->get_meta( '_wccr_recovered_cart_id', true ) ) ) {
			return;
		}

		if ( $respect_age ) {
			$settings = $this->settings_repository->get();
			$minutes  = max( 1, absint( $settings['abandon_after_minutes'] ?? 60 ) );
			$created  = $order->get_date_created();

			if ( $created && $created->getTimestamp() > time() - ( $minutes * MINUTE_IN_SECONDS ) ) {
				return;
			}
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'product_id'   => absint( $item->get_product_id() ),
				'variation_id' => absint( $item->get_variation_id() ),
				'quantity'     => absint( $item->get_quantity() ),
				'cart_item'    => array(),
			);
		}

		if ( empty( $items ) ) {
			return;
		}

		$user_id = $order->get_user_id() ? absint( $order->get_user_id() ) : null;
		$email   = $order->get_billing_email() ? sanitize_email( $order->get_billing_email() ) : null;
		$name    = trim( trim( (string) $order->get_billing_first_name() ) . ' ' . trim( (string) $order->get_billing_last_name() ) );
		$locale  = $order->get_meta( '_wccr_locale', true );
		$locale  = is_string( $locale ) && '' !== $locale ? sanitize_text_field( $locale ) : sanitize_text_field( $this->locale_resolver->resolve_locale( $user_id ) );

		$this->cart_repository->upsert_active_cart(
			'order_' . $order->get_id(),
			$user_id,
			$email,
			'' !== $name ? $name : null,
			$locale,
			$items,
			(float) $order->get_total(),
			$order->get_currency(),
			'order_pending'
		);

		$this->cart_repository->mark_session_abandoned( 'order_' . $order->get_id() );
	}

	/**
	 * Mark a linked recovery cart as recovered once the order is paid.
	 */
	public function mark_order_recovered( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$cart_id = absint( $order->get_meta( '_wccr_recovered_cart_id', true ) );
		if ( $cart_id ) {
			$this->cart_repository->mark_recovered_order( $cart_id, absint( $order_id ) );
			do_action( 'wccr_cart_recovered', $cart_id, $order_id );
		}
	}

	/**
	 * Link a newly created order to the clicked recovery cart in session.
	 */
	public function link_recovered_order( int $order_id ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$cart_id = absint( WC()->session->get( 'wccr_recovered_cart_id', 0 ) );
		if ( ! $cart_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( '_wccr_recovered_cart_id', $cart_id );
		$order->save();
	}
}
