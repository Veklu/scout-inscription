<?php
defined('ABSPATH') || exit;

class Scout_MFA {

    /**
     * Check if current user has medical access role.
     */
    public static function user_has_medical_role(): bool {
        $allowed_roles = get_option('scout_ins_medical_roles', ['administrator']);
        if (!is_array($allowed_roles)) $allowed_roles = ['administrator'];

        $user = wp_get_current_user();
        if (!$user || !$user->ID) return false;

        foreach ($allowed_roles as $role) {
            if (in_array($role, $user->roles) || ($role === 'administrator' && current_user_can('manage_options'))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if MFA is required.
     */
    public static function is_mfa_required(): bool {
        return (bool) get_option('scout_ins_require_mfa', 1);
    }

    /**
     * Check if user has a valid MFA session.
     */
    public static function has_valid_session(): bool {
        if (!self::is_mfa_required()) return true;

        $user_id = get_current_user_id();
        if (!$user_id) return false;

        $session_expiry = get_user_meta($user_id, '_scout_mfa_session_expiry', true);
        if (!$session_expiry) return false;

        return time() < intval($session_expiry);
    }

    /**
     * Generate and send MFA code via email.
     */
    public static function send_code(): bool {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) return false;

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = time() + (15 * 60); // 15 min to enter code

        update_user_meta($user->ID, '_scout_mfa_code', wp_hash($code));
        update_user_meta($user->ID, '_scout_mfa_code_expiry', $expiry);
        update_user_meta($user->ID, '_scout_mfa_attempts', 0);

        $group_name = get_bloginfo('name');
        $subject = sprintf('[%s] %s', $group_name, __('Code de vérification — Accès données médicales', 'scout-inscription'));
        $body = "
            <html><body style='font-family:sans-serif;padding:20px'>
            <h2 style='color:#007748'>🔒 Code de vérification</h2>
            <p>Vous avez demandé l'accès aux données médicales dans le système d'inscription du {$group_name}.</p>
            <div style='background:#f0faf4;border:2px solid #007748;border-radius:12px;padding:24px;text-align:center;margin:20px 0'>
                <div style='font-size:36px;font-weight:700;color:#007748;letter-spacing:8px;font-family:monospace'>{$code}</div>
            </div>
            <p>Ce code est valide pour <strong>15 minutes</strong>.</p>
            <p style='color:#c0392b'><strong>⚠️ Si vous n'avez pas fait cette demande, ignorez ce courriel et changez votre mot de passe.</strong></p>
            <p style='font-size:12px;color:#6a6a62'>Cet accès sera journalisé conformément à la Loi 25 du Québec.</p>
            </body></html>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        Scout_Access_Log::log($user->ID, null, 'mfa_code_sent', "Code MFA envoyé à {$user->user_email}");

        return wp_mail($user->user_email, $subject, $body, $headers);
    }

    /**
     * Verify MFA code and create session.
     */
    public static function verify_code(string $input_code): array {
        $user_id = get_current_user_id();
        if (!$user_id) return ['success' => false, 'error' => __('Non connecté.', 'scout-inscription')];

        // Rate limit — max 5 attempts
        $attempts = intval(get_user_meta($user_id, '_scout_mfa_attempts', true));
        if ($attempts >= 5) {
            Scout_Access_Log::log($user_id, null, 'mfa_locked', 'Trop de tentatives MFA');
            return ['success' => false, 'error' => __('Trop de tentatives. Demandez un nouveau code.', 'scout-inscription')];
        }

        // Check expiry
        $code_expiry = intval(get_user_meta($user_id, '_scout_mfa_code_expiry', true));
        if (time() > $code_expiry) {
            return ['success' => false, 'error' => __('Code expiré. Demandez un nouveau code.', 'scout-inscription')];
        }

        // Verify code (timing-safe)
        $stored_hash = get_user_meta($user_id, '_scout_mfa_code', true);
        if (!hash_equals($stored_hash, wp_hash($input_code))) {
            update_user_meta($user_id, '_scout_mfa_attempts', $attempts + 1);
            Scout_Access_Log::log($user_id, null, 'mfa_fail', "Tentative MFA échouée ({$attempts}/5)");
            return ['success' => false, 'error' => sprintf(
                /* translators: %d: number of remaining attempts */
                __('Code incorrect. %d tentatives restantes.', 'scout-inscription'),
                4 - $attempts
            )];
        }

        // Success — create session
        $duration = intval(get_option('scout_ins_mfa_duration', 15)) * 60;
        update_user_meta($user_id, '_scout_mfa_session_expiry', time() + $duration);

        // Clear code
        delete_user_meta($user_id, '_scout_mfa_code');
        delete_user_meta($user_id, '_scout_mfa_code_expiry');
        delete_user_meta($user_id, '_scout_mfa_attempts');

        Scout_Access_Log::log($user_id, null, 'mfa_success', 'Session MFA activée pour ' . ($duration / 60) . ' min');

        return ['success' => true];
    }

    /**
     * Check full access: role + MFA.
     */
    public static function can_access_medical(): bool {
        return self::user_has_medical_role() && self::has_valid_session();
    }
}
