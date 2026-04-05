<?php
/**
 * Plugin Name:       WooCommerce Cart Recovery
 * Plugin URI:        https://example.com/plugins/woocommerce-cart-recovery
 * Description:       Recover abandoned WooCommerce carts and pending orders with scheduled reminders, native coupons and locale-aware emails.
 * Version:           0.1.10
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            22MW
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vfwoo_woocommerce-cart-recovery
 * Domain Path:       /languages
 * WC requires at least: 9.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WCCR_VERSION', '0.1.10' );
define( 'WCCR_PLUGIN_FILE', __FILE__ );
define( 'WCCR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCCR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WCCR_PLUGIN_DIR . 'includes/class-requirements.php';
require_once WCCR_PLUGIN_DIR . 'includes/class-installer.php';
require_once WCCR_PLUGIN_DIR . 'includes/interfaces/interface-locale-resolver.php';
require_once WCCR_PLUGIN_DIR . 'includes/locale/class-default-locale-resolver.php';
require_once WCCR_PLUGIN_DIR . 'includes/locale/class-wpml-locale-resolver.php';
require_once WCCR_PLUGIN_DIR . 'includes/locale/class-polylang-locale-resolver.php';
require_once WCCR_PLUGIN_DIR . 'includes/locale/class-locale-resolver-manager.php';
require_once WCCR_PLUGIN_DIR . 'includes/repositories/class-settings-repository.php';
require_once WCCR_PLUGIN_DIR . 'includes/repositories/class-cart-repository.php';
require_once WCCR_PLUGIN_DIR . 'includes/repositories/class-email-log-repository.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-cart-capture-service.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-abandoned-cart-detector.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-pending-order-detector.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-coupon-service.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-email-eligibility-service.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-email-renderer.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-email-scheduler.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-recovery-service.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-cleanup-service.php';
require_once WCCR_PLUGIN_DIR . 'includes/domain/class-stats-service.php';
require_once WCCR_PLUGIN_DIR . 'includes/checkout/class-classic-checkout-capture-adapter.php';
require_once WCCR_PLUGIN_DIR . 'includes/checkout/class-blocks-checkout-capture-adapter.php';
require_once WCCR_PLUGIN_DIR . 'includes/checkout/class-checkout-capture-coordinator.php';
require_once WCCR_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
require_once WCCR_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
require_once WCCR_PLUGIN_DIR . 'includes/admin/class-abandoned-carts-page.php';
require_once WCCR_PLUGIN_DIR . 'includes/admin/class-stats-page.php';
require_once WCCR_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WCCR_Installer', 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! WCCR_Requirements::is_ready() ) {
			add_action( 'admin_notices', array( 'WCCR_Requirements', 'render_notice' ) );
			return;
		}

		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain( 'vfwoo_woocommerce-cart-recovery', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
				WCCR_Plugin::instance()->init();
			}
		);
	}
);
