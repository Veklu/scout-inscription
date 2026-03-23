<?php
defined('ABSPATH') || exit;

class Scout_Inscription_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook('scout_data_retention_cron');
    }
}
