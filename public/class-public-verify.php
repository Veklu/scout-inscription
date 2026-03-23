<?php
defined('ABSPATH') || exit;

class Scout_Public_Verify {

    public function render(): string {
        $ref = sanitize_text_field($_GET['ref'] ?? '');
        $tok = sanitize_text_field($_GET['tok'] ?? '');

        ob_start();
        if (empty($ref) || empty($tok)) {
            echo '<div class="scout-verify-box scout-verify-error">';
            echo '<h2>' . esc_html__('Paramètres manquants', 'scout-inscription') . '</h2>';
            echo '<p>' . esc_html__('Ce lien ne contient pas les paramètres nécessaires. Veuillez scanner le code QR fourni avec l\'inscription.', 'scout-inscription') . '</p>';
            echo '</div>';
        } else {
            $this->process_verification($ref, $tok);
        }
        return ob_get_clean();
    }

    private function process_verification(string $ref, string $tok): void {
        // Rate limit check
        $ip = $this->get_ip();
        if (!Scout_Access_Log::check_rate_limit($ip)) {
            Scout_Access_Log::log(0, null, 'qr_rate_limited', "IP: {$ip}");
            echo '<div class="scout-verify-box scout-verify-error">';
            echo '<h2>' . esc_html__('Trop de tentatives', 'scout-inscription') . '</h2>';
            echo '<p>' . esc_html__('Vous avez atteint la limite de vérifications. Réessayez dans une heure.', 'scout-inscription') . '</p>';
            echo '</div>';
            return;
        }

        // Verify HMAC token
        if (!Scout_Inscription_Model::verify_token($ref, $tok)) {
            Scout_Access_Log::log(0, null, 'qr_scan_fail', "Invalid token for {$ref} from {$ip}");
            echo '<div class="scout-verify-box scout-verify-error">';
            echo '<h2>' . esc_html__('Code QR invalide', 'scout-inscription') . '</h2>';
            echo '<p>' . esc_html__('Ce code QR ne correspond à aucune inscription valide. Il a peut-être expiré ou été altéré.', 'scout-inscription') . '</p>';
            echo '</div>';
            return;
        }

        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) {
            echo '<div class="scout-verify-box scout-verify-error">';
            echo '<h2>' . esc_html__('Inscription introuvable', 'scout-inscription') . '</h2>';
            echo '</div>';
            return;
        }

        Scout_Access_Log::log(0, $inscription->id, 'qr_scan_ok', "QR verified from {$ip}");

        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $medical = $inscription->medical_data_decrypted ?? [];
        $parents = array_values(array_filter($contacts, function($c) { return $c->type === 'parent'; }));
        $urgence = array_values(array_filter($contacts, function($c) { return $c->type === 'urgence'; }));

        $unite_names = [
            'castors' => '🟡 Castors', 'louveteaux' => '🟢 Louveteaux',
            'eclaireurs' => '🔵 Éclaireurs', 'pionniers' => '🔴 Pionniers',
        ];
        $payment_labels = [
            'en_attente' => '⏳ En attente', 'acompte_recu' => '💰 Acompte reçu', 'paye' => '✅ Payé',
        ];

        echo '<div class="scout-verify-box scout-verify-success">';
        echo '<h2>' . esc_html__('Inscription vérifiée', 'scout-inscription') . '</h2>';
        echo '<div class="scout-verify-ref">' . esc_html($inscription->ref_number) . '</div>';

        echo '<div class="scout-verify-grid">';

