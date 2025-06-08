<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '/../sync/outlook-sync.php';

/**
 * Gibt das aktuelle gültige Access Token zurück (ggf. per Refresh erneuert).
 *
 * @return string|null Access Token oder null bei Fehler
 */
function buukly_get_access_token() {
    $access_token = get_option('buukly_access_token');
    $expires      = get_option('buukly_token_expires');

    if (!$access_token || time() >= $expires) {
        return buukly_refresh_access_token();
    }

    return $access_token;
}
