<?php
defined('ABSPATH') || exit;

/**
 * Register plugin admin screens and shared assets.
 */
final class WCCR_Admin_Menu
{
	public function __construct(
		private WCCR_Settings_Page $settings_page,
		private WCCR_Abandoned_Carts_Page $carts_page,
		private WCCR_Stats_Page $stats_page
	) {}

	/**
	 * Register admin menu and asset hooks.
	 */
	public function register_hooks(): void
	{
		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	/**
	 * Register plugin menu pages.
	 */
	public function register_menu(): void
	{
		add_submenu_page(
			'woocommerce',
			__('Cart Recovery', 'vfwoo_woocommerce-cart-recovery'),
			__('Cart Recovery', 'vfwoo_woocommerce-cart-recovery'),
			'manage_woocommerce',
			'wccr-cart-recovery',
			array($this, 'render_page')
		);
	}

	/**
	 * Render the unified Cart Recovery admin page with tabs.
	 */
	public function render_page(): void
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'vfwoo_woocommerce-cart-recovery'));
		}

		$tab = $this->get_current_tab();
?>
		<div class="wrap wccr-admin">
			<?php settings_errors('wccr_settings'); ?>
			<div class="wccr-layout__header">
				<div class="wccr-layout__header-wrapper">
					<div class="wccr-layout__header-heading">
						<div class="wccr-layout__header-brand">
							<img src="<?php echo esc_url(WCCR_PLUGIN_URL . 'assets/img/logo.svg'); ?>" alt="<?php esc_attr_e('Plugin logo', 'vfwoo_woocommerce-cart-recovery'); ?>">
						</div>
						<div class="wccr-admin-header__title"><?php esc_html_e('Cart Recovery', 'vfwoo_woocommerce-cart-recovery'); ?></div>
						<span class="wccr-admin-header__version"><?php echo esc_html('v.' . WCCR_VERSION); ?></span>
					</div>
					<nav class="wccr-layout__header-tabs" aria-label="<?php esc_attr_e('Cart Recovery sections', 'vfwoo_woocommerce-cart-recovery'); ?>">
						<a href="<?php echo esc_url($this->get_tab_url('carts')); ?>" class="wccr-admin-pill <?php echo esc_attr('carts' === $tab ? 'is-active' : ''); ?>">
							<?php esc_html_e('Carts', 'vfwoo_woocommerce-cart-recovery'); ?>
						</a>
						<a href="<?php echo esc_url($this->get_tab_url('settings')); ?>" class="wccr-admin-pill <?php echo esc_attr('settings' === $tab ? 'is-active' : ''); ?>">
							<?php esc_html_e('Settings', 'vfwoo_woocommerce-cart-recovery'); ?>
						</a>
					</nav>
				</div>
			</div>
			<?php $this->render_active_tab($tab); ?>
		</div>
<?php
	}

	/**
	 * Render the selected tab content.
	 */
	private function render_active_tab(string $tab): void
	{
		if ('settings' === $tab) {
			$this->settings_page->render_content();
			return;
		}

		$this->carts_page->render_content();
	}

	/**
	 * Return the current admin tab key.
	 */
	private function get_current_tab(): string
	{
		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'carts';
		return in_array($tab, array('carts', 'settings'), true) ? $tab : 'carts';
	}

	/**
	 * Build the admin URL for a tab.
	 */
	private function get_tab_url(string $tab): string
	{
		return add_query_arg(
			array(
				'page' => 'wccr-cart-recovery',
				'tab'  => $tab,
			),
			admin_url('admin.php')
		);
	}

	/**
	 * Enqueue admin assets only on plugin pages.
	 */
	public function enqueue_assets(string $hook): void
	{
		if (false === strpos($hook, 'wccr')) {
			return;
		}

		wp_enqueue_style('wccr-admin', WCCR_PLUGIN_URL . 'assets/css/admin.css', array(), WCCR_VERSION);
		wp_enqueue_script('wccr-admin', WCCR_PLUGIN_URL . 'assets/js/admin.js', array(), WCCR_VERSION, true);
		wp_localize_script(
			'wccr-admin',
			'WCCRAdminI18n',
			array(
				'copyLabel'       => __('Copy URL', 'vfwoo_woocommerce-cart-recovery'),
				'copiedLabel'     => __('Copied', 'vfwoo_woocommerce-cart-recovery'),
				'deleteConfirm'   => __('Delete this recovery item?', 'vfwoo_woocommerce-cart-recovery'),
				'showEmailsLabel' => __('View email details', 'vfwoo_woocommerce-cart-recovery'),
				'hideEmailsLabel' => __('Hide email details', 'vfwoo_woocommerce-cart-recovery'),
				'ajaxUrl'         => admin_url('admin-ajax.php'),
				'exclusionNonce'  => wp_create_nonce('wccr_exclusion_search'),
				'searchingLabel'  => __('Searching…', 'vfwoo_woocommerce-cart-recovery'),
				'noResultsLabel'  => __('No matches found.', 'vfwoo_woocommerce-cart-recovery'),
				'resetNonce'      => wp_create_nonce('wccr_reset_step_locale'),
				'resetLabel'      => __('Reset to translated defaults', 'vfwoo_woocommerce-cart-recovery'),
				'resettingLabel'  => __('Resetting…', 'vfwoo_woocommerce-cart-recovery'),
				'saveNonce'       => wp_create_nonce('wccr_save_settings_ajax'),
				'savingLabel'     => __('Guardando…', 'vfwoo_woocommerce-cart-recovery'),
				'savedLabel'      => __('Guardado', 'vfwoo_woocommerce-cart-recovery'),
				'saveErrorLabel'  => __('Error al guardar', 'vfwoo_woocommerce-cart-recovery'),
			)
		);
	}
}
