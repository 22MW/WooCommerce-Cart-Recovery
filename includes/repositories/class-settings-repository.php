<?php
defined( 'ABSPATH' ) || exit;

/**
 * Repository for persisted plugin settings.
 */
final class WCCR_Settings_Repository {
	private const OPTION_KEY = 'wccr_settings';

	/**
	 * Return default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		$default_locale = self::get_default_locale();

		return array(
			'abandon_after_minutes' => 60,
			'cleanup_days'          => 90,
			'coupon_expiry_days'    => 7,
			'from_name'             => get_bloginfo( 'name' ),
			'excluded_product_ids'  => array(),
			'excluded_term_ids'     => array(),
			'steps'                 => self::get_default_steps( $default_locale ),
		);
	}

	/**
	 * Get merged plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$settings = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
		return $this->normalize_settings( $settings );
	}

	/**
	 * Save plugin settings.
	 *
	 * @param array<string, mixed> $settings Settings payload.
	 */
	public function save( array $settings ): void {
		update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Return the localized step settings for one locale with fallback.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return array<string, mixed>
	 */
	public function get_localized_step_settings( array $settings, int $step, string $locale ): array {
		$step_settings  = isset( $settings['steps'][ $step ] ) && is_array( $settings['steps'][ $step ] ) ? $settings['steps'][ $step ] : array();
		$translations   = isset( $step_settings['translations'] ) && is_array( $step_settings['translations'] ) ? $step_settings['translations'] : array();
		$default_locale = self::get_default_locale();
		$translation    = $this->find_translation( $translations, $locale, $default_locale, $step );

		$step_settings['subject'] = (string) ( $translation['subject'] ?? $step_settings['subject'] ?? '' );
		$step_settings['body']    = (string) ( $translation['body'] ?? $step_settings['body'] ?? '' );

		return $step_settings;
	}

	/**
	 * Return the translated default subject/body for one step and locale.
	 *
	 * @return array{subject:string,body:string}
	 */
	public function get_translated_default_step_settings( int $step, string $locale ): array {
		return $this->get_default_translation_with_fallback( $step, $locale, self::get_default_locale() );
	}

	/**
	 * Normalize settings and migrate legacy single-language fields.
	 *
	 * @param array<string, mixed> $settings Raw settings payload.
	 * @return array<string, mixed>
	 */
	private function normalize_settings( array $settings ): array {
		$default_locale = self::get_default_locale();
		$default_steps  = self::get_default_steps( $default_locale );

		$settings['excluded_product_ids'] = $this->normalize_id_list( $settings['excluded_product_ids'] ?? array() );
		$settings['excluded_term_ids']    = $this->normalize_id_list( $settings['excluded_term_ids'] ?? array() );

		foreach ( array( 1, 2, 3 ) as $step ) {
			$step_settings            = isset( $settings['steps'][ $step ] ) && is_array( $settings['steps'][ $step ] ) ? $settings['steps'][ $step ] : array();
			$settings['steps'][ $step ] = $this->normalize_step_settings( $step_settings, $default_steps[ $step ], $default_locale );
		}

		return $settings;
	}

	/**
	 * Normalize one step and ensure translated subject/body values exist.
	 *
	 * @param array<string, mixed> $step_settings Raw step settings.
	 * @param array<string, mixed> $default_step  Default step settings.
	 * @return array<string, mixed>
	 */
	private function normalize_step_settings( array $step_settings, array $default_step, string $default_locale ): array {
		$step_settings = wp_parse_args( $step_settings, $default_step );
		$translations  = isset( $step_settings['translations'] ) && is_array( $step_settings['translations'] ) ? $step_settings['translations'] : array();
		$translations  = $this->normalize_translations(
			$translations,
			(string) ( $step_settings['subject'] ?? '' ),
			(string) ( $step_settings['body'] ?? '' ),
			$default_locale,
			$default_step
		);

		$step_settings['translations'] = $translations;
		$step_settings['subject']      = (string) $translations[ $default_locale ]['subject'];
		$step_settings['body']         = (string) $translations[ $default_locale ]['body'];

		return $step_settings;
	}

	/**
	 * Normalize translated values for one step.
	 *
	 * @param array<string, mixed> $translations Raw translations.
	 * @param array<string, mixed> $default_step Default step settings.
	 * @return array<string, array{subject:string,body:string}>
	 */
	private function normalize_translations( array $translations, string $legacy_subject, string $legacy_body, string $default_locale, array $default_step ): array {
		$normalized = array();

		foreach ( $translations as $locale => $translation ) {
			if ( ! is_string( $locale ) || '' === $locale || ! is_array( $translation ) ) {
				continue;
			}

			$normalized[ $locale ] = array(
				'subject' => (string) ( $translation['subject'] ?? '' ),
				'body'    => (string) ( $translation['body'] ?? '' ),
			);
		}

		if ( ! isset( $normalized[ $default_locale ] ) ) {
			$normalized[ $default_locale ] = array(
				'subject' => '' !== $legacy_subject ? $legacy_subject : (string) ( $default_step['subject'] ?? '' ),
				'body'    => '' !== $legacy_body ? $legacy_body : (string) ( $default_step['body'] ?? '' ),
			);
		}

		return $normalized;
	}

	/**
	 * Resolve the best available translation for a locale.
	 *
	 * @param array<string, mixed> $translations Step translations.
	 * @return array<string, string>
	 */
	private function find_translation( array $translations, string $locale, string $default_locale, int $step ): array {
		$locale = sanitize_text_field( $locale );
		$translation = $this->find_saved_translation( $translations, $locale, $default_locale );
		if ( ! empty( $translation ) ) {
			return $translation;
		}

		return $this->get_default_translation_with_fallback( $step, $locale, $default_locale );
	}

	/**
	 * Resolve the best saved translation for a locale before falling back to defaults.
	 *
	 * @param array<string, mixed> $translations Step translations.
	 * @return array<string, string>
	 */
	private function find_saved_translation( array $translations, string $locale, string $default_locale ): array {
		$exact_translation = $this->get_usable_translation( $translations[ $locale ] ?? null );
		if ( ! empty( $exact_translation ) ) {
			return $exact_translation;
		}

		$language_code = strtok( $locale, '_' );
		foreach ( $translations as $translation_locale => $translation ) {
			if ( ! is_string( $translation_locale ) ) {
				continue;
			}

			$usable_translation = $this->get_usable_translation( $translation );
			if ( $language_code && $language_code === strtok( $translation_locale, '_' ) && ! empty( $usable_translation ) ) {
				return $usable_translation;
			}
		}

		$english_translation = $this->get_usable_translation( $translations['en_US'] ?? null );
		if ( ! empty( $english_translation ) ) {
			return $english_translation;
		}

		$default_translation = $this->get_usable_translation( $translations[ $default_locale ] ?? null );
		if ( ! empty( $default_translation ) ) {
			return $default_translation;
		}

		$first = reset( $translations );
		return $this->get_usable_translation( $first );
	}

	/**
	 * Return one saved translation only when it has useful content.
	 *
	 * @param mixed $translation Translation payload.
	 * @return array<string, string>
	 */
	private function get_usable_translation( $translation ): array {
		if ( ! is_array( $translation ) ) {
			return array();
		}

		$subject = trim( (string) ( $translation['subject'] ?? '' ) );
		$body    = trim( (string) ( $translation['body'] ?? '' ) );
		if ( '' === $subject && '' === $body ) {
			return array();
		}

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Build translated default subject/body values for one step and locale.
	 *
	 * @return array{subject:string,body:string}
	 */
	private function get_default_translation_with_fallback( int $step, string $locale, string $default_locale ): array {
		foreach ( $this->get_default_locale_candidates( $locale, $default_locale ) as $candidate_locale ) {
			$translation = self::get_default_step_translation( $step, $candidate_locale );
			if ( '' !== $translation['subject'] || '' !== $translation['body'] ) {
				return $translation;
			}
		}

		return array( 'subject' => '', 'body' => '' );
	}

	/**
	 * Return the ordered locale candidates for default translated content.
	 *
	 * @return array<int, string>
	 */
	private function get_default_locale_candidates( string $locale, string $default_locale ): array {
		return WCCR_Plugin_Locale_Switcher::get_locale_candidates( $locale, $default_locale );
	}

	/**
	 * Return default step definitions with translated content in the default locale.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_default_steps( string $default_locale ): array {
		$steps = array(
			1 => array(
				'enabled'         => 1,
				'delay_minutes'   => 60,
				'discount_type'   => 'none',
				'discount_amount' => 0,
				'min_cart_total'  => 0,
				'subject'         => __( '{customer_name}, you left something in your cart', 'vfwoo_woocommerce-cart-recovery' ),
				'body'            => __( 'Hi {customer_name}, your cart is still available. Click the button below to complete your order: {recovery_link}', 'vfwoo_woocommerce-cart-recovery' ),
			),
			2 => array(
				'enabled'         => 1,
				'delay_minutes'   => 1440,
				'discount_type'   => 'percent',
				'discount_amount' => 5,
				'min_cart_total'  => 0,
				'subject'         => __( '{customer_name}, your cart is waiting for you', 'vfwoo_woocommerce-cart-recovery' ),
				'body'            => __( 'Hi {customer_name}, complete your purchase here: {recovery_link}. Discount: {coupon_label}. Code: {coupon_code}', 'vfwoo_woocommerce-cart-recovery' ),
			),
			3 => array(
				'enabled'         => 1,
				'delay_minutes'   => 2880,
				'discount_type'   => 'percent',
				'discount_amount' => 10,
				'min_cart_total'  => 0,
				'subject'         => __( '{customer_name}, last reminder for your cart', 'vfwoo_woocommerce-cart-recovery' ),
				'body'            => __( 'Hi {customer_name}, your cart can still be recovered here: {recovery_link}. Discount: {coupon_label}. Code: {coupon_code}', 'vfwoo_woocommerce-cart-recovery' ),
			),
		);

		foreach ( $steps as $step => $step_settings ) {
			$steps[ $step ]['translations'] = array(
				$default_locale => array(
					'subject' => (string) $step_settings['subject'],
					'body'    => (string) $step_settings['body'],
				),
			);
		}

		return $steps;
	}

	/**
	 * Return the translated default subject/body for one step and locale.
	 *
	 * @return array{subject:string,body:string}
	 */
	private static function get_default_step_translation( int $step, string $locale ): array {
		$switched = self::switch_to_settings_locale( $locale );
		$steps    = self::get_default_steps( $locale );

		if ( $switched ) {
			WCCR_Plugin_Locale_Switcher::restore_previous_locale();
		}

		$step_settings = isset( $steps[ $step ] ) && is_array( $steps[ $step ] ) ? $steps[ $step ] : array();
		return array(
			'subject' => (string) ( $step_settings['subject'] ?? '' ),
			'body'    => (string) ( $step_settings['body'] ?? '' ),
		);
	}

	/**
	 * Switch locale temporarily while building translated defaults.
	 */
	private static function switch_to_settings_locale( string $locale ): bool {
		return WCCR_Plugin_Locale_Switcher::switch_to_locale( $locale );
	}

	/**
	 * Return the site default locale used as settings fallback.
	 */
	private static function get_default_locale(): string {
		return get_locale();
	}

	/**
	 * Normalize one list of stored object IDs.
	 *
	 * @param mixed $ids Raw ID payload.
	 * @return array<int, int>
	 */
	private function normalize_id_list( $ids ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $ids )
				)
			)
		);
	}
}
