<?php
defined( 'ABSPATH' ) || exit;

/**
 * Register and maintain recurring plugin actions in WooCommerce Action Scheduler.
 */
final class WCCR_Action_Scheduler {
	private const GROUP = 'wccr';

	/**
	 * Register all recurring actions used by the plugin.
	 */
	public static function ensure_recurring_actions(): void {
		self::clear_legacy_wp_cron();

		if ( ! self::is_available() ) {
			return;
		}

		self::schedule_recurring_action( 'wccr_detect_abandoned_carts', MINUTE_IN_SECONDS, MINUTE_IN_SECONDS );
		self::schedule_recurring_action( 'wccr_sync_unpaid_orders', MINUTE_IN_SECONDS, MINUTE_IN_SECONDS );
		self::schedule_recurring_action( 'wccr_process_recovery_queue', MINUTE_IN_SECONDS, 2 * MINUTE_IN_SECONDS );
		self::schedule_recurring_action( 'wccr_cleanup_old_data', DAY_IN_SECONDS, HOUR_IN_SECONDS );
	}

	/**
	 * Remove recurring actions owned by the plugin.
	 */
	public static function unschedule_all_actions(): void {
		self::clear_legacy_wp_cron();

		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( 'wccr_detect_abandoned_carts', array(), self::GROUP );
		as_unschedule_all_actions( 'wccr_sync_unpaid_orders', array(), self::GROUP );
		as_unschedule_all_actions( 'wccr_process_recovery_queue', array(), self::GROUP );
		as_unschedule_all_actions( 'wccr_cleanup_old_data', array(), self::GROUP );
	}

	/**
	 * Schedule one recurring action if it does not already exist.
	 */
	private static function schedule_recurring_action( string $hook, int $interval, int $offset ): void {
		if ( self::has_scheduled_action( $hook ) ) {
			return;
		}

		as_schedule_recurring_action( time() + $offset, $interval, $hook, array(), self::GROUP, true );
	}

	/**
	 * Check whether an action already exists for the given hook.
	 */
	private static function has_scheduled_action( string $hook ): bool {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			return (bool) as_has_scheduled_action( $hook, array(), self::GROUP );
		}

		if ( function_exists( 'as_next_scheduled_action' ) ) {
			return false !== as_next_scheduled_action( $hook, array(), self::GROUP );
		}

		return false;
	}

	/**
	 * Clear the old WordPress cron hooks replaced by Action Scheduler.
	 */
	private static function clear_legacy_wp_cron(): void {
		wp_clear_scheduled_hook( 'wccr_detect_abandoned_carts' );
		wp_clear_scheduled_hook( 'wccr_sync_unpaid_orders' );
		wp_clear_scheduled_hook( 'wccr_process_recovery_queue' );
		wp_clear_scheduled_hook( 'wccr_cleanup_old_data' );
	}

	/**
	 * Check whether WooCommerce Action Scheduler is available.
	 */
	private static function is_available(): bool {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_unschedule_all_actions' );
	}
}
