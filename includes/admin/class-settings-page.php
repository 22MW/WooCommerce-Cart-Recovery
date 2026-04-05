<?php
defined( 'ABSPATH' ) || exit;

/**
 * Render and save plugin settings.
 */
final class WCCR_Settings_Page {
	public function __construct(
		private WCCR_Settings_Repository $settings_repository,
		private WCCR_Abandoned_Cart_Detector $detector,
		private WCCR_Email_Scheduler $email_scheduler
	) {}

	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_init', array( $this, 'maybe_run_now' ) );
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

		foreach ( array( 1, 2, 3 ) as $step ) {
			$settings['steps'][ $step ] = array(
				'enabled'         => ! empty( $_POST['steps'][ $step ]['enabled'] ) ? 1 : 0,
				'delay_minutes'   => absint( $_POST['steps'][ $step ]['delay_minutes'] ?? 60 ),
				'discount_type'   => sanitize_text_field( wp_unslash( $_POST['steps'][ $step ]['discount_type'] ?? 'none' ) ),
				'discount_amount' => (float) ( $_POST['steps'][ $step ]['discount_amount'] ?? 0 ),
				'min_cart_total'  => (float) ( $_POST['steps'][ $step ]['min_cart_total'] ?? 0 ),
				'subject'         => sanitize_text_field( wp_unslash( $_POST['steps'][ $step ]['subject'] ?? '' ) ),
				'body'            => wp_kses_post( wp_unslash( $_POST['steps'][ $step ]['body'] ?? '' ) ),
			);
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
		$this->email_scheduler->process_queue();

		add_settings_error( 'wccr_settings', 'wccr_run_done', __( 'Recovery detector and email queue executed manually.', 'vfwoo_woocommerce-cart-recovery' ), 'updated' );
	}

	/**
	 * Render the settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'vfwoo_woocommerce-cart-recovery' ) );
		}

		$settings = $this->settings_repository->get();
		settings_errors( 'wccr_settings' );
		?>
		<div class="wrap wccr-admin">
			<h1><?php esc_html_e( 'Cart Recovery Settings', 'vfwoo_woocommerce-cart-recovery' ); ?></h1>
			<form method="post" class="wccr-run-now-form">
				<?php wp_nonce_field( 'wccr_run_now', 'wccr_run_now_nonce' ); ?>
				<?php submit_button( __( 'Run now', 'vfwoo_woocommerce-cart-recovery' ), 'secondary', 'wccr_run_now', false ); ?>
				<p class="description"><?php esc_html_e( 'Runs abandoned-cart detection and recovery email queue immediately.', 'vfwoo_woocommerce-cart-recovery' ); ?></p>
			</form>
			<form method="post">
				<?php wp_nonce_field( 'wccr_save_settings', 'wccr_settings_nonce' ); ?>
				<table class="form-table">
					<tr><th><label for="abandon_after_minutes"><?php esc_html_e( 'Mark cart abandoned after minutes', 'vfwoo_woocommerce-cart-recovery' ); ?></label></th><td><input type="number" name="abandon_after_minutes" id="abandon_after_minutes" value="<?php echo esc_attr( $settings['abandon_after_minutes'] ); ?>" min="1"></td></tr>
					<tr><th><label for="cleanup_days"><?php esc_html_e( 'Cleanup data after days', 'vfwoo_woocommerce-cart-recovery' ); ?></label></th><td><input type="number" name="cleanup_days" id="cleanup_days" value="<?php echo esc_attr( $settings['cleanup_days'] ); ?>" min="1"></td></tr>
					<tr><th><label for="coupon_expiry_days"><?php esc_html_e( 'Coupon expiry days', 'vfwoo_woocommerce-cart-recovery' ); ?></label></th><td><input type="number" name="coupon_expiry_days" id="coupon_expiry_days" value="<?php echo esc_attr( $settings['coupon_expiry_days'] ); ?>" min="1"></td></tr>
				</table>

				<?php foreach ( array( 1, 2, 3 ) as $step ) : $step_settings = $settings['steps'][ $step ] ?? array(); ?>
					<div class="wccr-card">
						<h2><?php echo esc_html( sprintf( __( 'Email step %d', 'vfwoo_woocommerce-cart-recovery' ), $step ) ); ?></h2>
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
						<p><label><?php esc_html_e( 'Subject', 'vfwoo_woocommerce-cart-recovery' ); ?><br><input type="text" class="large-text" name="steps[<?php echo esc_attr( $step ); ?>][subject]" value="<?php echo esc_attr( $step_settings['subject'] ?? '' ); ?>"></label></p>
						<p><label><?php esc_html_e( 'Body', 'vfwoo_woocommerce-cart-recovery' ); ?><br><textarea class="large-text" rows="6" name="steps[<?php echo esc_attr( $step ); ?>][body]"><?php echo esc_textarea( $step_settings['body'] ?? '' ); ?></textarea></label></p>
						<p class="description"><?php esc_html_e( 'Available variables: {recovery_link}, {coupon_code}, {coupon_label}, {cart_total}, {site_name}, {customer_name}', 'vfwoo_woocommerce-cart-recovery' ); ?></p>
					</div>
				<?php endforeach; ?>

				<?php submit_button( __( 'Save settings', 'vfwoo_woocommerce-cart-recovery' ) ); ?>
			</form>
		</div>
		<?php
	}
}
