<?php
defined( 'ABSPATH' ) || exit;

/**
 * Repository for abandoned cart persistence and recovery queries.
 */
final class WCCR_Cart_Repository {
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wccr_abandoned_carts';
	}

	/**
	 * Create or update the currently open cart for the session/customer.
	 *
	 * @param string      $session_key    WooCommerce session key.
	 * @param int|null    $user_id        Current user ID.
	 * @param string|null $email          Captured email.
	 * @param string|null $customer_name  Captured customer name.
	 * @param string      $locale         Resolved locale.
	 * @param array       $cart_payload   Serialized cart items.
	 * @param float       $cart_total     Cart total.
	 * @param string      $currency       Cart currency.
	 * @param string      $source         Capture source.
	 */
	public function upsert_active_cart( string $session_key, ?int $user_id, ?string $email, ?string $customer_name, string $locale, array $cart_payload, float $cart_total, string $currency, string $source = 'classic' ): void {
		$cart_hash   = $this->build_cart_hash( $cart_payload, $cart_total );
		$existing_id = $this->find_open_cart_id( $session_key, $email );
		$existing    = $existing_id ? $this->find_by_id( $existing_id ) : null;
		$now_gmt     = gmdate( 'Y-m-d H:i:s' );
		$data        = $this->build_cart_row_data( $session_key, $user_id, $email, $customer_name, $locale, $cart_payload, $cart_total, $currency, $source, 'cart', null, false, $cart_hash, $now_gmt );

		if ( $existing ) {
			$this->update_existing_open_cart( absint( $existing_id ), $existing, $data, $cart_hash, $now_gmt, false );
			return;
		}

		$this->insert_new_active_cart( $data, $now_gmt );
	}

	/**
	 * Create or update a recovery row backed by a pending or failed order.
	 *
	 * @param int         $order_id       WooCommerce order ID.
	 * @param int|null    $user_id        Customer user ID.
	 * @param string|null $email          Billing email.
	 * @param string|null $customer_name  Billing name.
	 * @param string      $locale         Resolved locale.
	 * @param array       $cart_payload   Order line snapshot.
	 * @param float       $cart_total     Order total.
	 * @param string      $currency       Order currency.
	 */
	public function upsert_unpaid_order( int $order_id, ?int $user_id, ?string $email, ?string $customer_name, string $locale, array $cart_payload, float $cart_total, string $currency ): string {
		$cart_hash   = $this->build_cart_hash( $cart_payload, $cart_total );
		$existing_id = $this->find_merge_candidate_for_order( $order_id, $email, $cart_hash, $cart_total );
		$existing    = $existing_id ? $this->find_by_id( $existing_id ) : null;
		$now_gmt     = gmdate( 'Y-m-d H:i:s' );
		$data        = $this->build_cart_row_data( 'order_' . $order_id, $user_id, $email, $customer_name, $locale, $cart_payload, $cart_total, $currency, 'order_pending', 'order', $order_id, (bool) $existing, $cart_hash, $now_gmt );

		if ( $existing ) {
			$this->update_existing_open_cart( $existing_id, $existing, $data, $cart_hash, $now_gmt, true );
			return ! empty( $existing['linked_order_id'] ) ? 'updated' : 'merged';
		}

		$this->insert_new_abandoned_order_cart( $data, $now_gmt );
		return 'imported';
	}

	/**
	 * Mark stale active carts as abandoned.
	 */
	public function mark_abandoned_older_than( int $minutes ): int {
		global $wpdb;

		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * MINUTE_IN_SECONDS ) );
		$now_gmt   = gmdate( 'Y-m-d H:i:s' );

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table} SET status = 'abandoned', abandoned_at_gmt = %s, updated_at_gmt = %s WHERE status = 'active' AND email IS NOT NULL AND email != '' AND last_activity_gmt < %s",
				$now_gmt,
				$now_gmt,
				$threshold
			)
		);
	}

	/**
	 * Return abandoned carts ready for a concrete step.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_abandoned_ready_for_step( int $step, int $delay_minutes ): array {
		global $wpdb;

		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $delay_minutes * MINUTE_IN_SECONDS ) );

		$rows = $wpdb->get_results(
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

		return $this->hydrate_rows_from_linked_orders( $rows );
	}

	/**
	 * Find a cart row by ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_by_id( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $this->hydrate_row_from_linked_order( $row ) : null;
	}

	/**
	 * Mark a cart as recovered without order linkage.
	 */
	public function mark_recovered( int $id ): void {
		$this->update_status(
			array(
				'status'           => 'recovered',
				'recovered_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark a cart as recovered and attach the resulting order.
	 */
	public function mark_recovered_order( int $id, int $order_id ): void {
		$this->update_status(
			array(
				'status'             => 'recovered',
				'recovered_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
				'recovered_order_id' => $order_id,
				'updated_at_gmt'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark a cart as clicked after recovery URL usage.
	 */
	public function mark_clicked( int $id, int $step = 0 ): void {
		$data = array(
			'status'         => 'clicked',
			'clicked_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'updated_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
		);

		if ( $step > 0 ) {
			$data['clicked_step'] = $step;
		}

		$this->update_status( $data, array( 'id' => $id ) );
	}

	/**
	 * Mark all carts in a session as abandoned.
	 */
	public function mark_session_abandoned( string $session_key ): void {
		$this->update_status(
			array(
				'status'           => 'abandoned',
				'abandoned_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'session_key' => $session_key )
		);
	}

	/**
	 * Mark carts in a session as recovered.
	 */
	public function mark_session_recovered( string $session_key, int $order_id ): void {
		$this->update_status(
			array(
				'status'             => 'recovered',
				'recovered_at_gmt'   => gmdate( 'Y-m-d H:i:s' ),
				'recovered_order_id' => $order_id,
				'updated_at_gmt'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'session_key' => $session_key )
		);
	}

	/**
	 * Count clicked carts, including recovered carts that were clicked first.
	 */
	public function count_clicked(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE status IN ('clicked','recovered') AND clicked_at_gmt IS NOT NULL" );
	}

	/**
	 * Delete rows older than the retention threshold.
	 */
	public function delete_old_rows( int $days ): int {
		global $wpdb;

		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table} WHERE updated_at_gmt < %s", $threshold ) );
	}

	/**
	 * Return recovery rows older than the retention threshold.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_rows_older_than( int $days ): array {
		global $wpdb;

		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$rows      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE updated_at_gmt < %s ORDER BY id ASC",
				$threshold
			),
			ARRAY_A
		);

		return $this->hydrate_rows_from_linked_orders( $rows );
	}

	/**
	 * Delete rows with missing email.
	 */
	public function delete_rows_without_email(): int {
		global $wpdb;

		return (int) $wpdb->query( "DELETE FROM {$this->table} WHERE email IS NULL OR email = ''" );
	}

	/**
	 * Return recent recovery items for admin listing.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_recovery_items( string $sort = 'recent', int $limit = 100 ): array {
		global $wpdb;

		$order_by = $this->get_list_order_by( $sort );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status IN ('abandoned','clicked','recovered') ORDER BY {$order_by} LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $this->hydrate_rows_from_linked_orders( $rows );
	}

	/**
	 * Return abandoned carts for queue processing.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_abandoned_carts( int $limit = 200 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = 'abandoned' AND email IS NOT NULL AND email != '' ORDER BY abandoned_at_gmt ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $this->hydrate_rows_from_linked_orders( $rows );
	}

	/**
	 * Count carts by status.
	 */
	public function count_by_status( string $status ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE status = %s", $status ) );
	}

	/**
	 * Sum recovered revenue.
	 */
	public function sum_recovered_revenue(): float {
		global $wpdb;

		return (float) $wpdb->get_var( "SELECT COALESCE(SUM(cart_total),0) FROM {$this->table} WHERE status = 'recovered'" );
	}

	/**
	 * Delete a single cart row by ID.
	 */
	public function delete_by_id( int $id ): void {
		global $wpdb;

		$wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Remove historical duplicates while preserving the best row per cart/email.
	 */
	public function delete_historical_duplicates(): int {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT id, email, cart_hash, status, recovered_order_id, updated_at_gmt
			FROM {$this->table}
			WHERE status IN ('abandoned','clicked','recovered')
			AND email IS NOT NULL AND email != ''
			AND cart_hash IS NOT NULL AND cart_hash != ''
			ORDER BY email ASC, cart_hash ASC, id DESC",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $this->group_rows_for_deduplication( $rows ) as $items ) {
			$deleted += $this->delete_group_duplicates( $items );
		}

		return $deleted;
	}

	/**
	 * Build normalized row data for cart persistence.
	 *
	 * @return array<string, mixed>
	 */
	private function build_cart_row_data( string $session_key, ?int $user_id, ?string $email, ?string $customer_name, string $locale, array $cart_payload, float $cart_total, string $currency, string $source, string $primary_source, ?int $linked_order_id, bool $is_merged, string $cart_hash, string $now_gmt ): array {
		return array(
			'session_key'    => $session_key,
			'user_id'        => $user_id,
			'email'          => $email,
			'customer_name'  => $customer_name,
			'locale'         => $locale,
			'cart_hash'      => $cart_hash,
			'cart_payload'   => wp_json_encode( $cart_payload ),
			'cart_total'     => $cart_total,
			'currency'       => $currency,
			'source'         => $source,
			'primary_source' => $primary_source,
			'linked_order_id' => $linked_order_id,
			'is_merged'      => $is_merged ? 1 : 0,
			'updated_at_gmt' => $now_gmt,
		);
	}

	/**
	 * Update an existing open cart.
	 *
	 * @param array<string, mixed> $existing Existing database row.
	 * @param array<string, mixed> $data     Replacement values.
	 */
	private function update_existing_open_cart( int $existing_id, array $existing, array $data, string $cart_hash, string $now_gmt, bool $from_order ): void {
		global $wpdb;

		$current_status = (string) ( $existing['status'] ?? '' );
		$current_hash   = (string) ( $existing['cart_hash'] ?? '' );
		$data           = $this->merge_existing_identity( $existing, $data, $from_order );

		if ( in_array( $current_status, array( 'abandoned', 'clicked' ), true ) && $current_hash === $cart_hash ) {
			$wpdb->update( $this->table, $data, array( 'id' => $existing_id ) );
			return;
		}

		if ( $from_order ) {
			$this->prepare_order_backed_update( $data, $existing, $now_gmt );
		} else {
			$this->prepare_cart_backed_update( $data, $existing, $now_gmt );
		}

		$wpdb->update( $this->table, $data, array( 'id' => $existing_id ) );
	}

	/**
	 * Insert a brand-new active cart row.
	 *
	 * @param array<string, mixed> $data Row values.
	 */
	private function insert_new_active_cart( array $data, string $now_gmt ): void {
		global $wpdb;

		$data['status']            = 'active';
		$data['last_activity_gmt'] = $now_gmt;
		$data['created_at_gmt']    = $now_gmt;

		$wpdb->insert( $this->table, $data );
	}

	/**
	 * Insert a brand-new row already backed by an abandoned unpaid order.
	 *
	 * @param array<string, mixed> $data Row values.
	 */
	private function insert_new_abandoned_order_cart( array $data, string $now_gmt ): void {
		global $wpdb;

		$data['status']            = 'abandoned';
		$data['last_activity_gmt'] = $now_gmt;
		$data['abandoned_at_gmt']  = $now_gmt;
		$data['created_at_gmt']    = $now_gmt;

		$wpdb->insert( $this->table, $data );
	}

	/**
	 * Centralize status updates.
	 *
	 * @param array<string, mixed> $data  Update data.
	 * @param array<string, mixed> $where Update condition.
	 */
	private function update_status( array $data, array $where ): void {
		global $wpdb;

		$wpdb->update( $this->table, $data, $where );
	}

	/**
	 * Return the SQL ORDER BY clause for the admin listing.
	 */
	private function get_list_order_by( string $sort ): string {
		$allowed = array(
			'recent'    => 'id DESC',
			'oldest'    => 'id ASC',
			'abandoned' => 'abandoned_at_gmt DESC, id DESC',
			'email'     => 'email ASC, id DESC',
			'status'    => "FIELD(status, 'abandoned', 'clicked', 'recovered') ASC, id DESC",
			'purchased' => 'recovered_order_id DESC, id DESC',
		);

		return $allowed[ $sort ] ?? $allowed['recent'];
	}

	/**
	 * Group duplicate candidate rows by email and cart hash.
	 *
	 * @param array<int, array<string, mixed>> $rows Candidate rows.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function group_rows_for_deduplication( array $rows ): array {
		$grouped = array();

		foreach ( $rows as $row ) {
			$key = strtolower( (string) $row['email'] ) . '|' . (string) $row['cart_hash'];
			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array();
			}

			$grouped[ $key ][] = $row;
		}

		return $grouped;
	}

	/**
	 * Delete duplicate rows within a grouped set.
	 *
	 * @param array<int, array<string, mixed>> $items Grouped rows.
	 */
	private function delete_group_duplicates( array $items ): int {
		if ( count( $items ) < 2 ) {
			return 0;
		}

		usort(
			$items,
			function ( array $left, array $right ): int {
				$priority_left  = $this->get_status_priority( (string) $left['status'], absint( $left['recovered_order_id'] ?? 0 ) );
				$priority_right = $this->get_status_priority( (string) $right['status'], absint( $right['recovered_order_id'] ?? 0 ) );

				if ( $priority_left !== $priority_right ) {
					return $priority_right <=> $priority_left;
				}

				return strcmp( (string) $right['updated_at_gmt'], (string) $left['updated_at_gmt'] );
			}
		);

		array_shift( $items );

		$deleted = 0;
		foreach ( $items as $item ) {
			$this->delete_by_id( absint( $item['id'] ) );
			++$deleted;
		}

		return $deleted;
	}

	/**
	 * Find an open cart by session first and email second.
	 */
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
				"SELECT id FROM {$this->table}
				WHERE email = %s
				AND status IN ('active','abandoned','clicked')
				AND ( linked_order_id IS NULL OR linked_order_id = 0 )
				AND updated_at_gmt >= %s
				ORDER BY id DESC LIMIT 1",
				$email,
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			)
		);
	}

	/**
	 * Find a matching open row for a pending or failed order.
	 */
	private function find_merge_candidate_for_order( int $order_id, ?string $email, string $cart_hash, float $cart_total ): int {
		$linked_id = $this->find_linked_order_cart_id( $order_id );
		if ( $linked_id ) {
			return $linked_id;
		}

		return $this->find_email_order_candidate_id( $email, $cart_hash, $cart_total );
	}

	/**
	 * Return a row already linked to the provided order ID.
	 */
	private function find_linked_order_cart_id( int $order_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE linked_order_id = %d AND status IN ('active','abandoned','clicked') ORDER BY id DESC LIMIT 1",
				$order_id
			)
		);
	}

	/**
	 * Find the best open cart candidate for an unpaid order using email and cart similarity.
	 */
	private function find_email_order_candidate_id( ?string $email, string $cart_hash, float $cart_total ): int {
		$candidates = $this->get_recent_email_candidates( $email );
		foreach ( $candidates as $candidate ) {
			if ( $this->is_order_merge_candidate( $candidate, $cart_hash, $cart_total ) ) {
				return absint( $candidate['id'] );
			}
		}

		return 0;
	}

	/**
	 * Load recent open rows for an email.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_recent_email_candidates( ?string $email ): array {
		global $wpdb;

		if ( empty( $email ) ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, cart_hash, cart_total, updated_at_gmt FROM {$this->table}
				WHERE email = %s AND status IN ('active','abandoned','clicked') AND updated_at_gmt >= %s
				ORDER BY id DESC LIMIT 5",
				$email,
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			),
			ARRAY_A
		);
	}

	/**
	 * Decide whether an open cart should merge into an unpaid order row.
	 *
	 * @param array<string, mixed> $candidate Candidate row.
	 */
	private function is_order_merge_candidate( array $candidate, string $cart_hash, float $cart_total ): bool {
		if ( (string) ( $candidate['cart_hash'] ?? '' ) === $cart_hash ) {
			return true;
		}

		return abs( (float) ( $candidate['cart_total'] ?? 0 ) - $cart_total ) < 0.01;
	}

	/**
	 * Calculate a stable cart hash from payload and total.
	 */
	private function build_cart_hash( array $cart_payload, float $cart_total ): string {
		$normalized_items = array();

		foreach ( $cart_payload as $item ) {
			$normalized_items[] = array(
				'product_id'   => absint( $item['product_id'] ?? 0 ),
				'variation_id' => absint( $item['variation_id'] ?? 0 ),
				'quantity'     => absint( $item['quantity'] ?? 0 ),
			);
		}

		return hash( 'sha256', wp_json_encode( $normalized_items ) . '|' . wc_format_decimal( $cart_total, 4 ) );
	}

	/**
	 * Preserve order linkage and merge metadata across updates.
	 *
	 * @param array<string, mixed> $existing Existing row.
	 * @param array<string, mixed> $data     New row data.
	 * @return array<string, mixed>
	 */
	private function merge_existing_identity( array $existing, array $data, bool $from_order ): array {
		$existing_order_id = absint( $existing['linked_order_id'] ?? 0 );

		if ( $existing_order_id && empty( $data['linked_order_id'] ) ) {
			$data['linked_order_id'] = $existing_order_id;
		}

		if ( $existing_order_id && ! $from_order ) {
			$data['primary_source'] = 'order';
		}

		if ( ! empty( $existing['is_merged'] ) || ( $from_order && ! empty( $existing['id'] ) ) ) {
			$data['is_merged'] = 1;
		}

		return $data;
	}

	/**
	 * Prepare an updated row that is now primarily backed by an unpaid order.
	 *
	 * @param array<string, mixed> $data     Update data.
	 * @param array<string, mixed> $existing Existing row.
	 */
	private function prepare_order_backed_update( array &$data, array $existing, string $now_gmt ): void {
		$data['status']           = 'clicked' === ( $existing['status'] ?? '' ) ? 'clicked' : 'abandoned';
		$data['last_activity_gmt'] = $now_gmt;
		$data['abandoned_at_gmt'] = $existing['abandoned_at_gmt'] ?: $now_gmt;
		$data['clicked_at_gmt']   = $existing['clicked_at_gmt'] ?? null;
		$data['recovered_at_gmt'] = $existing['recovered_at_gmt'] ?? null;
		$data['recovered_order_id'] = $existing['recovered_order_id'] ?? null;
	}

	/**
	 * Prepare an updated row that remains primarily backed by the live cart session.
	 *
	 * @param array<string, mixed> $data     Update data.
	 * @param array<string, mixed> $existing Existing row.
	 */
	private function prepare_cart_backed_update( array &$data, array $existing, string $now_gmt ): void {
		$data['status']            = 'active';
		$data['last_activity_gmt'] = $now_gmt;
		$data['abandoned_at_gmt']  = null;
		$data['clicked_at_gmt']    = null;
		$data['recovered_at_gmt']  = null;
		$data['recovered_order_id'] = null;

		if ( ! empty( $existing['linked_order_id'] ) ) {
			$data['primary_source'] = 'order';
			$data['linked_order_id'] = absint( $existing['linked_order_id'] );
		}
	}

	/**
	 * Return status priority for duplicate resolution.
	 */
	private function get_status_priority( string $status, int $order_id ): int {
		if ( 'recovered' === $status && $order_id > 0 ) {
			return 3;
		}

		if ( 'clicked' === $status ) {
			return 2;
		}

		if ( 'abandoned' === $status ) {
			return 1;
		}

		return 0;
	}

	/**
	 * Refresh row data from linked WooCommerce orders when available.
	 *
	 * @param array<int, array<string, mixed>> $rows Recovery rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function hydrate_rows_from_linked_orders( array $rows ): array {
		foreach ( $rows as $index => $row ) {
			$rows[ $index ] = $this->hydrate_row_from_linked_order( $row );
		}

		return $rows;
	}

	/**
	 * Prefer linked order data once a recovery row is backed by an order.
	 *
	 * @param array<string, mixed> $row Recovery row.
	 * @return array<string, mixed>
	 */
	private function hydrate_row_from_linked_order( array $row ): array {
		$order_id = absint( $row['linked_order_id'] ?? 0 );
		if ( ! $order_id ) {
			return $row;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return $row;
		}

		$row['primary_source'] = 'order';
		$row['email']          = $order->get_billing_email() ?: ( $row['email'] ?? '' );
		$row['customer_name']  = $this->get_order_customer_name( $order, (string) ( $row['customer_name'] ?? '' ) );
		$row['cart_total']     = (float) $order->get_total( 'edit' );
		$row['currency']       = $order->get_currency() ?: ( $row['currency'] ?? '' );
		$row['cart_payload']   = wp_json_encode( $this->get_order_payload( $order ) );
		$row['cart_hash']      = $this->build_cart_hash( $this->get_order_payload( $order ), (float) $row['cart_total'] );

		return $row;
	}

	/**
	 * Build a normalized customer name from a linked order.
	 */
	private function get_order_customer_name( WC_Order $order, string $fallback ): string {
		$name = trim( trim( (string) $order->get_billing_first_name() ) . ' ' . trim( (string) $order->get_billing_last_name() ) );
		return '' !== $name ? $name : $fallback;
	}

	/**
	 * Build a normalized payload from a linked order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_order_payload( WC_Order $order ): array {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'product_id'   => absint( $item->get_product_id() ),
				'variation_id' => absint( $item->get_variation_id() ),
				'quantity'     => absint( $item->get_quantity() ),
				'cart_item'    => array(),
			);
		}

		return $items;
	}
}
