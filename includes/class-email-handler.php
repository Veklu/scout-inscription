<?php
defined('ABSPATH') || exit;

class Scout_Email_Handler {

    /**
     * Send confirmation email after inscription.
     */
    public static function send_confirmation(int $inscription_id): bool {
        $inscription = Scout_Inscription_Model::get($inscription_id);
        if (!$inscription) return false;

        $parent = Scout_Contact_Model::get_finance_parent($inscription_id);
        if (!$parent || !$parent->courriel) return false;

        $to = $parent->courriel;
        $subject = sprintf(
            /* translators: 1: first name, 2: last name, 3: reference number */
            __('[5e Grand-Moulin] Confirmation d\'inscription — %1$s %2$s (%3$s)', 'scout-inscription'),
            $inscription->enfant_prenom, $inscription->enfant_nom, $inscription->ref_number);

        $unite_names = [
            'castors'    => 'Castors (7-8 ans)',
            'louveteaux' => 'Louveteaux (9-11 ans)',
            'eclaireurs' => 'Éclaireurs (12-14 ans)',
            'pionniers'  => 'Pionniers (14-17 ans)',
        ];
        $unite_label = $unite_names[$inscription->unite] ?? $inscription->unite;

        $qr_url = Scout_QR_Generator::build_verification_url($inscription->ref_number, $inscription->hmac_token);

        ob_start();
        include SCOUT_INS_DIR . 'templates/email-confirmation.php';
        $body = ob_get_clean();

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: 5e Groupe scout Grand-Moulin <' . get_option('scout_ins_email_from', 'info@5escoutgrandmoulin.org') . '>',
        ];

        $sent = wp_mail($to, $subject, $body, $headers);

        Scout_Access_Log::log(0, $inscription_id, 'email_sent',
            $sent ? "Confirmation email sent to {$to}" : "Email failed to {$to}");

