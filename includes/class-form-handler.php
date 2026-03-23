<?php
defined('ABSPATH') || exit;

class Scout_Form_Handler {

    /**
     * Process the complete 5-step form submission.
     * Returns ['success' => bool, 'ref' => string, 'errors' => array]
     */
    public static function process(array $data): array {
        $errors = self::validate($data);

        if (!empty($errors)) {
            return ['success' => false, 'ref' => '', 'errors' => $errors];
        }

        // ── DUPLICATE CHECK ──
        // Check if an inscription already exists for this child (same DOB + parent email + year)
        $current_year = Scout_Inscription_Model::get_current_year();
        $child_ddn = sanitize_text_field($data['enfant_ddn'] ?? '');
        $parent_email = sanitize_email($data['parent_1_courriel'] ?? '');
        $child_prenom = sanitize_text_field($data['enfant_prenom'] ?? '');
        $child_nom = sanitize_text_field($data['enfant_nom'] ?? '');

        if ($child_ddn && $parent_email) {
            $duplicate = self::check_duplicate($child_ddn, $parent_email, $child_prenom, $child_nom, $current_year);
            if ($duplicate) {
                return [
                    'success' => false,
                    'ref' => $duplicate->ref_number,
                    'errors' => [
                        'Une inscription existe déjà pour cet enfant cette année (réf: ' . $duplicate->ref_number . '). ' .
                        'Si vous souhaitez modifier l\'inscription, veuillez contacter le groupe.'
                    ],
                ];
            }
        }

        // Determine unit from age
        $unite = sanitize_text_field($data['unite'] ?? '');

        // Calculate payment total based on unit pricing for the current year
        $current_year = Scout_Inscription_Model::get_current_year();
        $pricing_years = get_option('scout_ins_pricing_years', []);
        if (isset($pricing_years[$current_year])) {
            $pricing = $pricing_years[$current_year];
        } else {
            $pricing = get_option('scout_ins_pricing', [
                'castors' => 245, 'louveteaux' => 285, 'eclaireurs' => 285, 'pionniers' => 285,
            ]);
        }
        $payment_total = floatval($pricing[$unite] ?? 285);

        // Build medical data array
        $medical_data = [
            'attention_particuliere'   => sanitize_text_field($data['attention_particuliere'] ?? 'non'),
            'attention_detail'         => sanitize_textarea_field($data['attention_detail'] ?? ''),
            'vaccins_jour'             => sanitize_text_field($data['vaccins_jour'] ?? 'oui'),
            'limite_physique'          => sanitize_text_field($data['limite_physique'] ?? 'non'),
            'limite_detail'            => sanitize_textarea_field($data['limite_detail'] ?? ''),
            'commentaires'             => sanitize_textarea_field($data['commentaires_medicaux'] ?? ''),
            'medicaments'              => sanitize_textarea_field($data['medicaments'] ?? ''),
            'allergies_alimentaires'   => sanitize_textarea_field($data['allergies_alimentaires'] ?? ''),
            'allergies_medicament'     => sanitize_textarea_field($data['allergies_medicament'] ?? ''),
            'restrictions_alimentaires'=> sanitize_textarea_field($data['restrictions_alimentaires'] ?? ''),
        ];

        // Build consents array with timestamps
        $now = current_time('mysql');
        $consents = [
            'donnees'         => ['accepted' => !empty($data['consent_donnees']),         'timestamp' => $now],
            'photos'          => ['accepted' => !empty($data['consent_photos']),           'timestamp' => $now],
            'risque'          => ['accepted' => !empty($data['consent_risque']),           'timestamp' => $now],
            'conditions'      => ['accepted' => !empty($data['consent_conditions']),       'timestamp' => $now],
            'confidentialite' => ['accepted' => !empty($data['consent_confidentialite']),  'timestamp' => $now],
        ];

        // Create inscription
        $inscription_id = Scout_Inscription_Model::create([
            'unite'                  => $unite,
            'enfant_prenom'          => $data['enfant_prenom'] ?? '',
            'enfant_nom'             => $data['enfant_nom'] ?? '',
            'enfant_ddn'             => $data['enfant_ddn'] ?? '',
            'enfant_sexe'            => $data['enfant_sexe'] ?? '',
            'enfant_adresse'         => $data['enfant_adresse'] ?? '',
            'enfant_ville'           => $data['enfant_ville'] ?? '',
            'enfant_code_postal'     => $data['enfant_code_postal'] ?? '',
            'enfant_telephone'       => $data['enfant_telephone'] ?? '',
            'assurance_maladie'      => $data['assurance_maladie'] ?? '',
            'assurance_expiration'   => $data['assurance_expiration'] ?? '',
            'date_entree_mouvement'  => $data['date_entree_mouvement'] ?? '',
            'autres_enfants_groupe'  => $data['autres_enfants_groupe'] ?? 0,
            'autres_enfants_detail'  => $data['autres_enfants_detail'] ?? '',
            'medical_data'           => $medical_data,
            'risk_signature'         => $data['risk_signature'] ?? '',
            'consents'               => $consents,
            'payment_total'          => $payment_total,
        ]);

        if (!$inscription_id) {
            return ['success' => false, 'ref' => '', 'errors' => ['Erreur lors de la création de l\'inscription.']];
        }

        // Add parents
        $parent_count = absint($data['parent_count'] ?? 1);
        for ($i = 1; $i <= min($parent_count, 4); $i++) {
            $prefix = "parent_{$i}_";
            if (empty($data[$prefix . 'prenom']) && empty($data[$prefix . 'nom'])) continue;

            Scout_Contact_Model::create($inscription_id, [
                'type'           => 'parent',
                'sort_order'     => $i,
                'prenom'         => $data[$prefix . 'prenom'] ?? '',
                'nom'            => $data[$prefix . 'nom'] ?? '',
                'lien'           => $data[$prefix . 'lien'] ?? '',
                'telephone'      => $data[$prefix . 'telephone'] ?? '',
                'cellulaire'     => $data[$prefix . 'cellulaire'] ?? '',
                'courriel'       => $data[$prefix . 'courriel'] ?? '',
                'resp_finances'  => !empty($data[$prefix . 'resp_finances']),
            ]);
        }

        // Add emergency contacts
        $emergency_count = absint($data['emergency_count'] ?? 1);
        for ($i = 1; $i <= min($emergency_count, 4); $i++) {
            $prefix = "urgence_{$i}_";
            if (empty($data[$prefix . 'nom'])) continue;

            Scout_Contact_Model::create($inscription_id, [
                'type'       => 'urgence',
                'sort_order' => $i,
                'nom'        => $data[$prefix . 'nom'] ?? '',
                'prenom'     => '',
                'telephone'  => $data[$prefix . 'telephone'] ?? '',
                'lien'       => $data[$prefix . 'lien'] ?? '',
            ]);
        }

        // Mark as complete
        Scout_Inscription_Model::update_status($inscription_id, 'complete');

        // Generate QR code
        Scout_QR_Generator::generate($inscription_id);

        // Generate PDFs
        Scout_PDF_Generator::generate_all($inscription_id);

        // Send confirmation email
        Scout_Email_Handler::send_confirmation($inscription_id);

        // Log
        Scout_Access_Log::log(0, $inscription_id, 'inscription_create', 'New registration completed');

        $inscription = Scout_Inscription_Model::get($inscription_id);

        // Create or link to family
        $family_token = '';
        $parent_email = sanitize_email($data['parent_1_courriel'] ?? '');
        if ($parent_email && class_exists('Scout_Family_Model')) {
            // Check if family already exists for this email
            $family = Scout_Family_Model::find_by_email($parent_email);
            if (!$family) {
                $family = Scout_Family_Model::create($parent_email);
            }
            if ($family) {
                Scout_Family_Model::link_inscription($inscription_id, $family->id);
                $family_token = $family->family_token;
                // Send dashboard link
                Scout_Family_Model::send_dashboard_link($family);
            }
        }

        return [
            'success'      => true,
            'ref'          => $inscription->ref_number,
            'token'        => $inscription->hmac_token,
            'family_token' => $family_token,
            'errors'       => [],
        ];
    }

