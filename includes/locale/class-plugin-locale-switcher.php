<?php
defined( 'ABSPATH' ) || exit;

/**
 * Switch the plugin locale and reload its translations.
 */
final class WCCR_Plugin_Locale_Switcher {
	private const TEXT_DOMAIN = 'vfwoo_woocommerce-cart-recovery';
	/**
	 * Track whether each plugin locale switch also changed the WordPress locale.
	 *
	 * @var array<int, bool>
	 */
	private static array $wp_locale_stack = array();

	/**
	 * Switch the active locale and reload the plugin text domain.
	 */
	public static function switch_to_locale( string $locale ): bool {
		$requested_locale = sanitize_text_field( $locale );
		$locale           = self::resolve_supported_locale( $requested_locale );
		if ( '' === $requested_locale ) {
			return false;
		}

		$switched = false;
		if ( function_exists( 'switch_to_locale' ) ) {
			$switched = (bool) switch_to_locale( $requested_locale );
		}

		self::$wp_locale_stack[] = $switched;
		self::reload_text_domain_for_locale( $locale );
		return true;
	}

	/**
	 * Restore the previous locale and reload the plugin text domain.
	 */
	public static function restore_previous_locale(): void {
		$did_switch = array_pop( self::$wp_locale_stack );
		if ( $did_switch && function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
		}

		if ( ! function_exists( 'determine_locale' ) ) {
			return;
		}

		self::reload_text_domain_for_locale( self::resolve_supported_locale( determine_locale() ) );
	}

	/**
	 * Reload the plugin translations for one locale.
	 */
	private static function reload_text_domain_for_locale( string $locale ): void {
		if ( function_exists( 'unload_textdomain' ) ) {
			unload_textdomain( self::TEXT_DOMAIN );
		}

		$mofile = WCCR_PLUGIN_DIR . 'languages/' . self::TEXT_DOMAIN . '-' . $locale . '.mo';
		if ( '' !== $locale && file_exists( $mofile ) && function_exists( 'load_textdomain' ) ) {
			load_textdomain( self::TEXT_DOMAIN, $mofile );
			return;
		}

		load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( WCCR_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Return the best plugin-supported locale for the requested language.
	 *
	 * @return array<int, string>
	 */
	public static function get_locale_candidates( string $locale, string $default_locale = '' ): array {
		$requested = sanitize_text_field( $locale );
		$default   = sanitize_text_field( $default_locale );
		$candidates = array();

		foreach ( array( $requested, $default ) as $candidate ) {
			if ( '' === $candidate ) {
				continue;
			}

			$candidates[] = $candidate;
			$matched      = self::find_same_language_locale( $candidate );
			if ( '' !== $matched ) {
				$candidates[] = $matched;
			}
		}

		$candidates[] = 'en_US';

		return array_values( array_unique( array_filter( $candidates ) ) );
	}

	/**
	 * Resolve one locale to the nearest plugin-supported locale.
	 */
	private static function resolve_supported_locale( string $locale ): string {
		$requested = sanitize_text_field( $locale );
		if ( '' === $requested ) {
			return '';
		}

		$available_locales = self::get_available_plugin_locales();
		if ( in_array( $requested, $available_locales, true ) ) {
			return $requested;
		}

		foreach ( self::get_locale_candidates( $requested ) as $candidate ) {
			if ( in_array( $candidate, $available_locales, true ) ) {
				return $candidate;
			}
		}

		return $requested;
	}

	/**
	 * Find a plugin-supported locale that shares the same language code.
	 */
	private static function find_same_language_locale( string $locale ): string {
		$language_code = strtok( sanitize_text_field( $locale ), '_' );
		if ( ! $language_code ) {
			return '';
		}

		foreach ( self::get_available_plugin_locales() as $available_locale ) {
			if ( $language_code === strtok( $available_locale, '_' ) ) {
				return $available_locale;
			}
		}

		return '';
	}

	/**
	 * Return locales available in the plugin language directory.
	 *
	 * @return array<int, string>
	 */
	private static function get_available_plugin_locales(): array {
		$pattern = WCCR_PLUGIN_DIR . 'languages/' . self::TEXT_DOMAIN . '-*.mo';
		$files   = glob( $pattern );
		if ( false === $files ) {
			return array();
		}

		$locales = array();
		foreach ( $files as $file ) {
			$basename = basename( $file, '.mo' );
			$prefix   = self::TEXT_DOMAIN . '-';
			if ( 0 !== strpos( $basename, $prefix ) ) {
				continue;
			}

			$locales[] = substr( $basename, strlen( $prefix ) );
		}

		return array_values( array_unique( array_filter( $locales ) ) );
	}
}
