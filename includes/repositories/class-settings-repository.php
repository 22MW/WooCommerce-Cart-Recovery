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
					'subject'          => __( 'You left something in your cart', 'woocommerce-cart-recovery' ),
					'body'             => __( 'Hi, your cart is still available. Click the button below to complete your order: {recovery_link}', 'woocommerce-cart-recovery' ),
				),
				2 => array(
					'enabled'          => 1,
					'delay_minutes'    => 1440,
					'discount_type'    => 'percent',
					'discount_amount'  => 5,
					'min_cart_total'   => 0,
					'subject'          => __( 'Your cart is waiting for you', 'woocommerce-cart-recovery' ),
					'body'             => __( 'Complete your purchase here: {recovery_link}. Coupon: {coupon_code}', 'woocommerce-cart-recovery' ),
				),
				3 => array(
					'enabled'          => 1,
					'delay_minutes'    => 2880,
					'discount_type'    => 'percent',
					'discount_amount'  => 10,
					'min_cart_total'   => 0,
					'subject'          => __( 'Last reminder for your cart', 'woocommerce-cart-recovery' ),
					'body'             => __( 'Your cart can still be recovered here: {recovery_link}. Coupon: {coupon_code}', 'woocommerce-cart-recovery' ),
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
