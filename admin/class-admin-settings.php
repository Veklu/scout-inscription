<?php
defined('ABSPATH') || exit;

class Scout_Admin_Settings
{

    public function register_settings(): void
    {
        register_setting('scout_ins_settings', 'scout_ins_current_year', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('scout_ins_settings', 'scout_ins_email_from', ['sanitize_callback' => 'sanitize_email']);
        register_setting('scout_ins_settings', 'scout_ins_privacy_officer', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('scout_ins_settings', 'scout_ins_retention_years', ['sanitize_callback' => 'absint']);
        register_setting('scout_ins_settings', 'scout_ins_pricing', ['sanitize_callback' => [$this, 'sanitize_pricing']]);
        register_setting('scout_ins_settings', 'scout_ins_medical_roles', ['sanitize_callback' => function ($input) {
            if (!is_array($input)) return ['administrator'];
            return array_map('sanitize_text_field', $input);
        }]);
        register_setting('scout_ins_settings', 'scout_ins_require_mfa', ['sanitize_callback' => 'absint']);
        register_setting('scout_ins_settings', 'scout_ins_mfa_duration', ['sanitize_callback' => 'absint']);

        // Daily digest settings
        register_setting('scout_ins_digest', Scout_Daily_Digest::OPTION_ENABLED, ['sanitize_callback' => 'absint']);
        register_setting('scout_ins_digest', Scout_Daily_Digest::OPTION_TIME, ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('scout_ins_digest', Scout_Daily_Digest::OPTION_RECIPIENTS, ['sanitize_callback' => function ($input) {
            if (!is_array($input)) return [];
            return array_map('intval', $input);
        }]);
        register_setting('scout_ins_digest', Scout_Daily_Digest::OPTION_SECTIONS, ['sanitize_callback' => function ($input) {
            if (!is_array($input)) return ['summary', 'new_inscriptions', 'payments', 'outstanding'];
            return array_map('sanitize_key', $input);
        }]);
    }

    public function sanitize_pricing($input): array
    {
        $defaults = ['castors' => 245, 'louveteaux' => 285, 'eclaireurs' => 285, 'pionniers' => 285];
        if (!is_array($input)) return $defaults;
        $clean = [];
        foreach ($defaults as $key => $default) {
            $clean[$key] = isset($input[$key]) ? abs(floatval($input[$key])) : $default;
        }
        return $clean;
    }

    public function render(): void
    {
        $pricing = get_option('scout_ins_pricing', ['castors' => 245, 'louveteaux' => 285, 'eclaireurs' => 285, 'pionniers' => 285]);
?>
        <div class="wrap">
            <h1><?php esc_html_e('Réglages — Plugin d\'inscription', 'scout-inscription'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('scout_ins_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Année scoute active', 'scout-inscription'); ?></th>
                        <td><input type="text" name="scout_ins_current_year" value="<?php echo esc_attr(get_option('scout_ins_current_year', '')); ?>" placeholder="<?php echo esc_attr(Scout_Inscription_Model::get_current_year()); ?>">
                            <p class="description"><?php esc_html_e('Laisser vide pour calcul automatique (sept-août).', 'scout-inscription'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Courriel générique du groupe', 'scout-inscription'); ?></th>
                        <td><input type="email" name="scout_ins_email_from" value="<?php echo esc_attr(get_option('scout_ins_email_from', 'info@example.com')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Utilisé comme adresse d\'expéditeur pour tous les courriels (confirmations, rejets, MFA, rappels) et comme adresse de contact dans les messages aux parents.', 'scout-inscription'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Responsable vie privée (Loi 25)', 'scout-inscription'); ?></th>
                        <td><input type="text" name="scout_ins_privacy_officer" value="<?php echo esc_attr(get_option('scout_ins_privacy_officer', 'Administration')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Rétention données (années)', 'scout-inscription'); ?></th>
                        <td><input type="number" name="scout_ins_retention_years" min="1" max="10" value="<?php echo esc_attr(get_option('scout_ins_retention_years', 2)); ?>">
                            <p class="description"><?php esc_html_e('Nombre d\'années après la fin de l\'année scoute avant la suppression automatique des données personnelles.', 'scout-inscription'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Tarification par unité et par année', 'scout-inscription'); ?></h2>
                <p class="description"><?php esc_html_e('Définissez les prix pour chaque unité par année scoute. L\'année active est utilisée pour les nouvelles inscriptions.', 'scout-inscription'); ?></p>
                <?php
                $pricing_years = get_option('scout_ins_pricing_years', []);
                $current_year = get_option('scout_ins_current_year', '') ?: Scout_Inscription_Model::get_current_year();
                $units_list = function_exists('scout_gm_get_units') ? scout_gm_get_units() : [
                    ['slug' => 'castors', 'name' => 'Castors'],
                    ['slug' => 'louveteaux', 'name' => 'Louveteaux'],
                    ['slug' => 'eclaireurs', 'name' => 'Éclaireurs'],
                    ['slug' => 'pionniers', 'name' => 'Pionniers'],
                ];
                // Ensure current year exists in the pricing
                if (!isset($pricing_years[$current_year])) {
                    $old_pricing = get_option('scout_ins_pricing', ['castors' => 245, 'louveteaux' => 285, 'eclaireurs' => 285, 'pionniers' => 285]);
                    $pricing_years[$current_year] = $old_pricing;
                }
                // Sort years descending
                krsort($pricing_years);
                ?>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom:16px" id="pricingTable">
                    <thead>
                        <tr>
                            <th style="width:120px"><?php esc_html_e('Année', 'scout-inscription'); ?></th>
                            <?php foreach ($units_list as $u): ?>
                                <th><?php echo esc_html($u['name']); ?></th>
                            <?php endforeach; ?>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_years as $year => $prices): ?>
                            <tr<?php echo ($year === $current_year) ? ' style="background:#f0faf4"' : ''; ?>>
                                <td>
                                    <input type="text" name="pricing_year[]" value="<?php echo esc_attr($year); ?>" style="width:100%;font-weight:600" placeholder="2025-2026">
                                    <?php if ($year === $current_year): ?><br><span style="font-size:10px;color:#007748">● Active</span><?php endif; ?>
                                </td>
                                <?php foreach ($units_list as $u): ?>
                                    <td><input type="number" name="pricing_<?php echo esc_attr($u['slug']); ?>[]" step="0.01" min="0" value="<?php echo esc_attr($prices[$u['slug']] ?? 285); ?>" style="width:100%"> $</td>
                                <?php endforeach; ?>
                                <td><button type="button" onclick="this.closest('tr').remove()" class="button" style="color:#c0392b">✕</button></td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="button" onclick="addPricingYear()">+ <?php esc_html_e('Ajouter une année', 'scout-inscription'); ?></button>
                <script>
                    function addPricingYear() {
                        var tbody = document.querySelector('#pricingTable tbody');
                        var tr = document.createElement('tr');
                        var nextYear = new Date().getFullYear();
                        var yearStr = nextYear + '-' + (nextYear + 1);
                        var cells = '<td><input type="text" name="pricing_year[]" value="' + yearStr + '" style="width:100%;font-weight:600"></td>';
                        <?php foreach ($units_list as $u): ?>
                            cells += '<td><input type="number" name="pricing_<?php echo esc_js($u['slug']); ?>[]" step="0.01" min="0" value="285" style="width:100%"> $</td>';
                        <?php endforeach; ?>
                        cells += '<td><button type="button" onclick="this.closest(\'tr\').remove()" class="button" style="color:#c0392b">✕</button></td>';
                        tr.innerHTML = cells;
                        tbody.insertBefore(tr, tbody.firstChild);
                    }
                </script>

                <?php submit_button(__('Sauvegarder les réglages', 'scout-inscription')); ?>

                <h2><?php esc_html_e('Accès aux données médicales (Loi 25)', 'scout-inscription'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Qui peut voir les fiches médicales?', 'scout-inscription'); ?></th>
                        <td>
                            <?php $med_roles = get_option('scout_ins_medical_roles', ['administrator']);
                            if (!is_array($med_roles)) $med_roles = ['administrator']; ?>
                            <?php
                            $all_roles = [
                                'administrator' => __('Administrateur', 'scout-inscription'),
                                'scout_animateur' => __('Animateur scout', 'scout-inscription'),
                                'scout_tresorier' => __('Trésorier scout', 'scout-inscription'),
                            ];
                            foreach ($all_roles as $role_key => $role_label): ?>
                                <label style="display:block;margin-bottom:6px">
                                    <input type="checkbox" name="scout_ins_medical_roles[]" value="<?php echo esc_attr($role_key); ?>"
                                        <?php checked(in_array($role_key, $med_roles)); ?>>
                                    <?php echo esc_html($role_label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('Seuls les utilisateurs avec ces rôles ET le MFA activé pourront accéder aux données médicales.', 'scout-inscription'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Exiger la vérification MFA', 'scout-inscription'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="scout_ins_require_mfa" value="1" <?php checked(get_option('scout_ins_require_mfa', 1)); ?>>
                                <?php esc_html_e('Oui — les utilisateurs doivent confirmer un code par courriel avant de voir les données médicales', 'scout-inscription'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Un code à 6 chiffres est envoyé par courriel. Valide pendant la durée choisie. Chaque accès est journalisé (Loi 25).', 'scout-inscription'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Durée de la session MFA', 'scout-inscription'); ?></th>
                        <td>
                            <select name="scout_ins_mfa_duration">
                                <?php $dur = intval(get_option('scout_ins_mfa_duration', 15)); ?>
                                <option value="5" <?php selected($dur, 5); ?>><?php esc_html_e('5 minutes', 'scout-inscription'); ?></option>
                                <option value="15" <?php selected($dur, 15); ?>><?php esc_html_e('15 minutes', 'scout-inscription'); ?></option>
                                <option value="30" <?php selected($dur, 30); ?>><?php esc_html_e('30 minutes', 'scout-inscription'); ?></option>
                                <option value="60" <?php selected($dur, 60); ?>><?php esc_html_e('1 heure', 'scout-inscription'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Sauvegarder les réglages', 'scout-inscription')); ?>
            </form>

            <!-- Daily Digest Settings (separate form) -->
            <hr style="margin:32px 0">
            <h2><?php esc_html_e('Rapport quotidien automatique', 'scout-inscription'); ?></h2>
            <p class="description" style="margin-bottom:16px"><?php esc_html_e('Un courriel récapitulatif est envoyé chaque jour aux destinataires configurés. Il résume les nouvelles inscriptions, paiements et revenus.', 'scout-inscription'); ?></p>

            <form method="post" action="options.php">
                <?php
                // Register these settings
                register_setting('scout_ins_digest', Scout_Daily_Digest::OPTION_ENABLED);
                register_setting('scout_ins_digest', Scout_Daily_Digest::OPTION_RECIPIENTS);
                register_setting('scout_ins_digest', Scout_Daily_Digest::OPTION_SECTIONS);
                register_setting('scout_ins_digest', Scout_Daily_Digest::OPTION_TIME);
                settings_fields('scout_ins_digest');
                ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Activer le rapport quotidien', 'scout-inscription'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo Scout_Daily_Digest::OPTION_ENABLED; ?>" value="1" <?php checked(get_option(Scout_Daily_Digest::OPTION_ENABLED, 0)); ?>>
                                <?php esc_html_e('Envoyer un rapport chaque jour', 'scout-inscription'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Heure d\'envoi', 'scout-inscription'); ?></th>
                        <td>
                            <input type="time" name="<?php echo Scout_Daily_Digest::OPTION_TIME; ?>" value="<?php echo esc_attr(get_option(Scout_Daily_Digest::OPTION_TIME, '07:00')); ?>">
                            <p class="description"><?php /* translators: %s: timezone string */ printf(esc_html__('Fuseau horaire du serveur WordPress (%s)', 'scout-inscription'), esc_html(wp_timezone_string())); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Destinataires', 'scout-inscription'); ?></th>
                        <td>
                            <?php
                            $selected_users = get_option(Scout_Daily_Digest::OPTION_RECIPIENTS, []);
                            if (!is_array($selected_users)) {
                                // Migrate from old textarea format
                                $old = $selected_users;
                                $selected_users = [];
                                if (is_string($old) && !empty($old)) {
                                    $emails = array_filter(array_map('trim', explode("\n", $old)));
                                    foreach ($emails as $email) {
                                        $user = get_user_by('email', $email);
                                        if ($user) $selected_users[] = $user->ID;
                                    }
                                }
                            }
                            $selected_users = array_map('intval', $selected_users);

                            // Get all users with admin-level roles
                            $all_users = get_users([
                                'role__in' => ['administrator', 'scout_animateur', 'scout_tresorier', 'editor'],
                                'orderby' => 'display_name',
                                'order' => 'ASC',
                            ]);
                            ?>
                            <div style="margin-bottom:8px">
                                <input type="text" id="digest-user-search" placeholder="<?php echo esc_attr__('Rechercher un utilisateur…', 'scout-inscription'); ?>" style="width:100%;max-width:400px;padding:6px 10px" oninput="
                                    var q = this.value.toLowerCase();
                                    document.querySelectorAll('.digest-user-item').forEach(function(el) {
                                        el.style.display = el.dataset.search.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
                                    });
                                ">
                            </div>
                            <div style="max-height:250px;overflow-y:auto;border:1px solid #ddd;border-radius:6px;padding:8px;background:#fafafa">
                                <?php if (empty($all_users)): ?>
                                    <p style="color:#6a6a62;margin:0"><?php esc_html_e('Aucun utilisateur trouvé.', 'scout-inscription'); ?></p>
                                <?php endif; ?>
                                <?php foreach ($all_users as $user):
                                    $roles = implode(', ', $user->roles);
                                    $role_badges = [];
                                    if (in_array('administrator', $user->roles)) $role_badges[] = '<span style="background:#c0392b;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px">Admin</span>';
                                    if (in_array('scout_animateur', $user->roles)) $role_badges[] = '<span style="background:#2563eb;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px">Animateur</span>';
                                    if (in_array('scout_tresorier', $user->roles)) $role_badges[] = '<span style="background:#27ae60;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px">Trésorier</span>';
                                    if (in_array('editor', $user->roles)) $role_badges[] = '<span style="background:#e67e22;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px">Éditeur</span>';
                                ?>
                                    <label class="digest-user-item" data-search="<?php echo esc_attr($user->display_name . ' ' . $user->user_email . ' ' . $roles); ?>" style="display:flex;align-items:center;gap:8px;padding:6px 8px;border-bottom:1px solid #f0f0f0;cursor:pointer">
                                        <input type="checkbox" name="<?php echo Scout_Daily_Digest::OPTION_RECIPIENTS; ?>[]" value="<?php echo $user->ID; ?>"
                                            <?php checked(in_array($user->ID, $selected_users)); ?>>
                                        <div>
                                            <strong style="font-size:13px"><?php echo esc_html($user->display_name); ?></strong>
                                            <span style="color:#6a6a62;font-size:12px;margin-left:4px"><?php echo esc_html($user->user_email); ?></span>
                                            <div style="margin-top:2px"><?php echo implode(' ', $role_badges); ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description" style="margin-top:6px"><?php esc_html_e('Cochez les utilisateurs qui recevront le rapport quotidien. Seuls les administrateurs, animateurs, trésoriers et éditeurs sont listés.', 'scout-inscription'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Sections à inclure', 'scout-inscription'); ?></th>
                        <td>
                            <?php
                            $current_sections = get_option(Scout_Daily_Digest::OPTION_SECTIONS, ['summary', 'new_inscriptions', 'payments', 'outstanding']);
                            if (!is_array($current_sections)) $current_sections = ['summary', 'new_inscriptions', 'payments', 'outstanding'];
                            $available_sections = [
                                'summary'           => [__('Résumé global', 'scout-inscription'), __('Total inscriptions, approuvées, en attente, par unité', 'scout-inscription')],
                                'new_inscriptions'  => [__('Nouvelles inscriptions', 'scout-inscription'), __('Liste des inscriptions reçues dans les dernières 24h', 'scout-inscription')],
                                'status_changes'    => [__('Changements de statut', 'scout-inscription'), __('Inscriptions dont le statut a changé (approuvée, rejetée, etc.)', 'scout-inscription')],
                                'payments'          => [__('Paiements reçus', 'scout-inscription'), __('Détail des paiements enregistrés dans les dernières 24h', 'scout-inscription')],
                                'outstanding'       => [__('Revenus & impayés', 'scout-inscription'), __('Total attendu, reçu et solde impayé', 'scout-inscription')],
                            ];
                            foreach ($available_sections as $key => $info): ?>
                                <label style="display:block;margin-bottom:8px">
                                    <input type="checkbox" name="<?php echo Scout_Daily_Digest::OPTION_SECTIONS; ?>[]" value="<?php echo esc_attr($key); ?>"
                                        <?php checked(in_array($key, $current_sections)); ?>>
                                    <strong><?php echo esc_html($info[0]); ?></strong>
                                    <span style="color:#6a6a62;font-size:12px;display:block;margin-left:24px"><?php echo esc_html($info[1]); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Sauvegarder les réglages du rapport', 'scout-inscription')); ?>
            </form>

            <!-- Test Send -->
            <div style="background:#f9f8f5;border:1px solid #e0ddd4;border-radius:8px;padding:16px;margin-top:12px">
                <h3 style="margin:0 0 8px;font-size:14px"><?php esc_html_e('Tester l\'envoi', 'scout-inscription'); ?></h3>
                <?php
                $last_sent = get_option('scout_ins_digest_last_sent', '');
                $next = wp_next_scheduled(Scout_Daily_Digest::CRON_HOOK);
                ?>
                <?php if ($last_sent): ?>
                    <p style="font-size:13px;color:#6a6a62;margin:0 0 8px"><?php /* translators: %s: date of last send */ printf(esc_html__('Dernier envoi : %s', 'scout-inscription'), '<strong>' . esc_html($last_sent) . '</strong>'); ?></p>
                <?php endif; ?>
                <?php if ($next): ?>
                    <p style="font-size:13px;color:#6a6a62;margin:0 0 8px"><?php /* translators: %s: next scheduled date */ printf(esc_html__('Prochain envoi prévu : %s', 'scout-inscription'), '<strong>' . esc_html(wp_date('Y-m-d H:i', $next)) . '</strong>'); ?></p>
                <?php elseif (get_option(Scout_Daily_Digest::OPTION_ENABLED, 0)): ?>
                    <p style="font-size:13px;color:#e67e22;margin:0 0 8px"><?php esc_html_e('Le cron n\'est pas programmé. Désactivez puis réactivez le rapport ou sauvegardez les réglages.', 'scout-inscription'); ?></p>
                <?php endif; ?>
                <form method="post">
                    <?php wp_nonce_field('scout_digest_test', '_scout_digest_test_nonce'); ?>
                    <button type="submit" name="scout_send_test_digest" class="button" onclick="return confirm('<?php echo esc_js(__('Envoyer un courriel test maintenant aux destinataires configurés?', 'scout-inscription')); ?>')"><?php esc_html_e('Envoyer un test maintenant', 'scout-inscription'); ?></button>
                    <span style="font-size:12px;color:#6a6a62;margin-left:8px"><?php esc_html_e('Envoie le rapport avec les données actuelles à tous les destinataires.', 'scout-inscription'); ?></span>
                </form>
            </div>

        </div>
<?php
    }
}

// Save pricing years on settings page submit
add_action('admin_init', function () {
    if (!isset($_POST['pricing_year']) || !current_user_can('manage_options')) return;
    if (!isset($_POST['option_page']) || $_POST['option_page'] !== 'scout_ins_settings') return;

    $years = $_POST['pricing_year'] ?? [];
    $units_list = function_exists('scout_gm_get_units') ? scout_gm_get_units() : [
        ['slug' => 'castors'],
        ['slug' => 'louveteaux'],
        ['slug' => 'eclaireurs'],
        ['slug' => 'pionniers'],
    ];

    $pricing_years = [];
    foreach ($years as $i => $year) {
        $year = sanitize_text_field($year);
        if (empty($year)) continue;
        $pricing_years[$year] = [];
        foreach ($units_list as $u) {
            $slug = $u['slug'];
            $val = isset($_POST['pricing_' . $slug][$i]) ? abs(floatval($_POST['pricing_' . $slug][$i])) : 285;
            $pricing_years[$year][$slug] = $val;
        }
    }
    update_option('scout_ins_pricing_years', $pricing_years);

    // Also update the flat pricing option with the current year's prices (backwards compat)
    $current_year = get_option('scout_ins_current_year', '') ?: (class_exists('Scout_Inscription_Model') ? Scout_Inscription_Model::get_current_year() : '');
    if (isset($pricing_years[$current_year])) {
        update_option('scout_ins_pricing', $pricing_years[$current_year]);
    }
});
