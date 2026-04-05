<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Abandoned_Cart_Detector {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Settings_Repository $settings_repository
	) {}

	public function run(): void {
		$settings = $this->settings_repository->get();
		$updated  = $this->cart_repository->mark_abandoned_older_than( absint( $settings['abandon_after_minutes'] ?? 60 ) );

		if ( $updated > 0 ) {
			do_action( 'wccr_cart_marked_abandoned', $updated );
		}
	}
}
