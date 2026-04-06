<?php
defined( 'ABSPATH' ) || exit;

/**
 * Capture the current WooCommerce cart once a valid email is known.
 */
final class WCCR_Cart_Capture_Service {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Locale_Resolver_Manager $locale_resolver
	) {}

	/**
	 * Persist the current cart snapshot for the current customer/session.
	 */
	public function capture_current_cart( ?string $email = null, ?string $customer_name = null, string $source = 'classic' ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
			return;
		}

		if ( defined( 'WCCR_IS_RESTORING_CART' ) && WCCR_IS_RESTORING_CART ) {
			return;
		}

		if ( $this->has_active_recovery_session() ) {
			return;
		}

		$items = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$items[] = array(
				'product_id'   => absint( $item['product_id'] ?? 0 ),
				'variation_id' => absint( $item['variation_id'] ?? 0 ),
				'quantity'     => absint( $item['quantity'] ?? 1 ),
				'cart_item'    => $item,
			);
		}

		if ( empty( $items ) ) {
			return;
		}

		$user_id = get_current_user_id() ?: null;
		$email   = $email ?: ( is_user_logged_in() ? wp_get_current_user()->user_email : null );
		$email   = $email ? sanitize_email( $email ) : '';

		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}

		$customer_name = $customer_name ?: $this->get_current_customer_name();

		$this->cart_repository->upsert_active_cart(
			(string) WC()->session->get_customer_id(),
			$user_id,
			$email,
			$customer_name ? sanitize_text_field( $customer_name ) : null,
			sanitize_text_field( $this->locale_resolver->resolve_locale( $user_id ) ),
			$items,
			(float) WC()->cart->get_total( 'edit' ),
			get_woocommerce_currency(),
			$source
		);
	}

	/**
	 * Block capture while the customer is actively checking out a recovered cart.
	 */
	private function has_active_recovery_session(): bool {
		return function_exists( 'WC' )
			&& WC()->session
			&& absint( WC()->session->get( 'wccr_recovered_cart_id', 0 ) ) > 0;
	}

	/**
	 * Derive the current customer name from user or checkout data.
	 */
	private function get_current_customer_name(): string {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$name = trim( trim( (string) $user->first_name ) . ' ' . trim( (string) $user->last_name ) );
			return '' !== $name ? $name : (string) $user->display_name;
		}

		if ( WC()->customer ) {
			$name = trim( trim( (string) WC()->customer->get_billing_first_name() ) . ' ' . trim( (string) WC()->customer->get_billing_last_name() ) );
			return $name;
		}

		return '';
	}
}
