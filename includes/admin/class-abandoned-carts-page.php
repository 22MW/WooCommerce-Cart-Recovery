<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Abandoned_Carts_Page {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Email_Log_Repository $email_log_repository,
		private WCCR_Settings_Repository $settings_repository,
		private WCCR_Email_Eligibility_Service $email_eligibility_service,
		private WCCR_Stats_Service $stats_service
	) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woocommerce-cart-recovery' ) );
		}

		$this->maybe_handle_delete();

		$sort     = $this->get_current_sort();
		$carts    = $this->cart_repository->list_recovery_items( $sort );
		$settings = $this->settings_repository->get();
		$stats    = $this->stats_service->get_stats();
		?>
		<div class="wrap wccr-admin">
			<h1><?php esc_html_e( 'Cart Recovery', 'woocommerce-cart-recovery' ); ?></h1>

			<?php if ( isset( $_GET['wccr_deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Recovery item deleted.', 'woocommerce-cart-recovery' ); ?></p></div>
			<?php endif; ?>

			<div class="wccr-stats-grid">
				<div class="wccr-card"><strong><?php echo esc_html( $stats['abandoned'] ); ?></strong><span><?php esc_html_e( 'Abandoned carts', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo esc_html( $stats['clicked'] ); ?></strong><span><?php esc_html_e( 'Recovery clicks', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo esc_html( $stats['recovered'] ); ?></strong><span><?php esc_html_e( 'Recovered carts', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo esc_html( $stats['recovery_rate'] ); ?>%</strong><span><?php esc_html_e( 'Recovery rate', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo wp_kses_post( wc_price( $stats['revenue'] ) ); ?></strong><span><?php esc_html_e( 'Recovered revenue', 'woocommerce-cart-recovery' ); ?></span></div>
				<div class="wccr-card"><strong><?php echo esc_html( $stats['emails_sent'] ); ?></strong><span><?php esc_html_e( 'Emails sent', 'woocommerce-cart-recovery' ); ?></span></div>
			</div>

			<form method="get" class="wccr-toolbar">
				<input type="hidden" name="page" value="wccr-cart-recovery">
				<label for="wccr-sort"><?php esc_html_e( 'Sort by', 'woocommerce-cart-recovery' ); ?></label>
				<select id="wccr-sort" name="sort">
					<?php foreach ( $this->get_sort_options() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $sort, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Apply', 'woocommerce-cart-recovery' ), 'secondary', '', false ); ?>
			</form>

			<div class="wccr-recovery-grid">
				<?php foreach ( $carts as $cart ) : ?>
					<?php $eligibility = $this->email_eligibility_service->get_status( $cart, $settings ); ?>
					<article class="wccr-recovery-item">
						<div class="wccr-recovery-item__header">
							<div>
								<h2 class="wccr-recovery-item__title"><?php echo esc_html( $cart['email'] ?: __( 'No email', 'woocommerce-cart-recovery' ) ); ?></h2>
								<p class="wccr-recovery-item__total"><?php echo esc_html( $cart['cart_total'] . ' ' . $cart['currency'] ); ?></p>
							</div>
							<span class="wccr-status-badge wccr-status-badge--<?php echo esc_attr( sanitize_html_class( (string) $cart['status'] ) ); ?>">
								<?php echo esc_html( ucfirst( (string) $cart['status'] ) ); ?>
							</span>
						</div>

						<dl class="wccr-meta-list">
							<div><dt><?php esc_html_e( 'Step', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( absint( $eligibility['current_step'] ?? 0 ) ?: '-' ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Emails sent', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( $this->email_log_repository->count_sent_for_cart( absint( $cart['id'] ) ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Coupon', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( $this->get_coupon_label( $cart ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Eligible at', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( $this->email_eligibility_service->format_gmt_for_display( (string) ( $eligibility['eligible_at_gmt'] ?? '' ) ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Reason', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( $this->email_eligibility_service->get_reason_label( (string) ( $eligibility['reason'] ?? '' ) ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Clicked email link', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( $this->get_clicked_label( $cart ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Purchased', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( $this->get_purchased_label( $cart ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Order', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo wp_kses_post( $this->get_order_link_html( absint( $cart['recovered_order_id'] ?? 0 ) ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Last error', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( $this->email_log_repository->get_last_error_for_cart( absint( $cart['id'] ) ) ?: '-' ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Abandoned at', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( $this->email_eligibility_service->format_gmt_for_display( (string) ( $cart['abandoned_at_gmt'] ?? '' ) ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Last update', 'woocommerce-cart-recovery' ); ?></dt><dd><?php echo esc_html( $this->email_eligibility_service->format_gmt_for_display( (string) ( $cart['updated_at_gmt'] ?? '' ) ) ); ?></dd></div>
						</dl>

						<div class="wccr-actions">
							<?php echo wp_kses_post( $this->get_recovery_url_html( $cart ) ); ?>
							<form method="post" class="wccr-delete-form">
								<?php wp_nonce_field( 'wccr_delete_cart_' . absint( $cart['id'] ), 'wccr_delete_nonce' ); ?>
								<input type="hidden" name="wccr_delete_cart_id" value="<?php echo esc_attr( absint( $cart['id'] ) ); ?>">
								<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'woocommerce-cart-recovery' ); ?></button>
							</form>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private function maybe_handle_delete(): void {
		if ( ! isset( $_POST['wccr_delete_cart_id'], $_POST['wccr_delete_nonce'] ) ) {
			return;
		}

		$cart_id = absint( $_POST['wccr_delete_cart_id'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccr_delete_nonce'] ) ), 'wccr_delete_cart_' . $cart_id ) ) {
			return;
		}

		$this->email_log_repository->delete_for_cart( $cart_id );
		$this->cart_repository->delete_by_id( $cart_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'wccr-cart-recovery',
					'sort'         => $this->get_current_sort(),
					'wccr_deleted' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function get_current_sort(): string {
		$sort = sanitize_key( wp_unslash( $_GET['sort'] ?? 'recent' ) );
		return array_key_exists( $sort, $this->get_sort_options() ) ? $sort : 'recent';
	}

	private function get_sort_options(): array {
		return array(
			'recent'    => __( 'Most recent', 'woocommerce-cart-recovery' ),
			'oldest'    => __( 'Oldest first', 'woocommerce-cart-recovery' ),
			'abandoned' => __( 'Abandoned date', 'woocommerce-cart-recovery' ),
			'email'     => __( 'Email A-Z', 'woocommerce-cart-recovery' ),
			'status'    => __( 'Status', 'woocommerce-cart-recovery' ),
			'purchased' => __( 'Purchased first', 'woocommerce-cart-recovery' ),
		);
	}

	private function get_order_link_html( int $order_id ): string {
		if ( ! $order_id ) {
			return '-';
		}

		$url = get_edit_post_link( $order_id );
		if ( ! $url ) {
			return '#' . $order_id;
		}

		return sprintf( '<a href="%s">#%d</a>', esc_url( $url ), $order_id );
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

		return $coupon_code;
	}

	private function get_clicked_label( array $cart ): string {
		$clicked_at = $this->email_eligibility_service->format_gmt_for_display( $cart['clicked_at_gmt'] ?? '' );

		if ( '-' === $clicked_at ) {
			return __( 'No', 'woocommerce-cart-recovery' );
		}

		return sprintf( __( 'Yes (%s)', 'woocommerce-cart-recovery' ), $clicked_at );
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
