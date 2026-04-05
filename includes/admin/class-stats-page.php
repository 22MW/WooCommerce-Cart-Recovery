<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Stats_Page {
	public function __construct(
		private WCCR_Stats_Service $stats_service
	) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woocommerce-cart-recovery' ) );
		}

		$stats = $this->stats_service->get_stats();
		?>
		<div class="wrap wccr-admin">
			<h1><?php esc_html_e( 'Cart Recovery Statistics', 'woocommerce-cart-recovery' ); ?></h1>
			<div class="wccr-stats-grid">
				<div class="wccr-card"><strong><?php echo esc_html( $stats['abandoned'] ); ?></strong><span><?php esc_html_e( 'Abandoned carts', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo esc_html( $stats['clicked'] ); ?></strong><span><?php esc_html_e( 'Recovery clicks', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo esc_html( $stats['recovered'] ); ?></strong><span><?php esc_html_e( 'Recovered carts', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo esc_html( $stats['recovery_rate'] ); ?>%</strong><span><?php esc_html_e( 'Recovery rate', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo wp_kses_post( wc_price( $stats['revenue'] ) ); ?></strong><span><?php esc_html_e( 'Recovered revenue', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo esc_html( $stats['emails_sent'] ); ?></strong><span><?php esc_html_e( 'Emails sent', 'woocommerce-cart-recovery' ); ?></span></div>
			</div>
		</div>
		<?php
	}
}
