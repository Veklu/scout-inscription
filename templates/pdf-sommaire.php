<?php defined('ABSPATH') || exit; ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 11px; color: #1a1a16; margin: 20px; }
h1 { color: #007748; font-size: 18px; }
h2 { color: #007748; font-size: 13px; border-bottom: 1px solid #007748; padding-bottom: 3px; margin-top: 16px; }
.header { background: #007748; color: #fff; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
.header h1 { color: #fff; margin: 0; }
.header p { color: #d4a017; margin: 2px 0 0; font-size: 10px; }
table { width: 100%; border-collapse: collapse; margin: 8px 0; }
td, th { padding: 5px 8px; border: 1px solid #d0d0c8; text-align: left; font-size: 10px; }
th { background: #f5f3ee; font-weight: 600; width: 30%; }
.qr-section { text-align: center; margin: 20px 0; padding: 16px; border: 2px solid #007748; border-radius: 8px; }
.qr-section img { width: 200px; height: 200px; }
.ref-big { font-size: 24px; font-weight: 700; color: #007748; letter-spacing: 2px; }
.payment-ok { color: #27ae60; } .payment-pending { color: #c0392b; }
.footer { text-align: center; font-size: 8px; color: #6a6a62; margin-top: 20px; border-top: 1px solid #d0d0c8; padding-top: 6px; }
</style></head><body>

<div class="header">
    <h1><?php esc_html_e('Sommaire d\'inscription', 'scout-inscription'); ?></h1>
    <p>5e Groupe scout Grand-Moulin · <?php echo esc_html($inscription->annee_scoute); ?></p>
</div>

<!-- QR CODE -->
<div class="qr-section">
    <div class="ref-big"><?php echo esc_html($inscription->ref_number); ?></div>
    <?php if (!empty($qr_base64)): ?>
        <img src="<?php echo esc_attr($qr_base64); ?>" alt="QR Code">
    <?php endif; ?>
    <p style="font-size:9px;color:#6a6a62"><?php esc_html_e('Scannez ce code QR pour vérifier l\'inscription', 'scout-inscription'); ?></p>
</div>

<h2><?php esc_html_e('Enfant', 'scout-inscription'); ?></h2>
<table>
    <tr><th><?php esc_html_e('Nom complet', 'scout-inscription'); ?></th><td><?php echo esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom); ?></td></tr>
    <tr><th><?php esc_html_e('Date de naissance', 'scout-inscription'); ?></th><td><?php echo esc_html($inscription->enfant_ddn); ?></td></tr>
    <tr><th><?php esc_html_e('Unité', 'scout-inscription'); ?></th><td><?php echo esc_html(ucfirst($inscription->unite)); ?></td></tr>
    <tr><th><?php esc_html_e('Adresse', 'scout-inscription'); ?></th><td><?php echo esc_html($inscription->enfant_adresse . ', ' . $inscription->enfant_ville . ' ' . $inscription->enfant_code_postal); ?></td></tr>
</table>

<h2><?php esc_html_e('Contacts', 'scout-inscription'); ?></h2>
<table>
    <tr><th><?php esc_html_e('Type', 'scout-inscription'); ?></th><th><?php esc_html_e('Nom', 'scout-inscription'); ?></th><th><?php esc_html_e('Lien', 'scout-inscription'); ?></th><th><?php esc_html_e('Téléphone', 'scout-inscription'); ?></th><th><?php esc_html_e('Courriel', 'scout-inscription'); ?></th></tr>
    <?php foreach ($contacts as $c): ?>
    <tr>
        <td><?php echo $c->type === 'parent' ? esc_html__('Parent', 'scout-inscription') : esc_html__('Urgence', 'scout-inscription'); ?></td>
        <td><?php echo esc_html(($c->prenom ? $c->prenom . ' ' : '') . $c->nom); ?></td>
        <td><?php echo esc_html($c->lien); ?></td>
        <td><?php echo esc_html($c->telephone); ?></td>
        <td><?php echo esc_html($c->courriel ?? ''); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2><?php esc_html_e('Paiement', 'scout-inscription'); ?></h2>
<table>
    <tr><th><?php esc_html_e('Total dû', 'scout-inscription'); ?></th><td><?php echo number_format($inscription->payment_total, 2); ?> $</td></tr>
    <tr><th><?php esc_html_e('Reçu', 'scout-inscription'); ?></th><td><?php echo number_format($inscription->payment_received, 2); ?> $</td></tr>
    <tr><th><?php esc_html_e('Solde', 'scout-inscription'); ?></th><td class="<?php echo ($inscription->payment_total - $inscription->payment_received) > 0 ? 'payment-pending' : 'payment-ok'; ?>">
        <strong><?php echo number_format($inscription->payment_total - $inscription->payment_received, 2); ?> $</strong></td></tr>
    <tr><th><?php esc_html_e('Statut', 'scout-inscription'); ?></th><td><?php
        $labels = ['en_attente' => __('En attente', 'scout-inscription'), 'acompte_recu' => __('Acompte reçu', 'scout-inscription'), 'paye' => __('Payé', 'scout-inscription')];
        echo esc_html($labels[$inscription->payment_status] ?? $inscription->payment_status);
    ?></td></tr>
</table>

<?php if (!empty($medical)): ?>
<h2><?php esc_html_e('Alertes médicales', 'scout-inscription'); ?></h2>
<table>
    <?php $allergies = array_filter([$medical['allergies_alimentaires'] ?? '', $medical['allergies_medicament'] ?? '']); ?>
    <?php if (!empty($allergies)): ?>
        <tr><th style="color:#c0392b"><?php esc_html_e('Allergies', 'scout-inscription'); ?></th><td><?php echo esc_html(implode(', ', $allergies)); ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($medical['medicaments'])): ?>
        <tr><th><?php esc_html_e('Médicaments', 'scout-inscription'); ?></th><td><?php echo esc_html($medical['medicaments']); ?></td></tr>
    <?php endif; ?>
    <?php if (($medical['attention_particuliere'] ?? 'non') !== 'non'): ?>
        <tr><th><?php esc_html_e('Attention', 'scout-inscription'); ?></th><td><?php echo esc_html($medical['attention_detail'] ?? ''); ?></td></tr>
    <?php endif; ?>
</table>
<?php endif; ?>

<div class="footer">
    5e Groupe scout Grand-Moulin · info@5escoutgrandmoulin.org · Conforme à la Loi 25 du Québec<br>
    🔒 Lien sécurisé par signature cryptographique HMAC-SHA256 · Document généré le <?php echo date('d/m/Y à H:i'); ?>
</div>
</body></html>
