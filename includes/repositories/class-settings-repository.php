<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Settings_Repository {
	private const OPTION_KEY = 'wccr_settings';

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
					'subject'          => __( '{customer_name}, you left something in your cart', 'woocommerce-cart-recovery' ),
					'body'             => __( 'Hi {customer_name}, your cart is still available. Click the button below to complete your order: {recovery_link}', 'woocommerce-cart-recovery' ),
				),
				2 => array(
					'enabled'          => 1,
					'delay_minutes'    => 1440,
					'discount_type'    => 'percent',
					'discount_amount'  => 5,
					'min_cart_total'   => 0,
					'subject'          => __( '{customer_name}, your cart is waiting for you', 'woocommerce-cart-recovery' ),
					'body'             => __( 'Hi {customer_name}, complete your purchase here: {recovery_link}. Discount: {coupon_label}. Code: {coupon_code}', 'woocommerce-cart-recovery' ),
				),
				3 => array(
					'enabled'          => 1,
					'delay_minutes'    => 2880,
					'discount_type'    => 'percent',
					'discount_amount'  => 10,
					'min_cart_total'   => 0,
					'subject'          => __( '{customer_name}, last reminder for your cart', 'woocommerce-cart-recovery' ),
					'body'             => __( 'Hi {customer_name}, your cart can still be recovered here: {recovery_link}. Discount: {coupon_label}. Code: {coupon_code}', 'woocommerce-cart-recovery' ),
				),
			),
		);
	}

	public function get(): array {
		$settings = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
	}

	public function save( array $settings ): void {
		update_option( self::OPTION_KEY, $settings );
	}
}
