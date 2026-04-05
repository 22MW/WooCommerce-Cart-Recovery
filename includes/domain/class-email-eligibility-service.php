<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Email_Eligibility_Service {
	public function __construct(
		private WCCR_Email_Log_Repository $email_log_repository
	) {}

	/**
	 * Build a single source of truth for email eligibility per cart.
	 *
	 * @param array $cart Abandoned cart row.
	 * @param array $settings Plugin settings.
	 * @return array<string, mixed>
	 */
	public function get_status( array $cart, array $settings ): array {
		$status = array(
			'current_step' => 0,
			'eligible_now' => false,
			'eligible_at_gmt' => '',
			'reason' => 'not_abandoned',
		);

		if ( 'abandoned' !== ( $cart['status'] ?? '' ) ) {
			return $status;
		}

		if ( empty( $cart['abandoned_at_gmt'] ) ) {
			$status['reason'] = 'missing_abandoned_at';
			return $status;
		}

		$sent_steps = $this->email_log_repository->get_sent_steps_for_cart( absint( $cart['id'] ) );
		$next_step  = 1;

		while ( in_array( $next_step, $sent_steps, true ) ) {
			$next_step++;
		}

		$status['current_step'] = $next_step;

		if ( $next_step > 3 ) {
			$status['reason'] = 'all_steps_sent';
			return $status;
		}

		$step_settings = $settings['steps'][ $next_step ] ?? array();
		if ( empty( $step_settings['enabled'] ) ) {
			$status['reason'] = 'step_disabled';
			return $status;
		}

		$delay_minutes = absint( $step_settings['delay_minutes'] ?? 0 );
		$eligible_at   = strtotime( (string) $cart['abandoned_at_gmt'] . ' UTC' ) + ( $delay_minutes * MINUTE_IN_SECONDS );

		if ( false === $eligible_at ) {
			$status['reason'] = 'invalid_abandoned_at';
			return $status;
		}

		$status['eligible_at_gmt'] = gmdate( 'Y-m-d H:i:s', $eligible_at );

		if ( time() < $eligible_at ) {
			$status['reason'] = 'waiting_delay';
			return $status;
		}

		$status['eligible_now'] = true;
		$status['reason']       = 'eligible_now';

		return $status;
	}

	public function get_reason_label( string $reason ): string {
		$labels = array(
			'not_abandoned'       => __( 'Not abandoned', 'vfwoo_woocommerce-cart-recovery' ),
			'missing_abandoned_at'=> __( 'Missing abandoned date', 'vfwoo_woocommerce-cart-recovery' ),
			'all_steps_sent'      => __( 'All steps sent', 'vfwoo_woocommerce-cart-recovery' ),
			'step_disabled'       => __( 'Step disabled', 'vfwoo_woocommerce-cart-recovery' ),
			'invalid_abandoned_at'=> __( 'Invalid abandoned date', 'vfwoo_woocommerce-cart-recovery' ),
			'waiting_delay'       => __( 'Waiting delay', 'vfwoo_woocommerce-cart-recovery' ),
			'eligible_now'        => __( 'Ready to send', 'vfwoo_woocommerce-cart-recovery' ),
		);

		return $labels[ $reason ] ?? $reason;
	}

	/**
	 * Convert a GMT timestamp into the local display format used in admin.
	 */
	public function format_gmt_for_display( string $datetime_gmt ): string {
		if ( '' === $datetime_gmt || '0000-00-00 00:00:00' === $datetime_gmt ) {
			return '-';
		}

		return get_date_from_gmt( $datetime_gmt, 'Y-m-d H:i:s' );
	}
}
