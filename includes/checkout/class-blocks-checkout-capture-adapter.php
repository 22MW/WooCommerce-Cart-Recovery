<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Blocks_Checkout_Capture_Adapter {
	public function __construct(
		private WCCR_Cart_Capture_Service $cart_capture_service
	) {}

	public function register_hooks(): void {
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'capture_from_store_api' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_capture_script' ) );
		add_action( 'wp_ajax_wccr_capture_checkout_contact', array( $this, 'ajax_capture_checkout_contact' ) );
		add_action( 'wp_ajax_nopriv_wccr_capture_checkout_contact', array( $this, 'ajax_capture_checkout_contact' ) );
	}

	public function capture_from_store_api( $customer, $request ): void {
		$email = '';
		$name  = null;
		if ( method_exists( $request, 'get_param' ) ) {
			$billing = $request->get_param( 'billing_address' );
			if ( is_array( $billing ) && ! empty( $billing['email'] ) ) {
				$email = sanitize_email( (string) $billing['email'] );
			}
			if ( is_array( $billing ) ) {
				$full_name = trim( trim( (string) ( $billing['first_name'] ?? '' ) ) . ' ' . trim( (string) ( $billing['last_name'] ?? '' ) ) );
				$name      = '' !== $full_name ? $full_name : null;
			}
		}

		$this->cart_capture_service->capture_current_cart( $email ?: null, $name, 'blocks' );
	}

	public function enqueue_capture_script(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'wccr-blocks-capture',
			WCCR_PLUGIN_URL . 'assets/js/blocks-checkout-capture.js',
			array(),
			WCCR_VERSION,
			true
		);

		wp_localize_script(
			'wccr-blocks-capture',
			'WCCRCheckoutCapture',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wccr_capture_checkout_contact' ),
			)
		);
	}

	public function ajax_capture_checkout_contact(): void {
		check_ajax_referer( 'wccr_capture_checkout_contact', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		$this->cart_capture_service->capture_current_cart(
			'' !== $email ? $email : null,
			'' !== $name ? $name : null,
			'blocks'
		);

		wp_send_json_success();
	}
}
