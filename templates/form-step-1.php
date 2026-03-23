<?php defined('ABSPATH') || exit; ?>

<style>
/* ═══════════ SCOUT INSCRIPTION FORM ═══════════ */
.scout-form-wrapper { max-width:800px; margin:0 auto; }

/* Stepper */
.scout-stepper { display:flex; gap:4px; margin-bottom:32px; }
.scout-step { flex:1; text-align:center; padding:12px 8px; background:#f5f3ee; border-radius:8px; font-size:13px; color:#6a6a62; transition:all 0.3s; cursor:pointer; }
.scout-step span { display:block; font-size:20px; font-weight:700; margin-bottom:2px; }
.scout-step.active { background:#007748; color:#fff; }
.scout-step.done { background:#005a36; color:#d4a017; }

/* Steps */
.scout-form-step { display:none !important; animation:fadeIn 0.3s; }
.scout-form-step.active { display:block !important; }
@keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

/* Fields */
.scout-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 16px; margin-bottom:16px; }
.scout-field { display:flex; flex-direction:column; }
.scout-field-wide { grid-column:span 2; }
.scout-field label { font-size:13px; font-weight:500; color:#3a3a36; margin-bottom:4px; }
.scout-form-wrapper input[type="text"],
.scout-form-wrapper input[type="email"],
.scout-form-wrapper input[type="tel"],
.scout-form-wrapper input[type="date"],
.scout-form-wrapper input[type="time"],
.scout-form-wrapper input[type="number"],
.scout-form-wrapper select,
.scout-form-wrapper textarea {
    padding:10px 12px !important; border:1.5px solid #d0d0c8 !important; border-radius:8px !important; font-size:14px !important;
    font-family:inherit !important; transition:border-color 0.2s !important; background:#fff !important;
    width:100% !important; box-sizing:border-box !important; -webkit-appearance:none; appearance:none;
    color:#1a1a16 !important; line-height:1.5 !important;
}
.scout-form-wrapper textarea {
    min-height:60px !important; resize:vertical !important;
}
.scout-form-wrapper select {
    cursor:pointer !important;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%236a6a62'/%3E%3C/svg%3E") !important;
    background-repeat:no-repeat !important;
    background-position:right 12px center !important;
    padding-right:32px !important;
}
.scout-form-wrapper input:focus,
.scout-form-wrapper select:focus,
.scout-form-wrapper textarea:focus {
    border-color:#007748 !important; outline:none !important; box-shadow:0 0 0 3px rgba(0,119,72,0.1) !important;
}

/* Contact blocks */
.scout-contact-block { background:#f9f8f5; padding:16px; border-radius:10px; margin-bottom:12px; position:relative; border:1px solid #e0ddd4; }
.scout-contact-block h3 { margin-top:0; font-size:15px; color:#007748; }
.scout-remove-btn { position:absolute; top:12px; right:12px; background:none; border:none; font-size:20px; cursor:pointer; color:#6a6a62; }
.scout-remove-btn:hover { background:#c0392b; color:#fff; border-radius:50%; }

/* Buttons */
.scout-btn-add { background:none; border:2px dashed #007748; color:#007748; padding:10px 20px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; margin:8px 0 24px; transition:all 0.2s; }
.scout-btn-add:hover { background:#007748; color:#fff; }
.scout-form-nav { display:flex; justify-content:space-between; margin-top:32px; padding-top:20px; border-top:1px solid #e0ddd4; }
.scout-btn-next, .scout-btn-submit { background:#007748; color:#fff; border:none; padding:12px 32px; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
.scout-btn-next:hover, .scout-btn-submit:hover { background:#005a36; }
.scout-btn-prev { background:none; border:1.5px solid #d0d0c8; color:#3a3a36; padding:12px 24px; border-radius:8px; font-size:14px; cursor:pointer; }
.scout-btn-prev:hover { border-color:#007748; color:#007748; }

/* Checkboxes */
.scout-checkbox { display:flex; gap:10px; padding:12px; border:1px solid #e0ddd4; border-radius:8px; margin-bottom:8px; cursor:pointer; }
.scout-checkbox:hover { border-color:#007748; }
.scout-checkbox input { margin-top:3px; accent-color:#007748; }
.scout-consent { padding:16px; background:#f9f8f5; }
.scout-consent strong { color:#007748; }
.scout-consent span { font-size:13px; color:#3a3a36; }

/* Medical */
.scout-medical-question { margin-bottom:16px; padding:12px; background:#f9f8f5; border-radius:8px; }
.scout-conditional { width:100%; margin-top:8px; }

/* Signature */
.scout-signature-box { background:#f9f8f5; padding:20px; border-radius:10px; border:2px solid #007748; }
.scout-signature-input { font-family:'Georgia',serif; font-size:22px; font-style:italic; color:#003d24; border-bottom:2px solid #007748!important; border-radius:0!important; }
.scout-small-text { font-size:12px; color:#6a6a62; margin-top:8px; }

/* Risk */
.scout-risk-notice { background:#fff3f3; padding:16px; border-radius:8px; border-left:4px solid #c0392b; margin-bottom:20px; }
.scout-risk-notice ul { padding-left:20px; }

/* Loi 25 */
.scout-loi25-notice { background:#e8f4ff; padding:16px; border-radius:8px; margin-top:20px; border-left:4px solid #0065cc; font-size:13px; }

/* Confirmation */
.scout-confirm-box { text-align:center; padding:40px 20px; }
.scout-ref-display { font-size:32px; font-weight:700; color:#007748; letter-spacing:3px; margin:20px 0; padding:20px; background:#f9f8f5; border-radius:12px; border:2px solid #007748; }

/* Errors */
.scout-form-errors { background:#fff3f3; border:1px solid #c0392b; border-radius:8px; padding:16px; margin-bottom:20px; color:#c0392b; }

/* Tarification */
.scout-tarif { background:#007748; color:#fff; border-radius:12px; padding:16px; position:sticky; top:20px; }
.scout-tarif h3 { margin:0 0 12px; font-size:16px; }
.scout-tarif-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid rgba(255,255,255,0.15); font-size:14px; }
.scout-tarif-row strong { font-size:16px; }

@media (max-width:640px) {
    .scout-form-grid { grid-template-columns:1fr; }
    .scout-field-wide { grid-column:span 1; }
    .scout-stepper { flex-wrap:wrap; }
    .scout-step { flex:none; width:calc(33% - 4px); }
}
</style>

<div id="scout-inscription-form" class="scout-form-wrapper">

  <!-- STEPPER -->
  <div class="scout-stepper">
    <div class="scout-step active" data-step="1"><span>1</span> Informations</div>
    <div class="scout-step" data-step="2"><span>2</span> Fiche médicale</div>
    <div class="scout-step" data-step="3"><span>3</span> Risques</div>
    <div class="scout-step" data-step="4"><span>4</span> Consentements</div>
    <div class="scout-step" data-step="5"><span>5</span> Confirmation</div>
  </div>

  <div class="scout-form-errors" id="formErrors" style="display:none"></div>

  <!-- STEP 1: INFORMATIONS -->
  <div class="scout-form-step active" id="step1">
    <h2>Informations de l'enfant</h2>

    <div class="scout-form-grid">
      <div class="scout-field"><label for="enfant_prenom">Prénom *</label><input type="text" id="enfant_prenom" name="enfant_prenom" required></div>
      <div class="scout-field"><label for="enfant_nom">Nom *</label><input type="text" id="enfant_nom" name="enfant_nom" required></div>
      <div class="scout-field"><label for="enfant_ddn">Date de naissance *</label><input type="date" id="enfant_ddn" name="enfant_ddn" required></div>
      <div class="scout-field">
        <label for="enfant_sexe">Sexe</label>
        <select id="enfant_sexe" name="enfant_sexe"><option value="">—</option><option value="M">Masculin</option><option value="F">Féminin</option><option value="Autre">Autre</option></select>
      </div>
    </div>

    <div class="scout-form-grid">
      <div class="scout-field scout-field-wide"><label for="enfant_adresse">Adresse *</label><input type="text" id="enfant_adresse" name="enfant_adresse" required></div>
      <div class="scout-field"><label for="enfant_ville">Ville *</label><input type="text" id="enfant_ville" name="enfant_ville" required></div>
      <div class="scout-field"><label for="enfant_code_postal">Code postal *</label><input type="text" id="enfant_code_postal" name="enfant_code_postal" required></div>
    </div>

    <div class="scout-form-grid">
      <div class="scout-field"><label for="enfant_telephone">Téléphone maison</label><input type="tel" id="enfant_telephone" name="enfant_telephone"></div>
      <div class="scout-field">
        <label for="unite">Unité *</label>
        <select id="unite" name="unite" required>
          <option value="">Sélectionner…</option>
          <option value="castors">Castors (7-8 ans)</option>
          <option value="louveteaux">Louveteaux (9-11 ans)</option>
          <option value="eclaireurs">Éclaireurs (12-14 ans)</option>
          <option value="pionniers">Pionniers (14-17 ans)</option>
        </select>
      </div>
    </div>

    <!-- PARENTS -->
    <h2>Parents / Tuteurs</h2>
    <div id="parents-container">
      <div class="scout-contact-block" data-index="1">
        <h3>Parent / Tuteur 1 <small>(principal)</small></h3>
        <div class="scout-form-grid">
          <div class="scout-field"><label>Prénom *</label><input type="text" name="parent_1_prenom" required></div>
          <div class="scout-field"><label>Nom *</label><input type="text" name="parent_1_nom" required></div>
          <div class="scout-field">
            <label>Lien *</label>
            <select name="parent_1_lien" required><option value="Pere">Père</option><option value="Mere">Mère</option><option value="Tuteur">Tuteur</option></select>
          </div>
        </div>
        <div class="scout-form-grid">
          <div class="scout-field"><label>Téléphone *</label><input type="tel" name="parent_1_telephone" required></div>
          <div class="scout-field"><label>Cellulaire</label><input type="tel" name="parent_1_cellulaire"></div>
          <div class="scout-field"><label>Courriel *</label><input type="email" name="parent_1_courriel" required></div>
        </div>
        <label class="scout-checkbox"><input type="checkbox" name="parent_1_resp_finances" checked> Responsable des finances</label>
      </div>
    </div>
    <button type="button" class="scout-btn-add" onclick="scoutAddParent()">+ Ajouter un parent / tuteur</button>
    <input type="hidden" id="parent_count" name="parent_count" value="1">

    <!-- EMERGENCY -->
    <h2>Contacts d'urgence</h2>
    <div id="urgence-container">
      <div class="scout-contact-block" data-index="1">
        <h3>Contact d'urgence 1</h3>
        <div class="scout-form-grid">
          <div class="scout-field"><label>Nom complet *</label><input type="text" name="urgence_1_nom" required></div>
          <div class="scout-field"><label>Téléphone *</label><input type="tel" name="urgence_1_telephone" required></div>
          <div class="scout-field"><label>Lien *</label><input type="text" name="urgence_1_lien" placeholder="Grand-parent, voisin…" required></div>
        </div>
      </div>
    </div>
    <button type="button" class="scout-btn-add" onclick="scoutAddEmergency()">+ Ajouter un contact d'urgence</button>
    <input type="hidden" id="emergency_count" name="emergency_count" value="1">

    <div class="scout-form-grid" style="margin-top:20px">
      <div class="scout-field"><label>Date d'entrée dans le mouvement (année/mois)</label><input type="text" name="date_entree_mouvement" placeholder="2023/09"></div>
      <div class="scout-field">
        <label>Autres enfants dans le groupe?</label>
        <select name="autres_enfants_groupe"><option value="0">Non</option><option value="1">Oui</option></select>
      </div>
      <div class="scout-field"><label>Si oui, nom et unité</label><input type="text" name="autres_enfants_detail"></div>
    </div>

    <div class="scout-form-nav"><button type="button" class="scout-btn-next" onclick="scoutNextStep(2)">Suivant →</button></div>
  </div>

  <!-- STEP 2: FICHE MÉDICALE -->
  <div class="scout-form-step" id="step2">
    <h2>Fiche médicale</h2>

    <div class="scout-form-grid">
      <div class="scout-field"><label>No. assurance maladie *</label><input type="text" name="assurance_maladie" required></div>
      <div class="scout-field"><label>Expiration *</label><input type="text" name="assurance_expiration" placeholder="2026/03" required></div>
    </div>

    <div class="scout-medical-question">
      <label>Attention particulière requise?</label>
      <div><label class="scout-radio"><input type="radio" name="attention_particuliere" value="oui"> Oui</label>
      <label class="scout-radio"><input type="radio" name="attention_particuliere" value="non" checked> Non</label></div>
      <textarea name="attention_detail" placeholder="Si oui, précisez…" rows="2" class="scout-conditional"></textarea>
    </div>
    <div class="scout-medical-question">
      <label>Les vaccins de l'enfant sont-ils à jour?</label>
      <div><label class="scout-radio"><input type="radio" name="vaccins_jour" value="oui" checked> Oui</label>
      <label class="scout-radio"><input type="radio" name="vaccins_jour" value="non"> Non</label></div>
    </div>
    <div class="scout-medical-question">
      <label>Les antécédents médicaux limitent-ils l'activité physique?</label>
      <div><label class="scout-radio"><input type="radio" name="limite_physique" value="oui"> Oui</label>
      <label class="scout-radio"><input type="radio" name="limite_physique" value="non" checked> Non</label></div>
      <textarea name="limite_detail" placeholder="Si oui, précisez…" rows="2" class="scout-conditional"></textarea>
    </div>

    <label>Commentaires supplémentaires</label>
    <textarea name="commentaires_medicaux" rows="3"></textarea>

    <h3>Allergies et médicaments</h3>
    <div class="scout-form-grid">
      <div class="scout-field"><label>Médicaments et posologie</label><textarea name="medicaments" rows="2"></textarea></div>
      <div class="scout-field"><label>Allergies alimentaires</label><textarea name="allergies_alimentaires" rows="2"></textarea></div>
      <div class="scout-field"><label>Allergies à un médicament</label><textarea name="allergies_medicament" rows="2"></textarea></div>
      <div class="scout-field"><label>Autres restrictions alimentaires</label><textarea name="restrictions_alimentaires" rows="2"></textarea></div>
    </div>

    <div class="scout-form-nav">
      <button type="button" class="scout-btn-prev" onclick="scoutPrevStep(1)">← Précédent</button>
      <button type="button" class="scout-btn-next" onclick="scoutNextStep(3)">Suivant →</button>
    </div>
  </div>

  <!-- STEP 3: ACCEPTATION DES RISQUES -->
  <div class="scout-form-step" id="step3">
    <h2>Acceptation des risques</h2>
    <div class="scout-risk-notice">
      <p>Le scoutisme est une activité éducative qui comporte des risques inhérents, incluant sans s'y limiter :</p>
      <ul>
        <li>Activités de plein air (randonnée, camping, canot)</li>
        <li>Activités aquatiques</li>
        <li>Utilisation d'outils (couteaux, haches, scies)</li>
        <li>Préparation de repas sur feu</li>
        <li>Transport en véhicule</li>
        <li>Exposition aux éléments naturels (froid, chaleur, insectes)</li>
        <li>Activités sportives et physiques</li>
      </ul>
    </div>

    <label class="scout-checkbox scout-required"><input type="checkbox" name="risk_accept_1" required> Je reconnais avoir été informé(e) des risques inhérents aux activités scoutes.</label>
    <label class="scout-checkbox scout-required"><input type="checkbox" name="risk_accept_2" required> Je reconnais que malgré les mesures de sécurité, des accidents peuvent survenir.</label>
    <label class="scout-checkbox scout-required"><input type="checkbox" name="risk_accept_3" required> J'autorise mon enfant à participer à toutes les activités du programme scout.</label>
    <label class="scout-checkbox scout-required"><input type="checkbox" name="risk_accept_4" required> En cas d'urgence, j'autorise les responsables à prendre les mesures nécessaires à la sauvegarde de la santé de mon jeune.</label>

    <div class="scout-signature-box" style="margin-top:24px">
      <label for="risk_signature"><strong>Signature électronique *</strong> — Tapez votre nom complet en guise de signature</label>
      <input type="text" id="risk_signature" name="risk_signature" required placeholder="Ex: Marie Tremblay" class="scout-signature-input">
      <p class="scout-small-text">En tapant votre nom, vous confirmez avoir lu et accepté les risques ci-dessus. Date : <?php echo date('d/m/Y'); ?></p>
    </div>

    <div class="scout-form-nav">
      <button type="button" class="scout-btn-prev" onclick="scoutPrevStep(2)">← Précédent</button>
      <button type="button" class="scout-btn-next" onclick="scoutNextStep(4)">Suivant →</button>
    </div>
  </div>

  <!-- STEP 4: CONSENTEMENTS (Loi 25) -->
  <div class="scout-form-step" id="step4">
    <h2>Consentements (Loi 25 du Québec)</h2>
    <p>Conformément à la Loi 25 sur la protection des renseignements personnels, nous demandons votre consentement explicite pour chacun des éléments suivants :</p>

    <label class="scout-checkbox scout-consent scout-required">
      <input type="checkbox" name="consent_donnees" required>
      <strong>Collecte et utilisation des données *</strong><br>
      <span>J'autorise le 5e Groupe scout Grand-Moulin à collecter et utiliser les renseignements personnels de mon enfant aux fins d'inscription, de gestion des activités scoutes et de communication.</span>
    </label>

    <label class="scout-checkbox scout-consent">
      <input type="checkbox" name="consent_photos">
      <strong>Photos et vidéos (optionnel)</strong><br>
      <span>J'autorise l'utilisation de photos et vidéos de mon enfant pour les communications du groupe (site web, réseaux sociaux, publications internes).</span>
    </label>

    <label class="scout-checkbox scout-consent scout-required">
      <input type="checkbox" name="consent_risque" required>
      <strong>Fiche médicale et acceptation des risques *</strong><br>
      <span>Je confirme que les informations médicales fournies sont exactes et j'accepte les risques liés aux activités scoutes.</span>
    </label>

    <label class="scout-checkbox scout-consent scout-required">
      <input type="checkbox" name="consent_conditions" required>
      <strong>Conditions d'utilisation *</strong><br>
      <span>J'ai lu et j'accepte les <a href="<?php echo esc_url(home_url('/conditions/')); ?>" target="_blank">conditions d'utilisation</a>.</span>
    </label>

    <label class="scout-checkbox scout-consent scout-required">
      <input type="checkbox" name="consent_confidentialite" required>
      <strong>Politique de confidentialité *</strong><br>
      <span>J'ai lu et j'accepte la <a href="<?php echo esc_url(get_privacy_policy_url()); ?>" target="_blank">politique de confidentialité</a> du groupe.</span>
    </label>

    <div class="scout-loi25-notice">
      <p>🔒 Responsable de la protection des renseignements : <strong><?php echo esc_html(get_option('scout_ins_privacy_officer', 'Jean Côté')); ?></strong></p>
      <p>Vous pouvez retirer votre consentement à tout moment en communiquant avec nous.</p>
    </div>

    <div class="scout-form-nav">
      <button type="button" class="scout-btn-prev" onclick="scoutPrevStep(3)">← Précédent</button>
      <button type="button" class="scout-btn-submit" onclick="scoutSubmitForm()">Soumettre l'inscription ✓</button>
    </div>
  </div>

  <!-- STEP 5: CONFIRMATION -->
  <div class="scout-form-step" id="step5">
    <div class="scout-confirm-box" id="confirmationBox">
      <h2>🎉 Inscription complétée!</h2>
      <p>L'inscription a été soumise avec succès. Vous recevrez un courriel de confirmation.</p>
      <div class="scout-ref-display" id="refDisplay"></div>
      <div id="qrCodeContainer" style="margin:24px auto;text-align:center"></div>
      <p id="qrCaption" style="font-size:0.82rem;color:#6a6a62;margin-bottom:24px">Présentez ce code QR lors de la première réunion pour vérification rapide.</p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:24px">
        <button onclick="downloadQR()" style="padding:10px 20px;background:#007748;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px">📥 Télécharger le QR</button>
        <button onclick="window.print()" style="padding:10px 20px;background:none;border:2px solid #d0d0c8;color:#3a3a36;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px">🖨️ Imprimer</button>
      </div>
      <div class="scout-confirm-info">
        <h3>Prochaines étapes :</h3>
        <p>1. Conservez votre numéro de référence et code QR</p>
        <p>2. Effectuez le paiement par Interac, chèque ou comptant</p>
        <p>3. Vérifiez votre courriel pour les détails de la première réunion</p>
      </div>
    </div>
  </div>
</div>

<script>
// Minimal QR Code Generator (self-contained, no CDN needed)
var scoutParentCount=1,scoutEmergencyCount=1;
function generateQR(ref, token){
  var container=document.getElementById('qrCodeContainer');
  container.innerHTML='';
  var siteUrl=(typeof scoutInscription!=='undefined')?scoutInscription.siteUrl:window.location.origin;
  var verifyUrl=siteUrl+'/inscription/verification/?ref='+encodeURIComponent(ref)+'&tok='+encodeURIComponent(token);
  var qr=qrcode(0,'H');
  qr.addData(verifyUrl);
  qr.make();
  var size=200,mc=qr.getModuleCount(),cellSize=size/mc;
  var canvas=document.createElement('canvas');
  canvas.width=size;canvas.height=size;
  var ctx=canvas.getContext('2d');
  for(var r=0;r<mc;r++)for(var c=0;c<mc;c++){ctx.fillStyle=qr.isDark(r,c)?'#007748':'#ffffff';ctx.fillRect(c*cellSize,r*cellSize,cellSize+1,cellSize+1);}
  container.appendChild(canvas);
}
function downloadQR(){
  var canvas=document.querySelector('#qrCodeContainer canvas');
  if(!canvas)return;
  var a=document.createElement('a');
  a.download='inscription-qr-'+document.getElementById('refDisplay').textContent+'.png';
  a.href=canvas.toDataURL('image/png');
  a.click();
}
function scoutNextStep(step){var c=step-1;if(!scoutValidateStep(c))return;document.getElementById('step'+c).classList.remove('active');document.getElementById('step'+step).classList.add('active');document.querySelectorAll('.scout-step').forEach(function(el){var s=parseInt(el.getAttribute('data-step'));if(s<step){el.classList.remove('active');el.classList.add('done')}else if(s===step){el.classList.add('active');el.classList.remove('done')}else{el.classList.remove('active','done')}});window.scrollTo({top:0,behavior:'smooth'});scoutHideErrors()}
function scoutPrevStep(step){var c=step+1;document.getElementById('step'+c).classList.remove('active');document.getElementById('step'+step).classList.add('active');document.querySelectorAll('.scout-step').forEach(function(el){var s=parseInt(el.getAttribute('data-step'));if(s<=step){el.classList.add(s===step?'active':'done');el.classList.remove(s===step?'done':'active')}else{el.classList.remove('active','done')}});window.scrollTo({top:0,behavior:'smooth'});scoutHideErrors()}
function scoutValidateStep(step){var el=document.getElementById('step'+step);if(!el)return true;var required=el.querySelectorAll('[required]');var errors=[];required.forEach(function(input){if(!input.value||!input.value.trim()){var label=input.closest('.scout-field,.scout-contact-block');var lt='';if(label){var lbl=label.querySelector('label');if(lbl)lt=lbl.textContent.replace(' *','')}errors.push(lt?lt+' est requis.':'Un champ obligatoire est vide.');input.style.borderColor='#c0392b'}else{input.style.borderColor=''}});if(step===3){for(var i=1;i<=4;i++){var cb=el.querySelector('[name="risk_accept_'+i+'"]');if(cb&&!cb.checked){errors.push('Veuillez accepter toutes les clauses.');break}}var sig=el.querySelector('[name="risk_signature"]');if(sig&&!sig.value.trim())errors.push('Signature requise.')}if(step===4){['consent_donnees','consent_risque','consent_conditions','consent_confidentialite'].forEach(function(n){var cb=el.querySelector('[name="'+n+'"]');if(cb&&!cb.checked)errors.push('Tous les consentements obligatoires doivent être acceptés.')});errors=errors.filter(function(v,i,a){return a.indexOf(v)===i})}if(errors.length>0){scoutShowErrors(errors);return false}return true}
function scoutShowErrors(errors){var el=document.getElementById('formErrors');if(!el)return;var h='<strong>⚠️ Veuillez corriger :</strong><ul>';errors.forEach(function(e){h+='<li>'+e+'</li>'});h+='</ul>';el.innerHTML=h;el.style.display='block';window.scrollTo({top:el.offsetTop-20,behavior:'smooth'})}
function scoutHideErrors(){var el=document.getElementById('formErrors');if(el)el.style.display='none'}
function scoutAddParent(){if(scoutParentCount>=4)return;scoutParentCount++;var i=scoutParentCount;document.getElementById('parent_count').value=i;var h='<div class="scout-contact-block" data-index="'+i+'"><button type="button" class="scout-remove-btn" onclick="scoutRemoveContact(this,\'parent\')" title="Supprimer">✕</button><h3>Parent/Tuteur '+i+'</h3><div class="scout-form-grid"><div class="scout-field"><label>Prénom</label><input type="text" name="parent_'+i+'_prenom"></div><div class="scout-field"><label>Nom</label><input type="text" name="parent_'+i+'_nom"></div><div class="scout-field"><label>Lien</label><select name="parent_'+i+'_lien"><option>Père</option><option>Mère</option><option>Tuteur</option></select></div></div><div class="scout-form-grid"><div class="scout-field"><label>Téléphone</label><input type="tel" name="parent_'+i+'_telephone"></div><div class="scout-field"><label>Courriel</label><input type="email" name="parent_'+i+'_courriel"></div></div></div>';document.getElementById('parents-container').insertAdjacentHTML('beforeend',h)}
function scoutAddEmergency(){if(scoutEmergencyCount>=4)return;scoutEmergencyCount++;var i=scoutEmergencyCount;document.getElementById('emergency_count').value=i;var h='<div class="scout-contact-block" data-index="'+i+'"><button type="button" class="scout-remove-btn" onclick="scoutRemoveContact(this,\'urgence\')" title="Supprimer">✕</button><h3>Contact d\'urgence '+i+'</h3><div class="scout-form-grid"><div class="scout-field"><label>Nom complet</label><input type="text" name="urgence_'+i+'_nom"></div><div class="scout-field"><label>Téléphone</label><input type="tel" name="urgence_'+i+'_telephone"></div><div class="scout-field"><label>Lien</label><input type="text" name="urgence_'+i+'_lien"></div></div></div>';document.getElementById('urgence-container').insertAdjacentHTML('beforeend',h)}
function scoutRemoveContact(btn,type){var b=btn.closest('.scout-contact-block');if(b)b.remove();if(type==='parent')scoutParentCount--;else scoutEmergencyCount--}
function scoutSubmitForm(){if(!scoutValidateStep(4))return;var w=document.getElementById('scout-inscription-form');var inputs=w.querySelectorAll('input,select,textarea');var data={};inputs.forEach(function(input){var n=input.name;if(!n)return;if(input.type==='checkbox')data[n]=input.checked?1:0;else if(input.type==='radio'){if(input.checked)data[n]=input.value}else data[n]=input.value});var btn=w.querySelector('.scout-btn-submit');if(btn){btn.disabled=true;btn.textContent='Envoi en cours...'}var restUrl=(typeof scoutInscription!=='undefined')?scoutInscription.restUrl:'/wp-json/scout-gm/v1/';var nonce=(typeof scoutInscription!=='undefined')?scoutInscription.nonce:'';fetch(restUrl+'inscription',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify(data)}).then(function(r){return r.json()}).then(function(result){if(result.success){scoutNextStep(5);var ref=document.getElementById('refDisplay');if(ref){ref.textContent=result.ref;generateQR(result.ref,result.token||'')}}else{scoutShowErrors(result.errors||['Erreur lors de la soumission.']);if(btn){btn.disabled=false;btn.textContent='Soumettre ✓'}}}).catch(function(){scoutShowErrors(['Erreur de connexion.']);if(btn){btn.disabled=false;btn.textContent='Soumettre ✓'}})}

// ── RENEWAL MODE ──
(function(){
  var params=new URLSearchParams(window.location.search);
  var renewRef=params.get('renew');
  var ftok=params.get('ftok');
  if(!renewRef||!ftok)return;

  var restUrl=(typeof scoutInscription!=='undefined')?scoutInscription.restUrl:'/wp-json/scout-gm/v1/';

  // Show loading state
  var step1=document.getElementById('step1');
  if(step1)step1.innerHTML='<div style="text-align:center;padding:40px"><p>⏳ Chargement du renouvellement...</p></div>';

  fetch(restUrl+'family/'+ftok+'/renew/'+encodeURIComponent(renewRef))
  .then(function(r){return r.json()})
  .then(function(data){
    if(data.error){
      step1.innerHTML='<div style="text-align:center;padding:40px;color:#c0392b"><h3>🚫 '+data.error+'</h3></div>';
      return;
    }

    // Build renewal summary for step 1
    var uniteNames={'castors':'Castors','louveteaux':'Louveteaux','eclaireurs':'Éclaireurs','pionniers':'Pionniers'};
    var unitChanged=(data.unite!==data.previous_unite);

    var html='<div style="background:#f0faf4;border:2px solid #007748;border-radius:12px;padding:24px;margin-bottom:20px">';
    html+='<h2 style="color:#007748;margin:0 0 4px">🔄 Renouvellement d\'inscription</h2>';
    html+='<p style="color:#6a6a62;font-size:0.85rem;margin-bottom:16px">Référence précédente : '+data.previous_ref+'</p>';

    html+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">';
    html+='<div><strong style="font-size:0.78rem;color:#6a6a62">Enfant</strong><br>'+data.enfant_prenom+' '+data.enfant_nom+'</div>';
    html+='<div><strong style="font-size:0.78rem;color:#6a6a62">Date de naissance</strong><br>'+data.enfant_ddn+' ('+data.age+' ans)</div>';
    html+='<div><strong style="font-size:0.78rem;color:#6a6a62">Adresse</strong><br>'+data.enfant_adresse+', '+data.enfant_ville+' '+data.enfant_code_postal+'</div>';
    html+='<div><strong style="font-size:0.78rem;color:#6a6a62">Unité suggérée</strong><br>'+(uniteNames[data.unite]||data.unite);
    if(unitChanged)html+=' <span style="font-size:0.75rem;color:#e67e22">← changement ('+data.age+' ans)</span>';
    html+='</div>';
    html+='</div>';

    // Parents summary
    if(data.parents&&data.parents.length>0){
      html+='<div style="margin-bottom:12px"><strong style="font-size:0.78rem;color:#6a6a62">Parents / Tuteurs</strong>';
      data.parents.forEach(function(p){
        html+='<div style="background:#fff;padding:8px 12px;border-radius:6px;margin-top:4px;font-size:0.85rem">';
        html+=p.prenom+' '+p.nom+' ('+p.lien+')';
        if(p.telephone)html+=' · 📞 '+p.telephone;
        if(p.courriel)html+=' · ✉️ '+p.courriel;
        html+='</div>';
      });
      html+='</div>';
    }

    // Emergency contacts summary
    if(data.urgence&&data.urgence.length>0){
      html+='<div><strong style="font-size:0.78rem;color:#6a6a62">Contacts d\'urgence</strong>';
      data.urgence.forEach(function(u){
        html+='<div style="background:#fff;padding:8px 12px;border-radius:6px;margin-top:4px;font-size:0.85rem">';
        html+=u.nom+' ('+u.lien+') · 📞 '+u.telephone;
        html+='</div>';
      });
      html+='</div>';
    }

    html+='<p style="font-size:0.78rem;color:#6a6a62;margin-top:12px">ℹ️ Ces informations proviennent de l\'inscription précédente. Si quelque chose a changé, contactez-nous après la soumission.</p>';
    html+='</div>';

    // Hidden fields to carry forward
    html+='<input type="hidden" name="renewal_from" value="'+data.previous_ref+'">';
    html+='<input type="hidden" name="family_token" value="'+ftok+'">';
    html+='<input type="hidden" name="enfant_prenom" value="'+data.enfant_prenom+'">';
    html+='<input type="hidden" name="enfant_nom" value="'+data.enfant_nom+'">';
    html+='<input type="hidden" name="enfant_ddn" value="'+data.enfant_ddn+'">';
    html+='<input type="hidden" name="enfant_sexe" value="'+(data.enfant_sexe||'')+'">';
    html+='<input type="hidden" name="enfant_adresse" value="'+data.enfant_adresse+'">';
    html+='<input type="hidden" name="enfant_ville" value="'+(data.enfant_ville||'')+'">';
    html+='<input type="hidden" name="enfant_code_postal" value="'+(data.enfant_code_postal||'')+'">';
    html+='<input type="hidden" name="enfant_telephone" value="'+(data.enfant_telephone||'')+'">';

    // Unit selection (editable)
    html+='<div style="margin-bottom:16px"><label style="font-size:13px;font-weight:500;color:#3a3a36;margin-bottom:4px;display:block">Unité pour cette année</label>';
    html+='<select name="unite" required style="padding:10px 12px;border:1.5px solid #d0d0c8;border-radius:8px;font-size:14px;width:100%;background:#fff">';
    html+='<option value="castors"'+(data.unite==='castors'?' selected':'')+'>Castors (7-8 ans)</option>';
    html+='<option value="louveteaux"'+(data.unite==='louveteaux'?' selected':'')+'>Louveteaux (9-11 ans)</option>';
    html+='<option value="eclaireurs"'+(data.unite==='eclaireurs'?' selected':'')+'>Éclaireurs (12-14 ans)</option>';
    html+='<option value="pionniers"'+(data.unite==='pionniers'?' selected':'')+'>Pionniers (14-17 ans)</option>';
    html+='</select></div>';

    // Pre-fill parent hidden fields
    if(data.parents){
      html+='<input type="hidden" name="parent_count" value="'+data.parents.length+'">';
      data.parents.forEach(function(p,i){
        var n=i+1;
        html+='<input type="hidden" name="parent_'+n+'_prenom" value="'+p.prenom+'">';
        html+='<input type="hidden" name="parent_'+n+'_nom" value="'+p.nom+'">';
        html+='<input type="hidden" name="parent_'+n+'_lien" value="'+p.lien+'">';
        html+='<input type="hidden" name="parent_'+n+'_telephone" value="'+p.telephone+'">';
        html+='<input type="hidden" name="parent_'+n+'_cellulaire" value="'+(p.cellulaire||'')+'">';
        html+='<input type="hidden" name="parent_'+n+'_courriel" value="'+(p.courriel||'')+'">';
        html+='<input type="hidden" name="parent_'+n+'_resp_finances" value="'+(p.resp_finances||0)+'">';
      });
    }
    if(data.urgence){
      html+='<input type="hidden" name="emergency_count" value="'+data.urgence.length+'">';
      data.urgence.forEach(function(u,i){
        var n=i+1;
        html+='<input type="hidden" name="urgence_'+n+'_nom" value="'+u.nom+'">';
        html+='<input type="hidden" name="urgence_'+n+'_telephone" value="'+u.telephone+'">';
        html+='<input type="hidden" name="urgence_'+n+'_lien" value="'+u.lien+'">';
      });
    }

    // Navigation
    html+='<div class="scout-form-nav"><div></div><button type="button" class="scout-btn-next" onclick="scoutNextStep(2)">Suivant : Fiche médicale →</button></div>';

    step1.innerHTML=html;

    // Update stepper label
    var stepperItems=document.querySelectorAll('.scout-step');
    if(stepperItems[0])stepperItems[0].innerHTML='<span>1</span> Confirmation';
  })
  .catch(function(err){
    step1.innerHTML='<div style="text-align:center;padding:40px;color:#c0392b"><h3>Erreur de chargement</h3><p>'+err+'</p></div>';
  });
})();
</script>
