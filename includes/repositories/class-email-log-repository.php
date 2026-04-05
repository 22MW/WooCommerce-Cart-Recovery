<?php
defined( 'ABSPATH' ) || exit;

/**
 * Repository for email send and failure logs.
 */
final class WCCR_Email_Log_Repository {
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wccr_email_log';
	}

	/**
	 * Insert a successful send log row.
	 */
	public function insert_sent( int $cart_id, int $step, string $locale, string $subject, ?string $coupon_code ): void {
		global $wpdb;

		$wpdb->insert(
			$this->table,
			array(
				'cart_id'          => $cart_id,
				'step'             => $step,
				'locale'           => $locale,
				'subject_snapshot' => $subject,
				'coupon_code'      => $coupon_code,
				'status'           => 'sent',
				'sent_at_gmt'      => gmdate( 'Y-m-d H:i:s' ),
				'created_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Insert a failed send log row.
	 */
	public function insert_failed( int $cart_id, int $step, string $locale, string $subject, string $error_message ): void {
		global $wpdb;

		$wpdb->insert(
			$this->table,
			array(
				'cart_id'          => $cart_id,
				'step'             => $step,
				'locale'           => $locale,
				'subject_snapshot' => $subject,
				'status'           => 'failed',
				'error_message'    => $error_message,
				'created_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Count all sent emails.
	 */
	public function count_sent(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE status = 'sent'" );
	}

	/**
	 * Count sent emails for a single cart.
	 */
	public function count_sent_for_cart( int $cart_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE cart_id = %d AND status = 'sent'",
				$cart_id
			)
		);
	}

	/**
	 * Get all sent step numbers for a cart.
	 *
	 * @param int $cart_id Cart ID.
	 * @return int[]
	 */
	public function get_sent_steps_for_cart( int $cart_id ): array {
		global $wpdb;

		$steps = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT step FROM {$this->table} WHERE cart_id = %d AND status = 'sent' ORDER BY step ASC",
				$cart_id
			)
		);

		return array_map( 'absint', is_array( $steps ) ? $steps : array() );
	}

	public function get_last_error_for_cart( int $cart_id ): string {
		global $wpdb;
		$error = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT error_message FROM {$this->table} WHERE cart_id = %d AND status = 'failed' ORDER BY id DESC LIMIT 1",
				$cart_id
			)
		);

		return is_string( $error ) ? $error : '';
	}

	/**
	 * Return the most recent coupon code logged for a cart.
	 */
	public function get_last_coupon_code_for_cart( int $cart_id ): string {
		global $wpdb;

		$coupon_code = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT coupon_code FROM {$this->table} WHERE cart_id = %d AND coupon_code IS NOT NULL AND coupon_code != '' ORDER BY id DESC LIMIT 1",
				$cart_id
			)
		);

		return is_string( $coupon_code ) ? $coupon_code : '';
	}

	/**
	 * Delete all log rows for a cart.
	 */
	public function delete_for_cart( int $cart_id ): void {
		global $wpdb;

		$wpdb->delete( $this->table, array( 'cart_id' => $cart_id ), array( '%d' ) );
	}

	/**
	 * Delete log rows older than the configured threshold.
	 */
	public function delete_old_rows( int $days ): int {
		global $wpdb;
		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table} WHERE created_at_gmt < %s", $threshold ) );
	}
}
