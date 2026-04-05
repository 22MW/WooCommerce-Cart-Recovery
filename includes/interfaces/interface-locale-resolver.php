<?php
defined( 'ABSPATH' ) || exit;

interface WCCR_Locale_Resolver_Interface {
	public function resolve_locale( ?int $user_id = null ): string;
}
