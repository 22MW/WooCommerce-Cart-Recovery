<?php
defined('ABSPATH') || exit;

/**
 * Restore saved carts from secure recovery URLs.
 */
final class WCCR_Recovery_Service
{
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Email_Log_Repository $email_log_repository,
		private WCCR_Audit_Logger $audit_logger
	) {}

	/**
	 * Register public recovery hooks.
	 */
	public function init(): void
	{
		add_action('template_redirect', array($this, 'maybe_restore_cart'));
		add_filter('woocommerce_checkout_get_value', array($this, 'prefill_checkout_email'), 10, 2);
	}

	/**
	 * Build the public recovery URL for a cart and optional coupon.
	 */
	public function build_recovery_url(int $cart_id, ?string $coupon_code = null, int $step = 0): string
	{
		$expires = time() + (30 * DAY_IN_SECONDS);
		$token   = wp_hash($cart_id . '|' . $expires . '|' . wp_salt('auth'));
		$args    = array(
			'wccr_recover' => $cart_id,
			'wccr_token'   => $token,
			'wccr_expires' => $expires,
		);

		if ($coupon_code) {
			$args['wccr_coupon'] = $coupon_code;
		}

		if ($step > 0) {
			$args['wccr_step'] = $step;
		}

		return apply_filters(
			'wccr_recovery_url',
			add_query_arg(
				$args,
				wc_get_cart_url()
			),
			$cart_id,
			$coupon_code,
			$step
		);
	}

	/**
	 * Restore the cart when a valid recovery link is visited.
	 */
	public function maybe_restore_cart(): void
	{
		$cart_id = isset($_GET['wccr_recover']) ? absint(wp_unslash($_GET['wccr_recover'])) : 0;
		$token   = isset($_GET['wccr_token']) ? sanitize_text_field(wp_unslash($_GET['wccr_token'])) : '';
		$expires = isset($_GET['wccr_expires']) ? absint(wp_unslash($_GET['wccr_expires'])) : 0;
		$coupon  = isset($_GET['wccr_coupon']) ? sanitize_text_field(wp_unslash($_GET['wccr_coupon'])) : '';
		$step    = isset($_GET['wccr_step']) ? absint(wp_unslash($_GET['wccr_step'])) : 0;

		if (! $cart_id || ! $token || ! $expires) {
			return;
		}

		if (time() > $expires) {
			return;
		}

		if (! hash_equals(wp_hash($cart_id . '|' . $expires . '|' . wp_salt('auth')), $token)) {
			return;
		}

		$cart = $this->cart_repository->find_by_id($cart_id);
		if (! $cart || empty($cart['cart_payload']) || ! function_exists('WC') || ! WC()->cart) {
			return;
		}

		if (isset($cart['status']) && 'recovered' === $cart['status']) {
			return;
		}

		if (! defined('WCCR_IS_RESTORING_CART')) {
			define('WCCR_IS_RESTORING_CART', true);
		}

		if (WC()->session) {
			WC()->session->set('wccr_recovered_cart_id', $cart_id);
		}

		$payload = json_decode((string) $cart['cart_payload'], true);
		if (! is_array($payload)) {
			return;
		}

		WC()->cart->empty_cart();
		foreach ($payload as $item) {
			WC()->cart->add_to_cart(
				absint($item['product_id'] ?? 0),
				max(1, absint($item['quantity'] ?? 1)),
				absint($item['variation_id'] ?? 0),
				array(),
				is_array($item['cart_item'] ?? null) ? $item['cart_item'] : array()
			);
		}

		if ($coupon && ! WC()->cart->has_discount($coupon)) {
			WC()->cart->apply_coupon($coupon);
		}

		// Store cart email in session so checkout can pre-fill billing_email.
		if (! empty($cart['email']) && WC()->session) {
			WC()->session->set('wccr_prefill_email', sanitize_email((string) $cart['email']));
		}

		// Persist session to DB before redirect so guest/incognito users retain the cart.
		$this->persist_session();

		$this->cart_repository->mark_clicked($cart_id, $step);
		if ($step > 0) {
			$this->email_log_repository->mark_step_clicked($cart_id, $step);
		}
		$this->audit_logger->log('recovery_clicked', 'cart', $cart_id, ['step' => $step]);
		do_action('wccr_cart_recovery_clicked', $cart_id);
		wp_safe_redirect(wc_get_checkout_url());
		exit;
	}

	/**
	 * Pre-fill billing_email in checkout with the email stored in session.
	 *
	 * Only fills when the field has no value (guest / incognito checkout).
	 *
	 * @param mixed  $value Field value.
	 * @param string $input Field key.
	 * @return mixed
	 */
	public function prefill_checkout_email(mixed $value, string $input): mixed
	{
		if ('billing_email' !== $input || ! empty($value)) {
			return $value;
		}

		if (! WC()->session) {
			return $value;
		}

		$email = sanitize_email((string) WC()->session->get('wccr_prefill_email', ''));
		return '' !== $email ? $email : $value;
	}

	/**
	 * Force WooCommerce to write the current session to the database
	 * and set the session cookie so it survives the redirect to checkout.
	 */
	private function persist_session(): void
	{
		if (! WC()->session) {
			return;
		}

		WC()->session->set_customer_session_cookie(true);
		WC()->cart->set_session();
		WC()->session->save_data();
	}
}
