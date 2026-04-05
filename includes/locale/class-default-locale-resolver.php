<?php
defined( 'ABSPATH' ) || exit;

final class WCCR_Default_Locale_Resolver implements WCCR_Locale_Resolver_Interface {
	public function resolve_locale( ?int $user_id = null ): string {
		if ( $user_id ) {
			$user_locale = get_user_locale( $user_id );
			if ( $user_locale ) {
				return $user_locale;
			}
		}

		return determine_locale();
	}
}
