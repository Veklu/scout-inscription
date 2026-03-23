<?php
defined('ABSPATH') || exit;

class Scout_Export {

    /**
     * Handle CSV export via REST API.
     */
    public static function handle_export(\WP_REST_Request $request): void {
        $filters = [
            'annee_scoute'   => sanitize_text_field($request->get_param('annee') ?? ''),
            'unite'          => sanitize_text_field($request->get_param('unite') ?? ''),
            'payment_status' => sanitize_text_field($request->get_param('payment') ?? ''),
        ];
        $filters = array_filter($filters);

        $inscriptions = Scout_Inscription_Model::list($filters, 1000, 0);

        $filename = 'inscriptions-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        // BOM for Excel UTF-8 compatibility
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, [
            __('Référence', 'scout-inscription'), __('Année scoute', 'scout-inscription'), __('Unité', 'scout-inscription'), __('Prénom enfant', 'scout-inscription'), __('Nom enfant', 'scout-inscription'),
            __('Date de naissance', 'scout-inscription'), __('Sexe', 'scout-inscription'), __('Adresse', 'scout-inscription'), __('Ville', 'scout-inscription'), __('Code postal', 'scout-inscription'),
            __('Parent 1 - Nom', 'scout-inscription'), __('Parent 1 - Téléphone', 'scout-inscription'), __('Parent 1 - Courriel', 'scout-inscription'),
            __('Contact urgence', 'scout-inscription'), __('Téléphone urgence', 'scout-inscription'),
            __('Statut paiement', 'scout-inscription'), __('Total dû', 'scout-inscription'), __('Reçu', 'scout-inscription'), __('Solde', 'scout-inscription'),
            __('Statut inscription', 'scout-inscription'), __('Date inscription', 'scout-inscription'),
        ], ';');

        foreach ($inscriptions as $ins) {
            $contacts = Scout_Contact_Model::get_for_inscription($ins->id);
            $parent1  = null;
            $urgence1 = null;

            foreach ($contacts as $c) {
                if ($c->type === 'parent' && $c->sort_order === 1) $parent1 = $c;
                if ($c->type === 'urgence' && $c->sort_order === 1) $urgence1 = $c;
            }

            fputcsv($output, [
                $ins->ref_number,
                $ins->annee_scoute,
                $ins->unite,
                $ins->enfant_prenom,
                $ins->enfant_nom,
                $ins->enfant_ddn,
                $ins->enfant_sexe,
                $ins->enfant_adresse,
                $ins->enfant_ville,
                $ins->enfant_code_postal,
                $parent1 ? ($parent1->prenom . ' ' . $parent1->nom) : '',
                $parent1 ? $parent1->telephone : '',
                $parent1 ? $parent1->courriel : '',
                $urgence1 ? $urgence1->nom : '',
                $urgence1 ? $urgence1->telephone : '',
                $ins->payment_status,
                number_format($ins->payment_total, 2),
                number_format($ins->payment_received, 2),
                number_format($ins->payment_total - $ins->payment_received, 2),
                $ins->status,
                $ins->created_at,
            ], ';');
        }

        fclose($output);

        Scout_Access_Log::log(get_current_user_id(), null, 'export',
            count($inscriptions) . ' inscriptions exported');

        exit;
    }
}
