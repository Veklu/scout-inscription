<?php defined('ABSPATH') || exit; ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 11px; color: #1a1a16; margin: 20px; }
h1 { color: #007748; font-size: 18px; border-bottom: 3px solid #007748; padding-bottom: 6px; }
.header { background: #007748; color: #fff; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
.header h1 { color: #fff; border: none; margin: 0; }
.header p { color: #d4a017; margin: 2px 0 0; font-size: 10px; }
.risk-list { margin: 12px 0; padding-left: 20px; }
.risk-list li { margin-bottom: 4px; }
.clause { background: #f9f8f5; padding: 8px 12px; margin: 6px 0; border-left: 3px solid #007748; font-size: 10px; }
.signature-box { border: 2px solid #007748; padding: 16px; margin-top: 20px; border-radius: 4px; }
.signature-box .sig-name { font-family: 'Georgia', serif; font-size: 18px; font-style: italic; color: #003d24; }
.footer { text-align: center; font-size: 8px; color: #6a6a62; margin-top: 20px; border-top: 1px solid #d0d0c8; padding-top: 6px; }
</style></head><body>

<div class="header">
    <h1><?php esc_html_e('Acceptation des risques', 'scout-inscription'); ?></h1>
    <p>5e Groupe scout Grand-Moulin · District Les Ailes du Nord · Scouts du Canada</p>
</div>

<p><strong><?php esc_html_e('Enfant :', 'scout-inscription'); ?></strong> <?php echo esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom); ?>
| <strong><?php esc_html_e('Unité :', 'scout-inscription'); ?></strong> <?php echo esc_html(ucfirst($inscription->unite)); ?>
| <strong><?php esc_html_e('Référence :', 'scout-inscription'); ?></strong> <?php echo esc_html($inscription->ref_number); ?></p>

<p><?php esc_html_e('Le scoutisme est une activité éducative qui comporte des risques inhérents, incluant sans s\'y limiter :', 'scout-inscription'); ?></p>

<ul class="risk-list">
    <li><?php esc_html_e('Activités de plein air (randonnée, camping, canot)', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Activités aquatiques', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Utilisation d\'outils (couteaux, haches, scies)', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Préparation de repas sur feu de camp', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Transport en véhicule', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Exposition aux éléments naturels (froid, chaleur, insectes)', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Activités sportives et physiques', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Camping en nature (terrain accidenté, faune)', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Activités nocturnes', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Construction de structures (ponts, abris)', 'scout-inscription'); ?></li>
    <li><?php esc_html_e('Premiers soins et situations d\'urgence', 'scout-inscription'); ?></li>
</ul>

<div class="clause"><?php esc_html_e('Je reconnais avoir été informé(e) des risques inhérents aux activités scoutes.', 'scout-inscription'); ?></div>
<div class="clause"><?php esc_html_e('Je reconnais que malgré les mesures de sécurité mises en place, des accidents peuvent survenir.', 'scout-inscription'); ?></div>
<div class="clause"><?php esc_html_e('J\'autorise mon enfant à participer à toutes les activités du programme scout.', 'scout-inscription'); ?></div>
<div class="clause"><?php esc_html_e('En cas d\'urgence, j\'autorise les responsables à prendre les mesures nécessaires à la sauvegarde de la santé de mon jeune.', 'scout-inscription'); ?></div>

<div class="signature-box">
    <p><strong><?php esc_html_e('Signature électronique :', 'scout-inscription'); ?></strong></p>
    <?php if (!empty($signature['name'])): ?>
        <p class="sig-name"><?php echo esc_html($signature['name']); ?></p>
        <p><?php esc_html_e('Date :', 'scout-inscription'); ?> <?php echo esc_html($signature['date'] ?? ''); ?></p>
        <p style="font-size:9px;color:#6a6a62">IP : <?php echo esc_html($signature['ip'] ?? ''); ?></p>
    <?php else: ?>
        <p>________________________________</p>
        <p><?php esc_html_e('Date :', 'scout-inscription'); ?> ______________</p>
    <?php endif; ?>
</div>

<div class="footer">
    5e Groupe scout Grand-Moulin · info@5escoutgrandmoulin.org · Conforme à la Loi 25 du Québec
</div>
</body></html>
