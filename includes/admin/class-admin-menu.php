<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Admin_Menu {
	public function __construct(
		private WCCR_Settings_Page $settings_page,
		private WCCR_Abandoned_Carts_Page $carts_page,
		private WCCR_Stats_Page $stats_page
	) {}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Cart Recovery', 'woocommerce-cart-recovery' ),
			__( 'Cart Recovery', 'woocommerce-cart-recovery' ),
			'manage_woocommerce',
			'wccr-cart-recovery',
			array( $this->stats_page, 'render' ),
			'dashicons-cart'
		);

		add_submenu_page( 'wccr-cart-recovery', __( 'Statistics', 'woocommerce-cart-recovery' ), __( 'Statistics', 'woocommerce-cart-recovery' ), 'manage_woocommerce', 'wccr-cart-recovery', array( $this->stats_page, 'render' ) );
		add_submenu_page( 'wccr-cart-recovery', __( 'Abandoned Carts', 'woocommerce-cart-recovery' ), __( 'Abandoned Carts', 'woocommerce-cart-recovery' ), 'manage_woocommerce', 'wccr-carts', array( $this->carts_page, 'render' ) );
		add_submenu_page( 'wccr-cart-recovery', __( 'Settings', 'woocommerce-cart-recovery' ), __( 'Settings', 'woocommerce-cart-recovery' ), 'manage_woocommerce', 'wccr-settings', array( $this->settings_page, 'render' ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'wccr' ) ) {
			return;
		}

		wp_enqueue_style( 'wccr-admin', WCCR_PLUGIN_URL . 'assets/css/admin.css', array(), WCCR_VERSION );
	}
}
