=== WooCommerce Cart Recovery ===
Contributors: 22mw
Tags: woocommerce, abandoned cart, cart recovery, coupons
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.28
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover abandoned WooCommerce carts with scheduled reminder emails, native coupons and locale-aware recovery links.

== Description ==

WooCommerce Cart Recovery helps store owners recover abandoned carts and unpaid WooCommerce orders using up to three reminder emails, optional native coupons, signed recovery links and local statistics.

Features:

* Captures carts once a valid customer email is available.
* Supports classic checkout and WooCommerce Checkout Blocks.
* Detects abandoned carts after a configurable inactivity delay.
* Sends up to three recovery emails with independent delays and discount rules.
* Uses native WooCommerce coupons when a step requires a discount.
* Restores carts with signed recovery URLs.
* Tracks sent, clicked and resolved recovery steps.
* Imports eligible pending and failed WooCommerce orders into the recovery flow.
* Supports multilingual email subjects and bodies by locale.
* Includes translation catalogs for `es_ES`, `en_US`, `de_DE` and `ca_ES`.
* Lets you exclude products and taxonomy terms from recovery, including their WPML/Polylang translations.
* Uses WooCommerce Action Scheduler for recurring recovery jobs.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/woocommerce-cart-recovery`.
2. Activate the plugin from WordPress admin.
3. Go to Cart Recovery > Settings.

== Frequently Asked Questions ==

= Does it work with WooCommerce Checkout Blocks? =

Yes. The plugin captures recovery data from classic checkout and Checkout Blocks / Store API flows.

= Can I recover unpaid orders too? =

Yes. Pending and failed WooCommerce orders can be imported into the same recovery workflow when they match the plugin rules.

= Can I configure different email content per language? =

Yes. If WPML or Polylang is active, the plugin shows separate email tabs by locale and sends each recovery email using the locale captured with the cart or order.

= Can I exclude products from recovery? =

Yes. You can exclude individual products and taxonomy terms from Settings. Exclusions also expand to translated products and terms when WPML or Polylang is active.

== Changelog ==

= 0.1.28 =
* Updated translation catalogs.
* Added Catalan (`ca_ES`) language files.
* Included latest admin CSS refinements.

= 0.1.27 =
* Added recovery exclusions for products and taxonomy terms.
* Added multilingual expansion for excluded products and terms.
* Added autocomplete selectors in Settings.
* Refined the Cart Recovery admin interface and cart card actions.

= 0.1.26 =
* Skipped discount steps when the cart total is below the configured minimum.
* Hid coupon and discount placeholders when no coupon is generated.
* Improved click tracking per email step.

= 0.1.25 =
* Switched recurring jobs to WooCommerce Action Scheduler.
* Fixed email rendering issues with serialized cart snapshots.
* Improved WPML locale capture and localized email fallback handling.
