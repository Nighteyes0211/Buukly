<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// 🔒 Pflichtfeld prüfen
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    // Falls der Callback versehentlich ohne Parameter geöffnet wird
    wp_redirect(admin_url('admin.php?page=buukly_employees'));
    exit;
}

// 🧠 State auslesen und decodieren
$state = json_decode(base64_decode($_GET['state']), true);

if (!isset($state['employee_id']) || !isset($state['nonce'])) {
    wp_die('Ungültiger State.');
}

// ❗ Nonce prüfen
if (!wp_verify_nonce($state['nonce'], 'buukly_employee_' . $state['employee_id'])) {
    wp_die('Ungültiges Token.');
}

$employee_id = intval($state['employee_id']);
$code        = sanitize_text_field($_GET['code']);

$client_id     = get_option('buukly_client_id');
$client_secret = get_option('buukly_client_secret');
$tenant_id     = get_option('buukly_tenant_id');

$redirect_uri  = admin_url('admin-post.php?action=buukly_outlook_employee_callback');

// ⛳ Token anfordern
$response = wp_remote_post("https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token", [
    'body' => [
        'grant_type'    => 'authorization_code',
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'code'          => $code,
        'redirect_uri'  => $redirect_uri,
    ]
]);

if (is_wp_error($response)) {
    wp_die('Token-Anfrage fehlgeschlagen.');
}

$data = json_decode(wp_remote_retrieve_body($response), true);

if (empty($data['access_token'])) {
    wp_die('Access Token fehlt.');
}

$access_token  = $data['access_token'];
$refresh_token = $data['refresh_token'];
$expires_at    = date('Y-m-d H:i:s', time() + intval($data['expires_in']));

// 💾 In Mitarbeiter-Tabelle speichern
$wpdb->update(
    $wpdb->prefix . 'buukly_employees',
    [
        'outlook_access_token'  => $access_token,
        'outlook_refresh_token' => $refresh_token,
        'outlook_token_expires' => $expires_at,
    ],
    ['id' => $employee_id]
);

// 🔍 Microsoft ID holen und speichern
$user_response = wp_remote_get('https://graph.microsoft.com/v1.0/me', [
    'headers' => [
        'Authorization' => 'Bearer ' . $access_token
    ]
]);

if (!is_wp_error($user_response)) {
    $user_data = json_decode(wp_remote_retrieve_body($user_response), true);
    $ms_id = sanitize_text_field($user_data['id'] ?? '');

    if (!empty($ms_id)) {
        $wpdb->update(
            $wpdb->prefix . 'buukly_employees',
            ['outlook_user_id' => $ms_id],
            ['id' => $employee_id]
        );
    }
}

// 🎯 Weiterleiten
wp_redirect(admin_url('admin.php?page=buukly_employees&connected=1&id=' . $employee_id));
exit;