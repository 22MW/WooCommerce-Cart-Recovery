<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

delete_option('wccr_settings');
$wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $wpdb->prefix . 'wccr_abandoned_carts'));
$wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $wpdb->prefix . 'wccr_email_log'));
$wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $wpdb->prefix . 'wccr_audit_log'));

wp_clear_scheduled_hook('wccr_detect_abandoned_carts');
wp_clear_scheduled_hook('wccr_sync_unpaid_orders');
wp_clear_scheduled_hook('wccr_process_recovery_queue');
wp_clear_scheduled_hook('wccr_cleanup_old_data');

if (function_exists('as_unschedule_all_actions')) {
	as_unschedule_all_actions('wccr_detect_abandoned_carts', array(), 'wccr');
	as_unschedule_all_actions('wccr_sync_unpaid_orders', array(), 'wccr');
	as_unschedule_all_actions('wccr_process_recovery_queue', array(), 'wccr');
	as_unschedule_all_actions('wccr_cleanup_old_data', array(), 'wccr');
}
