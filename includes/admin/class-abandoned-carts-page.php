<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Abandoned_Carts_Page {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Email_Log_Repository $email_log_repository,
		private WCCR_Settings_Repository $settings_repository,
		private WCCR_Email_Eligibility_Service $email_eligibility_service
	) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woocommerce-cart-recovery' ) );
		}

		$carts    = $this->cart_repository->list_recovery_items();
		$settings = $this->settings_repository->get();
		?>
		<div class="wrap wccr-admin">
			<h1><?php esc_html_e( 'Abandoned Carts', 'woocommerce-cart-recovery' ); ?></h1>
			<table class="widefat striped">
				<thead><tr><th>ID</th><th><?php esc_html_e( 'Email', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Total', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Status', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Step', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Emails sent', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Coupon code', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Eligible at', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Reason', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Clicked email link', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Purchased', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Order', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Recovery URL', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Last error', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Abandoned at', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Last update', 'woocommerce-cart-recovery' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $carts as $cart ) : ?>
					<?php $eligibility = $this->email_eligibility_service->get_status( $cart, $settings ); ?>
					<tr>
						<td><?php echo esc_html( $cart['id'] ); ?></td>
						<td><?php echo esc_html( $cart['email'] ); ?></td>
						<td><?php echo esc_html( $cart['cart_total'] . ' ' . $cart['currency'] ); ?></td>
						<td><?php echo esc_html( $cart['status'] ); ?></td>
						<td><?php echo esc_html( absint( $eligibility['current_step'] ?? 0 ) ?: '-' ); ?></td>
						<td><?php echo esc_html( $this->email_log_repository->count_sent_for_cart( absint( $cart['id'] ) ) ); ?></td>
						<td><?php echo esc_html( $this->get_coupon_label( $cart ) ); ?></td>
						<td><?php echo esc_html( $this->email_eligibility_service->format_gmt_for_display( (string) ( $eligibility['eligible_at_gmt'] ?? '' ) ) ); ?></td>
						<td><?php echo esc_html( $this->email_eligibility_service->get_reason_label( (string) ( $eligibility['reason'] ?? '' ) ) ); ?></td>
						<td><?php echo esc_html( $this->get_clicked_label( $cart ) ); ?></td>
						<td><?php echo esc_html( $this->get_purchased_label( $cart ) ); ?></td>
						<td><?php echo wp_kses_post( $this->get_order_link_html( absint( $cart['recovered_order_id'] ?? 0 ) ) ); ?></td>
						<td><?php echo wp_kses_post( $this->get_recovery_url_html( $cart ) ); ?></td>
						<td><?php echo esc_html( $this->email_log_repository->get_last_error_for_cart( absint( $cart['id'] ) ) ); ?></td>
						<td><?php echo esc_html( $this->email_eligibility_service->format_gmt_for_display( $cart['abandoned_at_gmt'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( $this->email_eligibility_service->format_gmt_for_display( $cart['updated_at_gmt'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
	private function get_order_link_html( int $order_id ): string {
		if ( ! $order_id ) {
			return '-';
		}

		$url = get_edit_post_link( $order_id );
		if ( ! $url ) {
			return '#' . $order_id;
		}

		return sprintf(
			'<a href="%s">#%d</a>',
			esc_url( $url ),
			$order_id
		);
	}

	private function get_purchased_label( array $cart ): string {
		return ! empty( $cart['recovered_order_id'] ) && 'recovered' === ( $cart['status'] ?? '' )
			? __( 'Yes', 'woocommerce-cart-recovery' )
			: __( 'No', 'woocommerce-cart-recovery' );
	}

	private function get_coupon_label( array $cart ): string {
		$coupon_code = $this->email_log_repository->get_last_coupon_code_for_cart( absint( $cart['id'] ?? 0 ) );

		if ( '' === $coupon_code ) {
			return '-';
		}

		$coupon = new WC_Coupon( $coupon_code );
		if ( ! $coupon->get_id() ) {
			return $coupon_code;
		}

		$amount   = (float) $coupon->get_amount();
		$currency = (string) ( $cart['currency'] ?? get_woocommerce_currency() );
		if ( 'fixed_cart' === $coupon->get_discount_type() ) {
			$label = html_entity_decode(
				wp_strip_all_tags( wc_price( $amount, array( 'currency' => $currency ) ) ),
				ENT_QUOTES,
				get_bloginfo( 'charset' )
			) . ' off';
		} else {
			$label = wc_format_decimal( $amount, 0 ) . '% off';
		}

		return $label . ' - ' . $coupon_code;
	}

	private function get_clicked_label( array $cart ): string {
		$clicked_at = $this->email_eligibility_service->format_gmt_for_display( $cart['clicked_at_gmt'] ?? '' );

		if ( '-' === $clicked_at ) {
			return __( 'No', 'woocommerce-cart-recovery' );
		}

		return sprintf(
			/* translators: %s: click date */
			__( 'Yes (%s)', 'woocommerce-cart-recovery' ),
			$clicked_at
		);
	}

	private function get_recovery_url_html( array $cart ): string {
		if ( empty( $cart['id'] ) ) {
			return '-';
		}

		$coupon_code = $this->email_log_repository->get_last_coupon_code_for_cart( absint( $cart['id'] ) );
		$args        = array(
			'wccr_recover' => absint( $cart['id'] ),
			'wccr_token'   => wp_hash( absint( $cart['id'] ) . '|' . wp_salt( 'auth' ) ),
		);

		if ( $coupon_code ) {
			$args['wccr_coupon'] = $coupon_code;
		}

		$recovery_url = add_query_arg(
			$args,
			function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' )
		);

		return sprintf(
			'<button type="button" class="button button-secondary wccr-copy-url" data-url="%s">%s</button>',
			esc_attr( $recovery_url ),
			esc_html__( 'Copy URL', 'woocommerce-cart-recovery' )
		);
	}
}
