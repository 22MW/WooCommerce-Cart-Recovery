# Changelog

All notable changes to this project should be documented in this file.

The format is based on Keep a Changelog, and this project follows a simple `MAJOR.MINOR.PATCH` versioning scheme.

## [Unreleased]

### Added

### Changed

### Fixed

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
