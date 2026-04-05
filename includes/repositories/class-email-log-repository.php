<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Email_Log_Repository {
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wccr_email_log';
	}

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

	public function count_sent(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE status = 'sent'" );
	}

	public function count_sent_for_cart( int $cart_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE cart_id = %d AND status = 'sent'",
				$cart_id
			)
		);
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

	public function delete_old_rows( int $days ): int {
		global $wpdb;
		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table} WHERE created_at_gmt < %s", $threshold ) );
	}
}
