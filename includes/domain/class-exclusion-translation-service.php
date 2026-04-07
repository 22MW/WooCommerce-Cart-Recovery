<?php
defined( 'ABSPATH' ) || exit;

/**
 * Expand excluded products and terms across active translations.
 */
final class WCCR_Exclusion_Translation_Service {
	/**
	 * Expand product IDs to every known translation.
	 *
	 * @param array<int, int> $product_ids Selected product IDs.
	 * @return array<int, int>
	 */
	public function expand_product_ids( array $product_ids ): array {
		$expanded = array();

		foreach ( array_map( 'absint', $product_ids ) as $product_id ) {
			if ( $product_id < 1 ) {
				continue;
			}

			$expanded = array_merge( $expanded, $this->get_related_product_ids( $product_id ) );
		}

		return $this->normalize_ids( $expanded );
	}

	/**
	 * Expand term IDs to every known translation.
	 *
	 * @param array<int, int> $term_ids Selected term IDs.
	 * @return array<int, int>
	 */
	public function expand_term_ids( array $term_ids ): array {
		$expanded = array();

		foreach ( array_map( 'absint', $term_ids ) as $term_id ) {
			if ( $term_id < 1 ) {
				continue;
			}

			$expanded = array_merge( $expanded, $this->get_related_term_ids( $term_id ) );
		}

		return $this->normalize_ids( $expanded );
	}

	/**
	 * Return the translation group for one product.
	 *
	 * @return array<int, int>
	 */
	public function get_related_product_ids( int $product_id ): array {
		$ids = array( $product_id );

		if ( has_filter( 'wpml_object_id' ) ) {
			$ids = array_merge( $ids, $this->get_wpml_product_ids( $product_id ) );
		}

		if ( function_exists( 'pll_get_post_translations' ) ) {
			$ids = array_merge( $ids, array_values( pll_get_post_translations( $product_id ) ) );
		}

		return $this->normalize_ids( $ids );
	}

	/**
	 * Return the translation group for one term.
	 *
	 * @return array<int, int>
	 */
	public function get_related_term_ids( int $term_id ): array {
		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return array( $term_id );
		}

		$ids = array( $term_id );

		if ( has_filter( 'wpml_object_id' ) ) {
			$ids = array_merge( $ids, $this->get_wpml_term_ids( $term ) );
		}

		if ( function_exists( 'pll_get_term_translations' ) ) {
			$ids = array_merge( $ids, array_values( pll_get_term_translations( $term_id ) ) );
		}

		return $this->normalize_ids( $ids );
	}

	/**
	 * Return active language codes for WPML or Polylang.
	 *
	 * @return array<int, string>
	 */
	private function get_language_codes(): array {
		if ( has_filter( 'wpml_active_languages' ) ) {
			$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
			if ( is_array( $languages ) ) {
				return array_values( array_filter( array_map( 'strval', array_keys( $languages ) ) ) );
			}
		}

		if ( function_exists( 'pll_languages_list' ) ) {
			$languages = pll_languages_list();
			if ( is_array( $languages ) ) {
				return array_values( array_filter( array_map( 'strval', $languages ) ) );
			}
		}

		return array();
	}

	/**
	 * Resolve WPML translations for one product.
	 *
	 * @return array<int, int>
	 */
	private function get_wpml_product_ids( int $product_id ): array {
		$ids = array();

		foreach ( $this->get_language_codes() as $language_code ) {
			$translated_id = apply_filters( 'wpml_object_id', $product_id, 'product', false, $language_code );
			if ( $translated_id ) {
				$ids[] = absint( $translated_id );
			}
		}

		return $ids;
	}

	/**
	 * Resolve WPML translations for one term.
	 *
	 * @return array<int, int>
	 */
	private function get_wpml_term_ids( WP_Term $term ): array {
		$ids = array();

		foreach ( $this->get_language_codes() as $language_code ) {
			$translated_id = apply_filters( 'wpml_object_id', $term->term_id, $term->taxonomy, false, $language_code );
			if ( $translated_id ) {
				$ids[] = absint( $translated_id );
			}
		}

		return $ids;
	}

	/**
	 * Normalize one list of IDs.
	 *
	 * @param array<int, int> $ids Raw IDs.
	 * @return array<int, int>
	 */
	private function normalize_ids( array $ids ): array {
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}
}
