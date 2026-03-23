<?php
defined('ABSPATH') || exit;

class Scout_Access_Log {

    private static function table(): string {
        return SCOUT_DB_PREFIX . 'access_log';
    }

    /**
     * Log an access event.
     */
    public static function log(int $user_id, ?int $inscription_id, string $action, string $details = ''): void {
        global $wpdb;

        $wpdb->insert(self::table(), [
            'user_id'        => $user_id ?: null,
            'inscription_id' => $inscription_id,
            'action'         => sanitize_text_field($action),
            'ip_address'     => self::get_ip(),
            'user_agent'     => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'details'        => sanitize_text_field($details),
        ]);
    }

    /**
     * Check rate limit for QR verification endpoint.
     * Returns true if the request should be allowed.
     */
    public static function check_rate_limit(string $ip, int $max_per_hour = 10): bool {
        global $wpdb;

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table() . "
             WHERE ip_address = %s
             AND action IN ('qr_scan_ok', 'qr_scan_fail')
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $ip
        ));

        return $count < $max_per_hour;
    }

    /**
     * Get access log for an inscription.
     */
    public static function get_for_inscription(int $inscription_id, int $limit = 100): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name as user_name
             FROM " . self::table() . " l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE l.inscription_id = %d
             ORDER BY l.created_at DESC
             LIMIT %d",
            $inscription_id, $limit
        ));
    }

    /**
     * Get recent log entries (admin overview).
     */
    public static function get_recent(int $limit = 50): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name as user_name
             FROM " . self::table() . " l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             ORDER BY l.created_at DESC
             LIMIT %d",
            $limit
        ));
    }

    private static function get_ip(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = explode(',', sanitize_text_field($_SERVER[$h]))[0];
                if (filter_var(trim($ip), FILTER_VALIDATE_IP)) return trim($ip);
            }
        }
        return '0.0.0.0';
    }
}
