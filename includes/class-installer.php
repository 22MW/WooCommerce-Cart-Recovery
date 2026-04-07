<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Installer {
	public static function activate(): void {
		self::create_tables();
		WCCR_Action_Scheduler::ensure_recurring_actions();
	}

	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$carts_table     = $wpdb->prefix . 'wccr_abandoned_carts';
		$emails_table    = $wpdb->prefix . 'wccr_email_log';

		dbDelta(
			"CREATE TABLE {$carts_table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				session_key VARCHAR(191) NOT NULL,
				user_id BIGINT UNSIGNED NULL,
				email VARCHAR(190) NULL,
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
				created_at_gmt DATETIME NOT NULL,
				updated_at_gmt DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY session_key (session_key),
				KEY status_activity (status, last_activity_gmt),
				KEY email (email),
				KEY linked_order_id (linked_order_id)
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

		add_option( 'wccr_settings', WCCR_Settings_Repository::default_settings() );

		$wpdb->query( "UPDATE {$carts_table} SET status = 'clicked', recovered_at_gmt = NULL WHERE status = 'recovered' AND (recovered_order_id IS NULL OR recovered_order_id = 0)" );
	}
}
