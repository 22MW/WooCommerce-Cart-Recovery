<?php
defined('ABSPATH') || exit;

/**
 * Capture checkout details from the classic WooCommerce checkout flow.
 */
final class WCCR_Classic_Checkout_Capture_Adapter
{
	public function __construct(
		private WCCR_Cart_Capture_Service $cart_capture_service
	) {}

	/**
	 * Register classic checkout capture hooks.
	 */
	public function register_hooks(): void
	{
		add_action('woocommerce_checkout_update_order_review', array($this, 'capture_checkout_email'));
		add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'capture_blocks_order_request'), 10, 2);
	}

	/**
	 * Capture cart updates from the classic cart page.
	 */
	public function capture_cart(): void
	{
		$this->cart_capture_service->capture_current_cart(null, null, 'classic');
	}

	/**
	 * Capture checkout contact details from serialized posted data.
	 */
	public function capture_checkout_email(string $posted_data): void
	{
		parse_str($posted_data, $data);
		$name = trim(trim((string) ($data['billing_first_name'] ?? '')) . ' ' . trim((string) ($data['billing_last_name'] ?? '')));
		$this->cart_capture_service->capture_current_cart(
			isset($data['billing_email']) ? sanitize_email((string) $data['billing_email']) : null,
			'' !== $name ? $name : null,
			'classic'
		);
	}

	/**
	 * Capture checkout contact details from Checkout Blocks order updates.
	 *
	 * @param mixed $order   Order object.
	 * @param mixed $request REST request.
	 */
	public function capture_blocks_order_request($order, $request): void
	{
		$email = null;
		$name  = null;

		if (method_exists($request, 'get_param')) {
			$billing_address = $request->get_param('billing_address');
			if (is_array($billing_address) && ! empty($billing_address['email'])) {
				$email = sanitize_email((string) $billing_address['email']);
			}
			if (is_array($billing_address)) {
				$full_name = trim(trim((string) ($billing_address['first_name'] ?? '')) . ' ' . trim((string) ($billing_address['last_name'] ?? '')));
				$name      = '' !== $full_name ? $full_name : null;
			}
		}

		$this->cart_capture_service->capture_current_cart($email ?: null, $name, 'blocks');
	}
}
