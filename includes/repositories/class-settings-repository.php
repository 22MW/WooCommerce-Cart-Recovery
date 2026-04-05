<?php
defined( 'ABSPATH' ) || exit;

/**
 * Repository for persisted plugin settings.
 */
final class WCCR_Settings_Repository {
	private const OPTION_KEY = 'wccr_settings';

	/**
	 * Return default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'abandon_after_minutes' => 60,
			'cleanup_days'          => 90,
			'coupon_expiry_days'    => 7,
			'from_name'             => get_bloginfo( 'name' ),
			'steps'                 => array(
				1 => array(
					'enabled'          => 1,
					'delay_minutes'    => 60,
					'discount_type'    => 'none',
					'discount_amount'  => 0,
					'min_cart_total'   => 0,
					'subject'          => __( '{customer_name}, you left something in your cart', 'vfwoo_woocommerce-cart-recovery' ),
					'body'             => __( 'Hi {customer_name}, your cart is still available. Click the button below to complete your order: {recovery_link}', 'vfwoo_woocommerce-cart-recovery' ),
				),
				2 => array(
					'enabled'          => 1,
					'delay_minutes'    => 1440,
					'discount_type'    => 'percent',
					'discount_amount'  => 5,
					'min_cart_total'   => 0,
					'subject'          => __( '{customer_name}, your cart is waiting for you', 'vfwoo_woocommerce-cart-recovery' ),
					'body'             => __( 'Hi {customer_name}, complete your purchase here: {recovery_link}. Discount: {coupon_label}. Code: {coupon_code}', 'vfwoo_woocommerce-cart-recovery' ),
				),
				3 => array(
					'enabled'          => 1,
					'delay_minutes'    => 2880,
					'discount_type'    => 'percent',
					'discount_amount'  => 10,
					'min_cart_total'   => 0,
					'subject'          => __( '{customer_name}, last reminder for your cart', 'vfwoo_woocommerce-cart-recovery' ),
					'body'             => __( 'Hi {customer_name}, your cart can still be recovered here: {recovery_link}. Discount: {coupon_label}. Code: {coupon_code}', 'vfwoo_woocommerce-cart-recovery' ),
				),
			),
		);
	}

	/**
	 * Get merged plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$settings = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
	}

	/**
	 * Save plugin settings.
	 *
	 * @param array<string, mixed> $settings Settings payload.
	 */
	public function save( array $settings ): void {
		update_option( self::OPTION_KEY, $settings );
	}
}
