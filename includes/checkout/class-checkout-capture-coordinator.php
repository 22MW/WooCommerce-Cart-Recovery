<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Checkout_Capture_Coordinator {
	public function __construct(
		private WCCR_Classic_Checkout_Capture_Adapter $classic_adapter,
		private WCCR_Blocks_Checkout_Capture_Adapter $blocks_adapter
	) {}

	public function register_hooks(): void {
		$this->classic_adapter->register_hooks();
		$this->blocks_adapter->register_hooks();
	}
}
