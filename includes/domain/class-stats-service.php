<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Stats_Service {
	public function __construct(
		private WCCR_Cart_Repository $cart_repository,
		private WCCR_Email_Log_Repository $email_log_repository
	) {}

	public function get_stats(): array {
		$abandoned = $this->cart_repository->count_by_status( 'abandoned' );
		$recovered = $this->cart_repository->count_by_status( 'recovered' );
		$total     = $abandoned + $recovered;

		return array(
			'abandoned'      => $abandoned,
			'clicked'        => $this->cart_repository->count_clicked(),
			'recovered'      => $recovered,
			'recovery_rate'  => $total > 0 ? round( ( $recovered / $total ) * 100, 2 ) : 0,
			'revenue'        => $this->cart_repository->sum_recovered_revenue(),
			'emails_sent'    => $this->email_log_repository->count_sent(),
		);
	}
}
