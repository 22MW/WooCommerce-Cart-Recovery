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

		$carts    = $this->cart_repository->list_recent();
		$settings = $this->settings_repository->get();
		?>
		<div class="wrap wccr-admin">
			<h1><?php esc_html_e( 'Abandoned Carts', 'woocommerce-cart-recovery' ); ?></h1>
			<table class="widefat striped">
				<thead><tr><th>ID</th><th><?php esc_html_e( 'Email', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Total', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Status', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Step', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Emails sent', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Eligible at', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Reason', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Last error', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Clicked at', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Order', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Abandoned at', 'woocommerce-cart-recovery' ); ?></th><th><?php esc_html_e( 'Last update', 'woocommerce-cart-recovery' ); ?></th></tr></thead>
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
						<td><?php echo esc_html( $this->email_eligibility_service->format_gmt_for_display( (string) ( $eligibility['eligible_at_gmt'] ?? '' ) ) ); ?></td>
						<td><?php echo esc_html( $this->email_eligibility_service->get_reason_label( (string) ( $eligibility['reason'] ?? '' ) ) ); ?></td>
						<td><?php echo esc_html( $this->email_log_repository->get_last_error_for_cart( absint( $cart['id'] ) ) ); ?></td>
						<td><?php echo esc_html( $this->email_eligibility_service->format_gmt_for_display( $cart['clicked_at_gmt'] ?? '' ) ); ?></td>
						<td><?php echo wp_kses_post( $this->get_order_link_html( absint( $cart['recovered_order_id'] ?? 0 ) ) ); ?></td>
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
}
