<?php
defined( 'ABSPATH' ) || exit;

/**
 * Resolve product and taxonomy exclusions for recovery capture/import flows.
 */
final class WCCR_Exclusion_Service {
	public function __construct(
		private WCCR_Settings_Repository $settings_repository
	) {}

	/**
	 * Return whether one cart or order payload should be excluded.
	 *
	 * @param array<int, array<string, mixed>> $items Normalized cart/order items.
	 */
	public function payload_is_excluded( array $items ): bool {
		$product_ids = $this->get_payload_product_ids( $items );
		if ( empty( $product_ids ) ) {
			return false;
		}

		$settings = $this->settings_repository->get();

		return $this->matches_excluded_products( $product_ids, $settings )
			|| $this->matches_excluded_terms( $product_ids, $settings );
	}

	/**
	 * Collect all relevant product IDs from one payload.
	 *
	 * @param array<int, array<string, mixed>> $items Normalized cart/order items.
	 * @return array<int, int>
	 */
	private function get_payload_product_ids( array $items ): array {
		$product_ids = array();

		foreach ( $items as $item ) {
			foreach ( array( 'product_id', 'variation_id' ) as $key ) {
				$product_id = absint( $item[ $key ] ?? 0 );
				if ( $product_id > 0 ) {
					$product_ids[] = $product_id;
				}
			}
		}

		return array_values( array_unique( $product_ids ) );
	}

	/**
	 * Check if any product ID is directly excluded.
	 *
	 * @param array<int, int>        $product_ids Product IDs from payload.
	 * @param array<string, mixed>   $settings    Plugin settings.
	 */
	private function matches_excluded_products( array $product_ids, array $settings ): bool {
		$excluded_ids = array_map( 'absint', $settings['excluded_product_ids'] ?? array() );
		if ( empty( $excluded_ids ) ) {
			return false;
		}

		return ! empty( array_intersect( $product_ids, $excluded_ids ) );
	}

	/**
	 * Check if any product belongs to an excluded product term.
	 *
	 * @param array<int, int>      $product_ids Product IDs from payload.
	 * @param array<string, mixed> $settings    Plugin settings.
	 */
	private function matches_excluded_terms( array $product_ids, array $settings ): bool {
		$excluded_term_ids = array_map( 'absint', $settings['excluded_term_ids'] ?? array() );
		if ( empty( $excluded_term_ids ) ) {
			return false;
		}

		$term_ids = wp_get_object_terms(
			$product_ids,
			$this->get_supported_taxonomies(),
			array( 'fields' => 'ids' )
		);
		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return false;
		}

		return ! empty( array_intersect( array_map( 'absint', $term_ids ), $excluded_term_ids ) );
	}

	/**
	 * Return the product taxonomies that are valid for exclusion checks.
	 *
	 * @return array<int, string>
	 */
	private function get_supported_taxonomies(): array {
		$taxonomies = get_object_taxonomies( 'product', 'names' );
		return array_values( array_diff( $taxonomies, array( 'product_type', 'product_visibility' ) ) );
	}
}
