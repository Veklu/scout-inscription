<?php
defined('ABSPATH') || exit;

class Scout_Contact_Model {

    private static function table(): string {
        return SCOUT_DB_PREFIX . 'contacts';
    }

    /**
     * Add a contact to an inscription.
     */
    public static function create(int $inscription_id, array $data): int|false {
        global $wpdb;

        $insert = [
            'inscription_id' => $inscription_id,
            'type'           => in_array($data['type'], ['parent', 'urgence']) ? $data['type'] : 'parent',
            'sort_order'     => absint($data['sort_order'] ?? 1),
            'prenom'         => Scout_Encryption::encrypt(sanitize_text_field($data['prenom'] ?? '')),
            'nom'            => Scout_Encryption::encrypt(sanitize_text_field($data['nom'] ?? '')),
            'lien'           => sanitize_text_field($data['lien'] ?? ''),
            'telephone'      => Scout_Encryption::encrypt(sanitize_text_field($data['telephone'] ?? '')),
            'cellulaire'     => Scout_Encryption::encrypt(sanitize_text_field($data['cellulaire'] ?? '')),
            'courriel'       => Scout_Encryption::encrypt(sanitize_email($data['courriel'] ?? '')),
            'resp_finances'  => !empty($data['resp_finances']) ? 1 : 0,
        ];

        $result = $wpdb->insert(self::table(), $insert);
        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Get all contacts for an inscription.
     */
    public static function get_for_inscription(int $inscription_id): array {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE inscription_id = %d ORDER BY type, sort_order",
            $inscription_id
        ));

        return array_map([self::class, 'decrypt_row'], $rows);
    }

    /**
     * Get parents only.
     */
    public static function get_parents(int $inscription_id): array {
        return array_filter(self::get_for_inscription($inscription_id), function($c) { return $c->type === 'parent'; });
    }

    /**
     * Get emergency contacts only.
     */
    public static function get_emergency(int $inscription_id): array {
        return array_filter(self::get_for_inscription($inscription_id), function($c) { return $c->type === 'urgence'; });
    }

    /**
     * Get the finance-responsible parent.
     */
    public static function get_finance_parent(int $inscription_id): ?object {
        $parents = self::get_parents($inscription_id);
        foreach ($parents as $p) {
            if ($p->resp_finances) return $p;
        }
        // Fallback to first parent
        return !empty($parents) ? reset($parents) : null;
    }

    /**
     * Delete all contacts for an inscription.
     */
    public static function delete_for_inscription(int $inscription_id): int {
        global $wpdb;
        return $wpdb->delete(self::table(), ['inscription_id' => $inscription_id]);
    }

    /**
     * Decrypt a contact row.
     */
    private static function decrypt_row(object $row): object {
        $encrypted = ['prenom', 'nom', 'telephone', 'cellulaire', 'courriel'];
        foreach ($encrypted as $field) {
            if (isset($row->$field) && $row->$field !== '') {
                $row->$field = Scout_Encryption::decrypt($row->$field);
            }
        }
        return $row;
    }
}
