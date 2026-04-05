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
}
