<?php
defined('ABSPATH') || exit;

/**
 * GDPR-compliant audit logger for plugin actions.
 */
final class WCCR_Audit_Logger
{

    /**
     * Get the audit log table name.
     */
    public function get_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wccr_audit_log';
    }

    /**
     * Log an auditable action.
     *
     * @param string              $action      Action identifier (e.g. 'cart_deleted', 'email_sent').
     * @param string              $object_type Object type (e.g. 'cart', 'email').
     * @param int                 $object_id   Related object ID.
     * @param array<string,mixed> $extra       Optional extra context.
     */
    public function log(string $action, string $object_type, int $object_id, array $extra = []): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->get_table_name(),
            [
                'user_id'        => get_current_user_id(),
                'action'         => sanitize_key($action),
                'object_type'    => sanitize_key($object_type),
                'object_id'      => $object_id,
                'ip_address'     => self::anonymize_ip(self::get_client_ip()),
                'extra'          => $extra ? wp_json_encode($extra) : null,
                'created_at_gmt' => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Delete audit log entries older than a given number of days.
     *
     * @param int $days Retention period in days.
     */
    public function delete_older_than(int $days): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->get_table_name()} WHERE created_at_gmt < %s",
                gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS))
            )
        );
    }

    /**
     * Get the client IP address from trusted headers.
     */
    private static function get_client_ip(): string
    {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * Anonymize an IP address for GDPR compliance.
     *
     * IPv4: last octet set to 0.
     * IPv6: last 80 bits set to 0.
     */
    private static function anonymize_ip(string $ip): string
    {
        if ('' === $ip) {
            return '';
        }

        if (function_exists('wp_privacy_anonymize_ip')) {
            return wp_privacy_anonymize_ip($ip);
        }

        if (str_contains($ip, '.')) {
            return (string) preg_replace('/\.\d+$/', '.0', $ip);
        }

        return (string) preg_replace('/:[0-9a-f]*:[0-9a-f]*:[0-9a-f]*:[0-9a-f]*:[0-9a-f]*$/i', ':0:0:0:0:0', $ip);
    }
}
