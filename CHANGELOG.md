# Changelog

All notable changes to this project should be documented in this file.

The format is based on Keep a Changelog, and this project follows a simple `MAJOR.MINOR.PATCH` versioning scheme.

## [Unreleased]

### Added

### Changed

### Fixed

## [0.1.26] - 2026-04-07

### Changed
- Skipped recovery steps with discounts when the cart total stays below the configured minimum amount.
- Hid coupon placeholders and discount copy when no coupon is actually generated for the recovery email.
- Refreshed the admin CSS styling bundled in this working tree.

### Fixed
- Stored click tracking per email step so the admin can show step clicks more accurately without relying only on the last clicked step.

## [0.1.25] - 2026-04-07

### Changed
- Switched recurring plugin jobs from custom WP-Cron scheduling to WooCommerce Action Scheduler hooks.
- Updated AGENTS.md communication rules to keep execution output shorter and more direct.

### Fixed
- Fixed WPML cart locale resolution during capture.
- Fixed localized email fallback so empty saved translations no longer block usable defaults.
- Added clearer email failure diagnostics in the carts admin screen.
- Hardened recovery email rendering for serialized cart payloads so product rows no longer break on stored array data.

## [0.1.24] - 2026-04-07

### Added
- Added a per-step, per-language reset action to restore translated default email subject and body values.
- Documented the multilingual email flow and WPML-tested behavior in the README.

### Changed
- Improved locale fallback handling so settings tabs and email rendering can resolve the nearest supported plugin locale before falling back to English or the site default locale.

### Fixed
- Fixed multilingual email defaults so German and other variant locales can resolve the correct plugin translations instead of falling back to Spanish.
- Fixed plugin locale switching so plugin strings reload correctly for translated defaults and locale-aware email sending.

## [0.1.23] - 2026-04-07

### Added
- Added base English and German translation catalogs for plugin defaults.

### Changed
- Improved multilingual email settings tabs to behave like real admin tabs.
- Added translated default fallback for empty email subject/body tabs.

### Fixed
- Fixed WPML locale resolution so the email settings can detect active languages correctly.

## [0.1.22] - 2026-04-07

### Changed
- Moved plugin navigation under WooCommerce into a single Cart Recovery screen with internal Carts and Settings tabs.
- Updated translation files to cover the new admin navigation labels.
- Removed visible borders from the recovery email product and totals tables.

## [0.1.21] - 2026-04-06

### Fixed
- Prevented imported unpaid orders from being re-imported after they were already recovered through the recovery flow.

## [0.1.20] - 2026-04-06

### Added
- Added multilingual email settings with per-locale tabs for each recovery step subject and body.

### Changed
- Recovery emails now resolve subject and body by the cart locale, with fallback to the site default locale.
- Email rendering now switches locale at send time so plugin strings follow the language captured with the cart.
- Discount labels and placeholder comments were updated to keep translation extraction clean.

## [0.1.19] - 2026-04-06

### Changed
- Moved the manual recovery actions to the main Cart Recovery dashboard and displayed them in a responsive two-column layout.
- Reworked recovery emails to use a more WooCommerce-native product table and summary structure based on the active store installation.
- Refreshed translation files to include the latest email and admin strings.

## [0.1.18] - 2026-04-06

### Changed
- Refreshed the translation catalog and compiled language files to include the latest PHP and JavaScript admin strings.

## [0.1.17] - 2026-04-06

### Changed
- Highlighted the resolved email step with the same visual recovery treatment used for recovered states.

## [0.1.16] - 2026-04-06

### Changed
- Reworked the README to document the plugin flow, capture paths, recovery logic and WooCommerce/Blocks integration more clearly.
- Updated the project development rules in `AGENTS.md` with stricter planning, editing and validation guidance.

### Fixed
- Fixed archived statistics so deleting clicked or recovered items no longer inflates abandoned cart totals.
- Fixed admin totals to use a consistent WooCommerce money format across captured and imported cases.
- Fixed per-email resolved tracking so only the email step that actually recovered the purchase is marked as resolved.

## [0.1.15] - 2026-04-06

### Fixed
- Fixed restored checkout capture so recovery sessions no longer create a second abandoned cart with discounted totals.
- Fixed recovery order linking for WooCommerce Blocks checkout and reinforced recovered detection with payment-complete handling.
- Fixed imported and order-backed cases so matching follow-up carts reuse the same recovery case instead of duplicating it.

## [0.1.14] - 2026-04-06

