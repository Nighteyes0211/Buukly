<?php

function buukly_render_calendar_shortcode($atts) {
    global $wpdb;
    $calendar_table = $wpdb->prefix . 'buukly_calendar';

    $atts = shortcode_atts([
        'id' => 0
    ], $atts);

    $calendar_id = intval($atts['id']);
    if ($calendar_id <= 0) return '<p>Ungültiger Kalender-ID.</p>';

    $calendar = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $calendar_table WHERE id = %d", $calendar_id
    ));

    if (!$calendar) return '<p>Kalender nicht gefunden.</p>';

    // Placeholder: Du kannst hier Kalenderdaten darstellen
    ob_start();
    ?>
    <div class="buukly-calendar">
        <h2><?php echo esc_html($calendar->name); ?> (<?php echo esc_html($calendar->year); ?>)</h2>
        <p>ID: <?php echo esc_html($calendar->id); ?></p>
        <!-- Hier folgt später die Buchungsanzeige -->
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('buukly_calendar', 'buukly_render_calendar_shortcode');



add_action('wp_ajax_buukly_load_tab', function () {
    check_ajax_referer('buukly_tab_nonce');

    $employee_id = intval($_POST['employee_id'] ?? 0);
    $tab = sanitize_key($_POST['tab'] ?? '');

    if (!$employee_id) {
        wp_die('Keine Mitarbeiter-ID');
    }

    if ($tab === 'general') {
        include plugin_dir_path(__FILE__) . '/admin/availability/general.php';
    } elseif ($tab === 'availability') {
        include plugin_dir_path(__FILE__) . '/admin/availability/form.php';
    } else {
        wp_die('Unbekannter Tab');
    }

    wp_die(); // wichtig!
});

add_action('wp_ajax_buukly_get_time_slots', 'buukly_get_time_slots');
function buukly_get_time_slots() {
    include plugin_dir_path(__FILE__) . 'get-availability.php';
    wp_die();
}


add_action('admin_init', function () {
    register_setting('general', 'buukly_admin_email', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_email',
        'default' => ''
    ]);

    add_settings_field(
        'buukly_admin_email',
        'Buukly: Admin-E-Mail',
        function () {
            $value = get_option('buukly_admin_email', '');
            echo '<input type="email" name="buukly_admin_email" value="' . esc_attr($value) . '" class="regular-text">';
        },
        'general'
    );
});



add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], null, true);
});



?>