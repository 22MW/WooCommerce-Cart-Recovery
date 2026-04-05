<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'wccr_settings' );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wccr_abandoned_carts" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wccr_email_log" );

wp_clear_scheduled_hook( 'wccr_detect_abandoned_carts' );
wp_clear_scheduled_hook( 'wccr_process_recovery_queue' );
wp_clear_scheduled_hook( 'wccr_cleanup_old_data' );
