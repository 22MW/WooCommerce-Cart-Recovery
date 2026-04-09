# Changelog

All notable changes to this project should be documented in this file.

The format is based on Keep a Changelog, and this project follows a simple `MAJOR.MINOR.PATCH` versioning scheme.

## [0.1.48] - 2026-04-09

### Changed

- "Delete selected" button styled red to match individual delete button.
- Removed "This action cannot be undone." from bulk delete warning and confirm dialog.
- Bulk Delete button and filters toolbar order swapped: Bulk Delete now above filters.
- Updated `.pot` and all translation catalogs with `Order date` string.

## [0.1.47] - 2026-04-09

### Fixed

- `abandoned_at_gmt` for imported order-backed carts is now always set to the import time so the email scheduler starts counting from capture, not from the original order date.
- `created_at_gmt` carries the real order date for display purposes only.
- Data migration (`wccr_db_version 0.1.47`) corrects `abandoned_at_gmt` on rows inserted with the wrong date in v0.1.46.

### Changed

- Cart card label "Abandoned at" renamed to "Abandoned cart created".
- Cart card now shows "Order date" (`created_at_gmt`) for imported orders only.
- Introduced `wccr_db_version` option to track and gate future data migrations.
- Added bulk delete: select individual carts or all at once via the checkbox bar and delete them in one action.

## [0.1.46] - 2026-04-08

### Fixed

- Pending/failed orders created before the plugin was activated are no longer auto-imported by the recurring sync task; only orders created after activation are considered. The manual import button still covers the full historical backlog.
- Orders whose customer email has a later completed, processing, or on-hold order are now skipped during import, preventing recovery emails to customers who already purchased.
- When a customer has multiple failed or pending orders, only the oldest eligible one is imported; subsequent orders for the same email are skipped to avoid duplicate recovery flows.
- Imported order-backed carts now use the original order date for `created_at_gmt`, `last_activity_gmt`, and `abandoned_at_gmt` instead of the import timestamp.

## [0.1.45] - 2026-04-08

### Fixed

- Default step settings now switch locale before resolving translated strings, so defaults load in the correct language on fresh installs
- Default discount type for steps 2 and 3 changed to none; removed coupon references from default email body text
- GitHub Release now includes the matching CHANGELOG entry as release body, so the changelog is visible in the WP plugin modal

## [0.1.44] - 2026-04-08

### Fixed

- Strip stale `_booking_id` from WooCommerce Bookings cart items before saving the payload, so a fresh booking is created on recovery instead of failing with a missing/expired booking

## [0.1.43] - 2026-04-08

### Changed

- Improved default email body text for all 3 steps: removed inline coupon dump, replaced with natural 2-3 sentence copy per step
- Updated translations for en_US, es_ES, de_DE, ca_ES

## [0.1.42] - 2026-04-08

### Fixed

- Auto-save now triggers when exclusion chips are added or removed (chip changes do not fire a DOM `change` event).

## [0.1.41] - 2026-04-08

### Fixed

- `get_abandoned_carts()` now includes `clicked` status so steps 2 and 3 are sent after the customer clicks a recovery link without completing the purchase.

## [0.1.40] - 2026-04-08

### Fixed

- Sincroniza expiración del enlace de recuperación con el ajuste `coupon_expiry_days` (antes fijo a 30 días).

### Added

- Settings: auto-guardado AJAX con debounce, sin botón submit. Toast de confirmación.
- Settings: switch toggle reemplaza checkbox en tarjetas de email. Campos deshabilitados (opacity + pointer-events) cuando el step está OFF.
- Settings: layout 2 columnas — idiomas en sidebar izquierda, sub-tabs Email 1/2/3 a la derecha por locale.
- Settings: tab de email deshabilitado cuando su step está OFF.
- Settings: texto de referencia de minutos bajo el campo "Marcar carrito abandonado".

### Improved

- Updater: modal de actualización muestra pestaña "Registro de cambios" con el body del release de GitHub, metadatos `requires` y `requires_php`, y autor con enlace.

## [0.1.38] - 2026-04-08

### Added

- Pre-rellena `billing_email` en checkout con el email del carrito abandonado via sesión WC (`woocommerce_checkout_get_value`).

## [0.1.37] - 2026-04-08

### Fixed

- GitHub updater: `filter_plugin_updates()` acepta `object|false` para evitar fatal error cuando el transient no está inicializado.
- Email CTA: eliminado whitespace entre `<td>` y `<a>` para evitar que `wpautop` inyecte un `<br>` en el botón de recuperación.
- WPML: `get_default_locale()` usa los filtros `wpml_default_language` y `wpml_locale_from_language` en lugar de `get_locale()` para obtener el idioma por defecto del sitio, no el del admin.

