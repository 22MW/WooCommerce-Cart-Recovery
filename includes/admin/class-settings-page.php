<?php
defined('ABSPATH') || exit;

/**
 * Render and save plugin settings.
 */
final class WCCR_Settings_Page
{
	public function __construct(
		private WCCR_Settings_Repository $settings_repository,
		private WCCR_Locale_Resolver_Manager $locale_resolver,
		private WCCR_Exclusion_Translation_Service $exclusion_translation_service,
		private WCCR_Abandoned_Cart_Detector $detector,
		private WCCR_Email_Scheduler $email_scheduler,
		private WCCR_Pending_Order_Detector $pending_order_detector
	) {}

	/**
	 * Register admin hooks for settings management.
	 */
	public function register_hooks(): void
	{
		add_action('admin_init', array($this, 'register_setting'));
		add_action('admin_init', array($this, 'maybe_reset_step_locale_defaults'));
		add_action('admin_init', array($this, 'maybe_save'));
		add_action('admin_init', array($this, 'maybe_run_now'));
		add_action('admin_init', array($this, 'maybe_import_unpaid_orders'));
		add_action('wp_ajax_wccr_search_excluded_products', array($this, 'ajax_search_excluded_products'));
		add_action('wp_ajax_wccr_search_excluded_terms', array($this, 'ajax_search_excluded_terms'));
		add_action('wp_ajax_wccr_reset_step_locale', array($this, 'ajax_reset_step_locale'));
		add_action('wp_ajax_wccr_save_settings', array($this, 'ajax_save_settings'));
	}

	/**
	 * Register the plugin option with the Settings API.
	 */
	public function register_setting(): void
	{
		register_setting('wccr_settings_group', 'wccr_settings', array(
			'type'              => 'object',
			'description'       => __('WooCommerce Cart Recovery settings.', 'vfwoo_woocommerce-cart-recovery'),
			'sanitize_callback' => null,
			'default'           => WCCR_Settings_Repository::default_settings(),
		));
	}

