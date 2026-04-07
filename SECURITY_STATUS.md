# Security Audit Status

## WooCommerce Cart Recovery v0.1.32

**Last updated:** 7 de abril de 2026

---

## Resolved

| #   | Issue                        | Severity | Fix                                                     | Version |
| --- | ---------------------------- | -------- | ------------------------------------------------------- | ------- |
| 1   | CSRF AJAX capture            | Critical | FALSE POSITIVE — `check_ajax_referer()` already present | n/a     |
| 2   | SQL injection uninstall.php  | Critical | `$wpdb->prepare()` with `%i` identifier                 | 0.1.30  |
| 3   | SQL injection installer.php  | Critical | `$wpdb->prepare()` with `%s` placeholders               | 0.1.30  |
| 4   | XSS in email renderer        | Critical | `wp_kses(…, wp_kses_allowed_html('post'))`              | 0.1.30  |
| 5   | Weak coupon entropy          | Critical | `bin2hex(random_bytes(6))` — 48-bit                     | 0.1.30  |
| 6   | Email stored plaintext       | Critical | AES-256-CBC encryption + `email_hash`                   | 0.1.31  |
| 7   | Cart payload plaintext       | Critical | AES-256-CBC encryption                                  | 0.1.31  |
| 8   | Recovery token no expiry     | High     | 30-day expiry via `wccr_expires` param                  | 0.1.31  |
| 9   | Session validation missing   | High     | `user_id` check in `find_open_cart_id()`                | 0.1.31  |
| 10  | Sensitive info in error logs | High     | `substr(sanitize_text_field(), 0, 500)`                 | 0.1.31  |
| 11  | No GDPR audit trail          | High     | `wccr_audit_log` table + anonymized IP                  | 0.1.32  |

---

## Pending — Code quality / standards

| #   | Issue                     | Effort | Notes                                                   |
| --- | ------------------------- | ------ | ------------------------------------------------------- |
| 12  | Inconsistent text domain  | 2-3 h  | `vfwoo_` prefix vs `wccr_` convention (affects .po/.mo) |
| 13  | No `apply_filters` hooks  | 1 h    | 0 own filters, 7 `do_action` only                       |
| 14  | Incomplete PHPDoc         | 1 h    | ~14 methods + 2 classes missing docs                    |
| 15  | No `register_setting()`   | 1-2 h  | Manual nonce+save works but outside Settings API        |
| 16  | Missing return type hints | 30 min | ~11 methods                                             |
| 17  | No WC version check       | 15 min | `WC requires at least: 9.0` declared but not verified   |

---

## Current scores

| Metric          | Before (v0.1.29) | After (v0.1.32) |
| --------------- | :--------------: | :-------------: |
| Security        |      7.2/10      |   **9.5/10**    |
| WP/WC Standards |      8.8/10      |     8.8/10      |
| Code Quality    |      8.5/10      |     8.5/10      |
| Critical issues |        7         |      **0**      |
| High issues     |        4         |      **0**      |
| Medium issues   |       12+        |      **6**      |
