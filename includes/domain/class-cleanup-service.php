<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cleanup routines for old and inconsistent plugin data.
 */
final class WCCR_Cleanup_Service {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Email_Log_Repository $email_log_repository,
		private WCCR_Stats_Repository $stats_repository,
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
		$this->delete_old_recovery_rows( $days );
		$this->email_log_repository->delete_old_failed_rows( $days );

		do_action( 'wccr_cleanup_completed', $days );
	}

	/**
	 * Archive and delete expired operational rows.
	 */
	private function delete_old_recovery_rows( int $days ): void {
		foreach ( $this->cart_repository->get_rows_older_than( $days ) as $cart ) {
			$this->stats_repository->archive_cart_metrics( $cart, $this->email_log_repository->count_sent_for_cart( absint( $cart['id'] ) ) );
			$this->delete_plugin_owned_order( absint( $cart['linked_order_id'] ?? 0 ) );
			$this->email_log_repository->delete_for_cart( absint( $cart['id'] ) );
			$this->cart_repository->delete_by_id( absint( $cart['id'] ) );
		}
	}

	/**
	 * Delete a linked WooCommerce order only if it belongs to this plugin and is still unpaid.
	 */
	private function delete_plugin_owned_order( int $order_id ): void {
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order || ! in_array( $order->get_status(), array( 'pending', 'failed' ), true ) ) {
			return;
		}

		if ( 1 !== absint( $order->get_meta( '_wccr_managed_unpaid_order', true ) ) ) {
			return;
		}

		$order->delete( true );
	}
}