	/**
	 * Persist settings after nonce and capability validation.
	 */
	public function maybe_save(): void
	{
		if (isset($_POST['wccr_reset_translation'])) {
			return;
		}

		if (! isset($_POST['wccr_settings_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wccr_settings_nonce'])), 'wccr_save_settings')) {
			return;
		}

		if (! current_user_can('manage_woocommerce')) {
			return;
		}

		$settings = $this->parse_posted_settings();
		$this->settings_repository->save($settings);
		add_settings_error('wccr_settings', 'wccr_saved', __('Settings saved.', 'vfwoo_woocommerce-cart-recovery'), 'updated');
	}

	/**
	 * AJAX: save all settings without page reload.
	 */
	public function ajax_save_settings(): void
	{
		check_ajax_referer('wccr_save_settings_ajax', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => 'Forbidden'), 403);
		}

		$settings = $this->parse_posted_settings();
		$this->settings_repository->save($settings);
		wp_send_json_success(array('message' => __('Settings saved.', 'vfwoo_woocommerce-cart-recovery')));
	}

	/**
	 * Parse and sanitize all settings from $_POST.
	 *
	 * @return array<string, mixed>
	 */
	private function parse_posted_settings(): array
	{
		$locales        = $this->locale_resolver->get_available_locales();
		$default_locale = $this->locale_resolver->get_default_locale();

		$settings = array(
			'abandon_after_minutes' => absint($_POST['abandon_after_minutes'] ?? 60),
			'cleanup_days'          => absint($_POST['cleanup_days'] ?? 90),
			'coupon_expiry_days'    => absint($_POST['coupon_expiry_days'] ?? 7),
			'from_name'             => sanitize_text_field(wp_unslash($_POST['from_name'] ?? '')),
			'excluded_product_ids'  => $this->exclusion_translation_service->expand_product_ids($this->collect_id_list($_POST['excluded_product_ids'] ?? array())),
			'excluded_term_ids'     => $this->exclusion_translation_service->expand_term_ids($this->collect_id_list($_POST['excluded_term_ids'] ?? array())),
			'steps'                 => array(),
		);

		foreach (array(1, 2, 3) as $step) {
			$settings['steps'][$step] = array(
				'enabled'         => ! empty($_POST['steps'][$step]['enabled']) ? 1 : 0,
				'delay_minutes'   => absint($_POST['steps'][$step]['delay_minutes'] ?? 60),
				'discount_type'   => sanitize_text_field(wp_unslash($_POST['steps'][$step]['discount_type'] ?? 'none')),
				'discount_amount' => (float) ($_POST['steps'][$step]['discount_amount'] ?? 0),
				'min_cart_total'  => (float) ($_POST['steps'][$step]['min_cart_total'] ?? 0),
				'translations'    => $this->collect_step_translations($step, $locales),
			);

			$default_translation = $settings['steps'][$step]['translations'][$default_locale] ?? reset($settings['steps'][$step]['translations']);
			$settings['steps'][$step]['subject'] = is_array($default_translation) ? (string) ($default_translation['subject'] ?? '') : '';
			$settings['steps'][$step]['body']    = is_array($default_translation) ? (string) ($default_translation['body'] ?? '') : '';
		}

		return $settings;
	}

	/**
	 * Reset one locale translation block to the translated plugin defaults.
	 */
	public function maybe_reset_step_locale_defaults(): void
	{
		if (! isset($_POST['wccr_reset_translation'], $_POST['wccr_settings_nonce'])) {
			return;
		}

		if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wccr_settings_nonce'])), 'wccr_save_settings') || ! current_user_can('manage_woocommerce')) {
			return;
		}

		$target = isset($_POST['wccr_reset_translation']) ? sanitize_text_field(wp_unslash($_POST['wccr_reset_translation'])) : '';
		$parts  = explode('|', $target, 2);
		$step   = isset($parts[0]) ? absint($parts[0]) : 0;
		$locale = isset($parts[1]) ? sanitize_text_field($parts[1]) : '';
		if (! in_array($step, array(1, 2, 3), true) || '' === $locale) {
			return;
		}

		$settings    = $this->settings_repository->get();
		$translation = $this->settings_repository->get_translated_default_step_settings($step, $locale);
		$settings['steps'][$step]['translations'][$locale] = $translation;
		$this->settings_repository->save($settings);

		add_settings_error('wccr_settings', 'wccr_reset_defaults', __('Translated defaults restored.', 'vfwoo_woocommerce-cart-recovery'), 'updated');
	}

	/**
	 * AJAX: return translated defaults for one step+locale without reloading.
	 */
	public function ajax_reset_step_locale(): void
	{
		check_ajax_referer('wccr_reset_step_locale', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => 'Forbidden'), 403);
		}

		$step   = isset($_POST['step']) ? absint($_POST['step']) : 0;
		$locale = isset($_POST['locale']) ? sanitize_text_field(wp_unslash($_POST['locale'])) : '';

		if (! in_array($step, array(1, 2, 3), true) || '' === $locale) {
			wp_send_json_error(array('message' => 'Invalid params'), 400);
		}

		$translation = $this->settings_repository->get_translated_default_step_settings($step, $locale);
		wp_send_json_success(array(
			'subject' => $translation['subject'] ?? '',
			'body'    => $translation['body'] ?? '',
		));
	}

	/**
	 * Manually run detector and queue from the settings screen.
	 */
	public function maybe_run_now(): void
	{
		if (! isset($_POST['wccr_run_now_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wccr_run_now_nonce'])), 'wccr_run_now')) {
			return;
		}

		if (! current_user_can('manage_woocommerce')) {
			return;
		}

		$this->detector->run();
		$this->pending_order_detector->sync_stale_pending_orders();
		$this->email_scheduler->process_queue();

		add_settings_error('wccr_settings', 'wccr_run_done', __('Recovery detector and email queue executed manually.', 'vfwoo_woocommerce-cart-recovery'), 'updated');
	}

	/**
	 * Manually import existing pending and failed orders.
	 */
	public function maybe_import_unpaid_orders(): void
	{
		if (! isset($_POST['wccr_import_unpaid_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wccr_import_unpaid_nonce'])), 'wccr_import_unpaid_orders')) {
			return;
		}

		if (! current_user_can('manage_woocommerce')) {
			return;
		}

		$results = $this->pending_order_detector->import_existing_unpaid_orders();

		add_settings_error(
			'wccr_settings',
			'wccr_import_done',
			$this->get_import_notice_message($results),
			'updated'
		);
	}

	/**
	 * Render the settings screen.
	 */
	public function render(): void
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'vfwoo_woocommerce-cart-recovery'));
		}

