/**
 * Scout Inscription — Public JS
 * Stepper, validation, submission, dynamic contacts
 */

var scoutParentCount = 1;
var scoutEmergencyCount = 1;

/* ── STEPPER ── */
function scoutNextStep(step) {
    var current = step - 1;
    if (!scoutValidateStep(current)) return;

    document.getElementById('step' + current).classList.remove('active');
    document.getElementById('step' + step).classList.add('active');

    var steps = document.querySelectorAll('.scout-step');
    steps.forEach(function(el) {
        var s = parseInt(el.getAttribute('data-step'));
        if (s < step) { el.classList.remove('active'); el.classList.add('done'); }
        else if (s === step) { el.classList.add('active'); el.classList.remove('done'); }
        else { el.classList.remove('active', 'done'); }
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
    scoutHideErrors();
}

function scoutPrevStep(step) {
    var current = step + 1;
    document.getElementById('step' + current).classList.remove('active');
    document.getElementById('step' + step).classList.add('active');

    var steps = document.querySelectorAll('.scout-step');
    steps.forEach(function(el) {
        var s = parseInt(el.getAttribute('data-step'));
        if (s <= step) { el.classList.add(s === step ? 'active' : 'done'); el.classList.remove(s === step ? 'done' : 'active'); }
        else { el.classList.remove('active', 'done'); }
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
    scoutHideErrors();
}

/* ── VALIDATION ── */
function scoutValidateStep(step) {
    var el = document.getElementById('step' + step);
    if (!el) return true;

    var required = el.querySelectorAll('[required]');
    var errors = [];

    required.forEach(function(input) {
        if (!input.value || !input.value.trim()) {
            var label = input.closest('.scout-field, .scout-contact-block');
            var labelText = '';
            if (label) {
                var lbl = label.querySelector('label');
                if (lbl) labelText = lbl.textContent.replace(' *', '');
            }
            errors.push(labelText ? labelText + ' est requis.' : 'Un champ obligatoire est vide.');
            input.style.borderColor = '#c0392b';
        } else {
            input.style.borderColor = '';
        }
    });

    // Step 3: check risk checkboxes
    if (step === 3) {
        for (var i = 1; i <= 4; i++) {
            var cb = el.querySelector('[name="risk_accept_' + i + '"]');
            if (cb && !cb.checked) {
                errors.push('Veuillez accepter toutes les clauses de reconnaissance.');
                break;
            }
        }
        var sig = el.querySelector('[name="risk_signature"]');
        if (sig && !sig.value.trim()) {
            errors.push('La signature électronique est requise.');
        }
    }

    // Step 4: check required consents
    if (step === 4) {
        ['consent_donnees', 'consent_risque', 'consent_conditions', 'consent_confidentialite'].forEach(function(name) {
            var cb = el.querySelector('[name="' + name + '"]');
            if (cb && !cb.checked) errors.push('Tous les consentements obligatoires doivent être acceptés.');
        });
        // Deduplicate
        errors = errors.filter(function(v, i, a) { return a.indexOf(v) === i; });
    }

    if (errors.length > 0) {
        scoutShowErrors(errors);
        return false;
    }

    return true;
}

/* ── ERRORS ── */
function scoutShowErrors(errors) {
    var el = document.getElementById('formErrors');
    if (!el) return;
    var html = '<strong>⚠️ Veuillez corriger les erreurs suivantes :</strong><ul>';
    errors.forEach(function(e) { html += '<li>' + e + '</li>'; });
    html += '</ul>';
    el.innerHTML = html;
    el.style.display = 'block';
    window.scrollTo({ top: el.offsetTop - 20, behavior: 'smooth' });
}

function scoutHideErrors() {
    var el = document.getElementById('formErrors');
    if (el) el.style.display = 'none';
}

/* ── ADD/REMOVE CONTACTS ── */
function scoutAddParent() {
    if (scoutParentCount >= 4) return;
    scoutParentCount++;
    document.getElementById('parent_count').value = scoutParentCount;
    var i = scoutParentCount;

    var html = '<div class="scout-contact-block" data-index="' + i + '">'
        + '<button type="button" class="scout-remove-btn" onclick="scoutRemoveContact(this, \'parent\')" title="Supprimer">✕</button>'
        + '<h3>Parent / Tuteur ' + i + '</h3>'
        + '<div class="scout-form-grid">'
        + '<div class="scout-field"><label>Prénom</label><input type="text" name="parent_' + i + '_prenom"></div>'
        + '<div class="scout-field"><label>Nom</label><input type="text" name="parent_' + i + '_nom"></div>'
        + '<div class="scout-field"><label>Lien</label><select name="parent_' + i + '_lien"><option value="Pere">Père</option><option value="Mere">Mère</option><option value="Tuteur">Tuteur</option></select></div>'
        + '</div><div class="scout-form-grid">'
        + '<div class="scout-field"><label>Téléphone</label><input type="tel" name="parent_' + i + '_telephone"></div>'
        + '<div class="scout-field"><label>Cellulaire</label><input type="tel" name="parent_' + i + '_cellulaire"></div>'
        + '<div class="scout-field"><label>Courriel</label><input type="email" name="parent_' + i + '_courriel"></div>'
        + '</div>'
        + '<label class="scout-checkbox"><input type="checkbox" name="parent_' + i + '_resp_finances"> Responsable des finances</label>'
        + '</div>';

    document.getElementById('parents-container').insertAdjacentHTML('beforeend', html);
}

function scoutAddEmergency() {
    if (scoutEmergencyCount >= 4) return;
    scoutEmergencyCount++;
    document.getElementById('emergency_count').value = scoutEmergencyCount;
    var i = scoutEmergencyCount;

    var html = '<div class="scout-contact-block" data-index="' + i + '">'
        + '<button type="button" class="scout-remove-btn" onclick="scoutRemoveContact(this, \'urgence\')" title="Supprimer">✕</button>'
        + '<h3>Contact d\'urgence ' + i + '</h3>'
        + '<div class="scout-form-grid">'
        + '<div class="scout-field"><label>Nom complet</label><input type="text" name="urgence_' + i + '_nom"></div>'
        + '<div class="scout-field"><label>Téléphone</label><input type="tel" name="urgence_' + i + '_telephone"></div>'
        + '<div class="scout-field"><label>Lien</label><input type="text" name="urgence_' + i + '_lien"></div>'
        + '</div></div>';

    document.getElementById('urgence-container').insertAdjacentHTML('beforeend', html);
}

function scoutRemoveContact(btn, type) {
    var block = btn.closest('.scout-contact-block');
    if (block) block.remove();
    if (type === 'parent') {
        scoutParentCount--;
        document.getElementById('parent_count').value = scoutParentCount;
    } else {
        scoutEmergencyCount--;
        document.getElementById('emergency_count').value = scoutEmergencyCount;
    }
}

/* ── SUBMIT ── */
function scoutSubmitForm() {
    if (!scoutValidateStep(4)) return;

    var wrapper = document.getElementById('scout-inscription-form');
    var inputs = wrapper.querySelectorAll('input, select, textarea');
    var data = {};

    inputs.forEach(function(input) {
        var name = input.name;
        if (!name) return;

        if (input.type === 'checkbox') {
            data[name] = input.checked ? 1 : 0;
        } else if (input.type === 'radio') {
            if (input.checked) data[name] = input.value;
        } else {
            data[name] = input.value;
        }
    });

    // Disable submit button
    var btn = wrapper.querySelector('.scout-btn-submit');
    if (btn) { btn.disabled = true; btn.textContent = 'Envoi en cours...'; }

    fetch(scoutInscription.restUrl + 'inscription', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': scoutInscription.nonce,
        },
        body: JSON.stringify(data),
    })
    .then(function(res) { return res.json(); })
    .then(function(result) {
        if (result.success) {
            // Go to step 5
            scoutNextStep(5);
            var refEl = document.getElementById('refDisplay');
            if (refEl) refEl.textContent = result.ref;
        } else {
            scoutShowErrors(result.errors || ['Erreur lors de la soumission.']);
            if (btn) { btn.disabled = false; btn.textContent = 'Soumettre l\'inscription ✓'; }
        }
    })
    .catch(function(err) {
        scoutShowErrors(['Erreur de connexion. Veuillez réessayer.']);
        if (btn) { btn.disabled = false; btn.textContent = 'Soumettre l\'inscription ✓'; }
    });
}
