<?php defined('ABSPATH') || exit;
$parent_name = $parents[0]->prenom ?? 'Parent';
$balance = $inscription->payment_total - $inscription->payment_received;
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#1a1a16;max-width:600px;margin:0 auto;padding:20px">
<div style="background:#007748;padding:20px;border-radius:12px 12px 0 0;text-align:center">
    <h1 style="color:#fff;margin:0;font-size:20px">⚜️ 5e Groupe scout Grand-Moulin</h1>
    <p style="color:#d4a017;margin:4px 0 0;font-size:13px">Scouts du Canada · Deux-Montagnes</p>
</div>
<div style="background:#fff;padding:30px;border:1px solid #e0ddd4;border-top:none;border-radius:0 0 12px 12px">

    <h2 style="color:#007748"><?php esc_html_e('Inscription confirmée!', 'scout-inscription'); ?></h2>
    <p><?php /* translators: %s: parent name */ printf(esc_html__('Bonjour %s,', 'scout-inscription'), esc_html($parent_name)); ?></p>
    <p><?php /* translators: %s: child full name */ printf(esc_html__('L\'inscription de %s au 5e Groupe scout Grand-Moulin a été complétée avec succès.', 'scout-inscription'), '<strong>' . esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom) . '</strong>'); ?></p>

    <div style="background:#f9f8f5;border:2px solid #007748;border-radius:8px;padding:20px;text-align:center;margin:20px 0">
        <p style="font-size:12px;color:#6a6a62;margin:0"><?php esc_html_e('Numéro de référence', 'scout-inscription'); ?></p>
        <p style="font-size:28px;font-weight:700;color:#007748;letter-spacing:3px;margin:8px 0"><?php echo esc_html($inscription->ref_number); ?></p>
        <p style="font-size:11px;color:#6a6a62"><?php esc_html_e('Conservez ce numéro pour toute communication.', 'scout-inscription'); ?></p>
    </div>

    <table style="width:100%;border-collapse:collapse;margin:16px 0">
        <tr><td style="padding:8px;border:1px solid #ddd"><strong><?php esc_html_e('Enfant', 'scout-inscription'); ?></strong></td><td style="padding:8px;border:1px solid #ddd"><?php echo esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom); ?></td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd"><strong><?php esc_html_e('Unité', 'scout-inscription'); ?></strong></td><td style="padding:8px;border:1px solid #ddd"><?php echo esc_html($unite_label); ?></td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd"><strong><?php esc_html_e('Année scoute', 'scout-inscription'); ?></strong></td><td style="padding:8px;border:1px solid #ddd"><?php echo esc_html($inscription->annee_scoute); ?></td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd"><strong><?php esc_html_e('Montant dû', 'scout-inscription'); ?></strong></td><td style="padding:8px;border:1px solid #ddd"><?php echo number_format($inscription->payment_total, 2); ?> $</td></tr>
    </table>

    <h3 style="color:#007748"><?php esc_html_e('Paiement', 'scout-inscription'); ?></h3>
    <p><?php esc_html_e('Modes de paiement acceptés :', 'scout-inscription'); ?></p>
    <ul>
        <li><strong><?php esc_html_e('Virement Interac', 'scout-inscription'); ?></strong> <?php esc_html_e('à :', 'scout-inscription'); ?> <?php echo esc_html(get_option('scout_ins_email_from', 'info@5escoutgrandmoulin.org')); ?></li>
        <li><strong><?php esc_html_e('Chèque', 'scout-inscription'); ?></strong> <?php esc_html_e('à l\'ordre de : 5e Groupe scout Grand-Moulin', 'scout-inscription'); ?></li>
        <li><strong><?php esc_html_e('Comptant', 'scout-inscription'); ?></strong> <?php esc_html_e('lors d\'une réunion', 'scout-inscription'); ?></li>
    </ul>
    <p><?php /* translators: %s: reference number */ printf(esc_html__('Incluez votre numéro de référence %s dans votre virement Interac.', 'scout-inscription'), '<strong>' . esc_html($inscription->ref_number) . '</strong>'); ?></p>

    <h3 style="color:#007748"><?php esc_html_e('Prochaines étapes', 'scout-inscription'); ?></h3>
    <p><?php esc_html_e('1. Conservez ce courriel et votre numéro de référence', 'scout-inscription'); ?><br>
    <?php esc_html_e('2. Effectuez le paiement', 'scout-inscription'); ?><br>
    <?php esc_html_e('3. Vous recevrez les détails de la première réunion par courriel', 'scout-inscription'); ?></p>

    <p><?php esc_html_e('Bienvenue dans la famille scoute!', 'scout-inscription'); ?></p>
    <p>— <?php esc_html_e('L\'équipe du 5e Groupe scout Grand-Moulin', 'scout-inscription'); ?></p>
</div>
<p style="text-align:center;font-size:11px;color:#6a6a62;margin-top:20px">
    Conforme à la Loi 25 du Québec · Responsable : <?php echo esc_html(get_option('scout_ins_privacy_officer', 'Jean Côté')); ?><br>
    <a href="<?php echo esc_url(get_privacy_policy_url()); ?>">Politique de confidentialité</a>
</p>
</body></html>
