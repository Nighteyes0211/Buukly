<?php
if (!defined('ABSPATH')) exit;

function buukly_get_oauth_url($employee_id) {
    $client_id    = get_option('buukly_client_id');
    $tenant_id    = get_option('buukly_tenant_id');
    $redirect_uri = admin_url('admin-post.php?action=buukly_outlook_employee_callback');

    $scopes = 'offline_access Calendars.Read User.Read';

    $state = base64_encode(json_encode([
        'employee_id' => $employee_id,
        'nonce' => wp_create_nonce('buukly_employee_' . $employee_id)
    ]));

    $params = [
        'client_id'     => $client_id,
        'response_type' => 'code',
        'redirect_uri'  => $redirect_uri,
        'response_mode' => 'query',
        'scope'         => $scopes,
        'state'         => $state,
    ];

    return "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/authorize?" . http_build_query($params);
}
