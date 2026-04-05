<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Polylang_Locale_Resolver implements WCCR_Locale_Resolver_Interface {
	public function resolve_locale( ?int $user_id = null ): string {
		if ( function_exists( 'pll_current_language' ) ) {
			$locale = pll_current_language( 'locale' );
			if ( is_string( $locale ) && '' !== $locale ) {
				return $locale;
			}
		}

		return ( new WCCR_Default_Locale_Resolver() )->resolve_locale( $user_id );
	}
}