    /**
     * Validate the form data.
     */
    private static function validate(array $data): array {
        $errors = [];

        // Step 1: Child info
        if (empty($data['enfant_prenom']))  $errors[] = 'Le prénom de l\'enfant est requis.';
        if (empty($data['enfant_nom']))     $errors[] = 'Le nom de l\'enfant est requis.';
        if (empty($data['enfant_ddn']))     $errors[] = 'La date de naissance est requise.';
        if (empty($data['unite']))          $errors[] = 'L\'unité est requise.';

        // At least one parent
        if (empty($data['parent_1_prenom']) && empty($data['parent_1_nom'])) {
            $errors[] = 'Au moins un parent/tuteur est requis.';
        }

        // At least one emergency contact
        if (empty($data['urgence_1_nom'])) {
            $errors[] = 'Au moins un contact d\'urgence est requis.';
        }

        // Step 3: Risk signature
        if (empty($data['risk_signature'])) {
            $errors[] = 'La signature d\'acceptation des risques est requise.';
        }

        // Step 4: Required consents
        if (empty($data['consent_donnees']))         $errors[] = 'Le consentement à la collecte de données est requis.';
        if (empty($data['consent_risque']))           $errors[] = 'Le consentement à l\'acceptation de risques est requis.';
        if (empty($data['consent_conditions']))       $errors[] = 'L\'acceptation des conditions est requise.';
        if (empty($data['consent_confidentialite']))  $errors[] = 'L\'acceptation de la politique de confidentialité est requise.';

        return $errors;
    }

