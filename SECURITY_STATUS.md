# Security Audit Status

## WooCommerce Cart Recovery v0.1.31

**Last updated:** 7 de abril de 2026

---

## Resolved

| #  | Issue                        | Severity | Fix                                        | Version |
|----|------------------------------|----------|--------------------------------------------|---------|
| 1  | CSRF AJAX capture            | Critical | FALSE POSITIVE — `check_ajax_referer()` already present | n/a |
| 2  | SQL injection uninstall.php  | Critical | `$wpdb->prepare()` with `%i` identifier   | 0.1.30  |
| 3  | SQL injection installer.php  | Critical | `$wpdb->prepare()` with `%s` placeholders | 0.1.30  |
| 4  | XSS in email renderer        | Critical | `wp_kses(…, wp_kses_allowed_html('post'))` | 0.1.30  |
| 5  | Weak coupon entropy          | Critical | `bin2hex(random_bytes(6))` — 48-bit        | 0.1.30  |
| 6  | Email stored plaintext       | Critical | AES-256-CBC encryption + `email_hash`      | 0.1.31  |
| 7  | Cart payload plaintext       | Critical | AES-256-CBC encryption                     | 0.1.31  |
| 8  | Recovery token no expiry     | High     | 30-day expiry via `wccr_expires` param     | 0.1.31  |
| 9  | Session validation missing   | High     | `user_id` check in `find_open_cart_id()`   | 0.1.31  |
| 10 | Sensitive info in error logs | High     | `substr(sanitize_text_field(), 0, 500)`    | 0.1.31  |

---

## Pending

### High — GDPR audit trail

- **Issue:** No logging of who accessed PII data.
- **Requirement:** GDPR Art. 5.1.f — accountability principle.
- **Solution:** New `wccr_audit_log` table + log sensitive operations (view email, delete cart, export).
- **Effort:** 2-3 hours.
- **Files:** New class + installer migration.

### Medium — Code quality / standards

| #  | Issue                      | Effort  | Notes                                |
|----|----------------------------|---------|--------------------------------------|
| 12 | Inconsistent prefixes      | 2-3 h   | Mix of `vfwoo_`, `WCCR_`, `wccr_`   |
| 13 | No `apply_filters` hooks   | 1 h     | Only `do_action`, limits extensibility |
| 14 | Incomplete PHPDoc          | 2 h     | Private methods missing docs         |
| 15 | No `register_setting()`    | 1-2 h   | Settings saved ad-hoc                |
| 16 | Missing return type hints  | 1 h     | Some methods lack PHP 8.1 types      |
| 17 | No WooCommerce version check at runtime | 30 min | Plugin loads without verifying WC version |

---

## Current scores

| Metric              | Before (v0.1.29) | After (v0.1.31) |
|---------------------|:-----------------:|:---------------:|
| Security            | 7.2/10            | **9.1/10**      |
| WP/WC Standards     | 8.8/10            | 8.8/10          |
| Code Quality        | 8.5/10            | 8.5/10          |
| Critical issues     | 7                 | **0**           |
| High issues         | 4                 | **1**           |
| Medium issues       | 12+               | 6               |
