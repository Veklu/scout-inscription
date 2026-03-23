<?php
defined('ABSPATH') || exit;

/**
 * PDF/Document generation for inscription documents.
 * Generates HTML documents on-the-fly — no pre-generation needed.
 */
class Scout_PDF_Generator {

    /**
     * Serve a document to the browser.
     */
    public static function serve_pdf(int $inscription_id, string $type): void {
        $inscription = Scout_Inscription_Model::get($inscription_id);
        if (!$inscription) {
            wp_die('Inscription introuvable.', 'Erreur', ['response' => 404]);
        }

        Scout_Access_Log::log(get_current_user_id(), $inscription_id, 'document_view', "Document {$type} consulté");

        $html = '';
        switch ($type) {
            case 'fiche_medicale':
                $html = self::render_fiche_medicale($inscription);
                break;
            case 'acceptation_risque':
                $html = self::render_acceptation_risque($inscription);
                break;
            case 'sommaire':
                $html = self::render_sommaire($inscription);
                break;
            default:
                wp_die('Type de document inconnu.', 'Erreur', ['response' => 400]);
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    private static function page_wrapper(string $title, string $content, string $ref): string {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>' . esc_html($title) . ' — ' . esc_html($ref) . '</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f3ee;color:#1a1a16;padding:20px}
            .doc{max-width:750px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);overflow:hidden}
            .doc-header{background:linear-gradient(135deg,#003320,#007748);color:#fff;padding:24px 32px;display:flex;justify-content:space-between;align-items:center}
            .doc-header h1{font-size:1.2rem;font-weight:700}
            .doc-header .ref{font-size:0.85rem;opacity:0.7}
            .doc-body{padding:32px}
            h2{font-size:1rem;color:#007748;margin:24px 0 12px;padding-bottom:8px;border-bottom:2px solid #e0ddd4}
            h2:first-child{margin-top:0}
            table{width:100%;border-collapse:collapse;margin-bottom:16px}
            th,td{padding:8px 12px;text-align:left;border-bottom:1px solid #f0ede6;font-size:0.88rem}
            th{color:#6a6a62;font-weight:500;width:180px}
            .alert{background:#fff3f3;border-left:4px solid #c0392b;padding:10px 14px;border-radius:0 8px 8px 0;margin:12px 0;color:#c0392b;font-weight:600;font-size:0.88rem}
            .ok{color:#27ae60}.warn{color:#c0392b;font-weight:600}
            .actions{padding:16px 32px;background:#f9f8f5;border-top:1px solid #e0ddd4;display:flex;gap:8px}
            .btn{padding:8px 16px;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
            .btn-green{background:#007748;color:#fff}.btn-grey{background:none;border:2px solid #d0d0c8;color:#3a3a36}
            .footer{text-align:center;padding:16px;font-size:0.72rem;color:#6a6a62}
            .signature-box{background:#f9f8f5;padding:16px;border-radius:8px;border:2px solid #007748;margin:12px 0}
            .signature-name{font-family:Georgia,serif;font-size:1.4rem;font-style:italic;color:#003d24}
            @media print{.actions{display:none}body{background:#fff;padding:0}.doc{box-shadow:none;border-radius:0}}
        </style></head><body>
        <div class="doc">
            <div class="doc-header">
                <div><h1>⚜️ 5e Groupe scout Grand-Moulin</h1><div class="ref">' . esc_html($title) . '</div></div>
                <div style="text-align:right"><div style="font-weight:700">' . esc_html($ref) . '</div><div class="ref">' . date('Y-m-d') . '</div></div>
            </div>
            <div class="doc-body">' . $content . '</div>
            <div class="actions">
                <button class="btn btn-green" onclick="window.print()">🖨️ Imprimer</button>
                <button class="btn btn-grey" onclick="history.back()">← Retour</button>
            </div>
            <div class="footer">🔒 Document généré le ' . date('Y-m-d H:i') . ' · Conforme à la Loi 25 du Québec</div>
        </div></body></html>';
    }

    private static function render_fiche_medicale(object $inscription): string {
        $medical = $inscription->medical_data_decrypted ?? [];
        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $urgence = array_filter($contacts, function($c) { return $c->type === 'urgence'; });
        $unite_names = ['castors'=>'Castors','louveteaux'=>'Louveteaux','eclaireurs'=>'Éclaireurs','pionniers'=>'Pionniers'];

        $has_allergies = !empty($medical['allergies_alimentaires']) || !empty($medical['allergies_medicament']);

        $html = '<h2>👤 Identification</h2><table>';
        $html .= '<tr><th>Enfant</th><td><strong>' . esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom) . '</strong></td></tr>';
        $html .= '<tr><th>Date de naissance</th><td>' . esc_html($inscription->enfant_ddn) . '</td></tr>';
        $html .= '<tr><th>Unité</th><td>' . esc_html($unite_names[$inscription->unite] ?? $inscription->unite) . '</td></tr>';
        $html .= '<tr><th>Assurance maladie</th><td>' . esc_html($inscription->assurance_maladie) . ' (exp: ' . esc_html($inscription->assurance_expiration) . ')</td></tr>';
        $html .= '</table>';

        if ($has_allergies) {
            $html .= '<div class="alert">⚠️ ALLERGIES DÉCLARÉES — Voir détails ci-dessous</div>';
        }

        $html .= '<h2>🏥 Informations médicales</h2><table>';
        $html .= '<tr><th>Allergies alimentaires</th><td class="' . ($medical['allergies_alimentaires'] ? 'warn' : '') . '">' . esc_html($medical['allergies_alimentaires'] ?: 'Aucune') . '</td></tr>';
        $html .= '<tr><th>Allergies médicament</th><td class="' . ($medical['allergies_medicament'] ? 'warn' : '') . '">' . esc_html($medical['allergies_medicament'] ?: 'Aucune') . '</td></tr>';
        $html .= '<tr><th>Médicaments / posologie</th><td>' . esc_html($medical['medicaments'] ?: '—') . '</td></tr>';
        $html .= '<tr><th>Restrictions alimentaires</th><td>' . esc_html($medical['restrictions_alimentaires'] ?: '—') . '</td></tr>';
        $html .= '<tr><th>Vaccins à jour</th><td>' . esc_html($medical['vaccins_jour'] ?? '—') . '</td></tr>';
        $html .= '<tr><th>Attention particulière</th><td>' . esc_html(($medical['attention_particuliere'] ?? 'non') !== 'non' ? ($medical['attention_detail'] ?? 'Oui') : 'Non') . '</td></tr>';
        $html .= '<tr><th>Limite physique</th><td>' . esc_html(($medical['limite_physique'] ?? 'non') !== 'non' ? ($medical['limite_detail'] ?? 'Oui') : 'Non') . '</td></tr>';
        $html .= '<tr><th>Commentaires</th><td>' . esc_html($medical['commentaires_medicaux'] ?? '—') . '</td></tr>';
        $html .= '</table>';

        $html .= '<h2>🚨 Contacts d\'urgence</h2><table>';
        foreach ($urgence as $u) {
            $html .= '<tr><th>' . esc_html($u->lien) . '</th><td><strong>' . esc_html($u->nom) . '</strong> — 📞 ' . esc_html($u->telephone) . '</td></tr>';
        }
        $html .= '</table>';

        return self::page_wrapper('Fiche médicale', $html, $inscription->ref_number);
    }

    private static function render_acceptation_risque(object $inscription): string {
        $signature = $inscription->risk_signature_decrypted ?? [];

        $html = '<h2>⚠️ Acceptation des risques</h2>';
        $html .= '<p style="margin-bottom:16px;font-size:0.9rem;color:#3a3a36">Je reconnais que les activités scoutes comportent des risques inhérents, incluant mais non limités aux activités de plein air, au camping, aux randonnées, aux activités nautiques et aux travaux manuels.</p>';

        $html .= '<div style="background:#f9f8f5;padding:16px;border-radius:8px;margin-bottom:16px">';
        $html .= '<p style="font-size:0.88rem">En signant ce document, je confirme :</p>';
        $html .= '<ul style="padding-left:20px;margin:8px 0;font-size:0.88rem">';
        $html .= '<li>Avoir été informé(e) des risques associés aux activités</li>';
        $html .= '<li>Autoriser mon enfant à participer aux activités du groupe</li>';
        $html .= '<li>Autoriser les responsables à prendre les mesures d\'urgence nécessaires</li>';
        $html .= '<li>M\'engager à informer le groupe de tout changement dans l\'état de santé de mon enfant</li>';
        $html .= '</ul></div>';

        $html .= '<h2>✍️ Signature</h2>';
        $html .= '<div class="signature-box">';
        if (is_array($signature) && !empty($signature['name'])) {
            $html .= '<div class="signature-name">' . esc_html($signature['name']) . '</div>';
            $html .= '<div style="font-size:0.78rem;color:#6a6a62;margin-top:4px">Signé électroniquement le ' . esc_html($signature['date'] ?? date('Y-m-d')) . '</div>';
        } else {
            $html .= '<div style="color:#6a6a62">Signature non disponible</div>';
        }
        $html .= '</div>';

        $html .= '<table style="margin-top:16px">';
        $html .= '<tr><th>Enfant</th><td>' . esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom) . '</td></tr>';
        $html .= '<tr><th>Année scoute</th><td>' . esc_html($inscription->annee_scoute) . '</td></tr>';
        $html .= '</table>';

        return self::page_wrapper('Acceptation des risques', $html, $inscription->ref_number);
    }

    private static function render_sommaire(object $inscription): string {
        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $parents = array_filter($contacts, function($c) { return $c->type === 'parent'; });
        $urgence = array_filter($contacts, function($c) { return $c->type === 'urgence'; });
        $payments = Scout_Payment_Model::get_for_inscription($inscription->id);
        $medical = $inscription->medical_data_decrypted ?? [];
        $unite_names = ['castors'=>'Castors','louveteaux'=>'Louveteaux','eclaireurs'=>'Éclaireurs','pionniers'=>'Pionniers'];
        $balance = $inscription->payment_total - $inscription->payment_received;

        $html = '<h2>👤 Enfant</h2><table>';
        $html .= '<tr><th>Nom</th><td><strong>' . esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom) . '</strong></td></tr>';
        $html .= '<tr><th>Date de naissance</th><td>' . esc_html($inscription->enfant_ddn) . '</td></tr>';
        $html .= '<tr><th>Unité</th><td>' . esc_html($unite_names[$inscription->unite] ?? $inscription->unite) . '</td></tr>';
        $html .= '<tr><th>Année scoute</th><td>' . esc_html($inscription->annee_scoute) . '</td></tr>';
        $html .= '<tr><th>Adresse</th><td>' . esc_html($inscription->enfant_adresse . ', ' . $inscription->enfant_ville . ' ' . $inscription->enfant_code_postal) . '</td></tr>';
        $html .= '</table>';

        $html .= '<h2>👨‍👩‍👧 Parents / Tuteurs</h2><table>';
        foreach ($parents as $p) {
            $html .= '<tr><th>' . esc_html($p->lien) . '</th><td>' . esc_html($p->prenom . ' ' . $p->nom) . ' — 📞 ' . esc_html($p->telephone);
            if ($p->courriel) $html .= ' — ✉️ ' . esc_html($p->courriel);
            $html .= '</td></tr>';
        }
        $html .= '</table>';

        $html .= '<h2>🚨 Contacts d\'urgence</h2><table>';
        foreach ($urgence as $u) {
            $html .= '<tr><th>' . esc_html($u->lien) . '</th><td>' . esc_html($u->nom) . ' — 📞 ' . esc_html($u->telephone) . '</td></tr>';
        }
        $html .= '</table>';

        $html .= '<h2>💳 Paiement</h2><table>';
        $html .= '<tr><th>Total</th><td>' . number_format($inscription->payment_total, 2) . ' $</td></tr>';
        $html .= '<tr><th>Reçu</th><td>' . number_format($inscription->payment_received, 2) . ' $</td></tr>';
        $html .= '<tr><th>Solde</th><td style="color:' . ($balance > 0 ? '#c0392b' : '#27ae60') . ';font-weight:700">' . number_format($balance, 2) . ' $</td></tr>';
        $html .= '</table>';

        if (!empty($payments)) {
            $html .= '<table><thead><tr><th>Date</th><th>Mode</th><th>Montant</th></tr></thead><tbody>';
            foreach ($payments as $pay) {
                $html .= '<tr><td>' . esc_html($pay->date_recu) . '</td><td>' . esc_html(ucfirst($pay->mode)) . '</td><td>' . number_format($pay->montant, 2) . ' $</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        // QR code placeholder
        $html .= '<h2>📱 Code QR</h2>';
        $html .= '<div style="text-align:center;padding:20px"><div id="summaryQR"></div>';
        $html .= '<p style="font-size:0.78rem;color:#6a6a62;margin-top:8px">' . esc_html($inscription->ref_number) . ' · Signé HMAC-SHA256</p></div>';

        // Add QR generator script
        $html .= '<script src="' . esc_url(SCOUT_INS_URL . 'public/js/qrcode-generator.js') . '"></script>';
        $html .= '<script>';
        $url = home_url('/inscription/verification/?ref=' . urlencode($inscription->ref_number) . '&tok=' . urlencode($inscription->hmac_token));
        $html .= 'var qr=qrcode(0,"H");qr.addData("' . esc_js($url) . '");qr.make();';
        $html .= 'var s=200,mc=qr.getModuleCount(),cs=s/mc,cv=document.createElement("canvas");cv.width=s;cv.height=s;var cx=cv.getContext("2d");';
        $html .= 'for(var r=0;r<mc;r++)for(var c=0;c<mc;c++){cx.fillStyle=qr.isDark(r,c)?"#007748":"#fff";cx.fillRect(c*cs,r*cs,cs+1,cs+1);}';
        $html .= 'document.getElementById("summaryQR").appendChild(cv);';
        $html .= '</script>';

        return self::page_wrapper('Sommaire d\'inscription', $html, $inscription->ref_number);
    }
}
