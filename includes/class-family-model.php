<?php
defined('ABSPATH') || exit;

class Scout_Family_Model {

    private static function table() {
        return SCOUT_DB_PREFIX . 'families';
    }

    /**
     * Create a new family and return it.
     */
    public static function create(string $email): ?object {
        global $wpdb;
        $token = bin2hex(random_bytes(32));
        $wpdb->insert(self::table(), [
            'family_token' => $token,
            'family_email' => Scout_Encryption::encrypt(sanitize_email($email)),
        ]);
        $id = $wpdb->insert_id;
        if (!$id) return null;
        return (object) ['id' => $id, 'family_token' => $token, 'family_email' => $email];
    }

    /**
     * Get family by token.
     */
    public static function get_by_token(string $token): ?object {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE family_token = %s", $token
        ));
        if ($row) {
            $row->family_email = Scout_Encryption::decrypt($row->family_email);
        }
        return $row;
    }

    /**
     * Get family by ID.
     */
    public static function get_by_id(int $id): ?object {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d", $id
        ));
        if ($row) {
            $row->family_email = Scout_Encryption::decrypt($row->family_email);
        }
        return $row;
    }

    /**
     * Find family by parent email across all inscriptions.
     */
    public static function find_by_email(string $email): ?object {
        global $wpdb;
        // First check families table directly
        $families = $wpdb->get_results("SELECT * FROM " . self::table());
        foreach ($families as $f) {
            $decrypted = Scout_Encryption::decrypt($f->family_email);
            if (strtolower($decrypted) === strtolower($email)) {
                $f->family_email = $decrypted;
                return $f;
            }
        }
        return null;
    }

    /**
     * Get all inscriptions for a family.
     */
    public static function get_inscriptions(int $family_id): array {
        global $wpdb;
        $table = SCOUT_DB_PREFIX . 'inscriptions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE family_id = %d ORDER BY annee_scoute DESC, created_at DESC", $family_id
        ));
        // Decrypt fields
        $encrypted_fields = ['enfant_prenom', 'enfant_nom', 'enfant_adresse'];
        foreach ($rows as &$r) {
            foreach ($encrypted_fields as $field) {
                if (!empty($r->$field)) {
                    $r->$field = Scout_Encryption::decrypt($r->$field);
                }
            }
        }
        return $rows;
    }

    /**
     * Get the most recent inscription for a child (by name + DOB) for renewal.
     */
    public static function get_latest_for_child(int $family_id, string $prenom, string $nom): ?object {
        $inscriptions = self::get_inscriptions($family_id);
        $best = null;
        foreach ($inscriptions as $ins) {
            if (strtolower($ins->enfant_prenom) === strtolower($prenom)
                && strtolower($ins->enfant_nom) === strtolower($nom)) {
                if (!$best || $ins->annee_scoute > $best->annee_scoute) {
                    $best = $ins;
                }
            }
        }
        return $best;
    }

    /**
     * Link an inscription to a family.
     */
    public static function link_inscription(int $inscription_id, int $family_id): bool {
        global $wpdb;
        $table = SCOUT_DB_PREFIX . 'inscriptions';
        return $wpdb->update($table, ['family_id' => $family_id], ['id' => $inscription_id]) !== false;
    }

    /**
     * Send dashboard access link by email.
     */
    public static function send_dashboard_link(object $family): bool {
        $url = home_url('/inscription/famille/?tok=' . $family->family_token);
        $group_name = get_bloginfo('name');
        $subject = "[{$group_name}] Accès à votre tableau de bord familial";

        $body = "<html><body style='font-family:sans-serif;padding:20px'>
            <h2 style='color:#007748'>⚜️ {$group_name}</h2>
            <p>Bonjour,</p>
            <p>Voici votre lien d'accès au tableau de bord familial :</p>
            <div style='background:#f0faf4;border:2px solid #007748;border-radius:12px;padding:20px;text-align:center;margin:20px 0'>
                <a href='" . esc_url($url) . "' style='display:inline-block;background:#007748;color:#fff;padding:14px 32px;border-radius:8px;font-weight:700;text-decoration:none;font-size:1rem'>Accéder au tableau de bord →</a>
            </div>
            <p style='font-size:13px;color:#6a6a62'>Ce lien est personnel et sécurisé. Ne le partagez pas.</p>
            <p style='font-size:12px;color:#999'>Conservez ce courriel pour accéder à votre tableau de bord à tout moment.</p>
        </body></html>";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($family->family_email, $subject, $body, $headers);
    }
}
