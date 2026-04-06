<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Locale_Resolver_Manager {
	private WCCR_Locale_Resolver_Interface $resolver;

	public function __construct() {
		if ( has_filter( 'wpml_current_language' ) ) {
			$this->resolver = new WCCR_WPML_Locale_Resolver();
			return;
		}

		if ( function_exists( 'pll_current_language' ) ) {
			$this->resolver = new WCCR_Polylang_Locale_Resolver();
			return;
		}

		$this->resolver = new WCCR_Default_Locale_Resolver();
	}

	public function resolve_locale( ?int $user_id = null ): string {
		return $this->resolver->resolve_locale( $user_id );
	}

	/**
	 * Return the default locale used as fallback for translated settings.
	 */
	public function get_default_locale(): string {
		return get_locale();
	}

	/**
	 * Return active locales available in the current multilingual setup.
	 *
	 * @return array<int, array{locale:string,label:string}>
	 */
	public function get_available_locales(): array {
		$locales = $this->get_wpml_locales();
		if ( ! empty( $locales ) ) {
			return $locales;
		}

		$locales = $this->get_polylang_locales();
		if ( ! empty( $locales ) ) {
			return $locales;
		}

		return array( $this->build_locale_item( $this->get_default_locale(), $this->get_default_locale() ) );
	}

	/**
	 * Return active locales from WPML when available.
	 *
	 * @return array<int, array{locale:string,label:string}>
	 */
	private function get_wpml_locales(): array {
		$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		if ( ! is_array( $languages ) ) {
			return array();
		}

		$items = array();
		foreach ( $languages as $language_code => $language ) {
			$locale = apply_filters( 'wpml_locale', null, $language_code );
			if ( ! is_string( $locale ) || '' === $locale ) {
				continue;
			}

			$label   = is_array( $language ) ? (string) ( $language['translated_name'] ?? $language['native_name'] ?? $locale ) : $locale;
			$items[] = $this->build_locale_item( $locale, $label );
		}

		return $items;
	}

	/**
	 * Return active locales from Polylang when available.
	 *
	 * @return array<int, array{locale:string,label:string}>
	 */
	private function get_polylang_locales(): array {
		if ( ! function_exists( 'pll_languages_list' ) ) {
			return array();
		}

		$locales = pll_languages_list( array( 'fields' => 'locale' ) );
		$labels  = pll_languages_list( array( 'fields' => 'name' ) );
		if ( ! is_array( $locales ) || empty( $locales ) ) {
			return array();
		}

		$items = array();
		foreach ( array_values( $locales ) as $index => $locale ) {
			if ( ! is_string( $locale ) || '' === $locale ) {
				continue;
			}

			$label   = isset( $labels[ $index ] ) && is_string( $labels[ $index ] ) ? $labels[ $index ] : $locale;
			$items[] = $this->build_locale_item( $locale, $label );
		}

		return $items;
	}

	/**
	 * Normalize one locale item for the settings UI.
	 *
	 * @return array{locale:string,label:string}
	 */
	private function build_locale_item( string $locale, string $label ): array {
		return array(
			'locale' => sanitize_text_field( $locale ),
			'label'  => sanitize_text_field( $label ),
		);
	}
}
