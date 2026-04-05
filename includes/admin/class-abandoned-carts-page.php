<?php
defined( 'ABSPATH' ) || exit;

/**
 * Render and manage the main recovery admin screen.
 */
final class WCCR_Abandoned_Carts_Page {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Email_Log_Repository $email_log_repository,
		private WCCR_Settings_Repository $settings_repository,
		private WCCR_Email_Eligibility_Service $email_eligibility_service,
		private WCCR_Stats_Service $stats_service
	) {}

	/**
	 * Render the unified recovery dashboard.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'vfwoo_woocommerce-cart-recovery' ) );
		}

		$this->maybe_handle_delete();

		$sort     = $this->get_current_sort();
		$carts    = $this->cart_repository->list_recovery_items( $sort );
		$settings = $this->settings_repository->get();
		$stats    = $this->stats_service->get_stats();

		?>
		<div class="wrap wccr-admin">
			<h1><?php esc_html_e( 'Cart Recovery', 'vfwoo_woocommerce-cart-recovery' ); ?></h1>
			<?php $this->render_deleted_notice(); ?>
			<?php $this->render_stats_grid( $stats ); ?>
			<?php $this->render_toolbar( $sort ); ?>
			<?php $this->render_cards_grid( $carts, $settings ); ?>
		</div>
		<?php
	}

	/**
	 * Delete a recovery item after nonce and capability validation.
	 */
	private function maybe_handle_delete(): void {
		if ( ! isset( $_POST['wccr_delete_cart_id'], $_POST['wccr_delete_nonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$cart_id = absint( wp_unslash( $_POST['wccr_delete_cart_id'] ) );
		$nonce   = sanitize_text_field( wp_unslash( $_POST['wccr_delete_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'wccr_delete_cart_' . $cart_id ) ) {
			return;
		}

		$this->email_log_repository->delete_for_cart( $cart_id );
		$this->cart_repository->delete_by_id( $cart_id );

		wp_safe_redirect( $this->get_dashboard_url( array( 'wccr_deleted' => 1 ) ) );
		exit;
	}

	/**
	 * Render a success notice after delete.
	 */
	private function render_deleted_notice(): void {
		if ( ! isset( $_GET['wccr_deleted'] ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Recovery item deleted.', 'vfwoo_woocommerce-cart-recovery' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the statistics cards.
	 *
	 * @param array<string, float|int> $stats Statistics payload.
	 */
	private function render_stats_grid( array $stats ): void {
		?>
		<div class="wccr-stats-grid">
			<div class="wccr-card"><strong><?php echo esc_html( (string) $stats['abandoned'] ); ?></strong><span><?php esc_html_e( 'Abandoned carts', 'vfwoo_woocommerce-cart-recovery' ); ?></span></div>
			<div class="wccr-card"><strong><?php echo esc_html( (string) $stats['clicked'] ); ?></strong><span><?php esc_html_e( 'Recovery clicks', 'vfwoo_woocommerce-cart-recovery' ); ?></span></div>
			<div class="wccr-card"><strong><?php echo esc_html( (string) $stats['recovered'] ); ?></strong><span><?php esc_html_e( 'Recovered carts', 'vfwoo_woocommerce-cart-recovery' ); ?></span></div>
			<div class="wccr-card"><strong><?php echo esc_html( (string) $stats['recovery_rate'] ); ?>%</strong><span><?php esc_html_e( 'Recovery rate', 'vfwoo_woocommerce-cart-recovery' ); ?></span></div>
			<div class="wccr-card"><strong><?php echo wp_kses_post( wc_price( (float) $stats['revenue'] ) ); ?></strong><span><?php esc_html_e( 'Recovered revenue', 'vfwoo_woocommerce-cart-recovery' ); ?></span></div>
			<div class="wccr-card"><strong><?php echo esc_html( (string) $stats['emails_sent'] ); ?></strong><span><?php esc_html_e( 'Emails sent', 'vfwoo_woocommerce-cart-recovery' ); ?></span></div>
		</div>
		<?php
	}

	/**
	 * Render the sorting toolbar.
	 */
	private function render_toolbar( string $sort ): void {
		?>
		<form method="get" class="wccr-toolbar">
			<input type="hidden" name="page" value="wccr-cart-recovery">
			<label for="wccr-sort"><?php esc_html_e( 'Sort by', 'vfwoo_woocommerce-cart-recovery' ); ?></label>
			<select id="wccr-sort" name="sort">
				<?php foreach ( $this->get_sort_options() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $sort, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Apply', 'vfwoo_woocommerce-cart-recovery' ), 'secondary', '', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render all recovery cards.
	 *
	 * @param array<int, array<string, mixed>> $carts    Recovery rows.
	 * @param array<string, mixed>             $settings Plugin settings.
	 */
	private function render_cards_grid( array $carts, array $settings ): void {
		?>
		<div class="wccr-recovery-grid">
			<?php foreach ( $carts as $cart ) : ?>
				<?php $this->render_recovery_card( $cart, $settings ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render a single recovery card.
	 *
	 * @param array<string, mixed> $cart     Recovery row.
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private function render_recovery_card( array $cart, array $settings ): void {
		$eligibility = $this->email_eligibility_service->get_status( $cart, $settings );
		?>
		<article class="wccr-recovery-item">
			<?php $this->render_card_header( $cart ); ?>
			<?php $this->render_card_meta( $cart, $eligibility ); ?>
			<?php $this->render_card_actions( $cart ); ?>
		</article>
		<?php
	}

	/**
	 * Render the card header.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function render_card_header( array $cart ): void {
		$email  = ! empty( $cart['email'] ) ? (string) $cart['email'] : __( 'No email', 'vfwoo_woocommerce-cart-recovery' );
		$total  = (string) $cart['cart_total'] . ' ' . (string) $cart['currency'];
		$status = (string) $cart['status'];
		?>
		<div class="wccr-recovery-item__header">
			<div>
				<h2 class="wccr-recovery-item__title"><?php echo esc_html( $email ); ?></h2>
				<p class="wccr-recovery-item__total"><?php echo esc_html( $total ); ?></p>
			</div>
			<span class="wccr-status-badge wccr-status-badge--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>">
				<?php echo esc_html( $this->get_status_label( $status ) ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Render the card metadata list.
	 *
	 * @param array<string, mixed> $cart        Recovery row.
	 * @param array<string, mixed> $eligibility Eligibility payload.
	 */
	private function render_card_meta( array $cart, array $eligibility ): void {
		$meta = array(
			__( 'Step', 'vfwoo_woocommerce-cart-recovery' )               => absint( $eligibility['current_step'] ?? 0 ) ?: '-',
			__( 'Emails sent', 'vfwoo_woocommerce-cart-recovery' )        => $this->email_log_repository->count_sent_for_cart( absint( $cart['id'] ) ),
			__( 'Coupon', 'vfwoo_woocommerce-cart-recovery' )             => $this->get_coupon_label( $cart ),
			__( 'Eligible at', 'vfwoo_woocommerce-cart-recovery' )        => $this->email_eligibility_service->format_gmt_for_display( (string) ( $eligibility['eligible_at_gmt'] ?? '' ) ),
			__( 'Reason', 'vfwoo_woocommerce-cart-recovery' )             => $this->email_eligibility_service->get_reason_label( (string) ( $eligibility['reason'] ?? '' ) ),
			__( 'Clicked email link', 'vfwoo_woocommerce-cart-recovery' ) => $this->get_clicked_label( $cart ),
			__( 'Purchased', 'vfwoo_woocommerce-cart-recovery' )          => $this->get_purchased_label( $cart ),
			__( 'Order', 'vfwoo_woocommerce-cart-recovery' )              => $this->get_order_link_html( absint( $cart['recovered_order_id'] ?? 0 ) ),
			__( 'Last error', 'vfwoo_woocommerce-cart-recovery' )         => $this->email_log_repository->get_last_error_for_cart( absint( $cart['id'] ) ) ?: '-',
			__( 'Abandoned at', 'vfwoo_woocommerce-cart-recovery' )       => $this->email_eligibility_service->format_gmt_for_display( (string) ( $cart['abandoned_at_gmt'] ?? '' ) ),
			__( 'Last update', 'vfwoo_woocommerce-cart-recovery' )        => $this->email_eligibility_service->format_gmt_for_display( (string) ( $cart['updated_at_gmt'] ?? '' ) ),
		);
		?>
		<dl class="wccr-meta-list">
			<?php foreach ( $meta as $label => $value ) : ?>
				<div>
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo is_string( $value ) && str_contains( $value, '<a ' ) ? wp_kses_post( $value ) : esc_html( (string) $value ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<?php
	}

	/**
	 * Render the action area for a card.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function render_card_actions( array $cart ): void {
		?>
		<div class="wccr-actions">
			<?php echo wp_kses_post( $this->get_recovery_url_html( $cart ) ); ?>
			<form method="post" class="wccr-delete-form">
				<?php wp_nonce_field( 'wccr_delete_cart_' . absint( $cart['id'] ), 'wccr_delete_nonce' ); ?>
				<input type="hidden" name="wccr_delete_cart_id" value="<?php echo esc_attr( absint( $cart['id'] ) ); ?>">
				<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'vfwoo_woocommerce-cart-recovery' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the current sort key from the request.
	 */
	private function get_current_sort(): string {
		$sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'recent';
		return array_key_exists( $sort, $this->get_sort_options() ) ? $sort : 'recent';
	}

	/**
	 * Return supported sort options.
	 *
	 * @return array<string, string>
	 */
	private function get_sort_options(): array {
		return array(
			'recent'    => __( 'Most recent', 'vfwoo_woocommerce-cart-recovery' ),
			'oldest'    => __( 'Oldest first', 'vfwoo_woocommerce-cart-recovery' ),
			'abandoned' => __( 'Abandoned date', 'vfwoo_woocommerce-cart-recovery' ),
			'email'     => __( 'Email A-Z', 'vfwoo_woocommerce-cart-recovery' ),
			'status'    => __( 'Status', 'vfwoo_woocommerce-cart-recovery' ),
			'purchased' => __( 'Purchased first', 'vfwoo_woocommerce-cart-recovery' ),
		);
	}

	/**
	 * Build the dashboard URL preserving current sort.
	 *
	 * @param array<string, scalar> $args Extra query args.
	 */
	private function get_dashboard_url( array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'wccr-cart-recovery',
					'sort' => $this->get_current_sort(),
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get the order link or a fallback label.
	 */
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

	/**
	 * Get a human-friendly purchase label.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_purchased_label( array $cart ): string {
		return ! empty( $cart['recovered_order_id'] ) && 'recovered' === ( $cart['status'] ?? '' )
			? __( 'Yes', 'vfwoo_woocommerce-cart-recovery' )
			: __( 'No', 'vfwoo_woocommerce-cart-recovery' );
	}

	/**
	 * Get the most recent coupon label for a cart.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_coupon_label( array $cart ): string {
		$coupon_code = $this->email_log_repository->get_last_coupon_code_for_cart( absint( $cart['id'] ?? 0 ) );
		return '' !== $coupon_code ? $coupon_code : '-';
	}

	/**
	 * Translate a stored cart status into an admin label.
	 */
	private function get_status_label( string $status ): string {
		$labels = array(
			'active'    => __( 'Active', 'vfwoo_woocommerce-cart-recovery' ),
			'abandoned' => __( 'Abandoned', 'vfwoo_woocommerce-cart-recovery' ),
			'clicked'   => __( 'Clicked', 'vfwoo_woocommerce-cart-recovery' ),
			'recovered' => __( 'Recovered', 'vfwoo_woocommerce-cart-recovery' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Get the clicked label with optional timestamp.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_clicked_label( array $cart ): string {
		$clicked_at = $this->email_eligibility_service->format_gmt_for_display( (string) ( $cart['clicked_at_gmt'] ?? '' ) );
		if ( '-' === $clicked_at ) {
			return __( 'No', 'vfwoo_woocommerce-cart-recovery' );
		}

		return sprintf(
			/* translators: %s: click timestamp */
			__( 'Yes (%s)', 'vfwoo_woocommerce-cart-recovery' ),
			$clicked_at
		);
	}

	/**
	 * Build the admin copy button for the recovery URL.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_recovery_url_html( array $cart ): string {
		if ( empty( $cart['id'] ) ) {
			return '-';
		}

		$coupon_code  = $this->email_log_repository->get_last_coupon_code_for_cart( absint( $cart['id'] ) );
		$recovery_url = add_query_arg(
			$this->get_recovery_query_args( absint( $cart['id'] ), $coupon_code ),
			function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' )
		);

		return sprintf(
			'<button type="button" class="button button-secondary wccr-copy-url" data-url="%s">%s</button>',
			esc_attr( $recovery_url ),
			esc_html__( 'Copy URL', 'vfwoo_woocommerce-cart-recovery' )
		);
	}

	/**
	 * Build query arguments for a recovery URL.
	 *
	 * @return array<string, int|string>
	 */
	private function get_recovery_query_args( int $cart_id, string $coupon_code ): array {
		$args = array(
			'wccr_recover' => $cart_id,
			'wccr_token'   => wp_hash( $cart_id . '|' . wp_salt( 'auth' ) ),
		);

		if ( '' !== $coupon_code ) {
			$args['wccr_coupon'] = $coupon_code;
		}

		return $args;
	}
}