    /**
     * Check for duplicate inscription.
     * Since child name is encrypted, we check by:
     * 1. Find all inscriptions for this year with same DOB (unencrypted field)
     * 2. For each match, decrypt and compare child name + check parent email in contacts
     * Only active statuses are checked (not annulee/rejetee).
     */
    private static function check_duplicate(string $ddn, string $parent_email, string $prenom, string $nom, string $year): ?object {
        global $wpdb;
        $table = SCOUT_DB_PREFIX . 'inscriptions';
        $contacts_table = SCOUT_DB_PREFIX . 'contacts';

        // Find inscriptions with same DOB in current year that are not cancelled/rejected
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, ref_number, enfant_prenom, enfant_nom, status FROM {$table} 
             WHERE annee_scoute = %s AND enfant_ddn = %s AND status NOT IN ('annulee', 'rejetee')",
            $year, $ddn
        ));

        if (empty($rows)) return null;

        foreach ($rows as $row) {
            // Decrypt child name and compare
            $dec_prenom = Scout_Encryption::decrypt($row->enfant_prenom);
            $dec_nom = Scout_Encryption::decrypt($row->enfant_nom);

            $name_match = (
                mb_strtolower(trim($dec_prenom)) === mb_strtolower(trim($prenom)) &&
                mb_strtolower(trim($dec_nom)) === mb_strtolower(trim($nom))
            );

            if (!$name_match) continue;

            // Name + DOB match — now check if parent email matches too
            if ($parent_email) {
                $contacts = $wpdb->get_results($wpdb->prepare(
                    "SELECT courriel FROM {$contacts_table} WHERE inscription_id = %d AND type = 'parent'",
                    $row->id
                ));

                foreach ($contacts as $contact) {
                    $dec_email = Scout_Encryption::decrypt($contact->courriel);
                    if (mb_strtolower(trim($dec_email)) === mb_strtolower(trim($parent_email))) {
                        return $row; // Full duplicate found
                    }
                }

                // Name + DOB match but different parent email — still flag it
                // (same child, possibly different parent registering)
                return $row;
            }

            // Name + DOB match, no email to compare — flag as duplicate
            return $row;
        }

        return null;
    }
}