        return $sent;
    }

    /**
     * Send payment reminder.
     */
    public static function send_payment_reminder(int $inscription_id): bool {
        $inscription = Scout_Inscription_Model::get($inscription_id);
        if (!$inscription || $inscription->payment_status === 'paye') return false;

        $parent = Scout_Contact_Model::get_finance_parent($inscription_id);
        if (!$parent || !$parent->courriel) return false;

        $balance = $inscription->payment_total - $inscription->payment_received;
        $to = $parent->courriel;
        $subject = sprintf(
            /* translators: 1: first name, 2: reference number */
            __('[5e Grand-Moulin] Rappel de paiement — %1$s (%2$s)', 'scout-inscription'),
            $inscription->enfant_prenom, $inscription->ref_number);

        $body = self::wrap_email("
            <h2 style='color:#007748'>Rappel de paiement</h2>
            <p>Bonjour {$parent->prenom},</p>
            <p>Ce courriel est un rappel amical concernant l'inscription de <strong>{$inscription->enfant_prenom} {$inscription->enfant_nom}</strong> au 5e Groupe scout Grand-Moulin.</p>
            <table style='width:100%;border-collapse:collapse;margin:20px 0'>
                <tr><td style='padding:8px;border:1px solid #ddd'><strong>Référence</strong></td><td style='padding:8px;border:1px solid #ddd'>{$inscription->ref_number}</td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd'><strong>Total dû</strong></td><td style='padding:8px;border:1px solid #ddd'>" . number_format($inscription->payment_total, 2) . " $</td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd'><strong>Reçu</strong></td><td style='padding:8px;border:1px solid #ddd'>" . number_format($inscription->payment_received, 2) . " $</td></tr>
                <tr style='background:#f9f8f5'><td style='padding:8px;border:1px solid #ddd'><strong>Solde restant</strong></td><td style='padding:8px;border:1px solid #ddd;color:#c0392b'><strong>" . number_format($balance, 2) . " $</strong></td></tr>
            </table>
            <p><strong>Modes de paiement acceptés :</strong></p>
            <ul>
                <li>Virement Interac à : " . esc_html(get_option('scout_ins_email_from', 'info@5escoutgrandmoulin.org')) . "</li>
                <li>Chèque à l'ordre de : 5e Groupe scout Grand-Moulin</li>
                <li>Comptant (lors d'une réunion)</li>
            </ul>
            <p>Veuillez inclure le numéro de référence <strong>{$inscription->ref_number}</strong> dans votre virement.</p>
            <p>Merci!<br>5e Groupe scout Grand-Moulin</p>
        ");

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: 5e Groupe scout Grand-Moulin <' . get_option('scout_ins_email_from', 'info@5escoutgrandmoulin.org') . '>',
        ];

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Send payment received notification.
     */
    public static function send_payment_received(int $inscription_id, float $amount): bool {
        $inscription = Scout_Inscription_Model::get($inscription_id);
        if (!$inscription) return false;

        $parent = Scout_Contact_Model::get_finance_parent($inscription_id);
        if (!$parent || !$parent->courriel) return false;

        $balance = $inscription->payment_total - $inscription->payment_received;
        $status_text = $balance <= 0 ? __('Inscription payée en totalité', 'scout-inscription') : sprintf(__('Solde restant : %s $', 'scout-inscription'), number_format($balance, 2));

        $to = $parent->courriel;
        $subject = sprintf(
            /* translators: %s: reference number */
            __('[5e Grand-Moulin] Paiement reçu — %s', 'scout-inscription'),
            $inscription->ref_number);

        $body = self::wrap_email("
            <h2 style='color:#007748'>Paiement reçu — merci!</h2>
            <p>Bonjour {$parent->prenom},</p>
            <p>Nous confirmons la réception d'un paiement de <strong>" . number_format($amount, 2) . " $</strong> pour l'inscription de {$inscription->enfant_prenom} {$inscription->enfant_nom}.</p>
            <p><strong>{$status_text}</strong></p>
            <p>Merci pour votre soutien!<br>5e Groupe scout Grand-Moulin</p>
        ");

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: 5e Groupe scout Grand-Moulin <' . get_option('scout_ins_email_from', 'info@5escoutgrandmoulin.org') . '>',
        ];

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Wrap email content in a styled template.
     */
    private static function wrap_email(string $content): string {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#1a1a16;max-width:600px;margin:0 auto;padding:20px">
            <div style="background:#007748;padding:20px;border-radius:12px 12px 0 0;text-align:center">
                <h1 style="color:#fff;margin:0;font-size:20px">⚜️ 5e Groupe scout Grand-Moulin</h1>
                <p style="color:#d4a017;margin:4px 0 0;font-size:13px">Scouts du Canada · Deux-Montagnes</p>
            </div>
            <div style="background:#fff;padding:30px;border:1px solid #e0ddd4;border-top:none;border-radius:0 0 12px 12px">' . $content . '</div>
            <p style="text-align:center;font-size:11px;color:#6a6a62;margin-top:20px">
                Ce courriel a été envoyé par le 5e Groupe scout Grand-Moulin.<br>
                Conforme à la Loi 25 du Québec. Responsable : ' . esc_html(get_option('scout_ins_privacy_officer', 'Jean Côté')) . '
            </p>
        </body></html>';
    }

    /**
     * Send rejection notification email.
     */
    public static function send_rejection($inscription, string $reason = ''): bool {
        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $parents = array_filter($contacts, function($c) { return $c->type === 'parent'; });

        $emails = [];
        foreach ($parents as $p) {
            if (!empty($p->courriel)) $emails[] = $p->courriel;
        }
        if (empty($emails)) return false;

        $child_name = Scout_Encryption::decrypt($inscription->enfant_prenom) . ' ' . Scout_Encryption::decrypt($inscription->enfant_nom);
        $group_name = get_bloginfo('name');
        $contact_email = get_option('scout_ins_email_from', get_option('admin_email'));

        $subject = sprintf(__('Inscription %s — Information importante', 'scout-inscription'), $inscription->ref_number);

        $reason_html = $reason ? '<p style="background:#fff5f5;padding:16px;border-radius:8px;border-left:4px solid #c0392b;margin:16px 0"><strong>Motif :</strong> ' . esc_html($reason) . '</p>' : '';

        $body = self::wrap_email("
            <h2 style='color:#1a1a16;margin-bottom:8px'>Information concernant l'inscription</h2>
            <p>Bonjour,</p>
            <p>Nous avons examiné la demande d'inscription de <strong>" . esc_html($child_name) . "</strong> (réf: {$inscription->ref_number}) et malheureusement, nous ne sommes pas en mesure de l'accepter à ce moment.</p>
            {$reason_html}
            <p>Si vous avez des questions ou souhaitez en discuter, n'hésitez pas à nous contacter à <a href='mailto:{$contact_email}'>{$contact_email}</a>.</p>
            <p>Cordialement,<br><strong>{$group_name}</strong></p>
        ");

        $headers = ['Content-Type: text/html; charset=UTF-8', 'From: ' . $group_name . ' <' . $contact_email . '>'];

        return wp_mail($emails, $subject, $body, $headers);
    }
}
