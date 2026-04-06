<?php
defined( 'ABSPATH' ) || exit;

/**
 * Store archived recovery statistics outside operational cart rows.
 */
final class WCCR_Stats_Repository {
	private const OPTION_KEY = 'wccr_archived_stats';

	/**
	 * Return archived counters.
	 *
	 * @return array<string, float|int>
	 */
	public function get(): array {
		$defaults = array(
			'abandoned'   => 0,
			'clicked'     => 0,
			'recovered'   => 0,
			'revenue'     => 0.0,
			'emails_sent' => 0,
		);

		$stats = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stats ) ) {
			return $defaults;
		}

		return wp_parse_args( $stats, $defaults );
	}

	/**
	 * Archive metrics for a deleted operational recovery row.
	 *
	 * @param array<string, mixed> $cart Recovery row.
	 */
	public function archive_cart_metrics( array $cart, int $sent_emails ): void {
		$stats = $this->get();
		$status = (string) ( $cart['status'] ?? '' );

		if ( 'abandoned' === $status ) {
			$stats['abandoned'] = (int) $stats['abandoned'] + 1;
		}

		if ( ! empty( $cart['clicked_at_gmt'] ) ) {
			$stats['clicked'] = (int) $stats['clicked'] + 1;
		}

		if ( 'recovered' === $status ) {
			$stats['recovered'] = (int) $stats['recovered'] + 1;
			$stats['revenue']   = (float) $stats['revenue'] + (float) ( $cart['cart_total'] ?? 0 );
		}

		$stats['emails_sent'] = (int) $stats['emails_sent'] + $sent_emails;

		update_option( self::OPTION_KEY, $stats, false );
	}
}
