<?php
/**
 * Plugin Name: Scout Inscription
 * Plugin URI:  https://www.5escoutgrandmoulin.org
 * Description: Plugin d'inscription en ligne pour le 5e Groupe scout Grand-Moulin. Formulaire 5 étapes, fiches médicales, QR codes signés HMAC-SHA256, suivi des paiements, conforme Loi 25.
 * Version:     1.0.0
 * Author:      5e Groupe scout Grand-Moulin
 * Author URI:  https://www.5escoutgrandmoulin.org
 * Text Domain: scout-inscription
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 * License:     GPL-2.0+
 */

defined('ABSPATH') || exit;

// ═══════════ CONSTANTS ═══════════
define('SCOUT_INS_VERSION', '1.0.0');
define('SCOUT_INS_FILE', __FILE__);
define('SCOUT_INS_DIR', plugin_dir_path(__FILE__));
define('SCOUT_INS_URL', plugin_dir_url(__FILE__));
define('SCOUT_INS_BASENAME', plugin_basename(__FILE__));

// Table prefix
global $wpdb;
define('SCOUT_DB_PREFIX', $wpdb->prefix . 'scout_');

// ═══════════ AUTOLOAD INCLUDES ═══════════
$includes = [
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-encryption.php',
    'includes/class-inscription-model.php',
    'includes/class-contact-model.php',
    'includes/class-payment-model.php',
    'includes/class-access-log.php',
    'includes/class-qr-generator.php',
    'includes/class-pdf-generator.php',
    'includes/class-form-handler.php',
    'includes/class-email-handler.php',
    'includes/class-rest-api.php',
    'includes/class-export.php',
    'includes/class-mfa.php',
    'includes/class-family-model.php',
    'includes/class-daily-digest.php',
    'admin/class-admin-dashboard.php',
    'admin/class-admin-settings.php',
    'public/class-public-form.php',
    'public/class-public-verify.php',
    'public/class-public-family.php',
];

foreach ($includes as $file) {
    $path = SCOUT_INS_DIR . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// ═══════════ ACTIVATION / DEACTIVATION ═══════════
register_activation_hook(__FILE__, ['Scout_Inscription_Activator', 'activate']);
register_activation_hook(__FILE__, ['Scout_Daily_Digest', 'activate']);
register_deactivation_hook(__FILE__, ['Scout_Inscription_Deactivator', 'deactivate']);
register_deactivation_hook(__FILE__, ['Scout_Daily_Digest', 'deactivate']);

// ═══════════ INIT ═══════════
add_action('plugins_loaded', function () {
    load_plugin_textdomain('scout-inscription', false, dirname(SCOUT_INS_BASENAME) . '/languages');
});

add_action('init', function () {
    // Register shortcodes
    if (class_exists('Scout_Public_Form')) {
        add_shortcode('scout_inscription', [new Scout_Public_Form(), 'render']);
    }
    if (class_exists('Scout_Public_Verify')) {
        add_shortcode('scout_verification', [new Scout_Public_Verify(), 'render']);
    }
    if (class_exists('Scout_Public_Family')) {
        add_shortcode('scout_famille', [new Scout_Public_Family(), 'render']);
    }

    // Initialize daily digest cron
    if (class_exists('Scout_Daily_Digest')) {
        Scout_Daily_Digest::init();
    }

    // Handle test digest send
    if (isset($_POST['scout_send_test_digest']) && current_user_can('manage_options')) {
        if (wp_verify_nonce($_POST['_scout_digest_test_nonce'] ?? '', 'scout_digest_test')) {
            $sent = Scout_Daily_Digest::send_test();
            add_action('admin_notices', function() use ($sent) {
                if ($sent) {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Courriel test envoyé avec succès!', 'scout-inscription') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Échec d\'envoi. Vérifiez les destinataires.', 'scout-inscription') . '</p></div>';
                }
            });
        }
    }
});

// Admin hooks
add_action('admin_menu', function () {
    if (class_exists('Scout_Admin_Dashboard')) {
        (new Scout_Admin_Dashboard())->register_menu();
    }
});

