<?php
defined('ABSPATH') || exit;

class Scout_Payment_Model {

    private static function table(): string {
        return SCOUT_DB_PREFIX . 'payments';
    }

    /**
     * Add a payment.
     */
    public static function create(int $inscription_id, array $data): int|false {
        global $wpdb;

        $valid_modes = ['interac', 'comptant', 'cheque'];
        $mode = in_array($data['mode'] ?? '', $valid_modes) ? $data['mode'] : 'comptant';

        $insert = [
            'inscription_id' => $inscription_id,
            'mode'           => $mode,
            'montant'        => abs(floatval($data['montant'] ?? 0)),
            'date_recu'      => sanitize_text_field($data['date_recu'] ?? date('Y-m-d')),
            'note'           => sanitize_textarea_field($data['note'] ?? ''),
            'marked_by'      => get_current_user_id(),
        ];

        $result = $wpdb->insert(self::table(), $insert);

        if ($result !== false) {
            $id = $wpdb->insert_id;

            // Update inscription payment totals
            Scout_Inscription_Model::update_payment_totals($inscription_id);

            // Log
            Scout_Access_Log::log(get_current_user_id(), $inscription_id, 'payment_mark',
                "Paiement {$mode} de {$insert['montant']}$");

            return $id;
        }

        return false;
    }

    /**
     * Get payment history for an inscription.
     */
    public static function get_for_inscription(int $inscription_id): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, u.display_name as marked_by_name
             FROM " . self::table() . " p
             LEFT JOIN {$wpdb->users} u ON p.marked_by = u.ID
             WHERE p.inscription_id = %d
             ORDER BY p.date_recu DESC, p.created_at DESC",
            $inscription_id
        ));
    }

    /**
     * Get total received for an inscription.
     */
    public static function get_total(int $inscription_id): float {
        global $wpdb;
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(montant), 0) FROM " . self::table() . " WHERE inscription_id = %d",
            $inscription_id
        ));
    }

    /**
     * Delete a payment (admin only).
     */
    public static function delete(int $id): bool {
        global $wpdb;

        // Get inscription_id before deleting
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d", $id
        ));

        if (!$payment) return false;

        $result = $wpdb->delete(self::table(), ['id' => $id]);

        if ($result !== false) {
            Scout_Inscription_Model::update_payment_totals($payment->inscription_id);
            Scout_Access_Log::log(get_current_user_id(), $payment->inscription_id, 'payment_delete',
                "Paiement #{$id} supprimé");
            return true;
        }

        return false;
    }
}