### Added

- Botón "Reset to translated defaults" funciona via AJAX sin recargar la página.

## [0.1.36] - 2026-04-08

### Fixed

- GitHub Actions workflow: añadido `permissions: contents: write` para que la Action pueda crear releases.

## [0.1.35] - 2026-04-07

### Fixed

- Carritos con status `clicked` ya se incluyen en la cola — los pasos 2 y 3 se envían aunque el cliente haya clicado sin pagar.
- Los enlaces de recuperación quedan inactivos en cuanto el carrito pasa a `recovered` — ninguno de los 3 pasos funciona tras un pago exitoso.
- Al recuperar un carrito se eliminan todos los cupones generados para ese carrito.
- Admin: botón "Copy URL" generaba token sin `expires` — ahora usa `build_recovery_url()` con token correcto.
- Sesión de WooCommerce no se persistía antes del redirect — usuarios invitado/incógnito llegaban al checkout con carrito vacío.

### Added

- Columna `recovered_total`: guarda el total real pagado (con descuentos) en lugar del total abandonado.
- Estadísticas de ingresos usan `recovered_total` con fallback a `cart_total` para registros anteriores.
- Botón CTA del email usa estructura `<table>` compatible con todos los clientes de email.

## [0.1.34] - 2026-04-07

### Fixed

- Restored admin menu hook registration so the plugin submenu loads again under WooCommerce.
- Dropped the legacy `email` index before `dbDelta()` so schema migrations to `TEXT` no longer fail on existing installs.

## [0.1.33] - 2026-04-07

### Added

- 8 `apply_filters` hooks for extensibility: `wccr_email_subject`, `wccr_email_content`, `wccr_recovery_url`, `wccr_cleanup_days`, `wccr_abandon_after_minutes`, `wccr_coupon_args`, `wccr_email_headers`, `wccr_email_eligibility`.
- `register_setting()` for `wccr_settings` option via Settings API.
- PHPDoc on 14 methods and 2 classes that were missing documentation.

### Changed

- Runtime requirements now verify WooCommerce >= 9.0 (matching plugin header).
- Added return type hints to 11 methods for PHP 8.1 consistency.

## [0.1.32] - 2026-04-07

### Added

- GDPR audit trail: new `wccr_audit_log` table with anonymized IP addresses.
- Audit logging for cart deletion (admin), email sends, and recovery link clicks.
- Automatic cleanup of old audit log entries during scheduled maintenance.

## [0.1.31] - 2026-04-07

### Added

- AES-256-CBC encryption for email and cart payload columns (GDPR Art. 32).
- SHA-256 `email_hash` column for secure email lookups without exposing plaintext.
- Automatic migration of existing plaintext PII on plugin activation.

### Changed

- Recovery tokens now expire after 30 days.
- Session validation prevents reusing a cart row owned by a different logged-in user.
- Error messages in email log sanitized and truncated to 500 characters.

### Fixed

- Deduplication queries now use `email_hash` instead of plaintext email.

## [0.1.30] - 2026-04-07

### Fixed

- **CRITICAL:** SQL injection in `uninstall.php` - Now using `$wpdb->prepare()` with identifier placeholders for DROP TABLE statements.
- **CRITICAL:** SQL injection in `class-installer.php` - Now using `$wpdb->prepare()` with parameterized values for UPDATE query.
- **CRITICAL:** XSS in `class-email-renderer.php` - Email body sanitized with `wp_kses_allowed_html('post')`.
- **CRITICAL:** Weak coupon entropy in `class-coupon-service.php` - Replaced `wp_generate_password(4)` with `bin2hex(random_bytes(6))`.

## [0.1.29] - 2026-04-07

### Changed

- Updated `README.md` with the latest recovery, multilingual and exclusion features.
- Reworked `readme.txt` to match the current plugin behavior and WordPress plugin readme format.

## [0.1.28] - 2026-04-07

### Added

- Added Catalan catalog files (`ca_ES`) for the plugin strings.

### Changed

- Refreshed the translation catalogs (`pot/po/mo`) for Spanish, English and German with the latest admin and email strings.
- Included the current admin CSS refinements in this release.

## [0.1.27] - 2026-04-07

### Added

- Added recovery exclusions for products and taxonomy terms, including autocomplete selectors and multilingual expansion across translated items.

### Changed

- Refined the Cart Recovery admin layout, header, settings cards, stats cards and cart cards for a cleaner responsive WooCommerce-style interface.
- Added per-cart email detail toggles and moved the email details action next to delete in the cart card footer.

### Fixed

- Prevented excluded carts and excluded unpaid orders from being captured or imported into recovery.

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