### Fixed
- Fixed recovery attempts so a clicked cart no longer falls back to active when the restored checkout refreshes.
- Fixed paid imported unpaid orders so they can move to recovered through their linked WooCommerce order.
- Fixed recovery identity so a new attempt in the same browser creates a clean case instead of inheriting old email logs and coupons.

## [0.1.13] - 2026-04-06

### Changed
- Refined the recovery card layout with a cleaner two-line summary and per-user saved view mode.
- Reworked email step summaries to show compact inline tracking details and keep copy links attached to each email.

### Fixed
- Removed duplicated or low-value admin fields such as merged, reason, last error and eligible-at from the card view.
- Fixed list and card layouts so badges, summary rows and email blocks align more cleanly in both modes.

## [0.1.12] - 2026-04-06

### Changed
- Added a secondary origin badge in recovery cards to show imported vs captured cases.

### Fixed
- Improved unpaid-order import feedback with reviewed, imported, merged, updated and skipped counts.
- Added a clear notice when no eligible pending or failed orders are found.

## [0.1.11] - 2026-04-06

### Added
- Added archived recovery statistics storage to preserve metrics after operational cleanup.

### Changed
- Marked plugin-managed unpaid WooCommerce orders so cleanup and manual delete can handle them safely.
- Updated cleanup and delete flows to archive metrics before removing recovery rows.

### Fixed
- Fixed cleanup so sent email history is no longer deleted blindly with old operational rows.
- Fixed manual and automatic deletion so only plugin-owned pending or failed orders can be removed.

## [0.1.10] - 2026-04-05

### Added
- Added translation files for the general catalog and `es_ES`.

### Changed
- Localized admin JavaScript labels through WordPress.
- Localized recovery status badges in the admin cards.

## [0.1.9] - 2026-04-05

### Changed
- Added PHPDoc and small WPCS-oriented cleanup across the `domain` layer.

### Fixed
- Hardened recovery request input handling in the recovery service.

## [0.1.8] - 2026-04-05

### Changed
- Changed the plugin text domain to `vfwoo_woocommerce-cart-recovery`.
- Refactored the main recovery admin screen and cart repository into smaller documented methods.
- Documented admin JavaScript and key PHP services with inline PHPDoc/JSDoc.

### Fixed
- Hardened admin delete handling with explicit capability checks and nonce validation.
- Tightened admin sorting and duplicate cleanup structure to better follow WordPress-safe patterns.

## [0.1.7] - 2026-04-05

### Changed
- Improved recovery coupon codes to use clearer labels in the generated code itself.
- Improved email subject and body variable rendering with better customer name fallback.

### Fixed
- Fixed duplicate recovery rows caused by re-capturing the same open cart.
- Fixed recovery carts so unchanged clicked or abandoned carts do not restart their timing.
- Fixed recovery coupons for checkout flow by removing email-restriction conflicts.

## [0.1.6] - 2026-04-05

### Added
- Added recovery view helpers for coupon code and copy URL actions.
- Added admin JavaScript for copying recovery URLs.

### Changed
- Updated the recovery list to hide active carts.
- Improved clicked status display with clear Yes/No output and date when available.

### Fixed
- Simplified the recovery list so it focuses on abandoned, clicked and recovered carts only.

## [0.1.5] - 2026-04-05

### Added
- Added a shared email eligibility service for queue and admin diagnostics.
- Added admin diagnostics for step, eligible time and send reason.
- Added repository helpers for abandoned-cart queue inspection.

### Changed
- Unified abandoned-email eligibility logic between admin and scheduler.
- Allowed `1` minute as the minimum value in abandoned cart settings.

### Fixed
- Fixed fatal email rendering error in WooCommerce mail wrapper usage.
- Fixed scheduler error handling so send failures are logged instead of crashing the process.
- Fixed early textdomain initialization notice.

## [0.1.4] - 2026-04-05

### Added
- Initial working plugin structure for WooCommerce Cart Recovery.
- Admin pages for settings, cart list, and statistics.
- Capture flow for classic checkout and WooCommerce Checkout Blocks.
- Abandoned cart detection, email queue, recovery links, and coupon generation.
- WPML/Polylang locale resolver support with WordPress fallback.
- Order recovery tracking with click vs real purchase separation.
- Git repository setup with `main` and `dev` workflow.

### Changed
- Improved recovery logic so `recovered` only applies to real completed/processing orders.
- Updated email rendering to better match WooCommerce email styling.
- Tightened capture rules so carts without a valid email are not persisted.

### Fixed
- Fixed stats rendering for recovered revenue output.
- Fixed recovery flow so restored carts do not block future captures in the same session.
- Fixed timing issues around cron frequency and manual `Run now` behavior.
