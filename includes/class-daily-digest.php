<?php
defined('ABSPATH') || exit;

/**
 * Daily Digest Email — sends a summary of registration activity
 * to configured recipients via WP Cron.
 */
class Scout_Daily_Digest {

    const OPTION_RECIPIENTS = 'scout_ins_digest_recipients';
    const OPTION_SECTIONS   = 'scout_ins_digest_sections';
    const OPTION_ENABLED    = 'scout_ins_digest_enabled';
    const OPTION_TIME       = 'scout_ins_digest_time';
    const CRON_HOOK         = 'scout_daily_digest_send';

    /**
     * Initialize cron schedule.
     */
    public static function init(): void {
        add_action(self::CRON_HOOK, [self::class, 'send_digest']);

        // Reschedule if settings changed
        add_action('update_option_' . self::OPTION_ENABLED, [self::class, 'reschedule']);
        add_action('update_option_' . self::OPTION_TIME, [self::class, 'reschedule']);
    }

    /**
     * Schedule or unschedule the cron event.
     */
    public static function reschedule(): void {
        wp_clear_scheduled_hook(self::CRON_HOOK);

        if (!get_option(self::OPTION_ENABLED, 0)) return;

        $time = get_option(self::OPTION_TIME, '07:00');
        $parts = explode(':', $time);
        $hour = intval($parts[0] ?? 7);
        $minute = intval($parts[1] ?? 0);

        // Calculate next run in site timezone
        $tz = wp_timezone();
        $now = new DateTime('now', $tz);
        $next = new DateTime('today', $tz);
        $next->setTime($hour, $minute);

        // If already past today's time, schedule for tomorrow
        if ($next <= $now) {
            $next->modify('+1 day');
        }

        wp_schedule_event($next->getTimestamp(), 'daily', self::CRON_HOOK);
    }

    /**
     * Activate — schedule on plugin activation.
     */
    public static function activate(): void {
        if (get_option(self::OPTION_ENABLED, 0)) {
            self::reschedule();
        }
    }

