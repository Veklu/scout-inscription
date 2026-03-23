<?php
defined('ABSPATH') || exit;

class Scout_Public_Form {

    public function render(): string {
        ob_start();
        include SCOUT_INS_DIR . 'templates/form-step-1.php';
        return ob_get_clean();
    }
}
