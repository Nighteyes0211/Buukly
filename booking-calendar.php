<?php
/**
 * Plugin Name:       Buukly – Booking Calendar
 * Description:       Einfaches und synchronisiertes Buchungssystem mit Outlook-Integration.
 * Version:           1.0.0
 * Author: <a href="https://www.kempener-werbejungs.de/software" target="_blank">KWJ Software</a> - Ein Unternehmen der <a href="https://www.kempener-werbejungs.de" target="_blank">Kempener Werbejungs</a>
 * Author URI:        https://kwj-software.de
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       buukly
 */

if (!defined('ABSPATH')) exit;

add_filter('cron_schedules', function($schedules) {
    $schedules['every_ten_minutes'] = [
        'interval' => 600, // 600 Sekunden = 10 Minuten
        'display'  => __('Alle 10 Minuten', 'Buukly')
    ];
    return $schedules;
});

// WP-Cron registrieren bei Plugin-Aktivierung
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('buukly_sync_outlook_events')) {
        wp_schedule_event(time(), 'every_ten_minutes', 'buukly_sync_outlook_events');
    }
});

// WP-Cron wieder entfernen bei Deaktivierung
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('buukly_sync_outlook_events');
});


add_action('buukly_sync_outlook_events', 'buukly_fetch_outlook_events');

require_once plugin_dir_path(__FILE__) . 'includes/sync/outlook-sync.php';


// 🔧 Installationsroutine beim Aktivieren
register_activation_hook(__FILE__, 'buukly_install');
function buukly_install() {
    require_once plugin_dir_path(__FILE__) . 'install.php';
}




if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';
}


function buukly_register_shortcodes() {
    require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/calendar-shortcode.php';
    add_shortcode('buukly_calendar', 'buukly_render_calendar_shortcode');
}
add_action('init', 'buukly_register_shortcodes');

require_once plugin_dir_path(__FILE__) . 'includes/ajax/calendar-availability.php';


require_once plugin_dir_path(__FILE__) . 'includes/oauth/outlook-callback.php';




function buukly_enqueue_scripts() {
    if (!is_singular()) return;

    global $post;
    if (!has_shortcode($post->post_content, 'buukly_calendar')) return;

    // FullCalendar v5.11.3 (letzte mit globalem Build)
    wp_enqueue_style(
        'fullcalendar-css',
        'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css'
    );

    wp_enqueue_script(
        'fullcalendar-js',
        'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js',
        [],
        '5.11.3',
        true
    );

    wp_enqueue_script(
        'fullcalendar-locales',
        'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js',
        ['fullcalendar-js'],
        '5.11.3',
        true
    );

    wp_enqueue_script(
        'buukly-calendar',
        plugin_dir_url(__FILE__) . 'includes/assets/js/buukly.js',
        ['fullcalendar-js', 'fullcalendar-locales'],
        null,
        true
    );

    wp_localize_script('buukly-calendar', 'buukly_ajax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
}



// Callback registrieren (für eingeloggte und nicht eingeloggte Benutzer)
add_action('admin_post_buukly_outlook_callback', 'buukly_handle_outlook_callback');
add_action('admin_post_nopriv_buukly_outlook_callback', 'buukly_handle_outlook_callback');

function buukly_handle_outlook_callback() {
    if (!isset($_GET['code'])) {
        wp_die('Fehlender Autorisierungscode');
    }

    $code = sanitize_text_field($_GET['code']);
    $client_id     = get_option('buukly_client_id');
    $client_secret = get_option('buukly_client_secret');
    $tenant_id     = get_option('buukly_tenant_id');
    $redirect_uri  = admin_url('admin-post.php?action=buukly_outlook_callback');

    // Token anfordern
    $response = wp_remote_post("https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token", [
        'body' => [
            'grant_type'    => 'authorization_code',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'code'          => $code,
            'redirect_uri'  => $redirect_uri,
            'scope'         => 'offline_access https://graph.microsoft.com/.default',
        ]
    ]);

    if (is_wp_error($response)) {
        wp_die('Fehler beim Abrufen des Tokens');
    }

    $token_data = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($token_data['access_token'])) {
        echo '<pre>'; print_r($token_data); echo '</pre>';
        wp_die('Token konnte nicht abgerufen werden');
    }

    // Token speichern
    update_option('buukly_access_token', $token_data['access_token']);
    update_option('buukly_refresh_token', $token_data['refresh_token'] ?? '');
    update_option('buukly_token_expires', time() + intval($token_data['expires_in']));

    // Microsoft Graph API: Benutzerinfos abfragen
    $user_response = wp_remote_get('https://graph.microsoft.com/v1.0/me', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token_data['access_token']
        ]
    ]);

    if (is_wp_error($user_response)) {
        wp_die('Fehler beim Abrufen der Benutzerdaten');
    }

    $data = json_decode(wp_remote_retrieve_body($user_response), true);

    // E-Mail holen (primär mail, sonst userPrincipalName)
    $user_email = $data['mail'] ?? $data['userPrincipalName'] ?? null;

    if ($user_email) {
        global $wpdb;
        $employees_table = $wpdb->prefix . 'buukly_employees';

        // Mitarbeiter mit passender E-Mail suchen
        $employee = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $employees_table WHERE email = %s", $user_email)
        );

        if ($employee) {
            // Outlook ID speichern
            $wpdb->update(
                $employees_table,
                ['ms_user_id' => sanitize_text_field($data['id'])],
                ['id' => $employee->id]
            );

            wp_redirect(admin_url('admin.php?page=buukly_employees&connected=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=buukly_employees&connected=0&error=no_match'));
            exit;
        }
    }

    wp_die('Benutzerdaten unvollständig');
}




