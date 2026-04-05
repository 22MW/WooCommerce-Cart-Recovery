<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_WPML_Locale_Resolver implements WCCR_Locale_Resolver_Interface {
	public function resolve_locale( ?int $user_id = null ): string {
		$locale = apply_filters( 'wpml_current_language', null );

		if ( is_string( $locale ) && '' !== $locale ) {
			$wp_locale = apply_filters( 'wpml_locale', null, $locale );
			if ( is_string( $wp_locale ) && '' !== $wp_locale ) {
				return $wp_locale;
			}
		}

		return ( new WCCR_Default_Locale_Resolver() )->resolve_locale( $user_id );
	}
}