add_action('admin_init', function () {
    if (class_exists('Scout_Admin_Settings')) {
        (new Scout_Admin_Settings())->register_settings();
    }
});

// REST API
add_action('rest_api_init', function () {
    if (class_exists('Scout_REST_API')) {
        (new Scout_REST_API())->register_routes();
    }
});

// Enqueue admin assets
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'scout-inscription') === false) return;
    wp_enqueue_style('scout-admin', SCOUT_INS_URL . 'admin/css/admin.css', [], SCOUT_INS_VERSION);
    wp_enqueue_script('scout-admin', SCOUT_INS_URL . 'admin/js/admin.js', ['jquery'], SCOUT_INS_VERSION, true);
    wp_localize_script('scout-admin', 'scoutAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('scout_admin_nonce'),
    ]);
});

// Enqueue public assets — always load on frontend (CSS is scoped to .scout-form-wrapper)
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('scout-public', SCOUT_INS_URL . 'public/css/inscription.css', [], SCOUT_INS_VERSION);
    wp_enqueue_script('scout-public', SCOUT_INS_URL . 'public/js/inscription.js', [], SCOUT_INS_VERSION, true);
    wp_localize_script('scout-public', 'scoutInscription', [
        'restUrl' => rest_url('scout-gm/v1/'),
        'nonce'   => wp_create_nonce('wp_rest'),
        'siteUrl' => home_url(),
    ]);
});

// Cron for data retention
add_action('scout_data_retention_cron', function () {
    if (class_exists('Scout_Inscription_Model')) {
        Scout_Inscription_Model::purge_expired();
    }
});

if (!wp_next_scheduled('scout_data_retention_cron')) {
    wp_schedule_event(time(), 'weekly', 'scout_data_retention_cron');
}

// Ensure all roles have correct capabilities (repairs failed activations)
add_action('admin_init', function () {
    // Admin gets everything
    $admin = get_role('administrator');
    if ($admin) {
        $caps = [
            'scout_view_inscriptions', 'scout_view_medical', 'scout_scan_qr',
            'scout_manage_payments', 'scout_export',
            'scout_manage_inscriptions', 'scout_manage_settings',
        ];
        foreach ($caps as $cap) {
            if (!$admin->has_cap($cap)) $admin->add_cap($cap);
        }
    }

    // Animateur
    $animateur = get_role('scout_animateur');
    if ($animateur) {
        foreach (['read', 'scout_view_inscriptions', 'scout_view_medical', 'scout_scan_qr'] as $cap) {
            if (!$animateur->has_cap($cap)) $animateur->add_cap($cap);
        }
    }

    // Trésorier
    $tresorier = get_role('scout_tresorier');
    if ($tresorier) {
        foreach (['read', 'scout_view_inscriptions', 'scout_view_medical', 'scout_scan_qr', 'scout_manage_payments', 'scout_export'] as $cap) {
            if (!$tresorier->has_cap($cap)) $tresorier->add_cap($cap);
        }
    }

    // Repair missing tables (fixes failed activations)
    global $wpdb;
    $tables_needed = [
        SCOUT_DB_PREFIX . 'inscriptions',
        SCOUT_DB_PREFIX . 'contacts',
        SCOUT_DB_PREFIX . 'payments',
        SCOUT_DB_PREFIX . 'access_log',
        SCOUT_DB_PREFIX . 'families',
    ];
    foreach ($tables_needed as $table) {
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$exists) {
            if (class_exists('Scout_Inscription_Activator')) {
                Scout_Inscription_Activator::activate();
            }
            break;
        }
    }
});

// Direct document handler — bypasses REST API cookie/nonce issues
add_action('init', function() {
    if (!isset($_GET['scout_doc'])) return;

    $ref = sanitize_text_field($_GET['scout_doc']);
    $type = sanitize_text_field($_GET['doc_type'] ?? '');

    if (!$ref || !$type) return;

    // WordPress is fully loaded at this point — cookies work
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
        exit;
    }
    if (!current_user_can('scout_view_inscriptions') && !current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les permissions nécessaires.', 'scout-inscription'), __('Accès refusé', 'scout-inscription'), ['response' => 403]);
    }

    $inscription = Scout_Inscription_Model::get_by_ref($ref);
    if (!$inscription) {
        wp_die(__('Inscription introuvable.', 'scout-inscription'), __('Erreur', 'scout-inscription'), ['response' => 404]);
    }

    Scout_PDF_Generator::serve_pdf($inscription->id, $type);
});

