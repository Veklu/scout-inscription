<?php
defined('ABSPATH') || exit;

class Scout_Public_Family {

    public function render(): string {
        $tok = sanitize_text_field($_GET['tok'] ?? '');
        $ref = sanitize_text_field($_GET['ref'] ?? '');
        $mode = !empty($tok) ? 'dashboard' : (!empty($ref) ? 'lookup' : 'start');

        ob_start();
        echo '<div id="scoutFamilyApp" class="scout-form-wrapper">';

        if ($mode === 'start' || $mode === 'lookup') {
            $this->render_lookup($ref);
        } elseif ($mode === 'dashboard') {
            $this->render_dashboard($tok);
        }

        echo '</div>';
        echo $this->inline_js();
        return ob_get_clean();
    }

    private function render_lookup(string $ref): void {
        ?>
        <div style="max-width:500px;margin:0 auto;text-align:center">
            <h2 style="color:#007748;margin-bottom:8px">👨‍👩‍👧‍👦 Tableau de bord familial</h2>
            <p style="color:#6a6a62;margin-bottom:24px">Gérez les inscriptions de vos enfants, renouvelez ou inscrivez un nouveau membre.</p>

            <div id="lookupPanel" style="background:#f9f8f5;padding:24px;border-radius:12px;border:1px solid #e0ddd4">
                <h3 style="margin:0 0 16px;font-size:1rem">Accéder à votre dossier</h3>
                <div style="margin-bottom:12px">
                    <input type="text" id="refInput" placeholder="Numéro de référence (GM-2025-XXXX)" value="<?php echo esc_attr($ref); ?>"
                        style="width:100%;padding:12px;border:1.5px solid #d0d0c8;border-radius:8px;font-size:14px;text-align:center;letter-spacing:1px;text-transform:uppercase">
                </div>
                <button onclick="familyLookup()" id="lookupBtn"
                    style="width:100%;padding:12px;background:#007748;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer">
                    Rechercher →
                </button>
                <div id="lookupResult" style="margin-top:16px;display:none"></div>
                <div id="lookupError" style="margin-top:12px;color:#c0392b;display:none"></div>
            </div>

            <p style="font-size:13px;color:#6a6a62;margin-top:16px">
                Vous n'avez pas votre numéro de référence? Vérifiez le courriel de confirmation reçu lors de l'inscription.
            </p>
        </div>
        <?php
    }

    private function render_dashboard(string $tok): void {
        ?>
        <div id="dashboardLoading" style="text-align:center;padding:40px">
            <p>⏳ Chargement de votre dossier familial...</p>
        </div>
        <div id="dashboardContent" style="display:none">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
                <h2 style="color:#007748;margin:0">👨‍👩‍👧‍👦 Tableau de bord familial</h2>
                <a href="<?php echo esc_url(home_url('/inscription/')); ?>" style="display:inline-block;padding:10px 20px;background:#007748;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px">+ Inscrire un nouvel enfant</a>
            </div>
            <div id="childrenList"></div>
        </div>
        <input type="hidden" id="familyToken" value="<?php echo esc_attr($tok); ?>">
        <?php
    }

    private function inline_js(): string {
        $rest_url = esc_url(rest_url('scout-gm/v1/'));
        $nonce = wp_create_nonce('wp_rest');
        $inscription_url = esc_url(home_url('/inscription/'));

        return <<<JS
<script>
var familyRestUrl = '{$rest_url}';
var familyNonce = '{$nonce}';
var inscriptionUrl = '{$inscription_url}';

function familyLookup() {
    var ref = document.getElementById('refInput').value.trim().toUpperCase();
    if (!ref) return;
    var btn = document.getElementById('lookupBtn');
    btn.disabled = true; btn.textContent = 'Recherche...';
    document.getElementById('lookupError').style.display = 'none';
    document.getElementById('lookupResult').style.display = 'none';

    fetch(familyRestUrl + 'family/lookup', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-WP-Nonce': familyNonce},
        body: JSON.stringify({ref: ref})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false; btn.textContent = 'Rechercher →';
        if (data.success) {
            var html = '<div style="background:#f0faf4;padding:16px;border-radius:8px;border:1px solid #007748;text-align:left">';
            html += '<p><strong>Référence :</strong> ' + ref + '</p>';
            html += '<p><strong>Courriel associé :</strong> ' + data.masked_emails.join(', ') + '</p>';
            html += '<p style="font-size:13px;color:#6a6a62;margin-top:8px">Un lien d\'accès au tableau de bord sera envoyé à cette adresse.</p>';
            html += '<button onclick="familySendLink(\'' + ref + '\')" id="sendLinkBtn" style="margin-top:12px;width:100%;padding:10px;background:#007748;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer">📧 Envoyer le lien d\'accès</button>';
            html += '<div id="sendResult" style="margin-top:8px"></div>';
            html += '</div>';
            document.getElementById('lookupResult').innerHTML = html;
            document.getElementById('lookupResult').style.display = 'block';
        } else {
            document.getElementById('lookupError').textContent = data.error || 'Introuvable.';
            document.getElementById('lookupError').style.display = 'block';
        }
    })
    .catch(function() {
        btn.disabled = false; btn.textContent = 'Rechercher →';
        document.getElementById('lookupError').textContent = 'Erreur de connexion.';
        document.getElementById('lookupError').style.display = 'block';
    });
}

function familySendLink(ref) {
    var btn = document.getElementById('sendLinkBtn');
    btn.disabled = true; btn.textContent = 'Envoi en cours...';
    fetch(familyRestUrl + 'family/send-link', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-WP-Nonce': familyNonce},
        body: JSON.stringify({ref: ref})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('sendResult').innerHTML = '<p style="color:#27ae60;font-weight:600">✅ ' + data.message + '</p><p style="font-size:12px;color:#6a6a62">Vérifiez votre boîte de réception (et les courriels indésirables).</p>';
            btn.style.display = 'none';
        } else {
            document.getElementById('sendResult').innerHTML = '<p style="color:#c0392b">' + (data.error || 'Erreur') + '</p>';
            btn.disabled = false; btn.textContent = '📧 Envoyer le lien d\'accès';
        }
    });
}

