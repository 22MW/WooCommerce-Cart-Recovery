<?php
defined('ABSPATH') || exit;

/**
 * Validate runtime requirements before bootstrapping the plugin.
 */
final class WCCR_Requirements
{
	/**
	 * Check whether required plugins and PHP version are available.
	 */
	public static function is_ready(): bool
	{
		return class_exists('WooCommerce')
			&& defined('WC_VERSION')
			&& version_compare(WC_VERSION, '9.0', '>=')
			&& version_compare(PHP_VERSION, '8.1', '>=');
	}

	/**
	 * Render the admin notice for unmet requirements.
	 */
	public static function render_notice(): void
	{
		if (! current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__('WooCommerce Cart Recovery requires WooCommerce 9.0+ active and PHP 8.1 or higher.', 'vfwoo_woocommerce-cart-recovery') . '</p></div>';
	}
}
