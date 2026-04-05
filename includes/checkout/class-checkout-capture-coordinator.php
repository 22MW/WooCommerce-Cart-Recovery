<?php
defined( 'ABSPATH' ) || exit;

/**
 * Register checkout capture hooks for both classic and block checkouts.
 */
final class WCCR_Checkout_Capture_Coordinator {
	public function __construct(
		private WCCR_Classic_Checkout_Capture_Adapter $classic_adapter,
		private WCCR_Blocks_Checkout_Capture_Adapter $blocks_adapter
	) {}

	/**
	 * Register all checkout capture hooks.
	 */
	public function register_hooks(): void {
		$this->classic_adapter->register_hooks();
		$this->blocks_adapter->register_hooks();
	}
}
