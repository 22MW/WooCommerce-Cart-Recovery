<?php
defined( 'ABSPATH' ) || exit;

/**
 * Restore saved carts from secure recovery URLs.
 */
final class WCCR_Recovery_Service {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository
	) {}

	/**
	 * Register public recovery hooks.
	 */
	public function init(): void {
		add_action( 'template_redirect', array( $this, 'maybe_restore_cart' ) );
	}

	/**
	 * Build the public recovery URL for a cart and optional coupon.
	 */
	public function build_recovery_url( int $cart_id, ?string $coupon_code = null, int $step = 0 ): string {
		$token = wp_hash( $cart_id . '|' . wp_salt( 'auth' ) );
		$args  = array(
			'wccr_recover' => $cart_id,
			'wccr_token'   => $token,
		);

		if ( $coupon_code ) {
			$args['wccr_coupon'] = $coupon_code;
		}

		if ( $step > 0 ) {
			$args['wccr_step'] = $step;
		}

		return add_query_arg(
			$args,
			wc_get_cart_url()
		);
	}

	/**
	 * Restore the cart when a valid recovery link is visited.
	 */
	public function maybe_restore_cart(): void {
		$cart_id = isset( $_GET['wccr_recover'] ) ? absint( wp_unslash( $_GET['wccr_recover'] ) ) : 0;
		$token   = isset( $_GET['wccr_token'] ) ? sanitize_text_field( wp_unslash( $_GET['wccr_token'] ) ) : '';
		$coupon  = isset( $_GET['wccr_coupon'] ) ? sanitize_text_field( wp_unslash( $_GET['wccr_coupon'] ) ) : '';
		$step    = isset( $_GET['wccr_step'] ) ? absint( wp_unslash( $_GET['wccr_step'] ) ) : 0;

		if ( ! $cart_id || ! $token || ! hash_equals( wp_hash( $cart_id . '|' . wp_salt( 'auth' ) ), $token ) ) {
			return;
		}

		$cart = $this->cart_repository->find_by_id( $cart_id );
		if ( ! $cart || empty( $cart['cart_payload'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		if ( ! defined( 'WCCR_IS_RESTORING_CART' ) ) {
			define( 'WCCR_IS_RESTORING_CART', true );
		}

		if ( WC()->session ) {
			WC()->session->set( 'wccr_recovered_cart_id', $cart_id );
		}

		$payload = json_decode( (string) $cart['cart_payload'], true );
		if ( ! is_array( $payload ) ) {
			return;
		}

		WC()->cart->empty_cart();
		foreach ( $payload as $item ) {
			WC()->cart->add_to_cart(
				absint( $item['product_id'] ?? 0 ),
				max( 1, absint( $item['quantity'] ?? 1 ) ),
				absint( $item['variation_id'] ?? 0 ),
				array(),
				is_array( $item['cart_item'] ?? null ) ? $item['cart_item'] : array()
			);
		}

		if ( $coupon && ! WC()->cart->has_discount( $coupon ) ) {
			WC()->cart->apply_coupon( $coupon );
		}

		$this->cart_repository->mark_clicked( $cart_id, $step );
		do_action( 'wccr_cart_recovery_clicked', $cart_id );
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}
}
