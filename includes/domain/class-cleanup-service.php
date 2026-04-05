<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cleanup routines for old and inconsistent plugin data.
 */
final class WCCR_Cleanup_Service {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Email_Log_Repository $email_log_repository,
		private WCCR_Settings_Repository $settings_repository
	) {}

	/**
	 * Run the scheduled cleanup process.
	 */
	public function run(): void {
		$settings = $this->settings_repository->get();
		$days     = absint( $settings['cleanup_days'] ?? 90 );

		$this->cart_repository->delete_rows_without_email();
		$this->cart_repository->delete_historical_duplicates();
		$this->cart_repository->delete_old_rows( $days );
		$this->email_log_repository->delete_old_rows( $days );

		do_action( 'wccr_cleanup_completed', $days );
	}
}
