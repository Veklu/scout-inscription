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
    <h1>Acceptation des risques</h1>
    <p>5e Groupe scout Grand-Moulin · District Les Ailes du Nord · Scouts du Canada</p>
</div>

<p><strong>Enfant :</strong> <?php echo esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom); ?>
| <strong>Unité :</strong> <?php echo esc_html(ucfirst($inscription->unite)); ?>
| <strong>Référence :</strong> <?php echo esc_html($inscription->ref_number); ?></p>

<p>Le scoutisme est une activité éducative qui comporte des risques inhérents, incluant sans s'y limiter :</p>

<ul class="risk-list">
    <li>Activités de plein air (randonnée, camping, canot)</li>
    <li>Activités aquatiques</li>
    <li>Utilisation d'outils (couteaux, haches, scies)</li>
    <li>Préparation de repas sur feu de camp</li>
    <li>Transport en véhicule</li>
    <li>Exposition aux éléments naturels (froid, chaleur, insectes)</li>
    <li>Activités sportives et physiques</li>
    <li>Camping en nature (terrain accidenté, faune)</li>
    <li>Activités nocturnes</li>
    <li>Construction de structures (ponts, abris)</li>
    <li>Premiers soins et situations d'urgence</li>
</ul>

<div class="clause">☑ Je reconnais avoir été informé(e) des risques inhérents aux activités scoutes.</div>
<div class="clause">☑ Je reconnais que malgré les mesures de sécurité mises en place, des accidents peuvent survenir.</div>
<div class="clause">☑ J'autorise mon enfant à participer à toutes les activités du programme scout.</div>
<div class="clause">☑ En cas d'urgence, j'autorise les responsables à prendre les mesures nécessaires à la sauvegarde de la santé de mon jeune.</div>

<div class="signature-box">
    <p><strong>Signature électronique :</strong></p>
    <?php if (!empty($signature['name'])): ?>
        <p class="sig-name"><?php echo esc_html($signature['name']); ?></p>
        <p>Date : <?php echo esc_html($signature['date'] ?? ''); ?></p>
        <p style="font-size:9px;color:#6a6a62">IP : <?php echo esc_html($signature['ip'] ?? ''); ?></p>
    <?php else: ?>
        <p>________________________________</p>
        <p>Date : ______________</p>
    <?php endif; ?>
</div>

<div class="footer">
    5e Groupe scout Grand-Moulin · info@5escoutgrandmoulin.org · Conforme à la Loi 25 du Québec
</div>
</body></html>
