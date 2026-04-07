<?php
defined( 'ABSPATH' ) || exit;

/**
 * Build email subject and HTML content for recovery emails.
 */
final class WCCR_Email_Renderer {
	public function __construct(
		private WCCR_Coupon_Service $coupon_service
	) {}

	/**
	 * Render subject and message for a recovery email.
	 *
	 * @param array<string, mixed> $cart          Cart row.
	 * @param array<string, mixed> $step_settings Step settings.
	 * @return array{subject:string,message:string}
	 */
	public function render( array $cart, array $step_settings, string $recovery_url, ?string $coupon_code ): array {
		$site_name      = get_bloginfo( 'name' );
		$customer_name  = $this->get_customer_name( $cart );
		$order          = $this->get_email_order_context( $cart );
		$cart_items     = $this->get_cart_items( $cart, $order );
		$cart_total     = wc_price( (float) $cart['cart_total'], array( 'currency' => $cart['currency'] ) );
		$coupon_label   = $this->coupon_service->get_coupon_label( $step_settings, (string) $cart['currency'], $coupon_code );
		$discount_text  = $this->coupon_service->get_coupon_label( $step_settings, (string) $cart['currency'] );
		$summary_totals = $this->get_summary_totals( $cart_total, $coupon_code, $discount_text );
		$text_align     = is_rtl() ? 'right' : 'left';
		$price_align    = 'right';
		$subject       = $this->cleanup_rendered_template(
			$this->replace_template_variables(
			(string) ( $step_settings['subject'] ?? $site_name ),
			$recovery_url,
			$coupon_code,
			$coupon_label,
			$cart_total,
			$site_name,
			$customer_name
			),
			'text'
		);
		$body          = $this->cleanup_rendered_template(
			$this->replace_template_variables(
			(string) ( $step_settings['body'] ?? '' ),
			$recovery_url,
			$coupon_code,
			$coupon_label,
			$cart_total,
			$site_name,
			$customer_name
			),
			'html'
		);

		ob_start();
		include WCCR_PLUGIN_DIR . 'templates/emails/base-email.php';
		$content = (string) ob_get_clean();

		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$mailer  = WC()->mailer();
			$content = $mailer->wrap_message( $subject, $content );
		}

