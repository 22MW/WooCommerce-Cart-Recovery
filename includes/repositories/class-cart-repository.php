<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Cart_Repository {
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wccr_abandoned_carts';
	}

	public function upsert_active_cart( string $session_key, ?int $user_id, ?string $email, ?string $customer_name, string $locale, array $cart_payload, float $cart_total, string $currency, string $source = 'classic' ): void {
		global $wpdb;

		$cart_hash   = hash( 'sha256', wp_json_encode( $cart_payload ) . '|' . $cart_total );
		$existing_id = $this->find_open_cart_id( $session_key, $email );
		$existing    = $existing_id ? $this->find_by_id( $existing_id ) : null;
		$now_gmt     = gmdate( 'Y-m-d H:i:s' );
		$data        = array(
			'session_key'       => $session_key,
			'user_id'           => $user_id,
			'email'             => $email,
			'customer_name'     => $customer_name,
			'locale'            => $locale,
			'cart_hash'         => $cart_hash,
			'cart_payload'      => wp_json_encode( $cart_payload ),
			'cart_total'        => $cart_total,
			'currency'          => $currency,
			'source'            => $source,
			'updated_at_gmt'    => $now_gmt,
		);

		if ( $existing ) {
			$current_status = (string) ( $existing['status'] ?? '' );
			$current_hash   = (string) ( $existing['cart_hash'] ?? '' );

			if ( in_array( $current_status, array( 'abandoned', 'clicked' ), true ) && $current_hash === $cart_hash ) {
				$wpdb->update( $this->table, $data, array( 'id' => absint( $existing_id ) ) );
				return;
			}

			$data['status']            = 'active';
			$data['last_activity_gmt'] = $now_gmt;
			$data['abandoned_at_gmt']  = null;
			$data['clicked_at_gmt']    = null;
			$data['recovered_at_gmt']  = null;
			$data['recovered_order_id'] = null;
			$wpdb->update( $this->table, $data, array( 'id' => absint( $existing_id ) ) );
			return;
		}

		$data['status']            = 'active';
		$data['last_activity_gmt'] = $now_gmt;
		$data['created_at_gmt'] = $now_gmt;
		$wpdb->insert( $this->table, $data );
	}

	public function mark_abandoned_older_than( int $minutes ): int {
		global $wpdb;

		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * MINUTE_IN_SECONDS ) );
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table} SET status = 'abandoned', abandoned_at_gmt = %s, updated_at_gmt = %s WHERE status = 'active' AND email IS NOT NULL AND email != '' AND last_activity_gmt < %s",
				gmdate( 'Y-m-d H:i:s' ),
				gmdate( 'Y-m-d H:i:s' ),
				$threshold
			)
		);
	}

	public function get_abandoned_ready_for_step( int $step, int $delay_minutes ): array {
		global $wpdb;

		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $delay_minutes * MINUTE_IN_SECONDS ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.* FROM {$this->table} c
				LEFT JOIN {$wpdb->prefix}wccr_email_log l ON l.cart_id = c.id AND l.step = %d AND l.status = 'sent'
				WHERE c.status = 'abandoned' AND c.abandoned_at_gmt IS NOT NULL AND c.abandoned_at_gmt <= %s AND c.email IS NOT NULL AND c.email != '' AND l.id IS NULL
				ORDER BY c.id ASC LIMIT 100",
				$step,
				$threshold
			),
			ARRAY_A
		);
	}

	public function find_by_id( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	public function mark_recovered( int $id ): void {
		global $wpdb;

		$wpdb->update(
			$this->table,
			array(
				'status'           => 'recovered',
				'recovered_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
	}

	public function mark_recovered_order( int $id, int $order_id ): void {
		global $wpdb;

		$wpdb->update(
			$this->table,
			array(
				'status'             => 'recovered',
				'recovered_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
				'recovered_order_id' => $order_id,
				'updated_at_gmt'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
	}

	public function mark_clicked( int $id ): void {
		global $wpdb;

		$wpdb->update(
			$this->table,
			array(
				'status'         => 'clicked',
				'clicked_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
	}

	public function mark_session_abandoned( string $session_key ): void {
		global $wpdb;

		$wpdb->update(
			$this->table,
			array(
				'status'           => 'abandoned',
				'abandoned_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'session_key' => $session_key )
		);
	}

	public function mark_session_recovered( string $session_key, int $order_id ): void {
		global $wpdb;

		$wpdb->update(
			$this->table,
			array(
				'status'           => 'recovered',
				'recovered_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'recovered_order_id' => $order_id,
				'updated_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'session_key' => $session_key )
		);
	}

	public function count_clicked(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE status IN ('clicked','recovered') AND clicked_at_gmt IS NOT NULL" );
	}

	public function delete_old_rows( int $days ): int {
		global $wpdb;

		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table} WHERE updated_at_gmt < %s", $threshold ) );
	}

	public function delete_rows_without_email(): int {
		global $wpdb;

		return (int) $wpdb->query( "DELETE FROM {$this->table} WHERE email IS NULL OR email = ''" );
	}

	public function list_recent( int $limit = 100 ): array {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public function list_recovery_items( int $limit = 100 ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status IN ('abandoned','clicked','recovered') ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public function get_abandoned_carts( int $limit = 200 ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = 'abandoned' AND email IS NOT NULL AND email != '' ORDER BY abandoned_at_gmt ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public function count_by_status( string $status ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE status = %s", $status ) );
	}

	public function sum_recovered_revenue(): float {
		global $wpdb;
		return (float) $wpdb->get_var( "SELECT COALESCE(SUM(cart_total),0) FROM {$this->table} WHERE status = 'recovered'" );
	}

	private function find_open_cart_id( string $session_key, ?string $email ): int {
		global $wpdb;

		$session_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE session_key = %s AND status IN ('active','abandoned','clicked') ORDER BY id DESC LIMIT 1",
				$session_key
			)
		);
		if ( $session_id ) {
			return $session_id;
		}

		if ( empty( $email ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE email = %s AND status IN ('active','abandoned','clicked') ORDER BY id DESC LIMIT 1",
				$email
			)
		);
	}
}
