<?php
defined( 'ABSPATH' ) || exit;

/**
 * Process the recovery email queue.
 */
final class WCCR_Email_Scheduler {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Email_Log_Repository $email_log_repository,
		private WCCR_Settings_Repository $settings_repository,
		private WCCR_Email_Eligibility_Service $email_eligibility_service,
		private WCCR_Coupon_Service $coupon_service,
		private WCCR_Email_Renderer $email_renderer,
		private WCCR_Recovery_Service $recovery_service
	) {}

	/**
	 * Iterate eligible abandoned carts and send the next email step.
	 */
	public function process_queue(): void {
		$settings = $this->settings_repository->get();
		$carts    = $this->cart_repository->get_abandoned_carts();

		foreach ( $carts as $cart ) {
			$eligibility = $this->email_eligibility_service->get_status( $cart, $settings );
			if ( empty( $eligibility['eligible_now'] ) ) {
				continue;
			}

			$step          = absint( $eligibility['current_step'] ?? 0 );
			$step_settings = $settings['steps'][ $step ] ?? array();
			if ( ! $step ) {
				continue;
			}

			$this->send_step_email( $cart, $step, $step_settings, absint( $settings['coupon_expiry_days'] ?? 7 ) );
		}
	}

	/**
	 * Send a single recovery email step and log the outcome.
	 *
	 * @param array<string, mixed> $cart          Cart row.
	 * @param array<string, mixed> $step_settings Step settings.
	 */
	private function send_step_email( array $cart, int $step, array $step_settings, int $coupon_expiry_days ): void {
		$email = array(
			'subject' => sanitize_text_field( (string) ( $step_settings['subject'] ?? '' ) ),
			'message' => '',
		);

		try {
			$coupon_code  = $this->coupon_service->maybe_generate_coupon( $cart, $step_settings, $coupon_expiry_days );
			$recovery_url = $this->recovery_service->build_recovery_url( absint( $cart['id'] ), $coupon_code );
			$email        = $this->email_renderer->render( $cart, $step_settings, $recovery_url, $coupon_code );
			$subject      = sanitize_text_field( (string) ( $email['subject'] ?? '' ) );
			$message      = (string) ( $email['message'] ?? '' );
			$headers      = array( 'Content-Type: text/html; charset=UTF-8' );

			do_action( 'wccr_before_recovery_email_send', $cart, $step, $subject );

			if ( wp_mail( sanitize_email( (string) $cart['email'] ), $subject, $message, $headers ) ) {
				$this->email_log_repository->insert_sent( absint( $cart['id'] ), $step, (string) $cart['locale'], $subject, $coupon_code );
				do_action( 'wccr_after_recovery_email_send', $cart, $step, $subject );
				return;
			}

			$this->email_log_repository->insert_failed( absint( $cart['id'] ), $step, (string) $cart['locale'], $subject, 'wp_mail failed' );
		} catch ( Throwable $throwable ) {
			$this->email_log_repository->insert_failed(
				absint( $cart['id'] ),
				$step,
				(string) $cart['locale'],
				sanitize_text_field( (string) ( $email['subject'] ?? $step_settings['subject'] ?? '' ) ),
				$throwable->getMessage()
			);
		}
	}
}
