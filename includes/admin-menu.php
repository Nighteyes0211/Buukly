<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '/admin/outlook-settings.php';
require_once plugin_dir_path(__FILE__) . '/ajax/send-booking.php';

function buukly_admin_menu() {
    // Hauptmenü
    add_menu_page('Buukly', 'Buukly', 'manage_options', 'buukly_dashboard', 'buukly_render_dashboard', 'dashicons-calendar-alt', 25);

    // 1. Dashboard
    add_submenu_page('buukly_dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'buukly_dashboard', 'buukly_render_dashboard');

    // 2. Kalender
    add_submenu_page('buukly_dashboard', 'Kalender erstellen', 'Kalender', 'manage_options', 'buukly_calendar_create', 'buukly_render_calendar_create');

    // 3. Buchungen
    add_submenu_page('buukly_dashboard', 'Buchungsübersicht', 'Buchungen', 'manage_options', 'buukly_bookings_overview', 'buukly_render_bookings');

    // 4. Mitarbeiter
    add_submenu_page('buukly_dashboard', 'Mitarbeiter', 'Mitarbeiter', 'manage_options', 'buukly_employees', 'buukly_render_employees');

    // 5. Standorte
    add_submenu_page('buukly_dashboard', 'Standorte', 'Standorte', 'manage_options', 'buukly_locations', 'buukly_render_locations');

    // 6. Verfügbarkeiten
    add_submenu_page('buukly_dashboard', 'Verfügbarkeiten', 'Verfügbarkeiten', 'manage_options', 'buukly_availability', 'buukly_render_availability');

    // 7. Termine (Outlook-Termine)
    add_submenu_page('buukly_dashboard', 'Outlook-Termine', 'Termine', 'manage_options', 'buukly_outlook_events', 'buukly_render_outlook_events');

    // 8. Einstellungen
    add_submenu_page('buukly_dashboard', 'Outlook Verbindung', 'Outlook Verbindung', 'manage_options', 'buukly_outlook', 'buukly_render_outlook_settings');
    add_submenu_page('buukly_dashboard', 'Outlook Sync Status', 'Outlook Sync', 'manage_options', 'buukly_sync_status', 'buukly_render_sync_status');

    // Versteckter Menüpunkt: Mitarbeiter bearbeiten
    add_submenu_page(null, 'Mitarbeiter bearbeiten', 'Bearbeiten', 'manage_options', 'buukly_edit_employee', 'buukly_render_employee_edit');
}

// Menü registrieren
add_action('admin_menu', 'buukly_admin_menu');


function buukly_admin_enqueue_assets($hook) {
    // Nur auf Buukly-Seiten laden
    if (strpos($hook, 'buukly') === false) return;

    // Bootstrap 5
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], null, true);

    // Chart.js
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
}
add_action('admin_enqueue_scripts', 'buukly_admin_enqueue_assets');

add_action('admin_post_connect_employee_outlook', 'buukly_handle_connect_outlook');


function buukly_handle_connect_outlook() {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.');
    }

    if (!isset($_POST['outlook_connect_employee'])) {
        wp_die('Fehlender Mitarbeiter.');
    }

    $employee_id = intval($_POST['outlook_connect_employee']);

    require_once plugin_dir_path(__FILE__) . 'oauth/outlook-connect.php';
    $url = buukly_get_oauth_url($employee_id);

    wp_redirect($url);
    exit;
}


// Render-Callbacks
require_once plugin_dir_path(__FILE__) . '/admin/admin-dashboard.php';

function buukly_render_calendar_create() {
    include plugin_dir_path(__FILE__) . '/admin/calendar-create.php';
}

function buukly_render_bookings() {
    include plugin_dir_path(__FILE__) . '/admin/bookings-overview.php';
}

function buukly_render_employees() {
    include plugin_dir_path(__FILE__) . '/admin/employees.php';
}

function buukly_render_locations() {
    include plugin_dir_path(__FILE__) . '/admin/locations.php';
}

function buukly_render_availability() {
    include plugin_dir_path(__FILE__) . '/admin/availability.php';
}

function buukly_render_outlook_events() {
    include plugin_dir_path(__FILE__) . '/admin/outlook-events.php';
}


function buukly_render_sync_status() {
    include plugin_dir_path(__FILE__) . '/admin/outlook-sync-status.php';
}

function buukly_render_employee_edit() {
    include plugin_dir_path(__FILE__) . '/admin/employee-edit.php';
}


