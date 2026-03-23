<?php
defined('ABSPATH') || exit;

/**
 * AES-256-CBC encryption for sensitive data stored in DB.
 * Uses SCOUT_SECRET_KEY from wp-config.php.
 * If defuse/php-encryption is installed via Composer, use that instead.
 */
class Scout_Encryption {

    private static function get_key(): string {
        if (defined('SCOUT_SECRET_KEY') && SCOUT_SECRET_KEY) {
            return SCOUT_SECRET_KEY;
        }
        // Fallback: use WP auth key (not ideal but better than nothing)
        return wp_salt('auth');
    }

    /**
     * Encrypt a string.
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') return '';

        $key    = hash('sha256', self::get_key(), true);
        $iv     = random_bytes(16);
        $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($cipher === false) {
            return '';
        }

        // MAC for tamper detection
        $mac = hash_hmac('sha256', $iv . $cipher, $key, true);

        return base64_encode($mac . $iv . $cipher);
    }

    /**
     * Decrypt a string.
     */
    public static function decrypt(string $encoded): string {
        if ($encoded === '') return '';

        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 48) return '';

        $key       = hash('sha256', self::get_key(), true);
        $mac       = substr($raw, 0, 32);
        $iv        = substr($raw, 32, 16);
        $cipher    = substr($raw, 48);

        // Verify MAC
        $calc_mac = hash_hmac('sha256', $iv . $cipher, $key, true);
        if (!hash_equals($mac, $calc_mac)) {
            return ''; // Tampered data
        }

        $plaintext = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        return $plaintext !== false ? $plaintext : '';
    }

    /**
     * Encrypt a JSON-serializable array.
     */
    public static function encrypt_json(array $data): string {
        return self::encrypt(wp_json_encode($data));
    }

    /**
     * Decrypt to array.
     */
    public static function decrypt_json(string $encoded): array {
        $json = self::decrypt($encoded);
        if ($json === '') return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
