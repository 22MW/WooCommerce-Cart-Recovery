<?php
defined( 'ABSPATH' ) || exit;

/**
 * Render and manage the main recovery admin screen.
 */
final class WCCR_Abandoned_Carts_Page {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Email_Log_Repository $email_log_repository,
		private WCCR_Stats_Repository $stats_repository,
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

		?>
		<div class="wrap wccr-admin">
			<h1><?php esc_html_e( 'Cart Recovery', 'vfwoo_woocommerce-cart-recovery' ); ?></h1>
			<?php $this->render_content(); ?>
		</div>
		<?php
	}

	/**
	 * Render the carts dashboard content without the page wrapper.
	 */
	public function render_content(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'vfwoo_woocommerce-cart-recovery' ) );
		}

		$this->maybe_handle_delete();

		$sort     = $this->get_current_sort();
		$view     = $this->get_current_view();
		$carts    = $this->cart_repository->list_recovery_items( $sort );
		$settings = $this->settings_repository->get();
		$stats    = $this->stats_service->get_stats();

		?>
		<?php $this->render_deleted_notice(); ?>
		<?php $this->render_stats_grid( $stats ); ?>
		<?php $this->render_dashboard_actions(); ?>
		<?php $this->render_toolbar( $sort, $view ); ?>
		<?php $this->render_cards_grid( $carts, $settings, $view ); ?>
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

		$cart = $this->cart_repository->find_by_id( $cart_id );
		if ( ! $cart ) {
			return;
		}

		$deleted_order = $this->delete_plugin_owned_order( absint( $cart['linked_order_id'] ?? 0 ) );
		$this->stats_repository->archive_cart_metrics( $cart, $this->email_log_repository->count_sent_for_cart( $cart_id ) );
		$this->email_log_repository->delete_for_cart( $cart_id );
		$this->cart_repository->delete_by_id( $cart_id );

		wp_safe_redirect( $this->get_dashboard_url( array( 'wccr_deleted' => $deleted_order ? 'order' : 'item' ) ) );
		exit;
	}

	/**
	 * Render a success notice after delete.
	 */
	private function render_deleted_notice(): void {
		$deleted = isset( $_GET['wccr_deleted'] ) ? sanitize_key( wp_unslash( $_GET['wccr_deleted'] ) ) : '';
		if ( '' === $deleted ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				echo esc_html(
					'order' === $deleted
						? __( 'Recovery item and linked unpaid order deleted.', 'vfwoo_woocommerce-cart-recovery' )
						: __( 'Recovery item deleted.', 'vfwoo_woocommerce-cart-recovery' )
				);
				?>
			</p>
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
	 * Render the run/import action cards under the statistics grid.
	 */
	private function render_dashboard_actions(): void {
		?>
		<div class="wccr-dashboard-actions">
			<form method="post" class="wccr-run-now-form">
				<?php wp_nonce_field( 'wccr_run_now', 'wccr_run_now_nonce' ); ?>
				<?php submit_button( __( 'Run now', 'vfwoo_woocommerce-cart-recovery' ), 'secondary', 'wccr_run_now', false ); ?>
				<p class="description"><?php esc_html_e( 'Runs abandoned-cart detection, unpaid-order sync and recovery email queue immediately.', 'vfwoo_woocommerce-cart-recovery' ); ?></p>
			</form>
			<form method="post" class="wccr-run-now-form">
				<?php wp_nonce_field( 'wccr_import_unpaid_orders', 'wccr_import_unpaid_nonce' ); ?>
				<?php submit_button( __( 'Import unpaid orders', 'vfwoo_woocommerce-cart-recovery' ), 'secondary', 'wccr_import_unpaid', false ); ?>
				<p class="description"><?php esc_html_e( 'Imports existing pending and failed WooCommerce orders that match the recovery rules.', 'vfwoo_woocommerce-cart-recovery' ); ?></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the sorting toolbar.
	 */
	private function render_toolbar( string $sort, string $view ): void {
		?>
		<form method="get" class="wccr-toolbar">
			<input type="hidden" name="page" value="wccr-cart-recovery">
			<label for="wccr-sort"><?php esc_html_e( 'Sort by', 'vfwoo_woocommerce-cart-recovery' ); ?></label>
			<select id="wccr-sort" name="sort">
				<?php foreach ( $this->get_sort_options() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $sort, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="wccr-view"><?php esc_html_e( 'Vista', 'vfwoo_woocommerce-cart-recovery' ); ?></label>
			<select id="wccr-view" name="view">
				<?php foreach ( $this->get_view_options() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $view, $value ); ?>><?php echo esc_html( $label ); ?></option>
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
	private function render_cards_grid( array $carts, array $settings, string $view ): void {
		?>
		<div class="wccr-recovery-grid wccr-recovery-grid--<?php echo esc_attr( $view ); ?>">
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
		?>
		<article class="wccr-recovery-item">
			<?php $this->render_card_header( $cart ); ?>
			<div class="wccr-recovery-item__body">
				<?php $this->render_card_meta( $cart ); ?>
				<?php $this->render_email_steps( $cart ); ?>
			</div>
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
		$title  = $this->get_card_title( $cart );
		$email  = ! empty( $cart['email'] ) ? (string) $cart['email'] : __( 'No email', 'vfwoo_woocommerce-cart-recovery' );
		$total  = $this->format_cart_total_label( $cart );
		$status = (string) $cart['status'];
		?>
		<div class="wccr-recovery-item__header">
			<div class="wccr-recovery-item__badges">
				<span class="wccr-status-badge wccr-status-badge--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>">
					<?php echo esc_html( $this->get_status_label( $status ) ); ?>
				</span>
				<span class="wccr-status-badge wccr-status-badge--source">
					<?php echo esc_html( $this->get_source_badge_label( $cart ) ); ?>
				</span>
			</div>
			<div class="wccr-recovery-item__headline">
				<p class="wccr-recovery-item__title-line">
					<span class="wccr-recovery-item__title"><?php echo esc_html( $title ); ?></span>
					<span class="wccr-recovery-item__separator">|</span>
					<span class="wccr-recovery-item__subtitle"><?php echo esc_html( $email ); ?></span>
					<span class="wccr-recovery-item__separator">|</span>
					<span class="wccr-recovery-item__total"><?php echo esc_html( $total ); ?></span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the card metadata list.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function render_card_meta( array $cart ): void {
		$meta = array_filter(
			array(
				$this->build_meta_item( __( 'Source', 'vfwoo_woocommerce-cart-recovery' ), $this->get_source_label( $cart ) ),
				$this->build_meta_item( __( 'Linked order', 'vfwoo_woocommerce-cart-recovery' ), $this->get_order_link_html( absint( $cart['linked_order_id'] ?? 0 ) ) ),
				$this->build_meta_item( __( 'Order', 'vfwoo_woocommerce-cart-recovery' ), $this->get_order_link_html( absint( $cart['recovered_order_id'] ?? 0 ) ) ),
				$this->build_meta_item( __( 'Last error', 'vfwoo_woocommerce-cart-recovery' ), $this->get_last_error_label( $cart ) ),
				$this->build_meta_item( __( 'Abandoned at', 'vfwoo_woocommerce-cart-recovery' ), $this->email_eligibility_service->format_gmt_for_display( (string) ( $cart['abandoned_at_gmt'] ?? '' ) ) ),
				$this->build_meta_item( __( 'Last update', 'vfwoo_woocommerce-cart-recovery' ), $this->email_eligibility_service->format_gmt_for_display( (string) ( $cart['updated_at_gmt'] ?? '' ) ) ),
			)
		);

		if ( empty( $meta ) ) {
			return;
		}
		?>
		<div class="wccr-meta-line">
			<?php foreach ( $meta as $item ) : ?>
				<div class="wccr-meta-line__item">
					<span class="wccr-meta-line__label"><?php echo esc_html( (string) $item['label'] ); ?></span>
					<span class="wccr-meta-line__separator">-</span>
					<span class="wccr-meta-line__value">
						<?php echo ! empty( $item['is_html'] ) ? wp_kses_post( (string) $item['value'] ) : esc_html( (string) $item['value'] ); ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
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
			<form method="post" class="wccr-delete-form">
				<?php wp_nonce_field( 'wccr_delete_cart_' . absint( $cart['id'] ), 'wccr_delete_nonce' ); ?>
				<input type="hidden" name="wccr_delete_cart_id" value="<?php echo esc_attr( absint( $cart['id'] ) ); ?>">
				<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'vfwoo_woocommerce-cart-recovery' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Render per-email-step tracking blocks.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function render_email_steps( array $cart ): void {
		$sent_logs = $this->email_log_repository->get_sent_logs_for_cart( absint( $cart['id'] ) );
		?>
		<div class="wccr-email-steps">
			<?php foreach ( array( 1, 2, 3 ) as $step ) : ?>
				<?php $this->render_email_step_card( $cart, $step, $sent_logs[ $step ] ?? array() ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render the tracking card for one email step.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 * @param array<string, mixed> $log  Step log row.
	 */
	private function render_email_step_card( array $cart, int $step, array $log ): void {
		$items = $this->get_email_step_items( $cart, $step, $log );
		?>
		<div class="wccr-email-step-card">
			<h3><?php echo esc_html( sprintf( /* translators: %d: email step number */ __( 'Email %d', 'vfwoo_woocommerce-cart-recovery' ), $step ) ); ?></h3>
			<?php if ( ! empty( $items ) ) : ?>
				<div class="wccr-email-step-card__meta">
					<?php foreach ( $items as $item ) : ?>
						<div class="wccr-email-step-card__item">
							<span class="wccr-email-step-card__label"><?php echo esc_html( (string) $item['label'] ); ?></span>
							<span class="wccr-email-step-card__separator">-</span>
							<span class="<?php echo esc_attr( $this->get_email_step_value_class( $item ) ); ?>"><?php echo esc_html( (string) $item['value'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php echo wp_kses_post( $this->get_step_recovery_url_html( $cart, $step, (string) ( $log['coupon_code'] ?? '' ) ) ); ?>
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
	 * Return supported view options.
	 *
	 * @return array<string, string>
	 */
	private function get_view_options(): array {
		return array(
			'grid' => __( 'Tarjetas', 'vfwoo_woocommerce-cart-recovery' ),
			'list' => __( 'Lista', 'vfwoo_woocommerce-cart-recovery' ),
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
					'view' => $this->get_current_view(),
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Return the current card layout mode.
	 */
	private function get_current_view(): string {
		$user_id = get_current_user_id();
		$view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';

		if ( array_key_exists( $view, $this->get_view_options() ) ) {
			if ( $user_id > 0 ) {
				update_user_meta( $user_id, 'wccr_admin_view', $view );
			}

			return $view;
		}

		$stored_view = $user_id > 0 ? get_user_meta( $user_id, 'wccr_admin_view', true ) : '';
		return is_string( $stored_view ) && array_key_exists( $stored_view, $this->get_view_options() ) ? $stored_view : 'grid';
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
	private function get_card_title( array $cart ): string {
		$name = trim( (string) ( $cart['customer_name'] ?? '' ) );
		if ( '' !== $name ) {
			return $name;
		}

		return ! empty( $cart['email'] ) ? (string) $cart['email'] : __( 'No email', 'vfwoo_woocommerce-cart-recovery' );
	}

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
	 * Get a translated source label for the current recovery row.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_source_label( array $cart ): string {
		$source = (string) ( $cart['primary_source'] ?? 'cart' );
		$labels = array(
			'cart'  => __( 'Cart', 'vfwoo_woocommerce-cart-recovery' ),
			'order' => __( 'Order', 'vfwoo_woocommerce-cart-recovery' ),
		);

		return $labels[ $source ] ?? $source;
	}

	/**
	 * Return a short origin label for the card header badge.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_source_badge_label( array $cart ): string {
		$source = (string) ( $cart['primary_source'] ?? 'cart' );

		if ( 'order' === $source ) {
			return __( 'Imported', 'vfwoo_woocommerce-cart-recovery' );
		}

		return __( 'Captured', 'vfwoo_woocommerce-cart-recovery' );
	}

	/**
	 * Build one compact meta item when it has meaningful content.
	 *
	 * @return array<string, mixed>|null
	 */
	private function build_meta_item( string $label, string $value ): ?array {
		if ( '' === trim( wp_strip_all_tags( $value ) ) || '-' === trim( $value ) ) {
			return null;
		}

		return array(
			'label'   => $label,
			'value'   => $value,
			'is_html' => str_contains( $value, '<a ' ),
		);
	}

	/**
	 * Return the latest email error for one cart when available.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_last_error_label( array $cart ): string {
		$error = trim( $this->email_log_repository->get_last_error_for_cart( absint( $cart['id'] ) ) );
		return '' !== $error ? $error : '-';
	}

	/**
	 * Build query arguments for a recovery URL.
	 *
	 * @return array<string, int|string>
	 */
	private function get_recovery_query_args( int $cart_id, string $coupon_code, int $step = 0 ): array {
		$args = array(
			'wccr_recover' => $cart_id,
			'wccr_token'   => wp_hash( $cart_id . '|' . wp_salt( 'auth' ) ),
		);

		if ( '' !== $coupon_code ) {
			$args['wccr_coupon'] = $coupon_code;
		}

		if ( $step > 0 ) {
			$args['wccr_step'] = $step;
		}

		return $args;
	}

	/**
	 * Build the copy-url button for a specific email step.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_step_recovery_url_html( array $cart, int $step, string $coupon_code ): string {
		if ( empty( $cart['id'] ) ) {
			return '-';
		}

		$recovery_url = add_query_arg(
			$this->get_recovery_query_args( absint( $cart['id'] ), $coupon_code, $step ),
			function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' )
		);

		return sprintf(
			'<button type="button" class="button button-secondary wccr-copy-url" data-url="%s">%s</button>',
			esc_attr( $recovery_url ),
			esc_html__( 'Copy URL', 'vfwoo_woocommerce-cart-recovery' )
		);
	}

	/**
	 * Return whether the selected email step is the one that received the click.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_step_clicked_label( array $cart, int $step, array $log ): string {
		if ( ! empty( $log['clicked_at_gmt'] ) ) {
			return __( 'Yes', 'vfwoo_woocommerce-cart-recovery' );
		}

		$clicked_step = absint( $cart['clicked_step'] ?? 0 );
		if ( $clicked_step < 1 ) {
			return __( 'No', 'vfwoo_woocommerce-cart-recovery' );
		}

		return $clicked_step === $step
			? __( 'Yes', 'vfwoo_woocommerce-cart-recovery' )
			: __( 'No', 'vfwoo_woocommerce-cart-recovery' );
	}

	/**
	 * Build the compact list of visible values for one email step.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 * @param array<string, mixed> $log  Step log row.
	 * @return array<int, array<string, string>>
	 */
	private function get_email_step_items( array $cart, int $step, array $log ): array {
		$items   = array();
		$is_sent = ! empty( $log );

		$items[] = array(
			'label' => __( 'Sent', 'vfwoo_woocommerce-cart-recovery' ),
			'value' => $is_sent ? __( 'Yes', 'vfwoo_woocommerce-cart-recovery' ) : __( 'No', 'vfwoo_woocommerce-cart-recovery' ),
		);

		$sent_at = $this->email_eligibility_service->format_gmt_for_display( (string) ( $log['sent_at_gmt'] ?? '' ) );
		if ( '-' !== $sent_at ) {
			$items[] = array(
				'label' => __( 'Sent at', 'vfwoo_woocommerce-cart-recovery' ),
				'value' => $sent_at,
			);
		}

		$coupon_code = (string) ( $log['coupon_code'] ?? '' );
		if ( '' !== $coupon_code ) {
			$items[] = array(
				'label' => __( 'Coupon', 'vfwoo_woocommerce-cart-recovery' ),
				'value' => $coupon_code,
			);
		}

		$items[] = array(
			'label' => __( 'Clicked', 'vfwoo_woocommerce-cart-recovery' ),
			'value' => $this->get_step_clicked_label( $cart, $step, $log ),
		);

		$items[] = array(
			'label' => __( 'Resolved', 'vfwoo_woocommerce-cart-recovery' ),
			'value' => $this->get_step_resolved_label( $cart, $step ),
			'value_class' => $this->is_resolved_step( $cart, $step ) ? 'wccr-email-step-card__value wccr-status-badge wccr-status-badge--recovered' : 'wccr-email-step-card__value',
		);

		return $items;
	}

	/**
	 * Get the CSS class list for one step value.
	 *
	 * @param array<string, mixed> $item Step item payload.
	 */
	private function get_email_step_value_class( array $item ): string {
		$value_class = isset( $item['value_class'] ) ? trim( (string) $item['value_class'] ) : '';
		return '' !== $value_class ? $value_class : 'wccr-email-step-card__value';
	}

	/**
	 * Return whether the recovery flow was completed.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function get_step_resolved_label( array $cart, int $step ): string {
		if ( ! $this->is_resolved_step( $cart, $step ) ) {
			return __( 'No', 'vfwoo_woocommerce-cart-recovery' );
		}

		return sprintf(
			/* translators: %s: recovered amount. */
			__( 'Yes (%s)', 'vfwoo_woocommerce-cart-recovery' ),
			$this->format_cart_total_label( $cart )
		);
	}

	/**
	 * Determine whether the provided email step is the one that recovered the order.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function is_resolved_step( array $cart, int $step ): bool {
		return 'recovered' === (string) ( $cart['status'] ?? '' ) && absint( $cart['clicked_step'] ?? 0 ) === $step;
	}

	/**
	 * Return a consistent plain-text money label for the cart total.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	private function format_cart_total_label( array $cart ): string {
		$formatted = wc_price(
			(float) ( $cart['cart_total'] ?? 0 ),
			array( 'currency' => (string) ( $cart['currency'] ?? '' ) )
		);

		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( $formatted, ENT_QUOTES, 'UTF-8' ) ) ) );
	}

	/**
	 * Delete a linked WooCommerce order only if it belongs to this plugin and is still unpaid.
	 */
	private function delete_plugin_owned_order( int $order_id ): bool {
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order || ! in_array( $order->get_status(), array( 'pending', 'failed' ), true ) ) {
			return false;
		}

		if ( 1 !== absint( $order->get_meta( '_wccr_managed_unpaid_order', true ) ) ) {
			return false;
		}

		$order->delete( true );
		return true;
	}
}
