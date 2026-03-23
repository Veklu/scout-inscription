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

    <h2 style="color:#007748">🎉 Inscription confirmée!</h2>
    <p>Bonjour <?php echo esc_html($parent_name); ?>,</p>
    <p>L'inscription de <strong><?php echo esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom); ?></strong> au 5e Groupe scout Grand-Moulin a été complétée avec succès.</p>

    <div style="background:#f9f8f5;border:2px solid #007748;border-radius:8px;padding:20px;text-align:center;margin:20px 0">
        <p style="font-size:12px;color:#6a6a62;margin:0">Numéro de référence</p>
        <p style="font-size:28px;font-weight:700;color:#007748;letter-spacing:3px;margin:8px 0"><?php echo esc_html($inscription->ref_number); ?></p>
        <p style="font-size:11px;color:#6a6a62">Conservez ce numéro pour toute communication.</p>
    </div>

    <table style="width:100%;border-collapse:collapse;margin:16px 0">
        <tr><td style="padding:8px;border:1px solid #ddd"><strong>Enfant</strong></td><td style="padding:8px;border:1px solid #ddd"><?php echo esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom); ?></td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd"><strong>Unité</strong></td><td style="padding:8px;border:1px solid #ddd"><?php echo esc_html($unite_label); ?></td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd"><strong>Année scoute</strong></td><td style="padding:8px;border:1px solid #ddd"><?php echo esc_html($inscription->annee_scoute); ?></td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd"><strong>Montant dû</strong></td><td style="padding:8px;border:1px solid #ddd"><?php echo number_format($inscription->payment_total, 2); ?> $</td></tr>
    </table>

    <h3 style="color:#007748">💳 Paiement</h3>
    <p>Modes de paiement acceptés :</p>
    <ul>
        <li><strong>Virement Interac</strong> à : <?php echo esc_html(get_option('scout_ins_email_from', 'info@5escoutgrandmoulin.org')); ?></li>
        <li><strong>Chèque</strong> à l'ordre de : 5e Groupe scout Grand-Moulin</li>
        <li><strong>Comptant</strong> lors d'une réunion</li>
    </ul>
    <p>Incluez votre numéro de référence <strong><?php echo esc_html($inscription->ref_number); ?></strong> dans votre virement Interac.</p>

    <h3 style="color:#007748">📅 Prochaines étapes</h3>
    <p>1. Conservez ce courriel et votre numéro de référence<br>
    2. Effectuez le paiement<br>
    3. Vous recevrez les détails de la première réunion par courriel</p>

    <p>Bienvenue dans la famille scoute! 🏕️</p>
    <p>— L'équipe du 5e Groupe scout Grand-Moulin</p>
</div>
<p style="text-align:center;font-size:11px;color:#6a6a62;margin-top:20px">
    Conforme à la Loi 25 du Québec · Responsable : <?php echo esc_html(get_option('scout_ins_privacy_officer', 'Jean Côté')); ?><br>
    <a href="<?php echo esc_url(get_privacy_policy_url()); ?>">Politique de confidentialité</a>
</p>
</body></html>
