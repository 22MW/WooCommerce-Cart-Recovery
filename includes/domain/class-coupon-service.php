<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Coupon_Service {
	public function maybe_generate_coupon( array $cart, array $step_settings, int $expiry_days ): ?string {
		if ( empty( $step_settings['discount_type'] ) || 'none' === $step_settings['discount_type'] ) {
			return null;
		}

		if ( (float) $cart['cart_total'] < (float) ( $step_settings['min_cart_total'] ?? 0 ) ) {
			return null;
		}

		$code   = 'WCCR-' . strtoupper( wp_generate_password( 10, false, false ) );
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' === $step_settings['discount_type'] ? 'fixed_cart' : 'percent' );
		$coupon->set_amount( (float) ( $step_settings['discount_amount'] ?? 0 ) );
		$coupon->set_usage_limit( 1 );
		$coupon->set_date_expires( time() + max( 1, $expiry_days ) * DAY_IN_SECONDS );

		if ( ! empty( $cart['email'] ) ) {
			$coupon->set_email_restrictions( array( sanitize_email( $cart['email'] ) ) );
		}

		$coupon->save();
		do_action( 'wccr_coupon_generated', $code, $cart, $step_settings );

		return $code;
	}

	public function get_coupon_label( array $step_settings, string $currency, ?string $coupon_code = null ): string {
		$discount_type   = (string) ( $step_settings['discount_type'] ?? 'none' );
		$discount_amount = (float) ( $step_settings['discount_amount'] ?? 0 );

		if ( 'none' === $discount_type || $discount_amount <= 0 ) {
			return (string) $coupon_code;
		}

		if ( 'fixed_cart' === $discount_type ) {
			$label = html_entity_decode(
				wp_strip_all_tags( wc_price( $discount_amount, array( 'currency' => $currency ) ) ),
				ENT_QUOTES,
				get_bloginfo( 'charset' )
			) . ' off';
		} else {
			$label = wc_format_decimal( $discount_amount, 0 ) . '% off';
		}

		if ( empty( $coupon_code ) ) {
			return $label;
		}

		return $label . ' - ' . $coupon_code;
	}
}
