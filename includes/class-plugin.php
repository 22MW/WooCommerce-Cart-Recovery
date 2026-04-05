<?php
defined( 'ABSPATH' ) || exit;

/**
 * Main plugin composition root.
 */
final class WCCR_Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Bootstrap services, hooks and scheduled events.
	 */
	public function init(): void {
		WCCR_Installer::create_tables();

		$settings_repository  = new WCCR_Settings_Repository();
		$cart_repository      = new WCCR_Cart_Repository();
		$email_log_repository = new WCCR_Email_Log_Repository();
		$locale_resolver      = new WCCR_Locale_Resolver_Manager();
		$email_eligibility    = new WCCR_Email_Eligibility_Service( $email_log_repository );
		$cart_capture_service = new WCCR_Cart_Capture_Service( $cart_repository, $locale_resolver );
		$recovery_service     = new WCCR_Recovery_Service( $cart_repository );
		$pending_detector     = new WCCR_Pending_Order_Detector( $cart_repository, $locale_resolver, $settings_repository );
		$coupon_service       = new WCCR_Coupon_Service();
		$email_renderer       = new WCCR_Email_Renderer( $coupon_service );
		$email_scheduler      = new WCCR_Email_Scheduler( $cart_repository, $email_log_repository, $settings_repository, $email_eligibility, $coupon_service, $email_renderer, $recovery_service );
		$detector             = new WCCR_Abandoned_Cart_Detector( $cart_repository, $settings_repository );
		$cleanup_service      = new WCCR_Cleanup_Service( $cart_repository, $email_log_repository, $settings_repository );
		$stats_service        = new WCCR_Stats_Service( $cart_repository, $email_log_repository );

		$checkout = new WCCR_Checkout_Capture_Coordinator(
			new WCCR_Classic_Checkout_Capture_Adapter( $cart_capture_service ),
			new WCCR_Blocks_Checkout_Capture_Adapter( $cart_capture_service )
		);
		$checkout->register_hooks();

		$settings_page = new WCCR_Settings_Page( $settings_repository, $detector, $email_scheduler );
		$settings_page->register_hooks();

		$admin = new WCCR_Admin_Menu(
			$settings_page,
			new WCCR_Abandoned_Carts_Page( $cart_repository, $email_log_repository, $settings_repository, $email_eligibility, $stats_service ),
			new WCCR_Stats_Page( $stats_service )
		);
		$admin->register_hooks();

		$recovery_service->init();
		$pending_detector->register_hooks();

		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
		$this->ensure_cron_events();

		add_action( 'wccr_detect_abandoned_carts', array( $detector, 'run' ) );
		add_action( 'wccr_detect_abandoned_carts', array( $pending_detector, 'sync_stale_pending_orders' ) );
		add_action( 'wccr_process_recovery_queue', array( $email_scheduler, 'process_queue' ) );
		add_action( 'wccr_cleanup_old_data', array( $cleanup_service, 'run' ) );
	}

	/**
	 * Register custom cron schedules.
	 *
	 * @param array<string, array<string, int|string>> $schedules Existing schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public function register_cron_schedules( array $schedules ): array {
		$schedules['wccr_every_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'vfwoo_woocommerce-cart-recovery' ),
		);

		return $schedules;
	}

	/**
	 * Ensure plugin cron events are scheduled with the expected frequency.
	 */
	private function ensure_cron_events(): void {
		$detect_event = wp_get_scheduled_event( 'wccr_detect_abandoned_carts' );
		if ( ! $detect_event || 'wccr_every_minute' !== $detect_event->schedule ) {
			wp_clear_scheduled_hook( 'wccr_detect_abandoned_carts' );
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'wccr_every_minute', 'wccr_detect_abandoned_carts' );
		}

		$queue_event = wp_get_scheduled_event( 'wccr_process_recovery_queue' );
		if ( ! $queue_event || 'wccr_every_minute' !== $queue_event->schedule ) {
			wp_clear_scheduled_hook( 'wccr_process_recovery_queue' );
			wp_schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), 'wccr_every_minute', 'wccr_process_recovery_queue' );
		}

		if ( ! wp_next_scheduled( 'wccr_cleanup_old_data' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wccr_cleanup_old_data' );
		}
	}
}