		return array(
			'subject' => sanitize_text_field( $subject ),
			'message' => $content,
		);
	}

	/**
	 * Resolve the best available WooCommerce order for the email context.
	 *
	 * @param array<string, mixed> $cart Cart row.
	 */
	private function get_email_order_context( array $cart ): ?WC_Order {
		$order_id = absint( $cart['linked_order_id'] ?? 0 );
		if ( ! $order_id ) {
			$order_id = absint( $cart['recovered_order_id'] ?? 0 );
		}

		return $order_id ? wc_get_order( $order_id ) : null;
	}

	/**
	 * Resolve the best available customer name for the email.
	 *
	 * @param array<string, mixed> $cart Cart row.
	 */
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

	/**
	 * Build the cart summary rows used in the email template.
	 *
	 * @param array<string, mixed> $cart Cart row.
	 * @return array<int, array{name:string,quantity:int,total:string}>
	 */
	private function get_cart_items( array $cart, ?WC_Order $order ): array {
		if ( $order ) {
			return $this->get_order_items( $order );
		}

		return $this->get_payload_items( $cart );
	}

	/**
	 * Build email rows from a real WooCommerce order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_order_items( WC_Order $order ): array {
		$items = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$items[] = array(
				'name'     => $item->get_name(),
				'image'    => $product->get_image( array( 48, 48 ) ),
				'meta'     => wc_display_item_meta( $item, array( 'echo' => false, 'separator' => '<br>' ) ),
				'quantity' => max( 1, absint( $item->get_quantity() ) ),
				'total'    => $order->get_formatted_line_subtotal( $item ),
			);
		}

		return $items;
	}

	/**
	 * Build email rows from the stored cart payload.
	 *
	 * @param array<string, mixed> $cart Cart row.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_payload_items( array $cart ): array {
		$payload = json_decode( (string) ( $cart['cart_payload'] ?? '' ), true );
		if ( ! is_array( $payload ) ) {
			return array();
		}

		$items = array();
		foreach ( $payload as $item ) {
			$email_item = $this->build_payload_item( is_array( $item ) ? $item : array(), (string) ( $cart['currency'] ?? '' ) );
			if ( ! empty( $email_item ) ) {
				$items[] = $email_item;
			}
		}

		return $items;
	}

	/**
	 * Build one cart row using WooCommerce product helpers.
	 *
	 * @param array<string, mixed> $item     Payload item.
	 * @return array<string, mixed>
	 */
	private function build_payload_item( array $item, string $currency ): array {
		$product = $this->get_payload_product( $item );
		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$quantity  = max( 1, absint( $item['quantity'] ?? 1 ) );
		$cart_item = isset( $item['cart_item'] ) && is_array( $item['cart_item'] ) ? $item['cart_item'] : array();

		return array(
			'name'     => $product->get_name(),
			'image'    => $product->get_image( array( 48, 48 ) ),
			'meta'     => $this->get_payload_item_meta( $cart_item, $product ),
			'quantity' => $quantity,
			'total'    => $this->get_payload_item_total( $cart_item, $product, $quantity, $currency ),
		);
	}

	/**
	 * Resolve the product object for a stored payload row.
	 *
	 * @param array<string, mixed> $item Payload item.
	 */
	private function get_payload_product( array $item ): ?WC_Product {
		$product_id = absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 );
		if ( ! $product_id ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		return $product instanceof WC_Product ? $product : null;
	}

	/**
	 * Build formatted variation or item meta for one cart payload row.
	 *
	 * @param array<string, mixed> $cart_item Cart item snapshot.
	 */
	private function get_payload_item_meta( array $cart_item, WC_Product $product ): string {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		if ( ! $product->is_type( 'variation' ) ) {
			return '';
		}

		return wc_get_formatted_variation( $product, true, false, true );
	}

	/**
	 * Build a line total for one cart payload row using WooCommerce formatting.
	 *
	 * @param array<string, mixed> $cart_item Cart item snapshot.
	 */
	private function get_payload_item_total( array $cart_item, WC_Product $product, int $quantity, string $currency ): string {
		$line_total = isset( $cart_item['line_total'] ) ? (float) $cart_item['line_total'] : null;
		$line_tax   = isset( $cart_item['line_tax'] ) ? (float) $cart_item['line_tax'] : null;

		if ( null !== $line_total ) {
			return wc_price( $line_total + ( $line_tax ?? 0 ), array( 'currency' => $currency ) );
		}

		return wc_price( (float) $product->get_price() * $quantity, array( 'currency' => $currency ) );
	}

	/**
	 * Build footer totals for the recovery summary table.
	 *
	 * @return array<int, array{label:string,value:string}>
	 */
	private function get_summary_totals( string $cart_total, ?string $coupon_code, string $discount_text ): array {
		$totals   = array();
		$totals[] = array(
			'label' => __( 'Total', 'vfwoo_woocommerce-cart-recovery' ),
			'value' => $cart_total,
		);

		if ( empty( $coupon_code ) ) {
			return $totals;
		}

		$value = '<strong>' . esc_html( $coupon_code ) . '</strong>';
		if ( '' !== $discount_text ) {
			$value .= ' (' . esc_html( $discount_text ) . ')';
		}

		$totals[] = array(
			'label' => __( 'Discount code', 'vfwoo_woocommerce-cart-recovery' ),
			'value' => $value,
		);

		return $totals;
	}

	/**
	 * Replace supported template variables in subject/body strings.
	 */
	private function replace_template_variables( string $template, string $recovery_url, ?string $coupon_code, string $coupon_label, string $cart_total, string $site_name, string $customer_name ): string {
		return str_replace(
			array( '{recovery_link}', '{coupon_code}', '{coupon_label}', '{cart_total}', '{site_name}', '{customer_name}' ),
			array(
				esc_url( $recovery_url ),
				esc_html( (string) $coupon_code ),
				esc_html( $coupon_label ),
				wp_kses_post( $cart_total ),
				esc_html( $site_name ),
				esc_html( $customer_name ),
			),
			$template
		);
	}

	/**
	 * Clean rendered text after variable replacement.
	 */
	private function cleanup_rendered_template( string $template, string $context = 'text' ): string {
		$template = preg_replace( '/\s+/', ' ', $template ) ?: $template;
		$template = str_replace( array( 'Hi ,', 'Hello ,' ), array( 'Hi,', 'Hello,' ), $template );
		$template = preg_replace( '/^[\s,\-:;]+/u', '', $template ) ?: $template;
		$template = preg_replace( '/\(\s*\)/u', '', $template ) ?: $template;
		$template = trim( $template );

		if ( 'html' === $context ) {
			return $template;
		}

		$template = sanitize_text_field( $template );
		return '' !== $template ? ucfirst( $template ) : '';
	}
}