// ── WordPress Dashboard Widgets (Screen Elements) ──
add_action('wp_dashboard_setup', function() {
    if (!current_user_can('scout_view_inscriptions') && !current_user_can('manage_options')) return;

    wp_add_dashboard_widget('scout_overview', '⚜️ ' . __('Inscriptions — Vue d\'ensemble', 'scout-inscription'), 'scout_dashboard_overview');
    wp_add_dashboard_widget('scout_payments', __('Paiements', 'scout-inscription'), 'scout_dashboard_payments');
    wp_add_dashboard_widget('scout_units', __('Inscriptions par unité', 'scout-inscription'), 'scout_dashboard_units');
    wp_add_dashboard_widget('scout_recent', __('Inscriptions récentes', 'scout-inscription'), 'scout_dashboard_recent');

    if (current_user_can('scout_manage_payments') || current_user_can('manage_options')) {
        wp_add_dashboard_widget('scout_revenue', __('Revenus', 'scout-inscription'), 'scout_dashboard_revenue');
    }
});

function scout_dashboard_overview() {
    $year = Scout_Inscription_Model::get_current_year();
    $total = Scout_Inscription_Model::count(['annee_scoute' => $year, 'status_not_in' => ['rejetee', 'annulee', 'doublon']]);
    $approved = Scout_Inscription_Model::count(['annee_scoute' => $year, 'status' => 'approuvee']);
    $pending = Scout_Inscription_Model::count(['annee_scoute' => $year, 'status' => 'complete']);
    $rejected = Scout_Inscription_Model::count(['annee_scoute' => $year, 'status' => 'rejetee']);
    $plans = Scout_Inscription_Model::count(['annee_scoute' => $year, 'status' => 'plan_paiement']);
    $link = admin_url('admin.php?page=scout-inscription');
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div style="background:#f0faf4;padding:14px;border-radius:8px;text-align:center">
            <div style="font-size:28px;font-weight:700;color:#007748"><?php echo $total; ?></div>
            <div style="font-size:11px;color:#6a6a62">Total <?php echo esc_html($year); ?></div>
        </div>
        <div style="background:#f0faf4;padding:14px;border-radius:8px;text-align:center">
            <div style="font-size:28px;font-weight:700;color:#27ae60"><?php echo $approved; ?></div>
            <div style="font-size:11px;color:#6a6a62"><?php esc_html_e('Approuvées', 'scout-inscription'); ?></div>
        </div>
        <div style="background:#fff8f0;padding:14px;border-radius:8px;text-align:center">
            <div style="font-size:28px;font-weight:700;color:#e67e22"><?php echo $pending; ?></div>
            <div style="font-size:11px;color:#6a6a62"><?php esc_html_e('À traiter', 'scout-inscription'); ?></div>
        </div>
        <div style="background:#fff5f5;padding:14px;border-radius:8px;text-align:center">
            <div style="font-size:28px;font-weight:700;color:#c0392b"><?php echo $rejected; ?></div>
            <div style="font-size:11px;color:#6a6a62"><?php esc_html_e('Rejetées', 'scout-inscription'); ?></div>
        </div>
    </div>
    <?php if ($plans > 0): ?>
    <div style="margin-top:8px;background:#e0f2fe;padding:8px 12px;border-radius:6px;font-size:12px;color:#0e7490;text-align:center">
        <?php /* translators: %d: number of active payment plans */ printf(esc_html__('%d plan(s) de paiement actif(s)', 'scout-inscription'), $plans); ?>
    </div>
    <?php endif; ?>
    <p style="text-align:right;margin-top:10px"><a href="<?php echo esc_url($link); ?>"><?php esc_html_e('Voir toutes les inscriptions', 'scout-inscription'); ?> →</a></p>
    <?php
}

