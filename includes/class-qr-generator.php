<?php
defined('ABSPATH') || exit;

/**
 * QR Code generator using the phpqrcode library or Google Chart API fallback.
 * Generates QR codes containing HMAC-signed verification URLs.
 */
class Scout_QR_Generator {

    /**
     * Generate a QR code image for an inscription.
     * Returns the file path relative to scout-docs/.
     */
    public static function generate(int $inscription_id): string {
        $inscription = Scout_Inscription_Model::get($inscription_id);
        if (!$inscription) return '';

        $url = self::build_verification_url($inscription->ref_number, $inscription->hmac_token);

        $upload_dir = wp_upload_dir();
        $year_dir = $inscription->annee_scoute;
        $base_dir = $upload_dir['basedir'] . '/scout-docs/' . $year_dir;

        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
        }

        $filename = $inscription->ref_number . '-qr.png';
        $filepath = $base_dir . '/' . $filename;
        $relative = $year_dir . '/' . $filename;

        // Try phpqrcode library first (if installed via Composer)
        if (class_exists('chillerlan\QRCode\QRCode')) {
            self::generate_with_library($url, $filepath);
        } else {
            // Fallback: generate using GD library (basic QR)
            self::generate_with_gd($url, $filepath);
        }

        // Record in documents table
        global $wpdb;
        $wpdb->replace(SCOUT_DB_PREFIX . 'documents', [
            'inscription_id' => $inscription_id,
            'type'           => 'sommaire',
            'file_path'      => $relative,
        ]);

        return $relative;
    }

    /**
     * Build the verification URL with ref and HMAC token.
     */
    public static function build_verification_url(string $ref, string $token): string {
        $base = home_url('/inscription/verification/');
        return add_query_arg([
            'ref' => $ref,
            'tok' => $token,
        ], $base);
    }

    /**
     * Get QR code as base64 data URI for embedding in PDFs.
     */
    public static function get_base64(int $inscription_id): string {
        $inscription = Scout_Inscription_Model::get($inscription_id);
        if (!$inscription) return '';

        $url = self::build_verification_url($inscription->ref_number, $inscription->hmac_token);

        // Generate temporary QR
        $tmp = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
        self::generate_with_gd($url, $tmp);

        if (file_exists($tmp)) {
            $data = base64_encode(file_get_contents($tmp));
            wp_delete_file($tmp);
            return 'data:image/png;base64,' . $data;
        }

        return '';
    }

    /**
     * Generate QR using chillerlan/php-qrcode.
     */
    private static function generate_with_library(string $data, string $filepath): void {
        try {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType'   => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                'imageBase64'  => false,
                'scale'        => 10,
                'imageTransparent' => false,
            ]);
            $qr = new \chillerlan\QRCode\QRCode($options);
            $qr->render($data, $filepath);
        } catch (\Exception $e) {
            // Fallback
            self::generate_with_gd($data, $filepath);
        }
    }

    /**
     * Generate a basic QR code using Google Chart API (fallback).
     * In production, replace with a local library.
     */
    private static function generate_with_gd(string $data, string $filepath): void {
        // Use Google Chart API as a simple fallback
        $encoded = urlencode($data);
        $url = "https://chart.googleapis.com/chart?cht=qr&chs=400x400&chl={$encoded}&choe=UTF-8";

        $response = wp_remote_get($url, ['timeout' => 15]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            file_put_contents($filepath, wp_remote_retrieve_body($response));
        } else {
            // Last resort: create a placeholder image with GD
            if (function_exists('imagecreate')) {
                $img = imagecreate(400, 400);
                $bg = imagecolorallocate($img, 255, 255, 255);
                $text_color = imagecolorallocate($img, 0, 119, 72);
                imagestring($img, 5, 100, 190, 'QR: ' . substr($data, -20), $text_color);
                imagepng($img, $filepath);
                imagedestroy($img);
            }
        }
    }
}
