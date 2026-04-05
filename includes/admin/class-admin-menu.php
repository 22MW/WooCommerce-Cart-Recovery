<?php
defined( 'ABSPATH' ) || exit;

/**
 * Register plugin admin screens and shared assets.
 */
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

	/**
	 * Register plugin menu pages.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Cart Recovery', 'vfwoo_woocommerce-cart-recovery' ),
			__( 'Cart Recovery', 'vfwoo_woocommerce-cart-recovery' ),
			'manage_woocommerce',
			'wccr-cart-recovery',
			array( $this->carts_page, 'render' ),
			'dashicons-cart'
		);

		add_submenu_page( 'wccr-cart-recovery', __( 'Cart Recovery', 'vfwoo_woocommerce-cart-recovery' ), __( 'Cart Recovery', 'vfwoo_woocommerce-cart-recovery' ), 'manage_woocommerce', 'wccr-cart-recovery', array( $this->carts_page, 'render' ) );
		add_submenu_page( 'wccr-cart-recovery', __( 'Settings', 'vfwoo_woocommerce-cart-recovery' ), __( 'Settings', 'vfwoo_woocommerce-cart-recovery' ), 'manage_woocommerce', 'wccr-settings', array( $this->settings_page, 'render' ) );
	}

	/**
	 * Enqueue admin assets only on plugin pages.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'wccr' ) ) {
			return;
		}

		wp_enqueue_style( 'wccr-admin', WCCR_PLUGIN_URL . 'assets/css/admin.css', array(), WCCR_VERSION );
		wp_enqueue_script( 'wccr-admin', WCCR_PLUGIN_URL . 'assets/js/admin.js', array(), WCCR_VERSION, true );
		wp_localize_script(
			'wccr-admin',
			'WCCRAdminI18n',
			array(
				'copyLabel'       => __( 'Copy URL', 'vfwoo_woocommerce-cart-recovery' ),
				'copiedLabel'     => __( 'Copied', 'vfwoo_woocommerce-cart-recovery' ),
				'deleteConfirm'   => __( 'Delete this recovery item?', 'vfwoo_woocommerce-cart-recovery' ),
			)
		);
	}
}
