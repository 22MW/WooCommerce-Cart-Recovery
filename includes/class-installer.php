<?php
defined('ABSPATH') || exit;

final class WCCR_Installer
{
	/**
	 * Run activation tasks: create tables and schedule recurring actions.
	 */
	public static function activate(): void
	{
		self::create_tables();
		WCCR_Action_Scheduler::ensure_recurring_actions();
	}

	/**
	 * Create or update all plugin database tables.
	 */
	public static function create_tables(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$carts_table     = $wpdb->prefix . 'wccr_abandoned_carts';
		$emails_table    = $wpdb->prefix . 'wccr_email_log';

		// Drop legacy `email` index before dbDelta — TEXT columns cannot be indexed without a key length.
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $carts_table)) === $carts_table) {
			$existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$carts_table}", ARRAY_A);
			if (is_array($existing_indexes)) {
				foreach ($existing_indexes as $idx) {
					if ('email' === ($idx['Key_name'] ?? '')) {
						$wpdb->query("ALTER TABLE {$carts_table} DROP INDEX `email`");
						break;
					}
				}
			}
		}

		dbDelta(
			"CREATE TABLE {$carts_table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				session_key VARCHAR(191) NOT NULL,
				user_id BIGINT UNSIGNED NULL,
				email TEXT NULL,
				email_hash VARCHAR(64) NOT NULL DEFAULT '',
				customer_name VARCHAR(190) NULL,
				locale VARCHAR(20) NOT NULL DEFAULT '',
				cart_hash VARCHAR(64) NOT NULL DEFAULT '',
				cart_payload LONGTEXT NULL,
				cart_total DECIMAL(18,4) NOT NULL DEFAULT 0,
				currency VARCHAR(10) NOT NULL DEFAULT '',
				status VARCHAR(20) NOT NULL DEFAULT 'active',
				source VARCHAR(20) NOT NULL DEFAULT 'classic',
				primary_source VARCHAR(20) NOT NULL DEFAULT 'cart',
				linked_order_id BIGINT UNSIGNED NULL,
				is_merged TINYINT(1) NOT NULL DEFAULT 0,
				last_activity_gmt DATETIME NOT NULL,
				abandoned_at_gmt DATETIME NULL,
				clicked_at_gmt DATETIME NULL,
				clicked_step TINYINT UNSIGNED NULL,
				recovered_at_gmt DATETIME NULL,
				recovered_order_id BIGINT UNSIGNED NULL,
				recovered_total DECIMAL(18,4) NULL,
				created_at_gmt DATETIME NOT NULL,
				updated_at_gmt DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY session_key (session_key),
				KEY status_activity (status, last_activity_gmt),
				KEY email_hash (email_hash),
				KEY linked_order_id (linked_order_id)
			) {$charset_collate};"
		);

		$audit_table = $wpdb->prefix . 'wccr_audit_log';

		dbDelta(
			"CREATE TABLE {$audit_table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				action VARCHAR(50) NOT NULL,
				object_type VARCHAR(50) NOT NULL,
				object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				ip_address VARCHAR(45) NOT NULL DEFAULT '',
				extra TEXT NULL,
				created_at_gmt DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY action_object (action, object_type, object_id),
				KEY created_at_gmt (created_at_gmt)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$emails_table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				cart_id BIGINT UNSIGNED NOT NULL,
				step TINYINT UNSIGNED NOT NULL,
				locale VARCHAR(20) NOT NULL DEFAULT '',
				subject_snapshot TEXT NULL,
				coupon_code VARCHAR(100) NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'queued',
				sent_at_gmt DATETIME NULL,
				clicked_at_gmt DATETIME NULL,
				error_message TEXT NULL,
				created_at_gmt DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY cart_step (cart_id, step),
				KEY status_created (status, created_at_gmt)
			) {$charset_collate};"
		);

		add_option('wccr_settings', WCCR_Settings_Repository::default_settings());
                add_option('wccr_first_activated_at', gmdate('Y-m-d H:i:s'));
		$wpdb->query($wpdb->prepare("UPDATE {$carts_table} SET status = %s, recovered_at_gmt = NULL WHERE status = %s AND (recovered_order_id IS NULL OR recovered_order_id = 0)", 'clicked', 'recovered'));

		WCCR_Cart_Repository::migrate_encrypt_pii();
	}
}
