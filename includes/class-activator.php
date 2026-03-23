<?php
defined('ABSPATH') || exit;

class Scout_Inscription_Activator {

    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::create_upload_dir();
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p = SCOUT_DB_PREFIX;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Inscriptions
        dbDelta("CREATE TABLE {$p}inscriptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ref_number VARCHAR(20) NOT NULL,
            hmac_token VARCHAR(64) NOT NULL,
            annee_scoute VARCHAR(9) NOT NULL,
            unite VARCHAR(20) NOT NULL,
            enfant_prenom TEXT NOT NULL,
            enfant_nom TEXT NOT NULL,
            enfant_ddn DATE NOT NULL,
            enfant_sexe VARCHAR(10) NOT NULL DEFAULT '',
            enfant_adresse TEXT NOT NULL,
            enfant_ville VARCHAR(100) NOT NULL DEFAULT '',
            enfant_code_postal VARCHAR(10) NOT NULL DEFAULT '',
            enfant_telephone VARCHAR(100) NOT NULL DEFAULT '',
            assurance_maladie VARCHAR(200) NOT NULL DEFAULT '',
            assurance_expiration VARCHAR(20) NOT NULL DEFAULT '',
            medical_data LONGTEXT NOT NULL,
            risk_signature TEXT NOT NULL DEFAULT '',
            consents JSON NOT NULL,
            payment_status ENUM('en_attente','acompte_recu','paye','annulee') NOT NULL DEFAULT 'en_attente',
            payment_total DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            payment_received DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            status ENUM('brouillon','complete','approuvee','rejetee','plan_paiement','annulee','doublon') NOT NULL DEFAULT 'brouillon',
            date_entree_mouvement VARCHAR(20) NOT NULL DEFAULT '',
            autres_enfants_groupe TINYINT(1) NOT NULL DEFAULT 0,
            autres_enfants_detail VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ref_number (ref_number),
            KEY annee_scoute (annee_scoute),
            KEY unite (unite),
            KEY payment_status (payment_status),
            KEY status (status)
        ) $charset;");

        // 2. Contacts (parents + urgence)
        dbDelta("CREATE TABLE {$p}contacts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            inscription_id BIGINT UNSIGNED NOT NULL,
            type ENUM('parent','urgence') NOT NULL,
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
            prenom VARCHAR(200) NOT NULL DEFAULT '',
            nom VARCHAR(200) NOT NULL DEFAULT '',
            lien VARCHAR(50) NOT NULL DEFAULT '',
            telephone VARCHAR(100) NOT NULL DEFAULT '',
            cellulaire VARCHAR(100) NOT NULL DEFAULT '',
            courriel VARCHAR(200) NOT NULL DEFAULT '',
            resp_finances TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY inscription_id (inscription_id)
        ) $charset;");

        // 3. Families
        dbDelta("CREATE TABLE {$p}families (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            family_token VARCHAR(64) NOT NULL,
            family_email VARCHAR(200) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY family_token (family_token),
            KEY family_email (family_email)
        ) $charset;");

        // Add family_id column to inscriptions if not exists
        $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$p}inscriptions LIKE 'family_id'");
        if (!$col_exists) {
            $wpdb->query("ALTER TABLE {$p}inscriptions ADD COLUMN family_id BIGINT UNSIGNED DEFAULT NULL AFTER id, ADD KEY family_id (family_id)");
        }

        // 4. Payments
        dbDelta("CREATE TABLE {$p}payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            inscription_id BIGINT UNSIGNED NOT NULL,
            mode ENUM('interac','comptant','cheque') NOT NULL,
            montant DECIMAL(8,2) NOT NULL,
            date_recu DATE NOT NULL,
            note TEXT NOT NULL DEFAULT '',
            marked_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY inscription_id (inscription_id)
        ) $charset;");

        // 4. Access log (Loi 25)
        dbDelta("CREATE TABLE {$p}access_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            inscription_id BIGINT UNSIGNED DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent TEXT NOT NULL DEFAULT '',
            details TEXT NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY inscription_id (inscription_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset;");

        // 5. Documents
        dbDelta("CREATE TABLE {$p}documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            inscription_id BIGINT UNSIGNED NOT NULL,
            type ENUM('fiche_medicale','acceptation_risque','sommaire') NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY inscription_id (inscription_id)
        ) $charset;");

        update_option('scout_ins_db_version', SCOUT_INS_VERSION);
    }

    private static function create_roles() {
        // Animateur — read-only on inscriptions for their unit
        add_role('scout_animateur', __('Animateur scout', 'scout-inscription'), [
            'read'                     => true,
            'scout_view_inscriptions'  => true,
            'scout_view_medical'       => true,
            'scout_scan_qr'            => true,
        ]);

        // Trésorier — animateur + payment management + export
        add_role('scout_tresorier', __('Trésorier scout', 'scout-inscription'), [
            'read'                     => true,
            'scout_view_inscriptions'  => true,
            'scout_view_medical'       => true,
            'scout_scan_qr'            => true,
            'scout_manage_payments'    => true,
            'scout_export'             => true,
        ]);

        // Give admin full plugin capabilities
        $admin = get_role('administrator');
        if ($admin) {
            $caps = [
                'scout_view_inscriptions', 'scout_view_medical', 'scout_scan_qr',
                'scout_manage_payments', 'scout_export',
                'scout_manage_inscriptions', 'scout_manage_settings',
            ];
            foreach ($caps as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    private static function create_upload_dir() {
        $upload_dir = wp_upload_dir();
        $scout_dir = $upload_dir['basedir'] . '/scout-docs';

        if (!file_exists($scout_dir)) {
            wp_mkdir_p($scout_dir);
        }

        // .htaccess to block direct access to PDFs
        $htaccess = $scout_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
        }

        // index.php to prevent directory listing
        $index = $scout_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden.');
        }
    }
}
