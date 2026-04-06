<?php
defined( 'ABSPATH' ) || exit;

/**
 * Render and save plugin settings.
 */
final class WCCR_Settings_Page {
	public function __construct(
		private WCCR_Settings_Repository $settings_repository,
		private WCCR_Locale_Resolver_Manager $locale_resolver,
		private WCCR_Abandoned_Cart_Detector $detector,
		private WCCR_Email_Scheduler $email_scheduler,
		private WCCR_Pending_Order_Detector $pending_order_detector
	) {}

	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_init', array( $this, 'maybe_run_now' ) );
		add_action( 'admin_init', array( $this, 'maybe_import_unpaid_orders' ) );
	}

	/**
	 * Persist settings after nonce and capability validation.
	 */
	public function maybe_save(): void {
		if ( ! isset( $_POST['wccr_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccr_settings_nonce'] ) ), 'wccr_save_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = array(
			'abandon_after_minutes' => absint( $_POST['abandon_after_minutes'] ?? 60 ),
			'cleanup_days'          => absint( $_POST['cleanup_days'] ?? 90 ),
			'coupon_expiry_days'    => absint( $_POST['coupon_expiry_days'] ?? 7 ),
			'from_name'             => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ),
			'steps'                 => array(),
		);
		$locales  = $this->locale_resolver->get_available_locales();
		$default_locale = $this->locale_resolver->get_default_locale();

		foreach ( array( 1, 2, 3 ) as $step ) {
			$settings['steps'][ $step ] = array(
				'enabled'         => ! empty( $_POST['steps'][ $step ]['enabled'] ) ? 1 : 0,
				'delay_minutes'   => absint( $_POST['steps'][ $step ]['delay_minutes'] ?? 60 ),
				'discount_type'   => sanitize_text_field( wp_unslash( $_POST['steps'][ $step ]['discount_type'] ?? 'none' ) ),
				'discount_amount' => (float) ( $_POST['steps'][ $step ]['discount_amount'] ?? 0 ),
				'min_cart_total'  => (float) ( $_POST['steps'][ $step ]['min_cart_total'] ?? 0 ),
				'translations'    => $this->collect_step_translations( $step, $locales ),
			);

			$default_translation = $settings['steps'][ $step ]['translations'][ $default_locale ] ?? reset( $settings['steps'][ $step ]['translations'] );
			$settings['steps'][ $step ]['subject'] = is_array( $default_translation ) ? (string) ( $default_translation['subject'] ?? '' ) : '';
			$settings['steps'][ $step ]['body']    = is_array( $default_translation ) ? (string) ( $default_translation['body'] ?? '' ) : '';
		}

		$this->settings_repository->save( $settings );
		add_settings_error( 'wccr_settings', 'wccr_saved', __( 'Settings saved.', 'vfwoo_woocommerce-cart-recovery' ), 'updated' );
	}

	/**
	 * Manually run detector and queue from the settings screen.
	 */
	public function maybe_run_now(): void {
		if ( ! isset( $_POST['wccr_run_now_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccr_run_now_nonce'] ) ), 'wccr_run_now' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$this->detector->run();
		$this->pending_order_detector->sync_stale_pending_orders();
		$this->email_scheduler->process_queue();

		add_settings_error( 'wccr_settings', 'wccr_run_done', __( 'Recovery detector and email queue executed manually.', 'vfwoo_woocommerce-cart-recovery' ), 'updated' );
	}

	/**
	 * Manually import existing pending and failed orders.
	 */
	public function maybe_import_unpaid_orders(): void {
		if ( ! isset( $_POST['wccr_import_unpaid_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccr_import_unpaid_nonce'] ) ), 'wccr_import_unpaid_orders' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$results = $this->pending_order_detector->import_existing_unpaid_orders();

		add_settings_error(
			'wccr_settings',
			'wccr_import_done',
			$this->get_import_notice_message( $results ),
			'updated'
		);
	}

	/**
	 * Render the settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'vfwoo_woocommerce-cart-recovery' ) );
		}

		?>
		<div class="wrap wccr-admin">
			<h1><?php esc_html_e( 'Cart Recovery Settings', 'vfwoo_woocommerce-cart-recovery' ); ?></h1>
			<?php settings_errors( 'wccr_settings' ); ?>
			<?php $this->render_content(); ?>
		</div>
		<?php
	}

	/**
	 * Render the settings form without the page wrapper.
	 */
	public function render_content(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'vfwoo_woocommerce-cart-recovery' ) );
		}

		$settings = $this->settings_repository->get();
		$locales  = $this->locale_resolver->get_available_locales();
		?>
		<form method="post">
			<?php wp_nonce_field( 'wccr_save_settings', 'wccr_settings_nonce' ); ?>
			<table class="form-table">
				<tr><th><label for="abandon_after_minutes"><?php esc_html_e( 'Mark cart abandoned after minutes', 'vfwoo_woocommerce-cart-recovery' ); ?></label></th><td><input type="number" name="abandon_after_minutes" id="abandon_after_minutes" value="<?php echo esc_attr( $settings['abandon_after_minutes'] ); ?>" min="1"></td></tr>
				<tr><th><label for="cleanup_days"><?php esc_html_e( 'Cleanup data after days', 'vfwoo_woocommerce-cart-recovery' ); ?></label></th><td><input type="number" name="cleanup_days" id="cleanup_days" value="<?php echo esc_attr( $settings['cleanup_days'] ); ?>" min="1"></td></tr>
				<tr><th><label for="coupon_expiry_days"><?php esc_html_e( 'Coupon expiry days', 'vfwoo_woocommerce-cart-recovery' ); ?></label></th><td><input type="number" name="coupon_expiry_days" id="coupon_expiry_days" value="<?php echo esc_attr( $settings['coupon_expiry_days'] ); ?>" min="1"></td></tr>
			</table>

			<?php $this->render_step_cards( $settings ); ?>
			<?php $this->render_locale_tabs( $settings, $locales ); ?>

			<?php submit_button( __( 'Save settings', 'vfwoo_woocommerce-cart-recovery' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Build a useful admin notice for unpaid-order imports.
	 *
	 * @param array{reviewed:int,imported:int,merged:int,updated:int,skipped:int} $results Import counters.
	 */
	private function get_import_notice_message( array $results ): string {
		if ( 0 === (int) $results['reviewed'] ) {
			return __( 'No eligible pending or failed orders were found.', 'vfwoo_woocommerce-cart-recovery' );
		}

		return sprintf(
			/* translators: 1: reviewed count, 2: imported count, 3: merged count, 4: updated count, 5: skipped count */
			__( 'Unpaid orders reviewed: %1$d. Imported: %2$d. Merged: %3$d. Updated: %4$d. Skipped: %5$d.', 'vfwoo_woocommerce-cart-recovery' ),
			(int) $results['reviewed'],
			(int) $results['imported'],
			(int) $results['merged'],
			(int) $results['updated'],
			(int) $results['skipped']
		);
	}

	/**
	 * Collect translated subject/body values for one email step.
	 *
	 * @param array<int, array{locale:string,label:string}> $locales Active locales.
	 * @return array<string, array{subject:string,body:string}>
	 */
	private function collect_step_translations( int $step, array $locales ): array {
		$translations = array();

		foreach ( $locales as $locale ) {
			$locale_key = sanitize_text_field( (string) ( $locale['locale'] ?? '' ) );
			if ( '' === $locale_key ) {
				continue;
			}

			$subject = $_POST['steps'][ $step ]['translations'][ $locale_key ]['subject'] ?? '';
			$body    = $_POST['steps'][ $step ]['translations'][ $locale_key ]['body'] ?? '';

			$translations[ $locale_key ] = array(
				'subject' => sanitize_text_field( wp_unslash( $subject ) ),
				'body'    => wp_kses_post( wp_unslash( $body ) ),
			);
		}

		return $translations;
	}

	/**
	 * Render global step cards shared across every language.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private function render_step_cards( array $settings ): void {
		foreach ( array( 1, 2, 3 ) as $step ) {
			$step_settings = isset( $settings['steps'][ $step ] ) && is_array( $settings['steps'][ $step ] ) ? $settings['steps'][ $step ] : array();
			?>
			<div class="wccr-card">
				<h2><?php echo esc_html( sprintf( /* translators: %d: email step number */ __( 'Email step %d', 'vfwoo_woocommerce-cart-recovery' ), $step ) ); ?></h2>
				<p><label><input type="checkbox" name="steps[<?php echo esc_attr( $step ); ?>][enabled]" value="1" <?php checked( ! empty( $step_settings['enabled'] ) ); ?>> <?php esc_html_e( 'Enabled', 'vfwoo_woocommerce-cart-recovery' ); ?></label></p>
				<p><label><?php esc_html_e( 'Delay minutes', 'vfwoo_woocommerce-cart-recovery' ); ?> <input type="number" name="steps[<?php echo esc_attr( $step ); ?>][delay_minutes]" value="<?php echo esc_attr( $step_settings['delay_minutes'] ?? 60 ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Discount type', 'vfwoo_woocommerce-cart-recovery' ); ?>
					<select name="steps[<?php echo esc_attr( $step ); ?>][discount_type]">
						<option value="none" <?php selected( $step_settings['discount_type'] ?? 'none', 'none' ); ?>><?php esc_html_e( 'None', 'vfwoo_woocommerce-cart-recovery' ); ?></option>
						<option value="percent" <?php selected( $step_settings['discount_type'] ?? 'none', 'percent' ); ?>><?php esc_html_e( 'Percentage', 'vfwoo_woocommerce-cart-recovery' ); ?></option>
						<option value="fixed_cart" <?php selected( $step_settings['discount_type'] ?? 'none', 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed cart', 'vfwoo_woocommerce-cart-recovery' ); ?></option>
					</select>
				</label></p>
				<p><label><?php esc_html_e( 'Discount amount', 'vfwoo_woocommerce-cart-recovery' ); ?> <input type="number" step="0.01" name="steps[<?php echo esc_attr( $step ); ?>][discount_amount]" value="<?php echo esc_attr( $step_settings['discount_amount'] ?? 0 ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Minimum cart total', 'vfwoo_woocommerce-cart-recovery' ); ?> <input type="number" step="0.01" name="steps[<?php echo esc_attr( $step ); ?>][min_cart_total]" value="<?php echo esc_attr( $step_settings['min_cart_total'] ?? 0 ); ?>"></label></p>
			</div>
			<?php
		}
	}

	/**
	 * Render translated email content tabs by locale.
	 *
	 * @param array<string, mixed>                $settings Plugin settings.
	 * @param array<int, array{locale:string,label:string}> $locales Active locales.
	 */
	private function render_locale_tabs( array $settings, array $locales ): void {
		?>
		<div class="wccr-locale-tabs">
			<div class="wccr-locale-tabs__nav" role="tablist" aria-label="<?php esc_attr_e( 'Email languages', 'vfwoo_woocommerce-cart-recovery' ); ?>">
				<?php foreach ( array_values( $locales ) as $index => $locale ) : ?>
					<?php $this->render_locale_tab_button( $locale, 0 === $index ); ?>
				<?php endforeach; ?>
			</div>
			<div class="wccr-locale-tabs__panels">
				<?php foreach ( array_values( $locales ) as $index => $locale ) : ?>
					<?php $this->render_locale_tab_panel( $settings, $locale, 0 === $index ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one locale tab button.
	 *
	 * @param array{locale:string,label:string} $locale   Locale item.
	 */
	private function render_locale_tab_button( array $locale, bool $is_active ): void {
		$locale_key = sanitize_key( str_replace( '-', '_', $locale['locale'] ) );
		?>
		<button
			type="button"
			class="wccr-locale-tabs__button<?php echo esc_attr( $is_active ? ' is-active' : '' ); ?>"
			data-locale-tab="<?php echo esc_attr( $locale['locale'] ); ?>"
			role="tab"
			aria-selected="<?php echo esc_attr( $is_active ? 'true' : 'false' ); ?>"
			aria-controls="wccr-locale-panel-<?php echo esc_attr( $locale_key ); ?>"
			id="wccr-locale-tab-<?php echo esc_attr( $locale_key ); ?>"
		><?php echo esc_html( $locale['label'] ); ?></button>
		<?php
	}

	/**
	 * Render one locale tab panel and its translated textareas.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param array{locale:string,label:string} $locale Locale item.
	 */
	private function render_locale_tab_panel( array $settings, array $locale, bool $is_active ): void {
		$locale_key = sanitize_key( str_replace( '-', '_', $locale['locale'] ) );
		?>
		<div
			class="wccr-locale-tabs__panel<?php echo esc_attr( $is_active ? ' is-active' : '' ); ?>"
			data-locale-panel="<?php echo esc_attr( $locale['locale'] ); ?>"
			id="wccr-locale-panel-<?php echo esc_attr( $locale_key ); ?>"
			role="tabpanel"
			aria-labelledby="wccr-locale-tab-<?php echo esc_attr( $locale_key ); ?>"
		>
			<?php foreach ( array( 1, 2, 3 ) as $step ) : ?>
				<?php $this->render_locale_translation_card( $settings, $step, (string) $locale['locale'] ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render one translated subject/body card for one locale and step.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private function render_locale_translation_card( array $settings, int $step, string $locale ): void {
		$step_settings = $this->settings_repository->get_localized_step_settings( $settings, $step, $locale );
		?>
		<div class="wccr-card">
			<h3><?php echo esc_html( sprintf( __( 'Email step %d', 'vfwoo_woocommerce-cart-recovery' ), $step ) ); ?></h3>
			<p><label><?php esc_html_e( 'Subject', 'vfwoo_woocommerce-cart-recovery' ); ?><br><input type="text" class="large-text" name="steps[<?php echo esc_attr( $step ); ?>][translations][<?php echo esc_attr( $locale ); ?>][subject]" value="<?php echo esc_attr( $step_settings['subject'] ?? '' ); ?>"></label></p>
			<p><label><?php esc_html_e( 'Body', 'vfwoo_woocommerce-cart-recovery' ); ?><br><textarea class="large-text" rows="6" name="steps[<?php echo esc_attr( $step ); ?>][translations][<?php echo esc_attr( $locale ); ?>][body]"><?php echo esc_textarea( $step_settings['body'] ?? '' ); ?></textarea></label></p>
			<p class="description"><?php esc_html_e( 'Available variables: {recovery_link}, {coupon_code}, {coupon_label}, {cart_total}, {site_name}, {customer_name}', 'vfwoo_woocommerce-cart-recovery' ); ?></p>
		</div>
		<?php
	}
}
