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
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'link_recovered_store_api_order' ), 30, 1 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'capture_failed_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'mark_order_recovered' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'mark_order_recovered' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'mark_order_recovered' ), 20, 1 );
		add_action( 'woocommerce_payment_complete', array( $this, 'mark_order_recovered' ), 20, 1 );
	}

	/**
	 * Sync stale pending or failed orders into the recovery table.
	 */
	public function sync_stale_pending_orders(): void {
		$this->sync_unpaid_orders( false );
	}

	/**
	 * Manually import existing pending and failed orders into the recovery table.
	 *
	 * @return array{reviewed:int,imported:int,merged:int,updated:int,skipped:int}
	 */
	public function import_existing_unpaid_orders(): array {
		return $this->sync_unpaid_orders( true );
	}

	/**
	 * Sync eligible pending and failed orders into the recovery table.
	 *
	 * @return array{reviewed:int,imported:int,merged:int,updated:int,skipped:int}
	 */
	private function sync_unpaid_orders( bool $manual_import ): array {
		$results  = $this->get_empty_import_results();
		$settings = $this->settings_repository->get();
		$minutes  = max( 1, absint( $settings['abandon_after_minutes'] ?? 60 ) );
		$orders   = wc_get_orders(
			array(
				'status'       => array( 'pending', 'failed' ),
				'limit'        => $manual_import ? 200 : 50,
				'orderby'      => 'date',
				'order'        => 'ASC',
				'date_created' => '<=' . gmdate( 'Y-m-d H:i:s', time() - ( $minutes * MINUTE_IN_SECONDS ) ),
				'return'       => 'ids',
			)
		);

		foreach ( $orders as $order_id ) {
			++$results['reviewed'];
			$outcome = $this->capture_unpaid_order( absint( $order_id ), true );
			if ( isset( $results[ $outcome ] ) ) {
				++$results[ $outcome ];
				continue;
			}

			++$results['skipped'];
		}

		return $results;
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
	private function capture_unpaid_order( int $order_id, bool $respect_age ): string {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! in_array( $order->get_status(), array( 'pending', 'failed' ), true ) ) {
			return 'skipped';
		}

		if ( $this->cart_repository->has_linked_order_row( $order_id ) ) {
			return 'skipped';
		}

		if ( absint( $order->get_meta( '_wccr_recovered_cart_id', true ) ) ) {
			return 'skipped';
		}

		if ( $respect_age ) {
			$settings = $this->settings_repository->get();
			$minutes  = max( 1, absint( $settings['abandon_after_minutes'] ?? 60 ) );
			$created  = $order->get_date_created();

			if ( $created && $created->getTimestamp() > time() - ( $minutes * MINUTE_IN_SECONDS ) ) {
				return 'skipped';
			}
		}

		$items = $this->get_order_items_snapshot( $order );

		if ( empty( $items ) ) {
			return 'skipped';
		}

		$user_id = $order->get_user_id() ? absint( $order->get_user_id() ) : null;
		$email   = $order->get_billing_email() ? sanitize_email( $order->get_billing_email() ) : null;
		$name    = trim( trim( (string) $order->get_billing_first_name() ) . ' ' . trim( (string) $order->get_billing_last_name() ) );
		$locale  = $order->get_meta( '_wccr_locale', true );
		$locale  = is_string( $locale ) && '' !== $locale ? sanitize_text_field( $locale ) : sanitize_text_field( $this->locale_resolver->resolve_locale( $user_id ) );

		$result = $this->cart_repository->upsert_unpaid_order(
			absint( $order->get_id() ),
			$user_id,
			$email,
			'' !== $name ? $name : null,
			$locale,
			$items,
			(float) $order->get_total( 'edit' ),
			$order->get_currency(),
		);

		$this->mark_order_as_plugin_managed( $order );
		return $result;
	}

	/**
	 * Mark a linked recovery cart as recovered once the order is paid.
	 */
	public function mark_order_recovered( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$cart_id = $this->resolve_recovered_cart_id( $order );
		if ( ! $cart_id ) {
			return;
		}

		$this->cart_repository->mark_recovered_order( $cart_id, absint( $order_id ) );
		do_action( 'wccr_cart_recovered', $cart_id, $order_id );
	}

	/**
	 * Link a newly created order to the clicked recovery cart in session.
	 */
	public function link_recovered_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$this->link_recovered_order_object( $order );
	}

	/**
	 * Link a newly created Store API order to the clicked recovery cart in session.
	 *
	 * @param mixed $order WooCommerce order object.
	 */
	public function link_recovered_store_api_order( $order ): void {
		if ( ! $order ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->link_recovered_order_object( $order );
	}

	/**
	 * Link a WooCommerce order object to the current recovered cart in session.
	 */
	private function link_recovered_order_object( WC_Order $order ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$cart_id = absint( WC()->session->get( 'wccr_recovered_cart_id', 0 ) );
		if ( ! $cart_id ) {
			return;
		}

		$order->update_meta_data( '_wccr_recovered_cart_id', $cart_id );
		$order->save();
		$this->clear_recovery_session();
	}

	/**
	 * Build a normalized item snapshot from a WooCommerce order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_order_items_snapshot( WC_Order $order ): array {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'product_id'   => absint( $item->get_product_id() ),
				'variation_id' => absint( $item->get_variation_id() ),
				'quantity'     => absint( $item->get_quantity() ),
				'cart_item'    => array(),
			);
		}

		return $items;
	}

	/**
	 * Mark an unpaid WooCommerce order as owned by the recovery plugin flow.
	 */
	private function mark_order_as_plugin_managed( WC_Order $order ): void {
		$order->update_meta_data( '_wccr_managed_unpaid_order', 1 );
		$order->save();
	}

	/**
	 * Resolve the recovery row that should be marked as paid.
	 */
	private function resolve_recovered_cart_id( WC_Order $order ): int {
		$cart_id = absint( $order->get_meta( '_wccr_recovered_cart_id', true ) );
		if ( $cart_id ) {
			return $cart_id;
		}

		$linked_cart = $this->cart_repository->find_by_linked_order_id( absint( $order->get_id() ) );
		return $linked_cart ? absint( $linked_cart['id'] ?? 0 ) : 0;
	}

	/**
	 * Clear the temporary session flag once the recovery cart is linked to an order.
	 */
	private function clear_recovery_session(): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( 'wccr_recovered_cart_id' );
		}
	}

	/**
	 * Return the default import counters structure.
	 *
	 * @return array{reviewed:int,imported:int,merged:int,updated:int,skipped:int}
	 */
	private function get_empty_import_results(): array {
		return array(
			'reviewed' => 0,
			'imported' => 0,
			'merged'   => 0,
			'updated'  => 0,
			'skipped'  => 0,
		);
	}
}