        // Child info
        echo '<div class="scout-verify-section">';
        echo '<h3>' . esc_html__('Enfant', 'scout-inscription') . '</h3>';
        echo '<p><strong>' . esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom) . '</strong></p>';
        echo '<p>' . esc_html($unite_names[$inscription->unite] ?? $inscription->unite) . '</p>';
        echo '<p>' . sprintf(esc_html__('Né(e) le %s', 'scout-inscription'), esc_html($inscription->enfant_ddn)) . '</p>';
        echo '</div>';

        // Payment
        echo '<div class="scout-verify-section">';
        echo '<h3>' . esc_html__('Paiement', 'scout-inscription') . '</h3>';
        echo '<p>' . esc_html($payment_labels[$inscription->payment_status] ?? $inscription->payment_status) . '</p>';
        if ($inscription->payment_total > 0) {
            $balance = $inscription->payment_total - $inscription->payment_received;
            echo '<p>' . sprintf(esc_html__('Reçu : %1$s $ / %2$s $', 'scout-inscription'), number_format($inscription->payment_received, 2), number_format($inscription->payment_total, 2)) . '</p>';
            if ($balance > 0) {
                echo '<p style="color:#c0392b"><strong>' . sprintf(esc_html__('Solde : %s $', 'scout-inscription'), number_format($balance, 2)) . '</strong></p>';
            }
        }
        echo '</div>';

        // Medical highlights
        echo '<div class="scout-verify-section">';
        echo '<h3>' . esc_html__('Santé', 'scout-inscription') . '</h3>';
        $allergies = array_filter([
            $medical['allergies_alimentaires'] ?? '',
            $medical['allergies_medicament'] ?? '',
        ]);
        if (!empty($allergies)) {
            echo '<p style="color:#c0392b"><strong>' . esc_html__('Allergies :', 'scout-inscription') . '</strong> ' . esc_html(implode(', ', $allergies)) . '</p>';
        } else {
            echo '<p>' . esc_html__('Aucune allergie déclarée', 'scout-inscription') . '</p>';
        }
        if (!empty($medical['medicaments'])) {
            echo '<p><strong>' . esc_html__('Médicaments :', 'scout-inscription') . '</strong> ' . esc_html($medical['medicaments']) . '</p>';
        }
        if (($medical['attention_particuliere'] ?? 'non') !== 'non') {
            echo '<p><strong>' . esc_html__('Attention :', 'scout-inscription') . '</strong> ' . esc_html($medical['attention_detail'] ?? '') . '</p>';
        }
        echo '</div>';

        // Emergency contacts
        echo '<div class="scout-verify-section">';
        echo '<h3>' . esc_html__('Urgence', 'scout-inscription') . '</h3>';
        foreach ($urgence as $u) {
            echo '<p><strong>' . esc_html($u->nom) . '</strong> (' . esc_html($u->lien) . ')<br>';
            echo '📞 <a href="tel:' . esc_attr($u->telephone) . '">' . esc_html($u->telephone) . '</a></p>';
        }
        foreach ($parents as $p) {
            echo '<p>' . esc_html($p->prenom . ' ' . $p->nom) . ' (' . esc_html($p->lien) . ')<br>';
            echo '📞 <a href="tel:' . esc_attr($p->telephone) . '">' . esc_html($p->telephone) . '</a></p>';
        }
        echo '</div>';

        echo '</div>'; // grid
        echo '<p class="scout-verify-footer">🔒 Accès vérifié par signature cryptographique HMAC-SHA256</p>';

        // ── ADMIN/ANIMATEUR REPRINT SECTION ──
        if (is_user_logged_in() && (current_user_can('scout_view_inscriptions') || current_user_can('manage_options'))) {
            $pdf_base = home_url('/?scout_doc=' . urlencode($inscription->ref_number) . '&doc_type=');
            echo '<div style="margin-top:24px;padding:20px;background:#f0faf4;border:2px solid #007748;border-radius:12px">';
            echo '<h3 style="margin:0 0 12px;color:#007748">' . esc_html__('Zone administrateur — Réimprimer pour un parent', 'scout-inscription') . '</h3>';
            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">';
            echo '<a href="' . esc_url($pdf_base . 'sommaire') . '" class="button" target="_blank" style="display:inline-block;padding:8px 16px;background:#007748;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:600;border:none">📄 Sommaire + QR</a>';
            echo '<a href="' . esc_url($pdf_base . 'fiche_medicale') . '" class="button" target="_blank" style="display:inline-block;padding:8px 16px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:600;border:none">🏥 Fiche médicale</a>';
            echo '<a href="' . esc_url($pdf_base . 'acceptation_risque') . '" class="button" target="_blank" style="display:inline-block;padding:8px 16px;background:#e67e22;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:600;border:none">⚠️ Acceptation risques</a>';
            echo '</div>';

            // Print-friendly confirmation with QR
            echo '<button onclick="reprintConfirmation()" style="padding:8px 16px;background:none;border:2px solid #007748;color:#007748;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer">' . esc_html__('Imprimer la confirmation avec QR', 'scout-inscription') . '</button>';

            echo '<script src="' . esc_url(SCOUT_INS_URL . 'public/js/qrcode-generator.js') . '"></script>';
            echo '<script>';
            echo 'function reprintConfirmation(){';
            echo 'var ref="' . esc_js($inscription->ref_number) . '";';
            echo 'var tok="' . esc_js($inscription->hmac_token) . '";';
            echo 'var url="' . esc_url(home_url('/inscription/verification/')) . '?ref="+encodeURIComponent(ref)+"&tok="+encodeURIComponent(tok);';
            echo 'var qr=qrcode(0,"H");qr.addData(url);qr.make();var size=250,mc=qr.getModuleCount(),cellSize=size/mc;var canvas=document.createElement("canvas");canvas.width=size;canvas.height=size;var ctx=canvas.getContext("2d");for(var r=0;r<mc;r++)for(var c=0;c<mc;c++){ctx.fillStyle=qr.isDark(r,c)?"#007748":"#ffffff";ctx.fillRect(c*cellSize,r*cellSize,cellSize+1,cellSize+1);}';
            echo 'var win=window.open("","","width=600,height=800");';
            echo 'win.document.write("<html><head><style>body{font-family:sans-serif;padding:40px;text-align:center}h1{color:#007748;font-size:1.5rem}table{width:100%;border-collapse:collapse;margin:20px 0;text-align:left}td,th{padding:8px 12px;border-bottom:1px solid #ddd}th{color:#007748;width:140px}footer{margin-top:30px;font-size:11px;color:#999}</style></head><body>");';
            echo 'win.document.write("<h1>⚜️ 5e Groupe scout Grand-Moulin</h1>");';
            echo 'win.document.write("<h2>Confirmation d\'inscription</h2>");';
            echo 'win.document.write("<p style=\\"font-size:24px;font-weight:700;color:#007748;letter-spacing:2px\\">"+ref+"</p>");';
            echo 'win.document.write("<img src=\\""+canvas.toDataURL("image/png")+"\\" style=\\"width:200px;height:200px\\">");';
            echo 'win.document.write("<table>");';
            echo 'win.document.write("<tr><th>Enfant</th><td>' . esc_js($inscription->enfant_prenom . ' ' . $inscription->enfant_nom) . '</td></tr>");';
            echo 'win.document.write("<tr><th>Unité</th><td>' . esc_js($unite_names[$inscription->unite] ?? $inscription->unite) . '</td></tr>");';
            echo 'win.document.write("<tr><th>Date de naissance</th><td>' . esc_js($inscription->enfant_ddn) . '</td></tr>");';
            echo 'win.document.write("<tr><th>Paiement</th><td>' . esc_js($payment_labels[$inscription->payment_status] ?? $inscription->payment_status) . '</td></tr>");';
            echo 'win.document.write("</table>");';
            echo 'win.document.write("<p style=\\"font-size:13px\\">Présentez ce code QR lors des activités pour vérification rapide.</p>");';
            echo 'win.document.write("<footer>Scouts du Canada · Conforme à la Loi 25 du Québec</footer>");';
            echo 'win.document.write("</body></html>");';
            echo 'win.document.close();win.print();}';
            echo '</script>';

            // Log the reprint access
            Scout_Access_Log::log(get_current_user_id(), $inscription->id, 'admin_reprint_view', 'Admin viewed reprint page');
            echo '<p style="font-size:11px;color:#6a6a62;margin-top:12px">' . esc_html__('Cet accès est enregistré dans le journal (Loi 25).', 'scout-inscription') . '</p>';
            echo '</div>';
        }

        echo '</div>'; // box
    }

    private function get_ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', sanitize_text_field($_SERVER[$h]))[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }
}
