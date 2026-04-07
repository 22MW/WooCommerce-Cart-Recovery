<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_WPML_Locale_Resolver implements WCCR_Locale_Resolver_Interface {
	public function resolve_locale( ?int $user_id = null ): string {
		$language_code = apply_filters( 'wpml_current_language', null );
		if ( ! is_string( $language_code ) || '' === $language_code ) {
			return ( new WCCR_Default_Locale_Resolver() )->resolve_locale( $user_id );
		}

		$active_languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		$language_locale  = $this->get_language_locale_from_active_languages( $active_languages, $language_code );
		if ( '' !== $language_locale ) {
			return $language_locale;
		}

		$sitepress_locale = $this->get_language_locale_from_sitepress( $language_code );
		if ( '' !== $sitepress_locale ) {
			return $sitepress_locale;
		}

		return ( new WCCR_Default_Locale_Resolver() )->resolve_locale( $user_id );
	}

	/**
	 * Resolve the WP locale from WPML active languages payload.
	 *
	 * @param mixed $active_languages WPML payload.
	 */
	private function get_language_locale_from_active_languages( $active_languages, string $language_code ): string {
		if ( ! is_array( $active_languages ) || ! isset( $active_languages[ $language_code ] ) || ! is_array( $active_languages[ $language_code ] ) ) {
			return '';
		}

		$language = $active_languages[ $language_code ];
		$locale   = (string) ( $language['default_locale'] ?? $language['locale'] ?? '' );
		return '' !== $locale ? sanitize_text_field( $locale ) : '';
	}

	/**
	 * Resolve the WP locale from SitePress when available.
	 */
	private function get_language_locale_from_sitepress( string $language_code ): string {
		global $sitepress;

		if ( ! isset( $sitepress ) || ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_locale' ) ) {
			return '';
		}

		$locale = $sitepress->get_locale( $language_code );
		return is_string( $locale ) && '' !== $locale ? sanitize_text_field( $locale ) : '';
	}
}