function buukly_handle_employee_outlook_callback() {
    if (!isset($_GET['code'], $_GET['employee'])) {
        wp_die('Fehlender Parameter.');
    }

    $employee_id = intval($_GET['employee']);
    if (!wp_verify_nonce($_GET['state'], 'buukly_employee_' . $employee_id)) {
        wp_die('Ungültiger Sicherheitscode.');
    }

    $client_id     = get_option('buukly_client_id');
    $client_secret = get_option('buukly_client_secret');
    $tenant_id     = get_option('buukly_tenant_id');
    $redirect_uri  = admin_url('admin-post.php?action=buukly_outlook_employee_callback');

    $response = wp_remote_post("https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token", [
        'body' => [
            'grant_type'    => 'authorization_code',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'code'          => sanitize_text_field($_GET['code']),
            'redirect_uri'  => $redirect_uri,
            'scope'         => 'offline_access Calendars.ReadWrite User.Read',
        ]
    ]);

    if (is_wp_error($response)) {
        wp_die('Fehler beim Token-Abruf.');
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data['access_token'])) {
        wp_die('Token konnte nicht geholt werden.');
    }

    $expires_at = date('Y-m-d H:i:s', time() + intval($data['expires_in']));

    global $wpdb;
    $table = $wpdb->prefix . 'buukly_employees';
    $wpdb->update($table, [
        'outlook_access_token'  => $data['access_token'],
        'outlook_refresh_token' => $data['refresh_token'] ?? '',
        'outlook_token_expires' => $expires_at,
    ], ['id' => $employee_id]);

    wp_redirect(admin_url('admin.php?page=buukly_employees&connected=1'));
    exit;
}






add_action('admin_post_buukly_outlook_employee_callback', 'bk_handle_outlook_employee_callback');

function bk_handle_outlook_employee_callback() {
    include plugin_dir_path(__FILE__) . 'includes/oauth/outlook-employee-callback.php';
    exit; // Wichtig, sonst lädt WordPress weiter
}


add_action('wp_ajax_buukly_load_tab', function () {
    check_ajax_referer('buukly_tab_nonce');

    $employee_id = intval($_POST['employee_id'] ?? 0);
    $tab = sanitize_key($_POST['tab'] ?? '');

    if (!$employee_id) {
        wp_send_json_error('Fehlende Mitarbeiter-ID');
    }

    $base = plugin_dir_path(__FILE__) . '/includes/admin/availability/';

    if ($tab === 'general') {
        include $base . 'general.php';
    } elseif ($tab === 'availability') {
        include $base . 'form.php';
    } else {
        wp_send_json_error('Unbekannter Tab: ' . esc_html($tab));
    }

    wp_die();
});



add_action('wp_ajax_buukly_save_availability', function () {
    global $wpdb;

    $employee_id = intval($_POST['employee_id'] ?? 0);
    if (!$employee_id) wp_send_json_error('Keine Mitarbeiter-ID');

    check_admin_referer('buukly_save_availability_' . $employee_id);

    $data = json_decode(stripslashes($_POST['availability_data']), true);
    if (!is_array($data)) wp_send_json_error('Ungültige Daten');

    $table = $wpdb->prefix . 'buukly_employee_availability';
    $wpdb->delete($table, ['employee_id' => $employee_id]);

    foreach ($data as $entry) {
        $wpdb->insert($table, [
            'employee_id' => $employee_id,
            'location_id' => intval($entry['location']),
            'weekday'     => intval($entry['weekday']),
            'start_time'  => sanitize_text_field($entry['start']),
            'end_time'    => sanitize_text_field($entry['end']),
        ]);
    }

    wp_send_json_success();
});


require_once plugin_dir_path(__FILE__) . 'includes/ajax/get-time-slots.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax/get-employees.php';

add_action('wp_enqueue_scripts', 'buukly_enqueue_scripts');
