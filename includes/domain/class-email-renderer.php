<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Email_Renderer {
	public function render( array $cart, array $step_settings, string $recovery_url, ?string $coupon_code ): string {
		$site_name     = get_bloginfo( 'name' );
		$customer_name = $this->get_customer_name( $cart );
		$cart_items    = $this->get_cart_items( $cart );
		$cart_total    = wc_price( (float) $cart['cart_total'], array( 'currency' => $cart['currency'] ) );
		$subject       = sanitize_text_field( (string) ( $step_settings['subject'] ?? $site_name ) );
		$body = (string) ( $step_settings['body'] ?? '' );
		$body = str_replace(
			array( '{recovery_link}', '{coupon_code}', '{cart_total}', '{site_name}', '{customer_name}' ),
			array(
				esc_url( $recovery_url ),
				esc_html( (string) $coupon_code ),
				wp_kses_post( $cart_total ),
				esc_html( $site_name ),
				esc_html( $customer_name ),
			),
			$body
		);

		ob_start();
		include WCCR_PLUGIN_DIR . 'templates/emails/base-email.php';
		$content = (string) ob_get_clean();

		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$mailer  = WC()->mailer();
			$content = $mailer->wrap_message( $subject, $content );
			return $mailer->style_inline( $content );
		}

		return $content;
	}

	private function get_customer_name( array $cart ): string {
		$stored_name = trim( (string) ( $cart['customer_name'] ?? '' ) );
		if ( '' !== $stored_name ) {
			return $stored_name;
		}

		$user_id = absint( $cart['user_id'] ?? 0 );
		if ( ! $user_id ) {
			return '';
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		$name = trim( (string) $user->first_name );
		return '' !== $name ? $name : (string) $user->display_name;
	}

	private function get_cart_items( array $cart ): array {
		$payload = json_decode( (string) ( $cart['cart_payload'] ?? '' ), true );
		if ( ! is_array( $payload ) ) {
			return array();
		}

		$items = array();
		foreach ( $payload as $item ) {
			$product_id = absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 );
			$product    = $product_id ? wc_get_product( $product_id ) : null;

			if ( ! $product ) {
				continue;
			}

			$quantity = max( 1, absint( $item['quantity'] ?? 1 ) );
			$items[]  = array(
				'name'     => $product->get_name(),
				'quantity' => $quantity,
				'total'    => wc_price( (float) $product->get_price() * $quantity, array( 'currency' => $cart['currency'] ) ),
			);
		}

		return $items;
	}
}
