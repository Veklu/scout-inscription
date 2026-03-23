<?php
defined('ABSPATH') || exit;

class Scout_Inscription_Model {

    private static function table(): string {
        return SCOUT_DB_PREFIX . 'inscriptions';
    }

    /**
     * Generate a unique reference number: GM-{year}-{4 digits}
     */
    public static function generate_ref(): string {
        global $wpdb;
        $year = date('Y');
        $table = self::table();

        do {
            $num = random_int(1000, 9999);
            $ref = "GM-{$year}-{$num}";
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE ref_number = %s", $ref
            ));
        } while ($exists > 0);

        return $ref;
    }

    /**
     * Generate HMAC-SHA256 token for a reference number.
     */
    public static function generate_token(string $ref): string {
        $key = defined('SCOUT_SECRET_KEY') ? SCOUT_SECRET_KEY : wp_salt('auth');
        return substr(hash_hmac('sha256', $ref, $key), 0, 32);
    }

    /**
     * Verify HMAC token (timing-safe).
     */
    public static function verify_token(string $ref, string $token): bool {
        $expected = self::generate_token($ref);
        return hash_equals($expected, $token);
    }

    /**
     * Create a new inscription.
     */
    public static function create(array $data): int|false {
        global $wpdb;

        $ref   = self::generate_ref();
        $token = self::generate_token($ref);
        $year  = self::get_current_year();

        // Encrypt sensitive fields
        $encrypted_fields = ['enfant_prenom', 'enfant_nom', 'enfant_adresse',
                             'enfant_ville', 'enfant_telephone', 'assurance_maladie'];

        $insert = [
            'ref_number'             => $ref,
            'hmac_token'             => $token,
            'annee_scoute'           => $year,
            'unite'                  => sanitize_text_field($data['unite'] ?? ''),
            'enfant_ddn'             => sanitize_text_field($data['enfant_ddn'] ?? ''),
            'enfant_sexe'            => sanitize_text_field($data['enfant_sexe'] ?? ''),
            'enfant_code_postal'     => sanitize_text_field($data['enfant_code_postal'] ?? ''),
            'assurance_expiration'   => sanitize_text_field($data['assurance_expiration'] ?? ''),
            'date_entree_mouvement'  => sanitize_text_field($data['date_entree_mouvement'] ?? ''),
            'autres_enfants_groupe'  => !empty($data['autres_enfants_groupe']) ? 1 : 0,
            'autres_enfants_detail'  => sanitize_text_field($data['autres_enfants_detail'] ?? ''),
            'status'                 => 'brouillon',
            'payment_status'         => 'en_attente',
            'payment_total'          => floatval($data['payment_total'] ?? 0),
            'consents'               => wp_json_encode($data['consents'] ?? []),
        ];

        // Encrypt sensitive text fields
        foreach ($encrypted_fields as $field) {
            $insert[$field] = Scout_Encryption::encrypt(sanitize_text_field($data[$field] ?? ''));
        }

        // Medical data as encrypted JSON
        $insert['medical_data'] = Scout_Encryption::encrypt_json($data['medical_data'] ?? []);

        // Risk signature
        if (!empty($data['risk_signature'])) {
            $insert['risk_signature'] = Scout_Encryption::encrypt(wp_json_encode([
                'name' => sanitize_text_field($data['risk_signature']),
                'date' => current_time('mysql'),
                'ip'   => self::get_client_ip(),
            ]));
        }

        $result = $wpdb->insert(self::table(), $insert);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get inscription by reference number.
     */
    public static function get_by_ref(string $ref): ?object {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE ref_number = %s", $ref
        ));

        if (!$row) return null;

        return self::decrypt_row($row);
    }

    /**
     * Get inscription by ID.
     */
    public static function get(int $id): ?object {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d", $id
        ));

        if (!$row) return null;

        return self::decrypt_row($row);
    }

    /**
     * List inscriptions with filters.
     */
    public static function list(array $filters = [], int $limit = 50, int $offset = 0): array {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        // Exclude doublons by default (they still exist for QR verification)
        if (empty($filters['include_doublons'])) {
            $where[] = "status != 'doublon'";
        }

        if (!empty($filters['annee_scoute'])) {
            $where[] = 'annee_scoute = %s';
            $values[] = $filters['annee_scoute'];
        }
        if (!empty($filters['unite'])) {
            $where[] = 'unite = %s';
            $values[] = $filters['unite'];
        }
        if (!empty($filters['payment_status'])) {
            $where[] = 'payment_status = %s';
            $values[] = $filters['payment_status'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }

        $where_sql = implode(' AND ', $where);
        $values[] = $limit;
        $values[] = $offset;

        $sql = "SELECT * FROM " . self::table() . " WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values));

        return array_map([self::class, 'decrypt_row'], $rows);
    }

    /**
     * Count inscriptions with filters.
     */
    public static function count(array $filters = []): int {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        // Exclude doublons by default
        if (empty($filters['include_doublons']) && empty($filters['status_not_in'])) {
            $where[] = "status != 'doublon'";
	}

	if (!empty($filters['status_not_in']) && is_array($filters['status_not_in'])) {
            $placeholders = implode(',', array_fill(0, count($filters['status_not_in']), '%s'));
            $where[] = "status NOT IN ($placeholders)";
            $values = array_merge($values, $filters['status_not_in']);
        }

        if (!empty($filters['annee_scoute'])) {
            $where[] = 'annee_scoute = %s';
            $values[] = $filters['annee_scoute'];
        }
        if (!empty($filters['unite'])) {
            $where[] = 'unite = %s';
            $values[] = $filters['unite'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }
        if (!empty($filters['payment_status'])) {
            $where[] = 'payment_status = %s';
            $values[] = $filters['payment_status'];
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM " . self::table() . " WHERE {$where_sql}";

        if (!empty($values)) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, ...$values));
        }
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Update inscription status.
     */
    public static function update_status(int $id, string $status): bool {
        global $wpdb;
        return $wpdb->update(self::table(), ['status' => $status], ['id' => $id]) !== false;
    }

    /**
     * Update payment totals after a payment is added.
     */
    public static function update_payment_totals(int $id): void {
        global $wpdb;
        $total_received = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(montant), 0) FROM " . SCOUT_DB_PREFIX . "payments WHERE inscription_id = %d", $id
        ));

        $inscription = self::get($id);
        if (!$inscription) return;

        $new_status = 'en_attente';
        if ($total_received >= $inscription->payment_total) {
            $new_status = 'paye';
        } elseif ($total_received > 0) {
            $new_status = 'acompte_recu';
        }

        $wpdb->update(self::table(), [
            'payment_received' => $total_received,
            'payment_status'   => $new_status,
        ], ['id' => $id]);
    }

    /**
     * Directly update payment status (for cancellations/rejections).
     */
    public static function update_payment_status(int $id, string $status): bool {
        global $wpdb;
        return $wpdb->update(self::table(), ['payment_status' => $status], ['id' => $id]) !== false;
    }

    /**
     * Purge expired inscriptions (Loi 25 data retention).
     */
    public static function purge_expired(): int {
        global $wpdb;

        $retention_years = (int) get_option('scout_ins_retention_years', 2);
        $cutoff = date('Y-m-d', strtotime("-{$retention_years} years"));

        // Get IDs of inscriptions to purge
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE annee_scoute < %s AND status != 'annulee'",
            (date('Y') - $retention_years) . '-' . (date('Y') - $retention_years + 1)
        ));

        if (empty($ids)) return 0;

        $count = 0;
        foreach ($ids as $id) {
            // Delete contacts
            $wpdb->delete(SCOUT_DB_PREFIX . 'contacts', ['inscription_id' => $id]);

            // Delete documents (files + records)
            $docs = $wpdb->get_results($wpdb->prepare(
                "SELECT file_path FROM " . SCOUT_DB_PREFIX . "documents WHERE inscription_id = %d", $id
            ));
            foreach ($docs as $doc) {
                $full_path = wp_upload_dir()['basedir'] . '/scout-docs/' . $doc->file_path;
                if (file_exists($full_path)) {
                    wp_delete_file($full_path);
                }
            }
            $wpdb->delete(SCOUT_DB_PREFIX . 'documents', ['inscription_id' => $id]);

            // Null out personal data but keep anonymized record
            $wpdb->update(self::table(), [
                'enfant_prenom'    => '',
                'enfant_nom'       => '',
                'enfant_adresse'   => '',
                'enfant_ville'     => '',
                'enfant_telephone' => '',
                'assurance_maladie'=> '',
                'medical_data'     => '',
                'risk_signature'   => '',
                'consents'         => '{}',
                'status'           => 'annulee',
            ], ['id' => $id]);

            Scout_Access_Log::log(0, (int)$id, 'data_purge', 'Loi 25 automatic data retention purge');
            $count++;
        }

        return $count;
    }

    /**
     * Decrypt encrypted fields in a row.
     */
    public static function decrypt_row(object $row): object {
        $encrypted_text = ['enfant_prenom', 'enfant_nom', 'enfant_adresse',
                           'enfant_ville', 'enfant_telephone', 'assurance_maladie'];

        foreach ($encrypted_text as $field) {
            if (isset($row->$field) && $row->$field !== '') {
                $row->$field = Scout_Encryption::decrypt($row->$field);
            }
        }

        if (isset($row->medical_data) && $row->medical_data !== '') {
            $row->medical_data_decrypted = Scout_Encryption::decrypt_json($row->medical_data);
        }

        if (isset($row->risk_signature) && $row->risk_signature !== '') {
            $sig = Scout_Encryption::decrypt($row->risk_signature);
            $row->risk_signature_decrypted = $sig ? json_decode($sig, true) : [];
        }

        if (isset($row->consents) && is_string($row->consents)) {
            $row->consents = json_decode($row->consents, true) ?: [];
        }

        return $row;
    }

    /**
     * Get current scout year (e.g. "2025-2026").
     */
    public static function get_current_year(): string {
        $custom = get_option('scout_ins_current_year', '');
        if ($custom) return $custom;

        $month = (int) date('n');
        $year  = (int) date('Y');
        // Scout year starts in September
        if ($month < 9) $year--;
        return $year . '-' . ($year + 1);
    }

    private static function get_client_ip(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', sanitize_text_field($_SERVER[$header]))[0];
                if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                    return trim($ip);
                }
            }
        }
        return '0.0.0.0';
    }
}
