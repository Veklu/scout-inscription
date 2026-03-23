<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all database tables, options, roles, and uploaded files.
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;
$prefix = $wpdb->prefix . 'scout_';

// Drop tables
$tables = ['inscriptions', 'contacts', 'payments', 'access_log', 'documents'];
foreach ($tables as $t) {
    $wpdb->query("DROP TABLE IF EXISTS {$prefix}{$t}");
}

// Remove options
$options = [
    'scout_ins_db_version', 'scout_ins_current_year', 'scout_ins_email_from',
    'scout_ins_privacy_officer', 'scout_ins_retention_years', 'scout_ins_pricing',
];
foreach ($options as $opt) {
    delete_option($opt);
}

// Remove roles
remove_role('scout_animateur');
remove_role('scout_tresorier');

// Remove admin capabilities
$admin = get_role('administrator');
if ($admin) {
    $caps = [
        'scout_view_inscriptions', 'scout_view_medical', 'scout_scan_qr',
        'scout_manage_payments', 'scout_export', 'scout_manage_inscriptions', 'scout_manage_settings',
    ];
    foreach ($caps as $cap) {
        $admin->remove_cap($cap);
    }
}

// Remove uploaded files
$upload_dir = wp_upload_dir();
$scout_dir = $upload_dir['basedir'] . '/scout-docs';
if (is_dir($scout_dir)) {
    $it = new RecursiveDirectoryIterator($scout_dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($scout_dir);
}

// Clear cron
wp_clear_scheduled_hook('scout_data_retention_cron');