    /**
     * Deactivate — clear schedule.
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Get digest data for the last 24 hours.
     */
    public static function get_digest_data(): array {
        global $wpdb;
        $table = SCOUT_DB_PREFIX . 'inscriptions';
        $contacts_table = SCOUT_DB_PREFIX . 'contacts';
        $payments_table = SCOUT_DB_PREFIX . 'payments';
        $year = Scout_Inscription_Model::get_current_year();
        $since = gmdate('Y-m-d H:i:s', strtotime('-24 hours'));

        $data = [];

        // ── NEW INSCRIPTIONS ──
        $new = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE annee_scoute = %s AND created_at >= %s AND status != 'doublon' ORDER BY created_at DESC",
            $year, $since
        ));
        $data['new_inscriptions'] = array_map([Scout_Inscription_Model::class, 'decrypt_row'], $new);

        // ── STATUS CHANGES (updated but not newly created) ──
        $changed = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE annee_scoute = %s AND updated_at >= %s AND created_at < %s AND status != 'doublon' ORDER BY updated_at DESC",
            $year, $since, $since
        ));
        $data['status_changes'] = array_map([Scout_Inscription_Model::class, 'decrypt_row'], $changed);

        // ── PAYMENTS RECEIVED ──
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, i.ref_number, i.enfant_prenom, i.enfant_nom, i.unite
             FROM {$payments_table} p
             JOIN {$table} i ON p.inscription_id = i.id
             WHERE i.annee_scoute = %s AND p.created_at >= %s
             ORDER BY p.created_at DESC",
            $year, $since
        ));
        // Decrypt child names in payment rows
        foreach ($payments as &$p) {
            $p->enfant_prenom = Scout_Encryption::decrypt($p->enfant_prenom);
            $p->enfant_nom = Scout_Encryption::decrypt($p->enfant_nom);
        }
        $data['payments'] = $payments;

        // ── OUTSTANDING SUMMARY ──
        $data['total_inscriptions'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE annee_scoute = %s AND status NOT IN ('annulee','rejetee','doublon')",
            $year
        ));
        $data['total_approved'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE annee_scoute = %s AND status = 'approuvee'",
            $year
        ));
        $data['pending_review'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE annee_scoute = %s AND status = 'complete'",
            $year
        ));
        $data['total_due'] = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(payment_total) FROM {$table} WHERE annee_scoute = %s AND status IN ('approuvee','plan_paiement','complete')",
            $year
        )));
        $data['total_received'] = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(payment_received) FROM {$table} WHERE annee_scoute = %s AND status IN ('approuvee','plan_paiement','complete')",
            $year
        )));
        $data['outstanding'] = $data['total_due'] - $data['total_received'];

        // ── PER UNIT ──
        $units = ['castors', 'louveteaux', 'eclaireurs', 'pionniers'];
        $data['per_unit'] = [];
        foreach ($units as $u) {
            $data['per_unit'][$u] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE annee_scoute = %s AND unite = %s AND status NOT IN ('annulee','rejetee','doublon')",
                $year, $u
            ));
        }

        $data['year'] = $year;

        return $data;
    }

    /**
     * Build the HTML email body.
     */
    public static function build_email(array $data, array $sections): string {
        $status_labels = [
            'brouillon' => '🔘 Brouillon', 'complete' => '📋 Complète', 'approuvee' => '✅ Approuvée',
            'rejetee' => '❌ Rejetée', 'plan_paiement' => '📅 Plan paiement',
            'annulee' => '🚫 Annulée', 'doublon' => '🔁 Doublon',
        ];
        $unit_labels = ['castors' => '🦫 Castors', 'louveteaux' => '🐺 Louveteaux', 'eclaireurs' => '🧭 Éclaireurs', 'pionniers' => '🔴 Pionniers'];

        $site_name = get_bloginfo('name');
        $admin_url = admin_url('admin.php?page=scout-inscription');
        $date = wp_date('l j F Y');

        ob_start();
        ?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f5f3ee;font-family:Arial,Helvetica,sans-serif">
<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;margin-top:20px;margin-bottom:20px;border:1px solid #e0ddd4">

<!-- Header -->
<div style="background:linear-gradient(135deg,#003320,#007748);padding:24px;text-align:center;color:#fff">
<div style="font-size:28px;margin-bottom:4px">⚜️</div>
<h1 style="margin:0;font-size:20px;font-weight:700;color:#fff">Rapport quotidien — Inscriptions</h1>
<p style="margin:4px 0 0;opacity:0.7;font-size:13px"><?php echo esc_html($site_name); ?> · <?php echo esc_html($date); ?></p>
</div>

<div style="padding:24px">

<?php if (in_array('summary', $sections)): ?>
<!-- SUMMARY -->
<div style="background:#f0faf4;border-radius:8px;padding:16px;margin-bottom:20px">
<h2 style="margin:0 0 12px;font-size:15px;color:#007748">📊 Résumé — <?php echo esc_html($data['year']); ?></h2>
<table style="width:100%;border-collapse:collapse;font-size:13px">
<tr><td style="padding:4px 0">Total inscriptions actives</td><td style="text-align:right;font-weight:700"><?php echo $data['total_inscriptions']; ?></td></tr>
<tr><td style="padding:4px 0">✅ Approuvées</td><td style="text-align:right;font-weight:700;color:#27ae60"><?php echo $data['total_approved']; ?></td></tr>
<tr><td style="padding:4px 0">📋 En attente de traitement</td><td style="text-align:right;font-weight:700;color:#2563eb"><?php echo $data['pending_review']; ?></td></tr>
<tr><td style="padding:4px 0;border-top:1px solid #d0e8d4;padding-top:8px">Par unité:</td><td style="border-top:1px solid #d0e8d4;padding-top:8px"></td></tr>
<?php foreach ($data['per_unit'] as $unit => $count): ?>
<tr><td style="padding:2px 0 2px 16px"><?php echo esc_html($unit_labels[$unit] ?? ucfirst($unit)); ?></td><td style="text-align:right"><?php echo $count; ?></td></tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<?php if (in_array('new_inscriptions', $sections)): ?>
<!-- NEW INSCRIPTIONS -->
<div style="margin-bottom:20px">
<h2 style="margin:0 0 8px;font-size:15px;color:#007748">🆕 Nouvelles inscriptions (dernières 24h)</h2>
<?php if (empty($data['new_inscriptions'])): ?>
<p style="color:#6a6a62;font-size:13px;margin:4px 0">Aucune nouvelle inscription.</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="background:#f9f8f5"><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Réf</th><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Enfant</th><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Unité</th><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Statut</th></tr>
<?php foreach ($data['new_inscriptions'] as $ins): ?>
<tr><td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html($ins->ref_number); ?></td>
<td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html($ins->enfant_prenom . ' ' . $ins->enfant_nom); ?></td>
<td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html(ucfirst($ins->unite)); ?></td>
<td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html($status_labels[$ins->status] ?? $ins->status); ?></td></tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (in_array('status_changes', $sections)): ?>
<!-- STATUS CHANGES -->
<div style="margin-bottom:20px">
<h2 style="margin:0 0 8px;font-size:15px;color:#007748">🔄 Changements de statut (dernières 24h)</h2>
<?php if (empty($data['status_changes'])): ?>
<p style="color:#6a6a62;font-size:13px;margin:4px 0">Aucun changement.</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="background:#f9f8f5"><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Réf</th><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Enfant</th><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Statut actuel</th></tr>
<?php foreach ($data['status_changes'] as $ins): ?>
<tr><td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html($ins->ref_number); ?></td>
<td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html($ins->enfant_prenom . ' ' . $ins->enfant_nom); ?></td>
<td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html($status_labels[$ins->status] ?? $ins->status); ?></td></tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (in_array('payments', $sections)): ?>
<!-- PAYMENTS -->
<div style="margin-bottom:20px">
<h2 style="margin:0 0 8px;font-size:15px;color:#007748">💰 Paiements reçus (dernières 24h)</h2>
<?php if (empty($data['payments'])): ?>
<p style="color:#6a6a62;font-size:13px;margin:4px 0">Aucun nouveau paiement.</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="background:#f9f8f5"><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Réf</th><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Enfant</th><th style="text-align:right;padding:6px 8px;border-bottom:1px solid #e0ddd4">Montant</th><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e0ddd4">Méthode</th></tr>
<?php
$total_payments = 0;
foreach ($data['payments'] as $p):
    $total_payments += floatval($p->amount);
?>
<tr><td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html($p->ref_number); ?></td>
<td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html($p->enfant_prenom . ' ' . $p->enfant_nom); ?></td>
<td style="padding:4px 8px;border-bottom:1px solid #f0f0f0;text-align:right;font-weight:700;color:#27ae60"><?php echo number_format($p->amount, 2); ?> $</td>
<td style="padding:4px 8px;border-bottom:1px solid #f0f0f0"><?php echo esc_html($p->method ?? '—'); ?></td></tr>
<?php endforeach; ?>
<tr style="background:#f0faf4"><td colspan="2" style="padding:6px 8px;font-weight:700">Total reçu</td><td style="padding:6px 8px;text-align:right;font-weight:700;color:#27ae60"><?php echo number_format($total_payments, 2); ?> $</td><td></td></tr>
</table>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (in_array('outstanding', $sections)): ?>
<!-- OUTSTANDING -->
<div style="background:#fff8f0;border:1px solid #f0dcc0;border-radius:8px;padding:16px;margin-bottom:20px">
<h2 style="margin:0 0 8px;font-size:15px;color:#92400e">⚠️ Revenus — Solde</h2>
<table style="width:100%;border-collapse:collapse;font-size:13px">
<tr><td style="padding:4px 0">💵 Total attendu</td><td style="text-align:right;font-weight:700"><?php echo number_format($data['total_due'], 2); ?> $</td></tr>
<tr><td style="padding:4px 0">✅ Total reçu</td><td style="text-align:right;font-weight:700;color:#27ae60"><?php echo number_format($data['total_received'], 2); ?> $</td></tr>
<tr style="border-top:2px solid #f0dcc0"><td style="padding:8px 0 0;font-weight:700;color:#c0392b">⚠️ Impayé</td><td style="text-align:right;font-weight:700;color:#c0392b;padding-top:8px;font-size:16px"><?php echo number_format($data['outstanding'], 2); ?> $</td></tr>
</table>
</div>
<?php endif; ?>

<!-- Footer -->
<div style="text-align:center;padding-top:12px;border-top:1px solid #e0ddd4">
<a href="<?php echo esc_url($admin_url); ?>" style="display:inline-block;background:#007748;color:#fff;text-decoration:none;padding:10px 24px;border-radius:6px;font-size:13px;font-weight:600">Ouvrir le tableau de bord →</a>
<p style="font-size:11px;color:#9ca3af;margin-top:12px">Ce courriel est envoyé automatiquement chaque jour. Vous pouvez modifier les destinataires et les sections dans Réglages → Plugin d'inscription.</p>
</div>

</div>
</div>
</body></html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send the daily digest.
     */
    public static function send_digest(): void {
        if (!get_option(self::OPTION_ENABLED, 0)) return;

        $recipients = self::get_recipient_emails();
        if (empty($recipients)) return;

        $sections = get_option(self::OPTION_SECTIONS, ['summary', 'new_inscriptions', 'payments', 'outstanding']);
        if (empty($sections)) return;

        $data = self::get_digest_data();

        // Skip sending if nothing happened and no summary requested
        $has_activity = !empty($data['new_inscriptions']) || !empty($data['status_changes']) || !empty($data['payments']);
        $wants_summary = in_array('summary', $sections) || in_array('outstanding', $sections);
        if (!$has_activity && !$wants_summary) return;

        $html = self::build_email($data, $sections);
        $subject = '⚜️ Rapport inscriptions — ' . wp_date('j F Y');

        $from_email = get_option('scout_ins_email_from', 'info@5escoutgrandmoulin.org');
        $from_name = get_bloginfo('name');

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        ];

        foreach ($recipients as $email) {
            wp_mail($email, $subject, $html, $headers);
        }

        // Log
        update_option('scout_ins_digest_last_sent', current_time('mysql'));
    }

    /**
     * Resolve stored user IDs to email addresses.
     */
    public static function get_recipient_emails(): array {
        $user_ids = get_option(self::OPTION_RECIPIENTS, []);

        // Backwards compat: migrate from old textarea format
        if (is_string($user_ids)) {
            $emails = array_filter(array_map('trim', explode("\n", $user_ids)));
            $migrated = [];
            foreach ($emails as $email) {
                $user = get_user_by('email', $email);
                if ($user) $migrated[] = $user->ID;
            }
            if (!empty($migrated)) {
                update_option(self::OPTION_RECIPIENTS, $migrated);
                $user_ids = $migrated;
            } else {
                return $emails; // Return raw emails if no user match
            }
        }

        if (!is_array($user_ids) || empty($user_ids)) return [];

        $emails = [];
        foreach ($user_ids as $uid) {
            $user = get_user_by('ID', intval($uid));
            if ($user && is_email($user->user_email)) {
                $emails[] = $user->user_email;
            }
        }
        return $emails;
    }

    /**
     * Send a test digest now.
     */
    public static function send_test(): bool {
        $recipients = self::get_recipient_emails();
        if (empty($recipients)) return false;

        $sections = get_option(self::OPTION_SECTIONS, ['summary', 'new_inscriptions', 'payments', 'outstanding']);
        $data = self::get_digest_data();
        $html = self::build_email($data, $sections);
        $subject = '⚜️ [TEST] Rapport inscriptions — ' . wp_date('j F Y');

        $from_email = get_option('scout_ins_email_from', 'info@5escoutgrandmoulin.org');
        $from_name = get_bloginfo('name');
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        ];

        $sent = false;
        foreach ($recipients as $email) {
            $sent = wp_mail($email, $subject, $html, $headers) || $sent;
        }
        return $sent;
    }
}