function scout_dashboard_payments() {
    $year = Scout_Inscription_Model::get_current_year();
    global $wpdb;
    $table = SCOUT_DB_PREFIX . 'inscriptions';
    // Count payment statuses excluding doublons, cancelled, and rejected
    $active_where = "annee_scoute = '{$year}' AND status NOT IN ('annulee','rejetee','doublon')";
    $waiting = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$active_where} AND payment_status = 'en_attente'");
    $partial = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$active_where} AND payment_status = 'acompte_recu'");
    $paid = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$active_where} AND payment_status = 'paye'");
    $total_active = $waiting + $partial + $paid;
    $pct_paid = $total_active > 0 ? round(($paid / $total_active) * 100) : 0;
    ?>
    <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
            <span><?php /* translators: %s: percentage */ printf(esc_html__('%s%% payé', 'scout-inscription'), $pct_paid); ?></span>
            <span><?php echo $paid; ?>/<?php echo $total_active; ?></span>
        </div>
        <div style="background:#e0ddd4;border-radius:20px;height:12px;overflow:hidden">
            <div style="background:linear-gradient(90deg,#27ae60,#2ecc71);height:100%;width:<?php echo $pct_paid; ?>%;border-radius:20px;transition:width 0.5s"></div>
        </div>
    </div>
    <div style="display:flex;gap:8px;justify-content:center">
        <div style="text-align:center;flex:1;padding:8px;background:#fff5f5;border-radius:6px">
            <div style="font-size:20px;font-weight:700;color:#c0392b"><?php echo $waiting; ?></div>
            <div style="font-size:10px;color:#6a6a62"><?php esc_html_e('En attente', 'scout-inscription'); ?></div>
        </div>
        <div style="text-align:center;flex:1;padding:8px;background:#fff8f0;border-radius:6px">
            <div style="font-size:20px;font-weight:700;color:#e67e22"><?php echo $partial; ?></div>
            <div style="font-size:10px;color:#6a6a62"><?php esc_html_e('Acompte', 'scout-inscription'); ?></div>
        </div>
        <div style="text-align:center;flex:1;padding:8px;background:#f0faf4;border-radius:6px">
            <div style="font-size:20px;font-weight:700;color:#27ae60"><?php echo $paid; ?></div>
            <div style="font-size:10px;color:#6a6a62"><?php esc_html_e('Payé', 'scout-inscription'); ?></div>
        </div>
    </div>
    <?php
}

function scout_dashboard_units() {
    $year = Scout_Inscription_Model::get_current_year();
    // Only show the 4 youth units, not admin/conseil
    $units_data = [
        'castors' => ['name' => 'Castors', 'color' => '#d4a017'],
        'louveteaux' => ['name' => 'Louveteaux', 'color' => '#007748'],
        'eclaireurs' => ['name' => 'Éclaireurs', 'color' => '#0065cc'],
        'pionniers' => ['name' => 'Pionniers', 'color' => '#c0392b'],
    ];
    if (function_exists('scout_gm_get_units')) {
        $custom_units = scout_gm_get_units();
        $units_data = [];
        // Only include units that are youth branches (not admin sections)
        $admin_slugs = ['administration', 'conseil', 'benevoles', 'routiers'];
        foreach ($custom_units as $u) {
            if (in_array($u['slug'], $admin_slugs)) continue;
            $units_data[$u['slug']] = ['name' => $u['name'], 'color' => $u['accent_color'] ?? $u['text_color'] ?? '#007748'];
        }
    }
    $max = 1;
    $counts = [];
    foreach ($units_data as $slug => $info) {
        $c = Scout_Inscription_Model::count(['annee_scoute' => $year, 'unite' => $slug, 'status_not_in' => ['rejetee', 'annulee', 'doublon']]);
        $counts[$slug] = $c;
        if ($c > $max) $max = $c;
    }
    foreach ($units_data as $slug => $info):
        $c = $counts[$slug];
        $pct = $max > 0 ? round(($c / $max) * 100) : 0;
    ?>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <div style="width:90px;font-size:12px;font-weight:500;color:#3a3a36"><?php echo esc_html($info['name']); ?></div>
        <div style="flex:1;background:#f0ede6;border-radius:6px;height:22px;overflow:hidden">
            <div style="background:<?php echo esc_attr($info['color']); ?>;height:100%;width:<?php echo $pct; ?>%;border-radius:6px;min-width:<?php echo $c > 0 ? '24px' : '0'; ?>;display:flex;align-items:center;justify-content:flex-end;padding-right:6px;transition:width 0.5s">
                <?php if ($c > 0): ?><span style="font-size:11px;font-weight:700;color:#fff"><?php echo $c; ?></span><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach;
}

