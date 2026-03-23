<?php defined('ABSPATH') || exit; ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 11px; color: #1a1a16; margin: 20px; }
h1 { color: #007748; font-size: 18px; border-bottom: 3px solid #007748; padding-bottom: 6px; margin-bottom: 4px; }
h2 { color: #007748; font-size: 13px; border-bottom: 1px solid #007748; padding-bottom: 3px; margin-top: 16px; }
.header { background: #007748; color: #fff; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
.header h1 { color: #fff; border: none; margin: 0; font-size: 18px; }
.header p { color: #d4a017; margin: 2px 0 0; font-size: 10px; }
table { width: 100%; border-collapse: collapse; margin: 8px 0; }
td, th { padding: 5px 8px; border: 1px solid #d0d0c8; text-align: left; font-size: 10px; }
th { background: #f5f3ee; font-weight: 600; width: 35%; }
.field-label { font-size: 9px; color: #6a6a62; }
.field-value { border-bottom: 1px solid #d0d0c8; min-height: 18px; padding: 2px 4px; }
.auth-box { border: 2px solid #007748; padding: 12px; margin-top: 16px; border-radius: 4px; background: #f9f8f5; }
.footer { text-align: center; font-size: 8px; color: #6a6a62; margin-top: 20px; border-top: 1px solid #d0d0c8; padding-top: 6px; }
</style></head><body>

<div class="header">
    <h1>Fiche Médicale</h1>
    <p>5e Groupe scout Grand-Moulin · District Les Ailes du Nord · Scouts du Canada</p>
</div>

<div style="float:right;font-size:10px">
    <strong>Unité:</strong> <?php echo esc_html(ucfirst($inscription->unite)); ?><br>
    <strong>Année scoute:</strong> <?php echo esc_html($inscription->annee_scoute); ?>
</div>

<h2>Informations du jeune</h2>
<table>
    <tr><th>Prénom</th><td><?php echo esc_html($inscription->enfant_prenom); ?></td><th>Nom</th><td><?php echo esc_html($inscription->enfant_nom); ?></td></tr>
    <tr><th>Date de naissance</th><td><?php echo esc_html($inscription->enfant_ddn); ?></td><th>Sexe</th><td><?php echo esc_html($inscription->enfant_sexe); ?></td></tr>
    <tr><th>Adresse</th><td colspan="3"><?php echo esc_html($inscription->enfant_adresse . ', ' . $inscription->enfant_ville . ' ' . $inscription->enfant_code_postal); ?></td></tr>
    <tr><th>Assurance maladie</th><td><?php echo esc_html($inscription->assurance_maladie); ?></td><th>Expiration</th><td><?php echo esc_html($inscription->assurance_expiration); ?></td></tr>
</table>

<h2>Informations des parents / tuteurs</h2>
<table>
    <tr><th>Nom</th><th>Lien</th><th>Téléphone</th><th>Courriel</th></tr>
    <?php foreach ($parents as $p): ?>
    <tr>
        <td><?php echo esc_html($p->prenom . ' ' . $p->nom); ?></td>
        <td><?php echo esc_html($p->lien); ?></td>
        <td><?php echo esc_html($p->telephone); ?></td>
        <td><?php echo esc_html($p->courriel); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>Contact d'urgence</h2>
<table>
    <tr><th>Nom</th><th>Téléphone</th><th>Lien</th></tr>
    <?php foreach ($urgence as $u): ?>
    <tr><td><?php echo esc_html($u->nom); ?></td><td><?php echo esc_html($u->telephone); ?></td><td><?php echo esc_html($u->lien); ?></td></tr>
    <?php endforeach; ?>
</table>

<h2>Antécédents de santé</h2>
<table>
    <tr><th>Attention particulière requise?</th><td><?php echo esc_html(ucfirst($medical['attention_particuliere'] ?? 'Non')); ?> <?php echo esc_html($medical['attention_detail'] ?? ''); ?></td></tr>
    <tr><th>Vaccins à jour?</th><td><?php echo esc_html(ucfirst($medical['vaccins_jour'] ?? 'Oui')); ?></td></tr>
    <tr><th>Limite physique?</th><td><?php echo esc_html(ucfirst($medical['limite_physique'] ?? 'Non')); ?> <?php echo esc_html($medical['limite_detail'] ?? ''); ?></td></tr>
    <tr><th>Commentaires</th><td><?php echo esc_html($medical['commentaires'] ?? '—'); ?></td></tr>
</table>

<h2>Allergies et médicaments</h2>
<table>
    <tr><th>Médicaments</th><td><?php echo esc_html($medical['medicaments'] ?? '—'); ?></td></tr>
    <tr><th>Allergies alimentaires</th><td><?php echo esc_html($medical['allergies_alimentaires'] ?? '—'); ?></td></tr>
    <tr><th>Allergies médicament</th><td><?php echo esc_html($medical['allergies_medicament'] ?? '—'); ?></td></tr>
    <tr><th>Restrictions alimentaires</th><td><?php echo esc_html($medical['restrictions_alimentaires'] ?? '—'); ?></td></tr>
</table>

<div class="auth-box">
    <p><strong>En cas d'urgence, j'autorise les responsables ou le personnel médical à prendre les mesures nécessaires à la sauvegarde de la santé de mon jeune.</strong></p>
    <p>Signature de l'autorité parentale: ______________________________ Date: ______________</p>
</div>

<div class="footer">
    5e Groupe scout Grand-Moulin · info@5escoutgrandmoulin.org · Conforme à la Loi 25 du Québec · Fiche médicale — Confidentiel
</div>
</body></html>