?>
		<div class="wrap wccr-admin">
			<h1><?php esc_html_e('Cart Recovery Settings', 'vfwoo_woocommerce-cart-recovery'); ?></h1>
			<?php settings_errors('wccr_settings'); ?>
			<?php $this->render_content(); ?>
		</div>
	<?php
	}

	/**
	 * Render the settings form without the page wrapper.
	 */
	public function render_content(): void
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'vfwoo_woocommerce-cart-recovery'));
		}

		$settings = $this->settings_repository->get();
		$locales  = $this->locale_resolver->get_available_locales();
	?>
		<form method="post" id="wccr-settings-form">
			<?php wp_nonce_field('wccr_save_settings', 'wccr_settings_nonce'); ?>
			<div class="wccr-card">
				<div class="wccr-settings-top-grid">
					<p><label for="abandon_after_minutes"><?php esc_html_e('Mark cart abandoned after minutes', 'vfwoo_woocommerce-cart-recovery'); ?><br><input type="number" name="abandon_after_minutes" id="abandon_after_minutes" value="<?php echo esc_attr($settings['abandon_after_minutes']); ?>" min="1"></label><span class="description"><?php esc_html_e('2h = 120 · 10h = 600 · 1d = 1440 · 10d = 14400 · 30d = 43200', 'vfwoo_woocommerce-cart-recovery'); ?></span></p>
					<p><label for="cleanup_days"><?php esc_html_e('Cleanup data after days', 'vfwoo_woocommerce-cart-recovery'); ?><br><input type="number" name="cleanup_days" id="cleanup_days" value="<?php echo esc_attr($settings['cleanup_days']); ?>" min="1"></label></p>
					<p><label for="coupon_expiry_days"><?php esc_html_e('Coupon expiry days', 'vfwoo_woocommerce-cart-recovery'); ?><br><input type="number" name="coupon_expiry_days" id="coupon_expiry_days" value="<?php echo esc_attr($settings['coupon_expiry_days']); ?>" min="1"></label></p>
				</div>
			</div>

			<?php $this->render_exclusion_settings($settings); ?>
			<?php $this->render_step_cards($settings); ?>
			<?php $this->render_locale_tabs($settings, $locales); ?>
		</form>
	<?php
	}

	/**
	 * Build a useful admin notice for unpaid-order imports.
	 *
	 * @param array{reviewed:int,imported:int,merged:int,updated:int,skipped:int} $results Import counters.
	 */
	private function get_import_notice_message(array $results): string
	{
		if (0 === (int) $results['reviewed']) {
			return __('No eligible pending or failed orders were found.', 'vfwoo_woocommerce-cart-recovery');
		}

		return sprintf(
			/* translators: 1: reviewed count, 2: imported count, 3: merged count, 4: updated count, 5: skipped count */
			__('Unpaid orders reviewed: %1$d. Imported: %2$d. Merged: %3$d. Updated: %4$d. Skipped: %5$d.', 'vfwoo_woocommerce-cart-recovery'),
			(int) $results['reviewed'],
			(int) $results['imported'],
			(int) $results['merged'],
			(int) $results['updated'],
			(int) $results['skipped']
		);
	}

	/**
	 * Collect translated subject/body values for one email step.
	 *
	 * @param array<int, array{locale:string,label:string}> $locales Active locales.
	 * @return array<string, array{subject:string,body:string}>
	 */
	private function collect_step_translations(int $step, array $locales): array
	{
		$translations = array();

		foreach ($locales as $locale) {
			$locale_key = sanitize_text_field((string) ($locale['locale'] ?? ''));
			if ('' === $locale_key) {
				continue;
			}

			$subject = $_POST['steps'][$step]['translations'][$locale_key]['subject'] ?? '';
			$body    = $_POST['steps'][$step]['translations'][$locale_key]['body'] ?? '';

			$translations[$locale_key] = array(
				'subject' => sanitize_text_field(wp_unslash($subject)),
				'body'    => wp_kses_post(wp_unslash($body)),
			);
		}

		return $translations;
	}

	/**
	 * Normalize one list of object IDs from the submitted settings form.
	 *
	 * @param mixed $ids Raw ID list.
	 * @return array<int, int>
	 */
	private function collect_id_list($ids): array
	{
		if (! is_array($ids)) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					array_map('absint', $ids)
				)
			)
		);
	}

	/**
	 * Render the exclusion selectors used by capture and unpaid-order import.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private function render_exclusion_settings(array $settings): void
	{
		$selected_products = $this->get_selected_product_items(array_map('absint', $settings['excluded_product_ids'] ?? array()));
		$selected_terms    = $this->get_selected_term_items(array_map('absint', $settings['excluded_term_ids'] ?? array()));
	?>
		<div class="wccr-card">
			<div class="wccr-section-title"><?php esc_html_e('Recovery exclusions', 'vfwoo_woocommerce-cart-recovery'); ?></div>
			<p class="description"><?php esc_html_e('If a cart or unpaid order contains any excluded product or taxonomy term, it will not be captured or imported into recovery.', 'vfwoo_woocommerce-cart-recovery'); ?></p>
			<div class="wccr-exclusion-grid">
				<?php $this->render_exclusion_autocomplete('products', __('Excluded products', 'vfwoo_woocommerce-cart-recovery'), __('Start typing a product name…', 'vfwoo_woocommerce-cart-recovery'), 'excluded_product_ids[]', $selected_products); ?>
				<?php $this->render_exclusion_autocomplete('terms', __('Excluded taxonomy terms', 'vfwoo_woocommerce-cart-recovery'), __('Start typing a taxonomy term…', 'vfwoo_woocommerce-cart-recovery'), 'excluded_term_ids[]', $selected_terms); ?>
			</div>
		</div>
	<?php
	}

	/**
	 * Render global step cards shared across every language.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private function render_step_cards(array $settings): void
	{
	?>
		<div class="wccr-step-grid">
			<?php
			foreach (array(1, 2, 3) as $step) {
				$step_settings = isset($settings['steps'][$step]) && is_array($settings['steps'][$step]) ? $settings['steps'][$step] : array();
			?>
				<div class="wccr-card">
					<div class="wccr-step-card__header">
						<div class="wccr-card-title"><?php echo esc_html(sprintf( /* translators: %d: email step number */__('Email %d', 'vfwoo_woocommerce-cart-recovery'), $step)); ?></div>
						<label class="wccr-step-card__toggle wccr-switch" aria-label="<?php esc_attr_e('Enabled', 'vfwoo_woocommerce-cart-recovery'); ?>"><input type="checkbox" name="steps[<?php echo esc_attr($step); ?>][enabled]" value="1" <?php checked(! empty($step_settings['enabled'])); ?>><span class="wccr-switch-slider"></span></label>
					</div>
					<div class="wccr-step-card__config">
						<p><label><?php esc_html_e('Delay minutes', 'vfwoo_woocommerce-cart-recovery'); ?> <input type="number" name="steps[<?php echo esc_attr($step); ?>][delay_minutes]" value="<?php echo esc_attr($step_settings['delay_minutes'] ?? 60); ?>"></label></p>
						<p><label><?php esc_html_e('Discount type', 'vfwoo_woocommerce-cart-recovery'); ?>
								<select name="steps[<?php echo esc_attr($step); ?>][discount_type]">
									<option value="none" <?php selected($step_settings['discount_type'] ?? 'none', 'none'); ?>><?php esc_html_e('None', 'vfwoo_woocommerce-cart-recovery'); ?></option>
									<option value="percent" <?php selected($step_settings['discount_type'] ?? 'none', 'percent'); ?>><?php esc_html_e('Percentage', 'vfwoo_woocommerce-cart-recovery'); ?></option>
									<option value="fixed_cart" <?php selected($step_settings['discount_type'] ?? 'none', 'fixed_cart'); ?>><?php esc_html_e('Fixed cart', 'vfwoo_woocommerce-cart-recovery'); ?></option>
								</select>
							</label></p>
						<p><label><?php esc_html_e('Discount amount', 'vfwoo_woocommerce-cart-recovery'); ?> <input type="number" step="0.01" name="steps[<?php echo esc_attr($step); ?>][discount_amount]" value="<?php echo esc_attr($step_settings['discount_amount'] ?? 0); ?>"></label></p>
						<p><label><?php esc_html_e('Minimum cart total', 'vfwoo_woocommerce-cart-recovery'); ?> <input type="number" step="0.01" name="steps[<?php echo esc_attr($step); ?>][min_cart_total]" value="<?php echo esc_attr($step_settings['min_cart_total'] ?? 0); ?>"></label></p>
					</div>
				</div>
			<?php
			}
			?>
		</div>
	<?php
	}

	/**
	 * Render one autocomplete selector with selected chips and hidden inputs.
	 *
	 * @param array<int, array{id:int,label:string}> $selected_items Selected items.
	 */
	private function render_exclusion_autocomplete(string $type, string $label, string $placeholder, string $input_name, array $selected_items): void
	{
	?>
		<div class="wccr-exclusion-field" data-type="<?php echo esc_attr($type); ?>" data-input-name="<?php echo esc_attr($input_name); ?>">
			<label for="wccr-exclusion-search-<?php echo esc_attr($type); ?>"><strong><?php echo esc_html($label); ?></strong></label>
			<div class="wccr-exclusion-field__box">
				<input type="search" id="wccr-exclusion-search-<?php echo esc_attr($type); ?>" class="wccr-exclusion-field__search" placeholder="<?php echo esc_attr($placeholder); ?>" autocomplete="off">
				<div class="wccr-exclusion-field__results" hidden></div>
				<div class="wccr-exclusion-field__selected">
					<?php foreach ($selected_items as $item) : ?>
						<?php $this->render_selected_exclusion_chip($input_name, $item); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Render one selected exclusion chip with its hidden input.
	 *
	 * @param array{id:int,label:string} $item Selected item.
	 */
	private function render_selected_exclusion_chip(string $input_name, array $item): void
	{
	?>
		<span class="wccr-exclusion-chip" data-id="<?php echo esc_attr($item['id']); ?>">
			<span class="wccr-exclusion-chip__label"><?php echo esc_html($item['label']); ?></span>
			<button type="button" class="wccr-exclusion-chip__remove" aria-label="<?php esc_attr_e('Remove exclusion', 'vfwoo_woocommerce-cart-recovery'); ?>">×</button>
			<input type="hidden" name="<?php echo esc_attr($input_name); ?>" value="<?php echo esc_attr($item['id']); ?>">
		</span>
	<?php
	}

	/**
	 * Build representative product chips from stored expanded IDs.
	 *
	 * @param array<int, int> $stored_ids Expanded stored IDs.
	 * @return array<int, array{id:int,label:string}>
	 */
	private function get_selected_product_items(array $stored_ids): array
	{
		$items   = array();
		$handled = array();

		foreach ($stored_ids as $product_id) {
			if (isset($handled[$product_id])) {
				continue;
			}

			$product = wc_get_product($product_id);
			if (! $product instanceof WC_Product) {
				continue;
			}

			foreach ($this->exclusion_translation_service->get_related_product_ids($product_id) as $related_id) {
				$handled[$related_id] = true;
			}

			$items[] = array(
				'id'    => $product_id,
				'label' => sprintf('%1$s (#%2$d)', $product->get_name(), $product_id),
			);
		}

		return $items;
	}

	/**
	 * Build representative term chips from stored expanded IDs.
	 *
	 * @param array<int, int> $stored_ids Expanded stored IDs.
	 * @return array<int, array{id:int,label:string}>
	 */
	private function get_selected_term_items(array $stored_ids): array
	{
		$items   = array();
		$handled = array();

		foreach ($stored_ids as $term_id) {
			if (isset($handled[$term_id])) {
				continue;
			}

			$term = get_term($term_id);
			if (! $term instanceof WP_Term) {
				continue;
			}

			foreach ($this->exclusion_translation_service->get_related_term_ids($term_id) as $related_id) {
				$handled[$related_id] = true;
			}

			$items[] = array(
				'id'    => $term_id,
				'label' => sprintf('%1$s: %2$s (#%3$d)', $this->get_taxonomy_label($term->taxonomy), $term->name, $term_id),
			);
		}

		return $items;
	}

	/**
	 * AJAX search for excluded products.
	 */
	public function ajax_search_excluded_products(): void
	{
		$this->assert_exclusion_search_request();

		$term     = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
		$products = wc_get_products(
			array(
				'limit'   => 20,
				'status'  => array('publish', 'private'),
				'return'  => 'objects',
				'search'  => '*' . $term . '*',
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);
		$results  = array();

		foreach ($products as $product) {
			if (! $product instanceof WC_Product) {
				continue;
			}

			$results[] = array(
				'id'    => $product->get_id(),
				'label' => sprintf('%1$s (#%2$d)', $product->get_name(), $product->get_id()),
			);
		}

		wp_send_json_success($results);
	}

	/**
	 * AJAX search for excluded taxonomy terms.
	 */
	public function ajax_search_excluded_terms(): void
	{
		$this->assert_exclusion_search_request();

		$term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
		$terms = get_terms(
			array(
				'taxonomy'   => $this->get_supported_product_taxonomies(),
				'hide_empty' => false,
				'number'     => 20,
				'search'     => $term,
			)
		);
		$results = array();

		if (! is_wp_error($terms)) {
			foreach ($terms as $taxonomy_term) {
				if (! $taxonomy_term instanceof WP_Term) {
					continue;
				}

				$results[] = array(
					'id'    => $taxonomy_term->term_id,
					'label' => sprintf('%1$s: %2$s (#%3$d)', $this->get_taxonomy_label($taxonomy_term->taxonomy), $taxonomy_term->name, $taxonomy_term->term_id),
				);
			}
		}

		wp_send_json_success($results);
	}

	/**
	 * Validate one exclusion search request.
	 */
	private function assert_exclusion_search_request(): void
	{
		check_ajax_referer('wccr_exclusion_search', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array(), 403);
		}
	}

	/**
	 * Return supported product taxonomies for term exclusions.
	 *
	 * @return array<int, string>
	 */
	private function get_supported_product_taxonomies(): array
	{
		return array_values(array_diff(get_object_taxonomies('product', 'names'), array('product_type', 'product_visibility')));
	}

	/**
	 * Return a readable taxonomy label.
	 */
	private function get_taxonomy_label(string $taxonomy): string
	{
		$taxonomy_object = get_taxonomy($taxonomy);
		return $taxonomy_object instanceof WP_Taxonomy ? $taxonomy_object->label : $taxonomy;
	}

	/**
	 * Render translated email content tabs by locale.
	 *
	 * @param array<string, mixed>                $settings Plugin settings.
	 * @param array<int, array{locale:string,label:string}> $locales Active locales.
	 */
	private function render_locale_tabs(array $settings, array $locales): void
	{
	?>
		<div class="wccr-settings-layout">
			<div class="wccr-settings-layout__sidebar">
				<div class="wccr-locale-tabs__nav" role="tablist" aria-label="<?php esc_attr_e('Email languages', 'vfwoo_woocommerce-cart-recovery'); ?>">
					<?php foreach (array_values($locales) as $index => $locale) : ?>
						<?php $this->render_locale_tab_button($locale, 0 === $index); ?>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="wccr-settings-layout__content">
				<div class="wccr-locale-tabs__panels">
					<?php foreach (array_values($locales) as $index => $locale) : ?>
						<?php $this->render_locale_tab_panel($settings, $locale, 0 === $index); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Render one locale tab button.
	 *
	 * @param array{locale:string,label:string} $locale   Locale item.
	 */
	private function render_locale_tab_button(array $locale, bool $is_active): void
	{
		$locale_key = sanitize_key(str_replace('-', '_', $locale['locale']));
	?>
		<button
			type="button"
			class="wccr-locale-tabs__button<?php echo esc_attr($is_active ? ' is-active' : ''); ?>"
			data-locale-tab="<?php echo esc_attr($locale['locale']); ?>"
			role="tab"
			aria-selected="<?php echo esc_attr($is_active ? 'true' : 'false'); ?>"
			aria-controls="wccr-locale-panel-<?php echo esc_attr($locale_key); ?>"
			id="wccr-locale-tab-<?php echo esc_attr($locale_key); ?>"><?php echo esc_html($locale['label']); ?></button>
	<?php
	}

	/**
	 * Render one locale tab panel and its translated textareas.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param array{locale:string,label:string} $locale Locale item.
	 */
	private function render_locale_tab_panel(array $settings, array $locale, bool $is_active): void
	{
		$locale_key = sanitize_key(str_replace('-', '_', $locale['locale']));
	?>
		<div
			class="wccr-locale-tabs__panel<?php echo esc_attr($is_active ? ' is-active' : ''); ?>"
			data-locale-panel="<?php echo esc_attr($locale['locale']); ?>"
			id="wccr-locale-panel-<?php echo esc_attr($locale_key); ?>"
			role="tabpanel"
			aria-labelledby="wccr-locale-tab-<?php echo esc_attr($locale_key); ?>">
			<div class="wccr-email-tabs__nav" role="tablist">
				<?php foreach (array(1, 2, 3) as $step) :
					$step_enabled = ! empty($settings['steps'][$step]['enabled']);
				?>
					<button
						type="button"
						class="wccr-email-tabs__button<?php echo esc_attr(1 === $step ? ' is-active' : ''); ?><?php echo esc_attr(! $step_enabled ? ' wccr-email-tab--disabled' : ''); ?>"
						data-email-tab="<?php echo esc_attr($step); ?>"
						data-step="<?php echo esc_attr($step); ?>"
						<?php echo ! $step_enabled ? 'disabled aria-disabled="true"' : ''; ?>
						role="tab"
						aria-selected="<?php echo esc_attr(1 === $step ? 'true' : 'false'); ?>">
						<?php echo esc_html(sprintf(__('Email %d', 'vfwoo_woocommerce-cart-recovery'), $step)); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="wccr-email-tabs__panels">
				<?php foreach (array(1, 2, 3) as $step) : ?>
					<div class="wccr-email-tabs__panel<?php echo esc_attr(1 === $step ? ' is-active' : ''); ?>" data-email-panel="<?php echo esc_attr($step); ?>">
						<?php $this->render_locale_translation_card($settings, $step, (string) $locale['locale']); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php
	}

	/**
	 * Render one translated subject/body card for one locale and step.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private function render_locale_translation_card(array $settings, int $step, string $locale): void
	{
		$step_settings = $this->settings_repository->get_localized_step_settings($settings, $step, $locale);
	?>
		<div class="wccr-card">
			<div class="wccr-card-title"><?php echo esc_html(sprintf(__('Email %d', 'vfwoo_woocommerce-cart-recovery'), $step)); ?></div>
			<p><label><?php esc_html_e('Subject', 'vfwoo_woocommerce-cart-recovery'); ?><br><input type="text" class="large-text" name="steps[<?php echo esc_attr($step); ?>][translations][<?php echo esc_attr($locale); ?>][subject]" value="<?php echo esc_attr($step_settings['subject'] ?? ''); ?>"></label></p>
			<p><label><?php esc_html_e('Body', 'vfwoo_woocommerce-cart-recovery'); ?><br><textarea class="large-text" rows="6" name="steps[<?php echo esc_attr($step); ?>][translations][<?php echo esc_attr($locale); ?>][body]"><?php echo esc_textarea($step_settings['body'] ?? ''); ?></textarea></label></p>
			<p class="description"><?php esc_html_e('Available variables: {recovery_link}, {coupon_code}, {coupon_label}, {cart_total}, {site_name}, {customer_name}', 'vfwoo_woocommerce-cart-recovery'); ?></p>
			<p>
				<button type="button" class="button button-secondary wccr-reset-translation" data-step="<?php echo esc_attr($step); ?>" data-locale="<?php echo esc_attr($locale); ?>"><?php esc_html_e('Reset to translated defaults', 'vfwoo_woocommerce-cart-recovery'); ?></button>
			</p>
		</div>
<?php
	}
}