function scout_dashboard_recent() {
    $items = Scout_Inscription_Model::list([], 5, 0);
    if (empty($items)) {
        echo '<p style="color:#6a6a62;text-align:center;padding:12px">' . esc_html__('Aucune inscription.', 'scout-inscription') . '</p>';
        return;
    }
    $status_icons = ['brouillon'=>'🔘','complete'=>'📋','approuvee'=>'✅','rejetee'=>'❌','plan_paiement'=>'📅','annulee'=>'🚫','doublon'=>'🔁'];
    echo '<table style="width:100%;border-collapse:collapse;font-size:12px">';
    foreach ($items as $ins):
        $icon = $status_icons[$ins->status] ?? '❓';
        $link = admin_url('admin.php?page=scout-inscription&ref=' . $ins->ref_number);
    ?>
    <tr style="border-bottom:1px solid #f0ede6">
        <td style="padding:6px 4px"><a href="<?php echo esc_url($link); ?>" style="font-weight:600;text-decoration:none"><?php echo esc_html($ins->ref_number); ?></a></td>
        <td style="padding:6px 4px"><?php echo esc_html($ins->enfant_prenom . ' ' . $ins->enfant_nom); ?></td>
        <td style="padding:6px 4px;text-align:center"><?php echo $icon; ?></td>
        <td style="padding:6px 4px;color:#6a6a62;text-align:right"><?php echo esc_html(date('j M', strtotime($ins->created_at))); ?></td>
    </tr>
    <?php endforeach;
    echo '</table>';
    echo '<p style="text-align:right;margin-top:8px"><a href="' . admin_url('admin.php?page=scout-inscription') . '">' . esc_html__('Voir tout', 'scout-inscription') . ' →</a></p>';
}

function scout_dashboard_revenue() {
    $year = Scout_Inscription_Model::get_current_year();
    global $wpdb;
    $table = SCOUT_DB_PREFIX . 'inscriptions';
    $due = floatval($wpdb->get_var("SELECT SUM(payment_total) FROM {$table} WHERE annee_scoute = '{$year}' AND status IN ('approuvee','plan_paiement','complete')"));
    $received = floatval($wpdb->get_var("SELECT SUM(payment_received) FROM {$table} WHERE annee_scoute = '{$year}' AND status IN ('approuvee','plan_paiement','complete')"));
    $outstanding = $due - $received;
    $pct = $due > 0 ? round(($received / $due) * 100) : 0;
    ?>
    <div style="text-align:center;margin-bottom:12px">
        <div style="font-size:32px;font-weight:700;color:#007748"><?php echo number_format($received, 2); ?> $</div>
        <div style="font-size:11px;color:#6a6a62"><?php /* translators: %s: amount due */ printf(esc_html__('reçu sur %s $ dû', 'scout-inscription'), number_format($due, 2)); ?></div>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px">
        <span><?php /* translators: %s: percentage */ printf(esc_html__('%s%% collecté', 'scout-inscription'), $pct); ?></span>
        <span style="color:#c0392b"><?php /* translators: %s: amount remaining */ printf(esc_html__('%s $ restant', 'scout-inscription'), number_format($outstanding, 2)); ?></span>
    </div>
    <div style="background:#e0ddd4;border-radius:20px;height:14px;overflow:hidden">
        <div style="background:linear-gradient(90deg,#007748,#27ae60);height:100%;width:<?php echo $pct; ?>%;border-radius:20px"></div>
    </div>
    <?php
}