// Dashboard mode
var dashTok = document.getElementById('familyToken');
if (dashTok && dashTok.value) {
    loadDashboard(dashTok.value);
}

function loadDashboard(tok) {
    fetch(familyRestUrl + 'family/' + tok + '/dashboard')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('dashboardLoading').style.display = 'none';
        if (data.error) {
            document.getElementById('dashboardContent').innerHTML = '<div style="text-align:center;padding:40px"><h2 style="color:#c0392b">🚫 ' + data.error + '</h2><p><a href="' + inscriptionUrl + 'famille/">Réessayer</a></p></div>';
            document.getElementById('dashboardContent').style.display = 'block';
            return;
        }
        document.getElementById('dashboardContent').style.display = 'block';
        var container = document.getElementById('childrenList');
        var html = '';

        if (data.children.length === 0) {
            html = '<div style="text-align:center;padding:40px;background:#f9f8f5;border-radius:12px"><p>Aucune inscription trouvée.</p><a href="' + inscriptionUrl + '" style="display:inline-block;margin-top:12px;padding:10px 24px;background:#007748;color:#fff;border-radius:8px;text-decoration:none;font-weight:600">Inscrire un enfant →</a></div>';
        }

        data.children.forEach(function(child) {
            html += '<div style="background:#fff;border:1px solid #e0ddd4;border-radius:12px;padding:20px;margin-bottom:16px">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">';
            html += '<h3 style="margin:0;color:#1a1a16">👦 ' + child.prenom + ' ' + child.nom + '</h3>';
            html += '<span style="font-size:13px;color:#6a6a62">Né(e) le ' + child.ddn + '</span>';
            html += '</div>';

            html += '<table style="width:100%;margin-top:12px;border-collapse:collapse;font-size:13px">';
            html += '<thead><tr style="background:#f9f8f5"><th style="padding:8px;text-align:left">Année</th><th style="padding:8px">Unité</th><th style="padding:8px">Statut</th><th style="padding:8px">Paiement</th><th style="padding:8px"></th></tr></thead><tbody>';

            child.inscriptions.forEach(function(ins) {
                var rowStyle = ins.is_current ? 'background:#f0faf4;font-weight:500' : '';
                html += '<tr style="border-bottom:1px solid #f0ede6;' + rowStyle + '">';
                html += '<td style="padding:8px">' + ins.annee + (ins.is_current ? ' <span style="color:#007748;font-size:11px">● En cours</span>' : '') + '</td>';
                html += '<td style="padding:8px;text-align:center">' + ins.unite + '</td>';
                html += '<td style="padding:8px;text-align:center">' + ins.status + '</td>';
                html += '<td style="padding:8px;text-align:center">' + ins.payment + '</td>';
                html += '<td style="padding:8px;text-align:right">';
                if (ins.can_renew) {
                    html += '<a href="' + inscriptionUrl + '?renew=' + ins.ref + '&ftok=' + tok + '" style="padding:4px 12px;background:#007748;color:#fff;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600">Renouveler →</a>';
                }
                html += '</td></tr>';
            });

            html += '</tbody></table></div>';
        });

        container.innerHTML = html;
    })
    .catch(function() {
        document.getElementById('dashboardLoading').innerHTML = '<p style="color:#c0392b">Erreur de connexion.</p>';
    });
}

document.getElementById('refInput') && document.getElementById('refInput').addEventListener('keydown', function(e) { if (e.key === 'Enter') familyLookup(); });
</script>
JS;
    }
}
