<?php
defined('ABSPATH') || exit;

class Scout_Admin_Dashboard {

    public function register_menu(): void {
        add_menu_page(
            __('Inscriptions', 'scout-inscription'),
            __('Inscriptions', 'scout-inscription'),
            'scout_view_inscriptions',
            'scout-inscription',
            [$this, 'render_dashboard'],
            'dashicons-groups',
            30
        );

        add_submenu_page('scout-inscription', __('Tableau de bord', 'scout-inscription'),
            __('Tableau de bord', 'scout-inscription'), 'scout_view_inscriptions', 'scout-inscription', [$this, 'render_dashboard']);

        add_submenu_page('scout-inscription', __('Réglages', 'scout-inscription'),
            __('Réglages', 'scout-inscription'), 'scout_manage_settings', 'scout-inscription-settings', [$this, 'render_settings']);

        add_submenu_page('scout-inscription', __('Journal d\'accès', 'scout-inscription'),
            __('Journal d\'accès', 'scout-inscription'), 'scout_manage_settings', 'scout-inscription-log', [$this, 'render_log']);
    }

    public function render_dashboard(): void {
        // Detail view?
        if (!empty($_GET['ref'])) {
            $this->render_detail(sanitize_text_field($_GET['ref']));
            return;
        }

        $filters = [
            'annee_scoute'   => sanitize_text_field($_GET['annee'] ?? ''),
            'unite'          => sanitize_text_field($_GET['unite'] ?? ''),
            'payment_status' => sanitize_text_field($_GET['payment'] ?? ''),
            'status'         => sanitize_text_field($_GET['statut'] ?? ''),
        ];
        // When explicitly filtering by doublon or annulee, include them in the list
        if (in_array($filters['status'] ?? '', ['doublon', 'annulee', 'rejetee'])) {
            $filters['include_doublons'] = true;
        }
        $filters = array_filter($filters);

        $page  = max(1, absint($_GET['paged'] ?? 1));
        $items = Scout_Inscription_Model::list($filters, 25, ($page - 1) * 25);
        $total = Scout_Inscription_Model::count($filters);
        $pages = ceil($total / 25);

        $current_year = Scout_Inscription_Model::get_current_year();
        $unites = ['castors' => 'Castors', 'louveteaux' => 'Louveteaux', 'eclaireurs' => 'Éclaireurs', 'pionniers' => 'Pionniers'];
        $payment_statuses = ['en_attente' => __('En attente', 'scout-inscription'), 'acompte_recu' => __('Acompte', 'scout-inscription'), 'paye' => __('Payé', 'scout-inscription'), 'annulee' => __('Annulée', 'scout-inscription')];

        ?>
        <div class="wrap">
            <h1>📋 Inscriptions — <?php echo esc_html($current_year); ?></h1>

            <style>
            .scout-stats-grid { display:flex; flex-wrap:wrap; gap:10px; margin:16px 0; }
            .scout-stat { background:#fff; padding:14px 18px; border-radius:10px; border-left:4px solid #007748; box-shadow:0 1px 4px rgba(0,0,0,0.06); min-width:140px; flex:1; max-width:220px; }
            .scout-stat-num { font-size:26px; font-weight:700; line-height:1.1; }
            .scout-stat-label { font-size:11px; color:#6a6a62; margin-top:2px; }
            .scout-stat-section { font-size:12px; font-weight:600; color:#3a3a36; margin:16px 0 6px; text-transform:uppercase; letter-spacing:0.5px; }
            </style>

            <?php
            // Base filters for active inscriptions only (no doublons, no cancelled)
            $active_filters = ['annee_scoute' => $current_year];
            $approved = Scout_Inscription_Model::count(array_merge($active_filters, ['status' => 'approuvee']));
            $pending = Scout_Inscription_Model::count(array_merge($active_filters, ['status' => 'complete']));
            $drafts = Scout_Inscription_Model::count(array_merge($active_filters, ['status' => 'brouillon']));
            $rejected = Scout_Inscription_Model::count(array_merge($active_filters, ['status' => 'rejetee']));
            $plans = Scout_Inscription_Model::count(array_merge($active_filters, ['status' => 'plan_paiement']));
            // Cancelled and doublons need include flag
            $cancelled = Scout_Inscription_Model::count(['annee_scoute' => $current_year, 'status' => 'annulee', 'include_doublons' => true]);
            $doublons = Scout_Inscription_Model::count(['annee_scoute' => $current_year, 'status' => 'doublon', 'include_doublons' => true]);
            $active_total = $approved + $pending + $drafts + $plans;

            $pay_waiting = Scout_Inscription_Model::count(array_merge($active_filters, ['payment_status' => 'en_attente']));
            $pay_partial = Scout_Inscription_Model::count(array_merge($active_filters, ['payment_status' => 'acompte_recu']));
            $pay_done = Scout_Inscription_Model::count(array_merge($active_filters, ['payment_status' => 'paye']));
            global $wpdb;
            $rev_table = SCOUT_DB_PREFIX . 'inscriptions';
            $total_due = floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(payment_total) FROM {$rev_table} WHERE annee_scoute = %s AND status IN ('approuvee','plan_paiement','complete')", $current_year)));
            $total_received = floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(payment_received) FROM {$rev_table} WHERE annee_scoute = %s AND status IN ('approuvee','plan_paiement','complete')", $current_year)));
            $total_outstanding = $total_due - $total_received;
            $unit_colors = ['castors' => '#d4a017', 'louveteaux' => '#007748', 'eclaireurs' => '#0065cc', 'pionniers' => '#c0392b'];
            ?>

            <!-- Inscriptions -->
            <div class="scout-stat-section"><?php esc_html_e('Inscriptions actives', 'scout-inscription'); ?></div>
            <div class="scout-stats-grid">
                <div class="scout-stat" style="border-color:#007748"><div class="scout-stat-num" style="color:#007748"><?php echo $active_total; ?></div><div class="scout-stat-label"><?php esc_html_e('Total actives', 'scout-inscription'); ?></div></div>
                <div class="scout-stat" style="border-color:#27ae60"><div class="scout-stat-num" style="color:#27ae60"><?php echo $approved; ?></div><div class="scout-stat-label"><?php esc_html_e('Approuvées', 'scout-inscription'); ?></div></div>
                <div class="scout-stat" style="border-color:#e67e22"><div class="scout-stat-num" style="color:#e67e22"><?php echo $pending; ?></div><div class="scout-stat-label"><?php esc_html_e('À traiter', 'scout-inscription'); ?></div></div>
                <div class="scout-stat" style="border-color:#c0392b"><div class="scout-stat-num" style="color:#c0392b"><?php echo $rejected; ?></div><div class="scout-stat-label"><?php esc_html_e('Rejetées', 'scout-inscription'); ?></div></div>
                <div class="scout-stat" style="border-color:#6a6a62"><div class="scout-stat-num" style="color:#6a6a62"><?php echo $cancelled; ?></div><div class="scout-stat-label"><?php esc_html_e('Annulées', 'scout-inscription'); ?></div></div>
                <div class="scout-stat" style="border-color:#9ca3af"><div class="scout-stat-num" style="color:#9ca3af"><?php echo $doublons; ?></div><div class="scout-stat-label"><?php esc_html_e('Doublons', 'scout-inscription'); ?></div></div>
            </div>

            <!-- Paiements -->
            <div class="scout-stat-section"><?php esc_html_e('Paiements', 'scout-inscription'); ?></div>
            <div class="scout-stats-grid">
                <div class="scout-stat" style="border-color:#c0392b"><div class="scout-stat-num" style="color:#c0392b"><?php echo $pay_waiting; ?></div><div class="scout-stat-label"><?php esc_html_e('En attente', 'scout-inscription'); ?></div></div>
                <div class="scout-stat" style="border-color:#e67e22"><div class="scout-stat-num" style="color:#e67e22"><?php echo $pay_partial; ?></div><div class="scout-stat-label"><?php esc_html_e('Acompte', 'scout-inscription'); ?></div></div>
                <div class="scout-stat" style="border-color:#27ae60"><div class="scout-stat-num" style="color:#27ae60"><?php echo $pay_done; ?></div><div class="scout-stat-label"><?php esc_html_e('Payé', 'scout-inscription'); ?></div></div>
                <div class="scout-stat" style="border-color:#007748"><div class="scout-stat-num" style="color:#007748;font-size:20px"><?php echo number_format($total_received, 2); ?> $</div><div class="scout-stat-label"><?php esc_html_e('Reçus', 'scout-inscription'); ?></div></div>
                <?php if ($total_outstanding > 0): ?>
                <div class="scout-stat" style="border-color:#c0392b;background:#fff5f5"><div class="scout-stat-num" style="color:#c0392b;font-size:20px"><?php echo number_format($total_outstanding, 2); ?> $</div><div class="scout-stat-label"><?php esc_html_e('Impayé', 'scout-inscription'); ?></div></div>
                <?php endif; ?>
            </div>

            <!-- Unités -->
            <div class="scout-stat-section"><?php esc_html_e('Par unité', 'scout-inscription'); ?></div>
            <div class="scout-stats-grid">
                <?php foreach ($unites as $uk => $un):
                    $uc = $unit_colors[$uk] ?? '#007748';
                    // Count only active statuses per unit
                    $unit_count = 0;
                    foreach (['brouillon','complete','approuvee','plan_paiement'] as $act_st) {
                        $unit_count += Scout_Inscription_Model::count(['annee_scoute' => $current_year, 'unite' => $uk, 'status' => $act_st]);
                    }
                ?>
                <div class="scout-stat" style="border-color:<?php echo $uc; ?>"><div class="scout-stat-num" style="color:<?php echo $uc; ?>"><?php echo $unit_count; ?></div><div class="scout-stat-label"><?php echo esc_html($un); ?></div></div>
                <?php endforeach; ?>
            </div>

            <!-- Filters -->
            <form method="get" style="margin-bottom:16px;display:flex;gap:8px;align-items:end;flex-wrap:wrap">
                <input type="hidden" name="page" value="scout-inscription">
                <select name="unite" style="padding:6px 24px 6px 10px">
                    <option value=""><?php esc_html_e('Toutes les unités', 'scout-inscription'); ?></option>
                    <?php foreach ($unites as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected($filters['unite'] ?? '', $k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="statut" style="padding:6px 24px 6px 10px">
                    <option value=""><?php esc_html_e('Tous les statuts', 'scout-inscription'); ?></option>
                    <option value="brouillon" <?php selected($filters['status'] ?? '', 'brouillon'); ?>><?php esc_html_e('Brouillon', 'scout-inscription'); ?></option>
                    <option value="complete" <?php selected($filters['status'] ?? '', 'complete'); ?>><?php esc_html_e('Complète', 'scout-inscription'); ?></option>
                    <option value="approuvee" <?php selected($filters['status'] ?? '', 'approuvee'); ?>><?php esc_html_e('Approuvée', 'scout-inscription'); ?></option>
                    <option value="rejetee" <?php selected($filters['status'] ?? '', 'rejetee'); ?>><?php esc_html_e('Rejetée', 'scout-inscription'); ?></option>
                    <option value="plan_paiement" <?php selected($filters['status'] ?? '', 'plan_paiement'); ?>><?php esc_html_e('Plan paiement', 'scout-inscription'); ?></option>
                    <option value="annulee" <?php selected($filters['status'] ?? '', 'annulee'); ?>><?php esc_html_e('Annulée', 'scout-inscription'); ?></option>
                    <option value="doublon" <?php selected($filters['status'] ?? '', 'doublon'); ?>><?php esc_html_e('Doublon', 'scout-inscription'); ?></option>
                </select>
                <select name="payment" style="padding:6px 24px 6px 10px">
                    <option value=""><?php esc_html_e('Tous les paiements', 'scout-inscription'); ?></option>
                    <?php foreach ($payment_statuses as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected($filters['payment_status'] ?? '', $k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php esc_html_e('Filtrer', 'scout-inscription'); ?></button>
                <?php if (current_user_can('scout_export')): ?>
                    <a href="<?php echo esc_url(rest_url('scout-gm/v1/inscriptions/export?' . http_build_query($filters))); ?>" class="button"><?php esc_html_e('Exporter CSV', 'scout-inscription'); ?></a>
                <?php endif; ?>
            </form>

            <!-- Bulk Actions -->
            <?php
            // Handle bulk actions
            if (isset($_POST['scout_bulk_action']) && wp_verify_nonce($_POST['_scout_bulk_nonce'], 'scout_bulk_action')) {
                $action = sanitize_key($_POST['bulk_action_select'] ?? '');
                $selected = array_map('intval', $_POST['bulk_ids'] ?? []);
                if (!empty($selected) && $action) {
                    $count = 0;
                    global $wpdb;
                    foreach ($selected as $sid) {
                        if ($action === 'cancel') {
                            Scout_Inscription_Model::update_status($sid, 'annulee');
                            $wpdb->update(SCOUT_DB_PREFIX . 'inscriptions', ['payment_status' => 'annulee'], ['id' => $sid]);
                            $count++;
                        } elseif ($action === 'duplicate') {
                            // Mark as duplicate — hidden from dashboard/stats but QR still works
                            $wpdb->update(SCOUT_DB_PREFIX . 'inscriptions', [
                                'status' => 'doublon',
                                'payment_status' => 'annulee',
                            ], ['id' => $sid]);
                            $count++;
                        }
                    }
                    echo '<div class="notice notice-success"><p>';
                    if ($action === 'duplicate') {
                        /* translators: %d: number of inscriptions */
                        printf(esc_html__('%d inscription(s) marquée(s) comme doublon. Masquées du tableau de bord mais les codes QR restent fonctionnels.', 'scout-inscription'), $count);
                    } else {
                        /* translators: %d: number of inscriptions */
                        printf(esc_html__('%d inscription(s) annulée(s).', 'scout-inscription'), $count);
                    }
                    echo '</p></div>';
                    // Refresh the list
                    $items = Scout_Inscription_Model::list($filters, 25, ($page - 1) * 25);
                    $total = Scout_Inscription_Model::count($filters);
                }
            }
            ?>

            <form method="post">
            <?php wp_nonce_field('scout_bulk_action', '_scout_bulk_nonce'); ?>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
                <select name="bulk_action_select" style="padding:4px 8px">
                    <option value=""><?php esc_html_e('— Actions groupées —', 'scout-inscription'); ?></option>
                    <option value="cancel"><?php esc_html_e('Annuler les sélectionnées', 'scout-inscription'); ?></option>
                    <option value="duplicate"><?php esc_html_e('Marquer comme doublon (masquer, garder QR)', 'scout-inscription'); ?></option>
                    <?php if (current_user_can('manage_options')): ?>
                    <option value="delete"><?php esc_html_e('Supprimer définitivement', 'scout-inscription'); ?></option>
                    <?php endif; ?>
                </select>
                <button type="submit" name="scout_bulk_action" class="button" onclick="return document.querySelectorAll('input[name=\'bulk_ids[]\']:checked').length ? (this.form.bulk_action_select.value === 'delete' ? confirm('<?php echo esc_js(__('Supprimer définitivement les inscriptions sélectionnées? Cette action est irréversible!', 'scout-inscription')); ?>') : confirm('<?php echo esc_js(__('Annuler les inscriptions sélectionnées?', 'scout-inscription')); ?>')) : (alert('<?php echo esc_js(__('Sélectionnez au moins une inscription.', 'scout-inscription')); ?>'), false)"><?php esc_html_e('Appliquer', 'scout-inscription'); ?></button>
                <span style="font-size:12px;color:#6a6a62;margin-left:8px">
                    <label><input type="checkbox" id="selectAll" onchange="document.querySelectorAll('input[name=\'bulk_ids[]\']').forEach(c => c.checked = this.checked)"> <?php esc_html_e('Tout sélectionner', 'scout-inscription'); ?></label>
                </span>
            </div>

            <!-- Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:30px"><input type="checkbox" onchange="document.querySelectorAll('input[name=\'bulk_ids[]\']').forEach(c => c.checked = this.checked)"></th>
                        <th style="width:120px"><?php esc_html_e('Référence', 'scout-inscription'); ?></th>
                        <th><?php esc_html_e('Enfant', 'scout-inscription'); ?></th>
                        <th style="width:100px"><?php esc_html_e('Unité', 'scout-inscription'); ?></th>
                        <th style="width:120px"><?php esc_html_e('Statut', 'scout-inscription'); ?></th>
                        <th style="width:120px"><?php esc_html_e('Paiement', 'scout-inscription'); ?></th>
                        <th style="width:90px"><?php esc_html_e('Reçu', 'scout-inscription'); ?></th>
                        <th style="width:90px"><?php esc_html_e('Solde', 'scout-inscription'); ?></th>
                        <th style="width:130px"><?php esc_html_e('Date', 'scout-inscription'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:40px;color:#6a6a62"><?php esc_html_e('Aucune inscription trouvée.', 'scout-inscription'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $ins): ?>
                        <tr<?php if ($ins->status === 'annulee') echo ' style="opacity:0.5"'; ?>>
                            <td><input type="checkbox" name="bulk_ids[]" value="<?php echo $ins->id; ?>"></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=scout-inscription&ref=' . $ins->ref_number)); ?>"><strong><?php echo esc_html($ins->ref_number); ?></strong></a></td>
                            <td><?php echo esc_html($ins->enfant_prenom . ' ' . $ins->enfant_nom); ?></td>
                            <td><span class="scout-badge scout-badge-<?php echo esc_attr($ins->unite); ?>"><?php echo esc_html(ucfirst($ins->unite)); ?></span></td>
                            <td><?php
                                $st_map = ['brouillon'=>'🔘','complete'=>'📋','approuvee'=>'✅','rejetee'=>'❌','plan_paiement'=>'📅','annulee'=>'🚫','doublon'=>'🔁'];
                                $st_labels = ['brouillon'=>__('Brouillon','scout-inscription'),'complete'=>__('Complète','scout-inscription'),'approuvee'=>__('Approuvée','scout-inscription'),'rejetee'=>__('Rejetée','scout-inscription'),'plan_paiement'=>__('Plan paiement','scout-inscription'),'annulee'=>__('Annulée','scout-inscription'),'doublon'=>__('Doublon','scout-inscription')];
                                $st_colors = ['brouillon'=>'#6a6a62','complete'=>'#2563eb','approuvee'=>'#27ae60','rejetee'=>'#c0392b','plan_paiement'=>'#e67e22','annulee'=>'#6a6a62','doublon'=>'#9ca3af'];
                                $icon = $st_map[$ins->status] ?? '❓';
                                $label = $st_labels[$ins->status] ?? $ins->status;
                                $color = $st_colors[$ins->status] ?? '#6a6a62';
                                echo '<span style="color:' . esc_attr($color) . '">' . esc_html($icon . ' ' . $label) . '</span>';
                            ?></td>
                            <td><?php echo esc_html($payment_statuses[$ins->payment_status] ?? $ins->payment_status); ?></td>
                            <td><?php echo number_format($ins->payment_received, 2); ?> $</td>
                            <td><?php echo number_format($ins->payment_total - $ins->payment_received, 2); ?> $</td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($ins->created_at))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </form>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <div class="tablenav" style="margin-top:12px">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php /* translators: %s: total count */ printf(esc_html__('%s éléments', 'scout-inscription'), esc_html($total)); ?></span>
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <a class="page-numbers <?php echo $i === $page ? 'current' : ''; ?>"
                               href="<?php echo esc_url(add_query_arg('paged', $i)); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_detail(string $ref): void {
        $inscription = Scout_Inscription_Model::get_by_ref($ref);
        if (!$inscription) {
            echo '<div class="wrap"><h1>' . esc_html__('Inscription introuvable', 'scout-inscription') . '</h1></div>';
            return;
        }

        Scout_Access_Log::log(get_current_user_id(), $inscription->id, 'view', 'Admin detail view');

        $contacts = Scout_Contact_Model::get_for_inscription($inscription->id);
        $parents  = array_values(array_filter($contacts, function($c) { return $c->type === 'parent'; }));
        $urgence  = array_values(array_filter($contacts, function($c) { return $c->type === 'urgence'; }));
        $payments = Scout_Payment_Model::get_for_inscription($inscription->id);
        $medical  = $inscription->medical_data_decrypted ?? [];
        $balance  = $inscription->payment_total - $inscription->payment_received;

        $unite_names = ['castors' => '🟡 Castors', 'louveteaux' => '🟢 Louveteaux', 'eclaireurs' => '🔵 Éclaireurs', 'pionniers' => '🔴 Pionniers'];

        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=scout-inscription')); ?>">← Inscriptions</a> /
                <?php echo esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom); ?>
                <span style="font-size:14px;color:#6a6a62;margin-left:12px"><?php echo esc_html($inscription->ref_number); ?></span>
                <?php
                $status_labels = [
                    'brouillon' => ['🔘', __('Brouillon', 'scout-inscription'), '#6a6a62'],
                    'complete' => ['📋', __('Complète', 'scout-inscription'), '#2563eb'],
                    'approuvee' => ['✅', __('Approuvée', 'scout-inscription'), '#27ae60'],
                    'rejetee' => ['❌', __('Rejetée', 'scout-inscription'), '#c0392b'],
                    'plan_paiement' => ['📅', __('Plan de paiement', 'scout-inscription'), '#e67e22'],
                    'annulee' => ['🚫', __('Annulée', 'scout-inscription'), '#6a6a62'],
                    'doublon' => ['🔁', __('Doublon', 'scout-inscription'), '#9ca3af'],
                ];
                $st = $status_labels[$inscription->status] ?? ['❓', $inscription->status, '#6a6a62'];
                ?>
                <span style="margin-left:12px;padding:4px 14px;border-radius:20px;font-size:13px;font-weight:600;background:<?php echo $st[2]; ?>18;color:<?php echo $st[2]; ?>"><?php echo $st[0] . ' ' . $st[1]; ?></span>
            </h1>

            <!-- ═══ ADMIN ACTIONS ═══ -->
            <div style="background:#fff;border:1px solid #e0ddd4;border-radius:12px;padding:20px;margin-top:16px;margin-bottom:20px">
                <h3 style="margin:0 0 12px;font-size:15px"><?php esc_html_e('Actions', 'scout-inscription'); ?></h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:start">
                    <?php if ($inscription->status !== 'approuvee'): ?>
                        <button onclick="scoutAction('approve')" class="button button-primary" style="background:#27ae60;border-color:#27ae60"><?php esc_html_e('Approuver', 'scout-inscription'); ?></button>
                    <?php endif; ?>
                    <?php if ($inscription->status !== 'rejetee'): ?>
                        <button onclick="document.getElementById('rejectPanel').style.display='block'" class="button" style="color:#c0392b;border-color:#c0392b"><?php esc_html_e('Rejeter', 'scout-inscription'); ?></button>
                    <?php endif; ?>
                    <?php if ($inscription->status !== 'plan_paiement'): ?>
                        <button onclick="document.getElementById('planPanel').style.display='block'" class="button" style="color:#e67e22;border-color:#e67e22"><?php esc_html_e('Plan de paiement', 'scout-inscription'); ?></button>
                    <?php endif; ?>
                    <?php if ($inscription->status !== 'annulee'): ?>
                        <button onclick="if(confirm('<?php echo esc_js(__('Annuler cette inscription?', 'scout-inscription')); ?>'))scoutAction('reject',{reason:'<?php echo esc_js(__('Annulée par l\'administrateur', 'scout-inscription')); ?>'})" class="button" style="color:#6a6a62"><?php esc_html_e('Annuler', 'scout-inscription'); ?></button>
                    <?php endif; ?>
                </div>

                <!-- Reject panel (hidden) -->
                <div id="rejectPanel" style="display:none;margin-top:16px;background:#fff5f5;padding:16px;border-radius:8px;border:1px solid #f0c0c0">
                    <h4 style="margin:0 0 8px;color:#c0392b"><?php esc_html_e('Raison du rejet', 'scout-inscription'); ?></h4>
                    <textarea id="rejectReason" rows="3" style="width:100%;padding:8px;border:1px solid #d0d0c8;border-radius:6px;font-family:inherit" placeholder="<?php echo esc_attr__('Ex: Groupe complet pour cette tranche d\'âge, documents manquants...', 'scout-inscription'); ?>"></textarea>
                    <div style="margin-top:8px;display:flex;gap:8px">
                        <button onclick="scoutAction('reject',{reason:document.getElementById('rejectReason').value})" class="button" style="background:#c0392b;color:#fff;border-color:#c0392b"><?php esc_html_e('Confirmer le rejet', 'scout-inscription'); ?></button>
                        <button onclick="document.getElementById('rejectPanel').style.display='none'" class="button"><?php esc_html_e('Annuler', 'scout-inscription'); ?></button>
                    </div>
                    <p style="font-size:12px;color:#6a6a62;margin-top:8px"><?php esc_html_e('Un courriel sera envoyé aux parents avec la raison du rejet.', 'scout-inscription'); ?></p>
                </div>

                <!-- Payment plan panel (hidden) -->
                <div id="planPanel" style="display:none;margin-top:16px;background:#fff8f0;padding:16px;border-radius:8px;border:1px solid #f0d8a0">
                    <h4 style="margin:0 0 8px;color:#e67e22"><?php esc_html_e('Configuration du plan de paiement', 'scout-inscription'); ?></h4>
                    <textarea id="planNote" rows="3" style="width:100%;padding:8px;border:1px solid #d0d0c8;border-radius:6px;font-family:inherit" placeholder="Ex: 3 versements de 95$ — 1er oct, 1er nov, 1er déc&#10;Entente conclue avec M./Mme [nom]"></textarea>
                    <div style="margin-top:8px;display:flex;gap:8px">
                        <button onclick="scoutAction('payment-plan',{note:document.getElementById('planNote').value})" class="button" style="background:#e67e22;color:#fff;border-color:#e67e22"><?php esc_html_e('Activer le plan', 'scout-inscription'); ?></button>
                        <button onclick="document.getElementById('planPanel').style.display='none'" class="button"><?php esc_html_e('Annuler', 'scout-inscription'); ?></button>
                    </div>
                    <p style="font-size:12px;color:#6a6a62;margin-top:8px"><?php esc_html_e('Ce statut est visible uniquement dans l\'admin. Le parent ne voit pas de distinction — enregistrez les paiements partiels normalement.', 'scout-inscription'); ?></p>
                </div>
            </div>

            <script>
            function scoutAction(action, body) {
                var ref = '<?php echo esc_js($inscription->ref_number); ?>';
                var url = '<?php echo esc_url(rest_url('scout-gm/v1/inscription/')); ?>' + ref + '/' + action;
                fetch(url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'},
                    body: body ? JSON.stringify(body) : '{}'
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Erreur: ' + (data.error || 'Inconnu'));
                    }
                })
                .catch(function() { alert('Erreur de connexion.'); });
            }
            </script>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">

                <!-- Left: Child info -->
                <div class="postbox" style="padding:20px">
                    <h2 style="margin-top:0"><?php esc_html_e('Enfant', 'scout-inscription'); ?></h2>
                    <table class="form-table">
                        <tr><th><?php esc_html_e('Nom', 'scout-inscription'); ?></th><td><?php echo esc_html($inscription->enfant_prenom . ' ' . $inscription->enfant_nom); ?></td></tr>
                        <tr><th><?php esc_html_e('Date de naissance', 'scout-inscription'); ?></th><td><?php echo esc_html($inscription->enfant_ddn); ?></td></tr>
                        <tr><th><?php esc_html_e('Unité', 'scout-inscription'); ?></th><td><?php echo esc_html($unite_names[$inscription->unite] ?? $inscription->unite); ?></td></tr>
                        <tr><th><?php esc_html_e('Adresse', 'scout-inscription'); ?></th><td><?php echo esc_html($inscription->enfant_adresse . ', ' . $inscription->enfant_ville . ' ' . $inscription->enfant_code_postal); ?></td></tr>
                    </table>

                    <h3><?php esc_html_e('Parents / Tuteurs', 'scout-inscription'); ?></h3>
                    <?php foreach ($parents as $p): ?>
                        <div style="background:#f9f8f5;padding:12px;border-radius:8px;margin-bottom:8px">
                            <strong><?php echo esc_html($p->prenom . ' ' . $p->nom); ?></strong>
                            (<?php echo esc_html($p->lien); ?>)
                            <?php if ($p->resp_finances): ?><span style="color:#007748">💰 Resp. finances</span><?php endif; ?>
                            <br>📞 <?php echo esc_html($p->telephone); ?>
                            <?php if ($p->courriel): ?><br>✉️ <?php echo esc_html($p->courriel); ?><?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <h3><?php esc_html_e('Contacts d\'urgence', 'scout-inscription'); ?></h3>
                    <?php foreach ($urgence as $u): ?>
                        <div style="background:#fff3f3;padding:12px;border-radius:8px;margin-bottom:8px">
                            <strong><?php echo esc_html($u->nom); ?></strong> (<?php echo esc_html($u->lien); ?>)
                            <br>📞 <?php echo esc_html($u->telephone); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Right: Medical + Payments -->
                <div>
                    <div class="postbox" style="padding:20px;margin-bottom:20px">
                        <h2 style="margin-top:0"><?php esc_html_e('Fiche médicale', 'scout-inscription'); ?></h2>
                        <?php if (Scout_MFA::can_access_medical()): ?>
                            <table class="form-table">
                                <tr><th><?php esc_html_e('Assurance maladie', 'scout-inscription'); ?></th><td><?php echo esc_html($inscription->assurance_maladie); ?> (exp: <?php echo esc_html($inscription->assurance_expiration); ?>)</td></tr>
                                <tr><th><?php esc_html_e('Attention particulière', 'scout-inscription'); ?></th><td><?php echo esc_html($medical['attention_particuliere'] ?? 'Non'); ?> <?php echo esc_html($medical['attention_detail'] ?? ''); ?></td></tr>
                                <tr><th><?php esc_html_e('Vaccins à jour', 'scout-inscription'); ?></th><td><?php echo esc_html($medical['vaccins_jour'] ?? ''); ?></td></tr>
                                <tr><th><?php esc_html_e('Limite physique', 'scout-inscription'); ?></th><td><?php echo esc_html($medical['limite_physique'] ?? 'Non'); ?> <?php echo esc_html($medical['limite_detail'] ?? ''); ?></td></tr>
                                <tr><th><?php esc_html_e('Allergies alimentaires', 'scout-inscription'); ?></th><td style="<?php echo !empty($medical['allergies_alimentaires']) ? 'color:#c0392b;font-weight:700' : ''; ?>"><?php echo esc_html($medical['allergies_alimentaires'] ?: '—'); ?></td></tr>
                                <tr><th><?php esc_html_e('Allergies médicament', 'scout-inscription'); ?></th><td style="<?php echo !empty($medical['allergies_medicament']) ? 'color:#c0392b;font-weight:700' : ''; ?>"><?php echo esc_html($medical['allergies_medicament'] ?: '—'); ?></td></tr>
                                <tr><th><?php esc_html_e('Médicaments', 'scout-inscription'); ?></th><td><?php echo esc_html($medical['medicaments'] ?? '—'); ?></td></tr>
                            </table>
                            <div style="margin-top:12px">
                                <a href="<?php echo esc_url(rest_url("scout-gm/v1/inscription/{$inscription->ref_number}/medical")); ?>" class="button" target="_blank"><?php esc_html_e('Voir la fiche complète (HTML)', 'scout-inscription'); ?></a>
                            </div>
                            <?php Scout_Access_Log::log(get_current_user_id(), $inscription->id, 'medical_view_admin', 'Données médicales affichées dans admin'); ?>
                        <?php elseif (Scout_MFA::user_has_medical_role()): ?>
                            <div style="background:#fff8f0;border:2px solid #e67e22;border-radius:8px;padding:20px;text-align:center">
                                <p style="font-size:1.1rem;margin-bottom:12px"><?php esc_html_e('Vérification requise pour accéder aux données médicales', 'scout-inscription'); ?></p>
                                <div id="mfaPanel">
                                    <button onclick="sendMfaCode()" class="button button-primary" id="mfaSendBtn"><?php esc_html_e('Envoyer le code de vérification', 'scout-inscription'); ?></button>
                                    <div id="mfaCodeInput" style="display:none;margin-top:12px">
                                        <p style="font-size:13px;color:#6a6a62;margin-bottom:8px"><?php esc_html_e('Un code à 6 chiffres a été envoyé à votre courriel.', 'scout-inscription'); ?></p>
                                        <input type="text" id="mfaCode" maxlength="6" placeholder="000000" style="font-size:24px;text-align:center;letter-spacing:6px;width:180px;padding:8px;border:2px solid #e67e22;border-radius:8px;font-family:monospace">
                                        <button onclick="verifyMfaCode()" class="button button-primary" style="margin-left:8px"><?php esc_html_e('Vérifier', 'scout-inscription'); ?></button>
                                        <div id="mfaError" style="color:#c0392b;margin-top:8px;font-size:13px"></div>
                                    </div>
                                </div>
                                <p style="font-size:11px;color:#6a6a62;margin-top:12px"><?php esc_html_e('Cet accès sera journalisé conformément à la Loi 25.', 'scout-inscription'); ?></p>
                            </div>
                            <script>
                            var mfaRestUrl = '<?php echo esc_url(rest_url("scout-gm/v1/")); ?>';
                            var mfaNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';
                            function sendMfaCode(){
                                document.getElementById('mfaSendBtn').disabled=true;
                                document.getElementById('mfaSendBtn').textContent='Envoi en cours...';
                                fetch(mfaRestUrl+'mfa/send',{method:'POST',headers:{'X-WP-Nonce':mfaNonce}})
                                .then(function(r){return r.json()})
                                .then(function(data){
                                    if(data.success){
                                        document.getElementById('mfaCodeInput').style.display='block';
                                        document.getElementById('mfaSendBtn').textContent='📧 Renvoyer le code';
                                        document.getElementById('mfaSendBtn').disabled=false;
                                        document.getElementById('mfaCode').focus();
                                    } else {
                                        alert(data.error||'Erreur');
                                        document.getElementById('mfaSendBtn').disabled=false;
                                        document.getElementById('mfaSendBtn').textContent='📧 Envoyer le code';
                                    }
                                });
                            }
                            function verifyMfaCode(){
                                var code=document.getElementById('mfaCode').value.trim();
                                if(code.length!==6){document.getElementById('mfaError').textContent='Entrez un code à 6 chiffres.';return;}
                                fetch(mfaRestUrl+'mfa/verify',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':mfaNonce},body:JSON.stringify({code:code})})
                                .then(function(r){return r.json()})
                                .then(function(data){
                                    if(data.success){location.reload();}
                                    else{document.getElementById('mfaError').textContent=data.error||'Code incorrect.';}
                                });
                            }
                            document.getElementById('mfaCode').addEventListener('keydown',function(e){if(e.key==='Enter')verifyMfaCode();});
                            </script>
                        <?php else: ?>
                            <div style="background:#f5f3ee;padding:16px;border-radius:8px;text-align:center;color:#6a6a62">
                                <p><?php esc_html_e('Vous n\'avez pas accès aux données médicales.', 'scout-inscription'); ?></p>
                                <p style="font-size:12px"><?php esc_html_e('Contactez un administrateur pour obtenir les permissions nécessaires.', 'scout-inscription'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="postbox" style="padding:20px">
                        <h2 style="margin-top:0"><?php esc_html_e('Paiements', 'scout-inscription'); ?></h2>
                        <div style="display:flex;gap:16px;margin-bottom:16px">
                            <div><strong><?php esc_html_e('Total dû:', 'scout-inscription'); ?></strong> <?php echo number_format($inscription->payment_total, 2); ?> $</div>
                            <div><strong><?php esc_html_e('Reçu:', 'scout-inscription'); ?></strong> <?php echo number_format($inscription->payment_received, 2); ?> $</div>
                            <div><strong><?php esc_html_e('Solde:', 'scout-inscription'); ?></strong> <span style="color:<?php echo $balance > 0 ? '#c0392b' : '#27ae60'; ?>"><?php echo number_format($balance, 2); ?> $</span></div>
                        </div>

                        <?php if (!empty($payments)): ?>
                            <table class="wp-list-table widefat fixed striped" style="margin-bottom:16px">
                                <thead><tr><th><?php esc_html_e('Date', 'scout-inscription'); ?></th><th><?php esc_html_e('Mode', 'scout-inscription'); ?></th><th><?php esc_html_e('Montant', 'scout-inscription'); ?></th><th><?php esc_html_e('Par', 'scout-inscription'); ?></th><th><?php esc_html_e('Note', 'scout-inscription'); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td><?php echo esc_html($pay->date_recu); ?></td>
                                        <td><?php echo esc_html(ucfirst($pay->mode)); ?></td>
                                        <td><?php echo number_format($pay->montant, 2); ?> $</td>
                                        <td><?php echo esc_html($pay->marked_by_name ?? ''); ?></td>
                                        <td><?php echo esc_html($pay->note); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (current_user_can('scout_manage_payments') && $balance > 0): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#f9f8f5;padding:16px;border-radius:8px">
                                <h3 style="margin-top:0"><?php esc_html_e('Enregistrer un paiement', 'scout-inscription'); ?></h3>
                                <?php wp_nonce_field('scout_add_payment', '_scout_nonce'); ?>
                                <input type="hidden" name="action" value="scout_add_payment">
                                <input type="hidden" name="inscription_id" value="<?php echo esc_attr($inscription->id); ?>">
                                <input type="hidden" name="ref" value="<?php echo esc_attr($inscription->ref_number); ?>">
                                <div style="display:flex;gap:8px;flex-wrap:wrap">
                                    <input type="number" name="montant" step="0.01" min="0.01" max="<?php echo esc_attr($balance); ?>" value="<?php echo esc_attr($balance); ?>" required style="width:120px;padding:6px">
                                    <select name="mode" required style="padding:6px">
                                        <option value="interac">Interac</option>
                                        <option value="comptant">Comptant</option>
                                        <option value="cheque">Chèque</option>
                                    </select>
                                    <input type="date" name="date_recu" value="<?php echo esc_attr(date('Y-m-d')); ?>" required style="padding:6px">
                                    <input type="text" name="note" placeholder="<?php echo esc_attr__('Note (optionnel)', 'scout-inscription'); ?>" style="padding:6px;flex:1;min-width:150px">
                                    <button type="submit" class="button button-primary"><?php esc_html_e('Enregistrer', 'scout-inscription'); ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- PDFs -->
                    <div class="postbox" style="padding:20px;margin-top:20px">
                        <h2 style="margin-top:0"><?php esc_html_e('Documents', 'scout-inscription'); ?></h2>
                        <?php
                        $doc_base = home_url('/?scout_doc=' . urlencode($inscription->ref_number) . '&doc_type=');
                        ?>
                        <a href="<?php echo esc_url($doc_base . 'fiche_medicale'); ?>" class="button" target="_blank"><?php esc_html_e('Fiche médicale', 'scout-inscription'); ?></a>
                        <a href="<?php echo esc_url($doc_base . 'acceptation_risque'); ?>" class="button" target="_blank"><?php esc_html_e('Acceptation risques', 'scout-inscription'); ?></a>
                        <a href="<?php echo esc_url($doc_base . 'sommaire'); ?>" class="button" target="_blank"><?php esc_html_e('Sommaire + QR', 'scout-inscription'); ?></a>
                    </div>

                    <!-- QR Code -->
                    <div class="postbox" style="padding:20px;margin-top:20px;text-align:center">
                        <h2 style="margin-top:0"><?php esc_html_e('Code QR de vérification', 'scout-inscription'); ?></h2>
                        <div id="adminQR" style="margin:16px auto"></div>
                        <p style="font-size:12px;color:#6a6a62;margin:8px 0 16px">
                            <?php echo esc_html($inscription->ref_number); ?> · Signé HMAC-SHA256
                        </p>
                        <button onclick="adminDownloadQR()" class="button button-primary"><?php esc_html_e('Télécharger le QR', 'scout-inscription'); ?></button>
                        <button onclick="adminPrintQR()" class="button"><?php esc_html_e('Imprimer', 'scout-inscription'); ?></button>
                    </div>

                    <script src="<?php echo esc_url(SCOUT_INS_URL . 'public/js/qrcode-generator.js'); ?>"></script>
                    <script>
                    (function(){
                        var ref = '<?php echo esc_js($inscription->ref_number); ?>';
                        var tok = '<?php echo esc_js($inscription->hmac_token); ?>';
                        var url = '<?php echo esc_url(home_url("/inscription/verification/")); ?>?ref=' + encodeURIComponent(ref) + '&tok=' + encodeURIComponent(tok);
                        var qr = qrcode(0, 'H');
                        qr.addData(url);
                        qr.make();
                        var size=220, mc=qr.getModuleCount(), cellSize=size/mc;
                        var canvas = document.createElement('canvas');
                        canvas.width=size; canvas.height=size;
                        var ctx = canvas.getContext('2d');
                        for(var r=0;r<mc;r++)for(var c=0;c<mc;c++){ctx.fillStyle=qr.isDark(r,c)?'#007748':'#ffffff';ctx.fillRect(c*cellSize,r*cellSize,cellSize+1,cellSize+1);}
                        document.getElementById('adminQR').appendChild(canvas);
                    })();
                    function adminDownloadQR(){
                        var canvas = document.querySelector('#adminQR canvas');
                        if(!canvas) return;
                        var a = document.createElement('a');
                        a.download = 'qr-<?php echo esc_attr($inscription->ref_number); ?>.png';
                        a.href = canvas.toDataURL('image/png');
                        a.click();
                    }
                    function adminPrintQR(){
                        var canvas = document.querySelector('#adminQR canvas');
                        if(!canvas) return;
                        var win = window.open('','','width=400,height=500');
                        win.document.write('<html><body style="text-align:center;font-family:sans-serif;padding:40px">');
                        win.document.write('<h2>⚜️ 5e Groupe scout Grand-Moulin</h2>');
                        win.document.write('<p><strong><?php echo esc_js($inscription->ref_number); ?></strong></p>');
                        win.document.write('<img src="' + canvas.toDataURL('image/png') + '" style="width:200px;height:200px">');
                        win.document.write('<p style="font-size:12px;color:#666">Scannez pour vérifier l\'inscription</p>');
                        win.document.write('</body></html>');
                        win.document.close();
                        win.print();
                    }
                    </script>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings(): void {
        if (class_exists('Scout_Admin_Settings')) {
            (new Scout_Admin_Settings())->render();
        }
    }

    public function render_log(): void {
        $logs = Scout_Access_Log::get_recent(100);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Journal d\'accès (Loi 25)', 'scout-inscription'); ?></h1>
            <p><?php esc_html_e('Les 100 derniers accès aux données personnelles.', 'scout-inscription'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th><?php esc_html_e('Date', 'scout-inscription'); ?></th><th><?php esc_html_e('Utilisateur', 'scout-inscription'); ?></th><th><?php esc_html_e('Action', 'scout-inscription'); ?></th><th><?php esc_html_e('Inscription', 'scout-inscription'); ?></th><th><?php esc_html_e('IP', 'scout-inscription'); ?></th><th><?php esc_html_e('Détails', 'scout-inscription'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td><?php echo esc_html($log->user_name ?? __('Anonyme', 'scout-inscription')); ?></td>
                        <td><code><?php echo esc_html($log->action); ?></code></td>
                        <td><?php echo esc_html($log->inscription_id ?? '—'); ?></td>
                        <td><?php echo esc_html($log->ip_address); ?></td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?php echo esc_html($log->details); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Handle payment form submission
add_action('admin_post_scout_add_payment', function () {
    if (!wp_verify_nonce($_POST['_scout_nonce'] ?? '', 'scout_add_payment')) {
        wp_die(__('Nonce invalide', 'scout-inscription'));
    }
    if (!current_user_can('scout_manage_payments')) {
        wp_die(__('Permission refusée', 'scout-inscription'));
    }

    $inscription_id = absint($_POST['inscription_id'] ?? 0);
    $ref = sanitize_text_field($_POST['ref'] ?? '');

    Scout_Payment_Model::create($inscription_id, [
        'mode'      => sanitize_text_field($_POST['mode'] ?? 'comptant'),
        'montant'   => floatval($_POST['montant'] ?? 0),
        'date_recu' => sanitize_text_field($_POST['date_recu'] ?? date('Y-m-d')),
        'note'      => sanitize_textarea_field($_POST['note'] ?? ''),
    ]);

    // Send email
    Scout_Email_Handler::send_payment_received($inscription_id, floatval($_POST['montant'] ?? 0));

    wp_redirect(admin_url('admin.php?page=scout-inscription&ref=' . $ref . '&payment_added=1'));
    exit;
});
