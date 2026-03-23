<?php
defined('ABSPATH') || exit;

class Scout_REST_API {

    public function register_routes(): void {
        $ns = 'scout-gm/v1';

        // Submit inscription (public, nonce-protected)
        register_rest_route($ns, '/inscription', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submit_inscription'],
            'permission_callback' => '__return_true',
        ]);

        // Verify QR code (public, rate-limited)
        register_rest_route($ns, '/verify', [
            'methods'             => 'GET',
            'callback'            => [$this, 'verify_qr'],
            'permission_callback' => '__return_true',
        ]);

        // Get single inscription
        register_rest_route($ns, '/inscription/(?P<ref>[A-Z0-9-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_inscription'],
            'permission_callback' => [$this, 'can_view'],
        ]);

        // List inscriptions
        register_rest_route($ns, '/inscriptions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'list_inscriptions'],
            'permission_callback' => [$this, 'can_view'],
        ]);

        // Add payment
        register_rest_route($ns, '/inscription/(?P<ref>[A-Z0-9-]+)/payment', [
            'methods'             => 'POST',
            'callback'            => [$this, 'add_payment'],
            'permission_callback' => [$this, 'can_manage_payments'],
        ]);

        // Export
        register_rest_route($ns, '/inscriptions/export', [
            'methods'             => 'GET',
            'callback'            => [$this, 'export_csv'],
            'permission_callback' => [$this, 'can_export'],
        ]);

        // Download PDF — permission checked inside handler (allows direct browser access)
        register_rest_route($ns, '/inscription/(?P<ref>[A-Z0-9-]+)/pdf/(?P<type>[a-z_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'download_pdf'],
            'permission_callback' => '__return_true',
        ]);

        // Admin actions: approve, reject, payment plan
        register_rest_route($ns, '/inscription/(?P<ref>[A-Z0-9-]+)/approve', [
            'methods'  => 'POST',
            'callback' => [$this, 'approve_inscription'],
            'permission_callback' => [$this, 'can_view'],
        ]);
        register_rest_route($ns, '/inscription/(?P<ref>[A-Z0-9-]+)/reject', [
            'methods'  => 'POST',
            'callback' => [$this, 'reject_inscription'],
            'permission_callback' => [$this, 'can_view'],
        ]);
        register_rest_route($ns, '/inscription/(?P<ref>[A-Z0-9-]+)/payment-plan', [
            'methods'  => 'POST',
            'callback' => [$this, 'set_payment_plan'],
            'permission_callback' => [$this, 'can_manage_payments'],
        ]);

        // MFA endpoints
        register_rest_route($ns, '/mfa/send', [
            'methods'  => 'POST',
            'callback' => [$this, 'mfa_send_code'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        register_rest_route($ns, '/mfa/verify', [
            'methods'  => 'POST',
            'callback' => [$this, 'mfa_verify_code'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        register_rest_route($ns, '/mfa/status', [
            'methods'  => 'GET',
            'callback' => [$this, 'mfa_status'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Medical data viewer (HTML, not PDF)
        register_rest_route($ns, '/inscription/(?P<ref>[A-Z0-9-]+)/medical', [
            'methods'  => 'GET',
            'callback' => [$this, 'view_medical'],
            'permission_callback' => [$this, 'can_view_medical'],
        ]);

        // Pricing
        register_rest_route($ns, '/pricing', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_pricing'],
            'permission_callback' => '__return_true',
        ]);

        // Family dashboard
        register_rest_route($ns, '/family/lookup', [
            'methods'  => 'POST',
            'callback' => [$this, 'family_lookup'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/family/send-link', [
            'methods'  => 'POST',
            'callback' => [$this, 'family_send_link'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/family/(?P<tok>[a-f0-9]+)/dashboard', [
            'methods'  => 'GET',
            'callback' => [$this, 'family_dashboard'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/family/(?P<tok>[a-f0-9]+)/renew/(?P<ref>[A-Z0-9-]+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'family_renew_data'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── SUBMIT INSCRIPTION ──
    public function submit_inscription(\WP_REST_Request $request): \WP_REST_Response {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_REST_Response(['error' => 'Nonce invalide.'], 403);
        }

        $data   = $request->get_json_params();
        $result = Scout_Form_Handler::process($data);

        if ($result['success']) {
            return new \WP_REST_Response([
                'success' => true,
                'ref'     => $result['ref'],
                'token'   => $result['token'],
                'message' => 'Inscription complétée avec succès!',
            ], 201);
        }

        return new \WP_REST_Response([
            'success' => false,
            'errors'  => $result['errors'],
        ], 400);
    }

    // ── VERIFY QR ──
    public function verify_qr(\WP_REST_Request $request): \WP_REST_Response {
        $ref = sanitize_text_field($request->get_param('ref') ?? '');
        $tok = sanitize_text_field($request->get_param('tok') ?? '');
        $ip  = self::get_client_ip();

        // Rate limiting
        if (!Scout_Access_Log::check_rate_limit($ip)) {
            Scout_Access_Log::log(0, null, 'qr_rate_limited', "IP: {$ip}");
            return new \WP_REST_Response([
                'error' => 'Trop de tentatives. Réessayez dans une heure.',
            ], 429);
        }

        if (empty($ref) || empty($tok)) {
            Scout_Access_Log::log(0, null, 'qr_scan_fail', "Missing ref or tok from IP: {$ip}");
            return new \WP_REST_Response(['error' => 'Paramètres manquants.'], 400);
        }

        // Verify HMAC (timing-safe)
        if (!Scout_Inscription_Model::verify_token($ref, $tok)) {
            Scout_Access_Log::log(0, null, 'qr_scan_fail', "Invalid token for ref {$ref} from IP: {$ip}");
            return new \WP_REST_Response(['error' => 'Code QR invalide.'], 403);
        }

        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) {
            Scout_Access_Log::log(0, null, 'qr_scan_fail', "Ref not found: {$ref}");
            return new \WP_REST_Response(['error' => 'Inscription introuvable.'], 404);
        }

        Scout_Access_Log::log(0, $inscription->id, 'qr_scan_ok', "QR verified from IP: {$ip}");

        $payment_labels = [
            'en_attente'   => '⏳ En attente',
            'acompte_recu' => '💰 Acompte reçu',
            'paye'         => '✅ Payé',
        ];

        $status_note = '';
        if ($inscription->status === 'doublon') {
            $status_note = '⚠️ Cette inscription est un doublon. Vérifiez l\'inscription principale.';
        }

        return new \WP_REST_Response([
            'success'        => true,
            'ref'            => $inscription->ref_number,
            'enfant'         => $inscription->enfant_prenom . ' ' . $inscription->enfant_nom,
            'unite'          => $inscription->unite,
            'status'         => $inscription->status,
            'status_note'    => $status_note,
            'payment_status' => $payment_labels[$inscription->payment_status] ?? $inscription->payment_status,
            'medical_data'   => $inscription->medical_data_decrypted ?? [],
        ], 200);
    }

    // ── GET INSCRIPTION ──
    public function get_inscription(\WP_REST_Request $request): \WP_REST_Response {
        $ref = sanitize_text_field($request->get_param('ref'));
        $inscription = Scout_Inscription_Model::get_by_ref($ref);

        if (!$inscription) {
            return new \WP_REST_Response(['error' => 'Inscription introuvable.'], 404);
        }

        Scout_Access_Log::log(get_current_user_id(), $inscription->id, 'view', 'REST API view');

        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $payments = Scout_Payment_Model::get_for_inscription($inscription->id);

        return new \WP_REST_Response([
            'inscription' => $inscription,
            'contacts'    => $contacts,
            'payments'    => $payments,
        ]);
    }

    // ── LIST INSCRIPTIONS ──
    public function list_inscriptions(\WP_REST_Request $request): \WP_REST_Response {
        $filters = [
            'annee_scoute'   => sanitize_text_field($request->get_param('annee') ?? ''),
            'unite'          => sanitize_text_field($request->get_param('unite') ?? ''),
            'payment_status' => sanitize_text_field($request->get_param('payment') ?? ''),
            'status'         => sanitize_text_field($request->get_param('status') ?? ''),
        ];
        $filters = array_filter($filters);

        $page  = max(1, absint($request->get_param('page') ?? 1));
        $per   = min(100, max(1, absint($request->get_param('per_page') ?? 50)));
        $offset = ($page - 1) * $per;

        $items = Scout_Inscription_Model::list($filters, $per, $offset);
        $total = Scout_Inscription_Model::count($filters);

        return new \WP_REST_Response([
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'total_pages'=> ceil($total / $per),
        ]);
    }

    // ── ADD PAYMENT ──
    public function add_payment(\WP_REST_Request $request): \WP_REST_Response {
        $ref = sanitize_text_field($request->get_param('ref'));
        $inscription = Scout_Inscription_Model::get_by_ref($ref);

        if (!$inscription) {
            return new \WP_REST_Response(['error' => 'Inscription introuvable.'], 404);
        }

        $data = $request->get_json_params();
        $payment_id = Scout_Payment_Model::create($inscription->id, $data);

        if (!$payment_id) {
            return new \WP_REST_Response(['error' => 'Erreur lors de l\'enregistrement du paiement.'], 500);
        }

        // Send payment received email
        Scout_Email_Handler::send_payment_received($inscription->id, floatval($data['montant'] ?? 0));

        // Refresh inscription data
        $updated = Scout_Inscription_Model::get($inscription->id);

        return new \WP_REST_Response([
            'success'         => true,
            'payment_id'      => $payment_id,
            'payment_status'  => $updated->payment_status,
            'payment_received'=> $updated->payment_received,
            'balance'         => $updated->payment_total - $updated->payment_received,
        ], 201);
    }

    // ── EXPORT CSV ──
    public function export_csv(\WP_REST_Request $request): \WP_REST_Response {
        if (class_exists('Scout_Export')) {
            return Scout_Export::handle_export($request);
        }
        return new \WP_REST_Response(['error' => 'Export non disponible.'], 500);
    }

    // ── DOWNLOAD PDF ──
    public function download_pdf(\WP_REST_Request $request): void {
        // Check permissions (bypasses REST nonce so direct browser links work)
        if (!is_user_logged_in()) {
            wp_die('Veuillez vous connecter pour accéder à ce document. <a href="' . wp_login_url($_SERVER['REQUEST_URI']) . '">Se connecter</a>', 'Connexion requise', ['response' => 403]);
        }
        if (!current_user_can('scout_view_inscriptions') && !current_user_can('manage_options')) {
            wp_die('Vous n\'avez pas les permissions nécessaires pour accéder à ce document.', 'Accès refusé', ['response' => 403]);
        }

        $ref  = sanitize_text_field($request->get_param('ref'));
        $type = sanitize_text_field($request->get_param('type'));

        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) {
            wp_die('Inscription introuvable.', 'Erreur', ['response' => 404]);
        }

        Scout_PDF_Generator::serve_pdf($inscription->id, $type);
    }

    // ── PERMISSION CALLBACKS ──
    public function can_view(): bool {
        return current_user_can('scout_view_inscriptions') || current_user_can('manage_options');
    }

    public function can_manage_payments(): bool {
        return current_user_can('scout_manage_payments') || current_user_can('manage_options');
    }

    public function can_export(): bool {
        return current_user_can('scout_export') || current_user_can('manage_options');
    }

    private static function get_client_ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', sanitize_text_field($_SERVER[$h]))[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    // ── APPROVE ──
    public function approve_inscription(\WP_REST_Request $request): \WP_REST_Response {
        $ref = sanitize_text_field($request->get_param('ref'));
        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) return new \WP_REST_Response(['error' => 'Introuvable.'], 404);
        Scout_Inscription_Model::update_status($inscription->id, 'approuvee');
        Scout_Access_Log::log(get_current_user_id(), $inscription->id, 'inscription_approved', "Approuvée par " . wp_get_current_user()->display_name);
        return new \WP_REST_Response(['success' => true, 'message' => 'Inscription approuvée.']);
    }

    // ── REJECT ──
    public function reject_inscription(\WP_REST_Request $request): \WP_REST_Response {
        $ref = sanitize_text_field($request->get_param('ref'));
        $reason = sanitize_textarea_field($request->get_json_params()['reason'] ?? '');
        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) return new \WP_REST_Response(['error' => 'Introuvable.'], 404);
        Scout_Inscription_Model::update_status($inscription->id, 'rejetee');
        Scout_Inscription_Model::update_payment_status($inscription->id, 'annulee');
        Scout_Access_Log::log(get_current_user_id(), $inscription->id, 'inscription_rejected', "Rejetée: {$reason}");
        if (class_exists('Scout_Email_Handler')) {
            Scout_Email_Handler::send_rejection($inscription, $reason);
        }
        return new \WP_REST_Response(['success' => true, 'message' => 'Inscription rejetée.']);
    }

    // ── PAYMENT PLAN ──
    public function set_payment_plan(\WP_REST_Request $request): \WP_REST_Response {
        $ref = sanitize_text_field($request->get_param('ref'));
        $params = $request->get_json_params();
        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) return new \WP_REST_Response(['error' => 'Introuvable.'], 404);
        Scout_Inscription_Model::update_status($inscription->id, 'plan_paiement');
        Scout_Access_Log::log(get_current_user_id(), $inscription->id, 'payment_plan_set', "Plan: " . sanitize_text_field($params['note'] ?? ''));
        return new \WP_REST_Response(['success' => true, 'message' => 'Plan de paiement activé.']);
    }

    // ── MFA: SEND CODE ──
    public function mfa_send_code(\WP_REST_Request $request): \WP_REST_Response {
        if (!Scout_MFA::user_has_medical_role()) {
            return new \WP_REST_Response(['error' => 'Accès refusé.'], 403);
        }
        $sent = Scout_MFA::send_code();
        if ($sent) {
            return new \WP_REST_Response(['success' => true, 'message' => 'Code envoyé par courriel.']);
        }
        return new \WP_REST_Response(['error' => 'Erreur d\'envoi du courriel.'], 500);
    }

    // ── MFA: VERIFY CODE ──
    public function mfa_verify_code(\WP_REST_Request $request): \WP_REST_Response {
        $code = sanitize_text_field($request->get_json_params()['code'] ?? '');
        if (empty($code)) {
            return new \WP_REST_Response(['error' => 'Code requis.'], 400);
        }
        $result = Scout_MFA::verify_code($code);
        $status = $result['success'] ? 200 : 403;
        return new \WP_REST_Response($result, $status);
    }

    // ── MFA: STATUS ──
    public function mfa_status(\WP_REST_Request $request): \WP_REST_Response {
        return new \WP_REST_Response([
            'has_role' => Scout_MFA::user_has_medical_role(),
            'mfa_required' => Scout_MFA::is_mfa_required(),
            'session_valid' => Scout_MFA::has_valid_session(),
            'can_access' => Scout_MFA::can_access_medical(),
        ]);
    }

    // ── PERMISSION: Medical access ──
    public function can_view_medical(): bool {
        return Scout_MFA::can_access_medical();
    }

    // ── MEDICAL VIEWER (HTML) ──
    public function view_medical(\WP_REST_Request $request): void {
        $ref = sanitize_text_field($request->get_param('ref'));
        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) { wp_die('Inscription introuvable.', 404); }

        $medical = $inscription->medical_data_decrypted ?? [];
        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $urgence = array_filter($contacts, function($c) { return $c->type === 'urgence'; });

        Scout_Access_Log::log(get_current_user_id(), $inscription->id, 'medical_view', 'Fiche médicale consultée (HTML)');

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>Fiche médicale — ' . esc_html($inscription->ref_number) . '</title>';
        echo '<style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,sans-serif;background:#f5f3ee;padding:20px;color:#1a1a16}
            .card{background:#fff;border-radius:12px;padding:24px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,0.06);border:1px solid #e0ddd4}
            h1{font-size:1.4rem;color:#007748;margin-bottom:16px;display:flex;align-items:center;gap:8px}
            h2{font-size:1.1rem;color:#007748;margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #e0ddd4}
            .ref{font-size:0.85rem;color:#6a6a62;font-weight:400}
            table{width:100%;border-collapse:collapse}
            th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #f0ede6;font-size:0.9rem}
            th{color:#6a6a62;font-weight:500;width:180px}
            .alert{background:#fff3f3;border-left:4px solid #c0392b;padding:12px 16px;border-radius:0 8px 8px 0;margin-bottom:12px;color:#c0392b;font-weight:600}
            .ok{color:#27ae60}
            .warn{color:#c0392b;font-weight:600}
            .footer{text-align:center;font-size:0.75rem;color:#6a6a62;margin-top:24px;padding:16px}
            .actions{display:flex;gap:8px;margin-bottom:16px}
            .btn{padding:8px 16px;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
            .btn-print{background:#007748;color:#fff}
            .btn-back{background:none;border:2px solid #d0d0c8;color:#3a3a36}
            @media print{.actions{display:none}.card{box-shadow:none;border:1px solid #ddd}}
        </style></head><body>';

        echo '<div style="max-width:700px;margin:0 auto">';
        echo '<div class="actions"><button class="btn btn-print" onclick="window.print()">🖨️ Imprimer</button>';
        echo '<button class="btn btn-back" onclick="history.back()">← Retour</button></div>';

        echo '<div class="card"><h1>🏥 Fiche médicale <span class="ref">' . esc_html($inscription->ref_number) . '</span></h1>';
        echo '<table>';
        echo '<tr><th>Enfant</th><td><strong>' . esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom) . '</strong></td></tr>';
        echo '<tr><th>Date de naissance</th><td>' . esc_html($inscription->enfant_ddn) . '</td></tr>';
        echo '<tr><th>Assurance maladie</th><td>' . esc_html($inscription->assurance_maladie) . ' (exp: ' . esc_html($inscription->assurance_expiration) . ')</td></tr>';
        echo '</table></div>';

        // Allergies (prominent)
        $has_allergies = !empty($medical['allergies_alimentaires']) || !empty($medical['allergies_medicament']);
        if ($has_allergies) {
            echo '<div class="alert">⚠️ ALLERGIES DÉCLARÉES</div>';
        }

        echo '<div class="card"><h2>Santé</h2><table>';
        echo '<tr><th>Allergies alimentaires</th><td class="' . ($medical['allergies_alimentaires'] ? 'warn' : 'ok') . '">' . esc_html($medical['allergies_alimentaires'] ?: 'Aucune') . '</td></tr>';
        echo '<tr><th>Allergies médicament</th><td class="' . ($medical['allergies_medicament'] ? 'warn' : 'ok') . '">' . esc_html($medical['allergies_medicament'] ?: 'Aucune') . '</td></tr>';
        echo '<tr><th>Médicaments / posologie</th><td>' . esc_html($medical['medicaments'] ?: '—') . '</td></tr>';
        echo '<tr><th>Restrictions alimentaires</th><td>' . esc_html($medical['restrictions_alimentaires'] ?: '—') . '</td></tr>';
        echo '<tr><th>Vaccins à jour</th><td>' . esc_html($medical['vaccins_jour'] ?? '—') . '</td></tr>';
        echo '<tr><th>Attention particulière</th><td>' . esc_html(($medical['attention_particuliere'] ?? 'non') !== 'non' ? ($medical['attention_detail'] ?? 'Oui') : 'Non') . '</td></tr>';
        echo '<tr><th>Limite physique</th><td>' . esc_html(($medical['limite_physique'] ?? 'non') !== 'non' ? ($medical['limite_detail'] ?? 'Oui') : 'Non') . '</td></tr>';
        echo '<tr><th>Commentaires</th><td>' . esc_html($medical['commentaires_medicaux'] ?? '—') . '</td></tr>';
        echo '</table></div>';

        echo '<div class="card"><h2>🚨 Contacts d\'urgence</h2><table>';
        foreach ($urgence as $u) {
            echo '<tr><th>' . esc_html($u->lien) . '</th><td><strong>' . esc_html($u->nom) . '</strong><br>📞 <a href="tel:' . esc_attr($u->telephone) . '">' . esc_html($u->telephone) . '</a></td></tr>';
        }
        echo '</table></div>';

        echo '<div class="footer">🔒 Accès journalisé — Loi 25 du Québec · Consulté par ' . esc_html(wp_get_current_user()->display_name) . ' le ' . date('Y-m-d H:i') . '</div>';
        echo '</div></body></html>';
        exit;
    }

    // ── FAMILY: Lookup by ref number ──
    public function family_lookup(\WP_REST_Request $request): \WP_REST_Response {
        $ref = sanitize_text_field($request->get_json_params()['ref'] ?? '');
        if (empty($ref)) return new \WP_REST_Response(['error' => 'Numéro de référence requis.'], 400);

        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) return new \WP_REST_Response(['error' => 'Inscription introuvable.'], 404);

        // Get parent email for this inscription
        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $parent_emails = [];
        foreach ($contacts as $c) {
            if ($c->type === 'parent' && !empty($c->courriel)) {
                // Mask email for display: j***@gmail.com
                $email = $c->courriel;
                $parts = explode('@', $email);
                $masked = substr($parts[0], 0, 1) . str_repeat('*', max(3, strlen($parts[0]) - 1)) . '@' . $parts[1];
                $parent_emails[] = $masked;
            }
        }

        if (empty($parent_emails)) {
            return new \WP_REST_Response(['error' => 'Aucun courriel associé à cette inscription.'], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'ref' => $inscription->ref_number,
            'masked_emails' => $parent_emails,
        ]);
    }

    // ── FAMILY: Send dashboard link ──
    public function family_send_link(\WP_REST_Request $request): \WP_REST_Response {
        $ref = sanitize_text_field($request->get_json_params()['ref'] ?? '');
        if (empty($ref)) return new \WP_REST_Response(['error' => 'Référence requise.'], 400);

        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) return new \WP_REST_Response(['error' => 'Introuvable.'], 404);

        // Ensure families table exists
        global $wpdb;
        $table = SCOUT_DB_PREFIX . 'families';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$exists) {
            // Run activator to create missing tables
            if (class_exists('Scout_Inscription_Activator')) {
                Scout_Inscription_Activator::activate();
            }
            // Check again
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$exists) {
                return new \WP_REST_Response(['error' => 'Table famille manquante. Désactivez et réactivez le plugin.'], 500);
            }
        }

        // Find parent email
        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $parent_email = '';
        foreach ($contacts as $c) {
            if ($c->type === 'parent' && !empty($c->courriel)) {
                $parent_email = $c->courriel;
                break;
            }
        }
        if (!$parent_email) return new \WP_REST_Response(['error' => 'Aucun courriel trouvé.'], 400);

        // Find or create family
        $family = Scout_Family_Model::find_by_email($parent_email);
        if (!$family) {
            $family = Scout_Family_Model::create($parent_email);
        }
        if (!$family) return new \WP_REST_Response(['error' => 'Erreur de création.'], 500);

        // Link this inscription if not already linked
        if (empty($inscription->family_id)) {
            Scout_Family_Model::link_inscription($inscription->id, $family->id);
        }

        // Also link any other inscriptions with the same parent email
        global $wpdb;
        $all_inscriptions = $wpdb->get_results("SELECT i.id FROM " . SCOUT_DB_PREFIX . "inscriptions i INNER JOIN " . SCOUT_DB_PREFIX . "contacts c ON c.inscription_id = i.id WHERE i.family_id IS NULL");
        foreach ($all_inscriptions as $ins) {
            $ins_contacts = Scout_Contact_Model::get_for_inscription($ins->id);
            foreach ($ins_contacts as $ic) {
                if ($ic->type === 'parent' && strtolower($ic->courriel) === strtolower($parent_email)) {
                    Scout_Family_Model::link_inscription($ins->id, $family->id);
                    break;
                }
            }
        }

        // Send the link
        $sent = Scout_Family_Model::send_dashboard_link($family);
        if (!$sent) return new \WP_REST_Response(['error' => 'Erreur d\'envoi du courriel.'], 500);

        Scout_Access_Log::log(0, $inscription->id, 'family_link_sent', "Dashboard link sent to parent");

        return new \WP_REST_Response(['success' => true, 'message' => 'Lien envoyé par courriel.']);
    }

    // ── FAMILY: Dashboard data ──
    public function family_dashboard(\WP_REST_Request $request): \WP_REST_Response {
        $tok = sanitize_text_field($request->get_param('tok'));
        $family = Scout_Family_Model::get_by_token($tok);
        if (!$family) return new \WP_REST_Response(['error' => 'Lien invalide.'], 403);

        $inscriptions = Scout_Family_Model::get_inscriptions($family->id);
        $current_year = Scout_Inscription_Model::get_current_year();
        $unite_names = ['castors' => 'Castors', 'louveteaux' => 'Louveteaux', 'eclaireurs' => 'Éclaireurs', 'pionniers' => 'Pionniers'];
        $payment_labels = ['en_attente' => 'En attente', 'acompte_recu' => 'Acompte reçu', 'paye' => 'Payé', 'annulee' => 'Annulée'];
        $status_labels = ['brouillon' => 'Brouillon', 'complete' => 'Complète', 'approuvee' => 'Approuvée', 'rejetee' => 'Rejetée', 'plan_paiement' => 'Plan de paiement', 'annulee' => 'Annulée'];

        // Group by child
        $children = [];
        foreach ($inscriptions as $ins) {
            $key = strtolower($ins->enfant_prenom . '_' . $ins->enfant_nom);
            if (!isset($children[$key])) {
                $children[$key] = [
                    'prenom' => $ins->enfant_prenom,
                    'nom' => $ins->enfant_nom,
                    'ddn' => $ins->enfant_ddn,
                    'inscriptions' => [],
                ];
            }
            $children[$key]['inscriptions'][] = [
                'ref' => $ins->ref_number,
                'annee' => $ins->annee_scoute,
                'unite' => $unite_names[$ins->unite] ?? $ins->unite,
                'unite_slug' => $ins->unite,
                'status' => $status_labels[$ins->status] ?? $ins->status,
                'payment' => $payment_labels[$ins->payment_status] ?? $ins->payment_status,
                'is_current' => ($ins->annee_scoute === $current_year),
                'can_renew' => ($ins->annee_scoute !== $current_year && !in_array($ins->status, ['annulee', 'rejetee'])),
            ];
        }

        return new \WP_REST_Response([
            'family_id' => $family->id,
            'current_year' => $current_year,
            'children' => array_values($children),
        ]);
    }

    // ── FAMILY: Get pre-fill data for renewal ──
    public function family_renew_data(\WP_REST_Request $request): \WP_REST_Response {
        $tok = sanitize_text_field($request->get_param('tok'));
        $ref = sanitize_text_field($request->get_param('ref'));

        $family = Scout_Family_Model::get_by_token($tok);
        if (!$family) return new \WP_REST_Response(['error' => 'Lien invalide.'], 403);

        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription || intval($inscription->family_id) !== intval($family->id)) {
            return new \WP_REST_Response(['error' => 'Accès refusé.'], 403);
        }

        // Calculate suggested unit based on age
        $age = date_diff(date_create($inscription->enfant_ddn), date_create())->y;
        $suggested_unit = $inscription->unite;
        if ($age >= 14) $suggested_unit = 'pionniers';
        elseif ($age >= 12) $suggested_unit = 'eclaireurs';
        elseif ($age >= 9) $suggested_unit = 'louveteaux';
        elseif ($age >= 7) $suggested_unit = 'castors';

        // Get contacts from last inscription
        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $parents = [];
        $urgence = [];
        foreach ($contacts as $c) {
            $contact_data = [
                'prenom' => $c->prenom,
                'nom' => $c->nom,
                'lien' => $c->lien,
                'telephone' => $c->telephone,
                'cellulaire' => $c->cellulaire ?? '',
                'courriel' => $c->courriel ?? '',
                'resp_finances' => $c->resp_finances ?? 0,
            ];
            if ($c->type === 'parent') $parents[] = $contact_data;
            else $urgence[] = $contact_data;
        }

        return new \WP_REST_Response([
            'renewal' => true,
            'previous_ref' => $inscription->ref_number,
            'enfant_prenom' => $inscription->enfant_prenom,
            'enfant_nom' => $inscription->enfant_nom,
            'enfant_ddn' => $inscription->enfant_ddn,
            'enfant_sexe' => $inscription->enfant_sexe,
            'enfant_adresse' => $inscription->enfant_adresse,
            'enfant_ville' => $inscription->enfant_ville,
            'enfant_code_postal' => $inscription->enfant_code_postal,
            'enfant_telephone' => $inscription->enfant_telephone ?? '',
            'unite' => $suggested_unit,
            'previous_unite' => $inscription->unite,
            'age' => $age,
            'parents' => $parents,
            'urgence' => $urgence,
            'family_token' => $tok,
        ]);
    }

    // ── PRICING ──
    public function get_pricing(\WP_REST_Request $request): \WP_REST_Response {
        $year = sanitize_text_field($request->get_param('year'));
        if (!$year) {
            $year = get_option('scout_ins_current_year', '') ?: Scout_Inscription_Model::get_current_year();
        }
        $pricing_years = get_option('scout_ins_pricing_years', []);
        if (isset($pricing_years[$year])) {
            return new \WP_REST_Response(['year' => $year, 'prices' => $pricing_years[$year]]);
        }
        // Fallback to flat pricing
        $pricing = get_option('scout_ins_pricing', ['castors' => 245, 'louveteaux' => 285, 'eclaireurs' => 285, 'pionniers' => 285]);
        return new \WP_REST_Response(['year' => $year, 'prices' => $pricing]);
    }
}
