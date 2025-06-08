<?php
if (!defined('ABSPATH')) exit;

// 🚀 AJAX registrieren
add_action('wp_ajax_buukly_get_employees', 'buukly_get_employees');
add_action('wp_ajax_nopriv_buukly_get_employees', 'buukly_get_employees');

function buukly_get_employees() {
    global $wpdb;

    $location_id = intval($_POST['location_id'] ?? 0);
    $date        = sanitize_text_field($_POST['date'] ?? '');

    if (!$location_id || !$date) {
        wp_send_json_error('Fehlende Parameter.');
    }

    $weekday = (int) date('N', strtotime($date)); // 1 = Montag

    $available_employees = $wpdb->get_results($wpdb->prepare(
        "SELECT e.id, e.name, e.email, a.start_time, a.end_time
         FROM {$wpdb->prefix}buukly_employees e
         INNER JOIN {$wpdb->prefix}buukly_employee_availability a ON e.id = a.employee_id
         WHERE a.location_id = %d AND a.weekday = %d",
        $location_id,
        $weekday
    ));

    if (!$available_employees) {
        wp_send_json_success('');
    }

    ob_start();

    $already_output = [];

    foreach ($available_employees as $emp) {
        if (in_array($emp->id, $already_output)) {
            continue;
        }

        $start = strtotime("$date {$emp->start_time}");
        $end   = strtotime("$date {$emp->end_time}");

        $has_free_slot = false;

        while ($start + 3600 <= $end) {
            $slot_start = date('Y-m-d H:i:s', $start);
            $slot_end   = date('Y-m-d H:i:s', $start + 3600);

            $conflict = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}buukly_outlook_events
                 WHERE employee_id = %d
                 AND NOT (
                    end_time <= %s OR start_time >= %s
                 )",
                $emp->id,
                $slot_start,
                $slot_end
            ));

            if ($conflict == 0) {
                $has_free_slot = true;
                break;
            }

            $start += 1800;
        }

        if (!$has_free_slot) continue;

        echo sprintf(
            '<option value="%d">%s (%s)</option>',
            esc_attr($emp->id),
            esc_html($emp->name),
            esc_html($emp->email)
        );

        $already_output[] = $emp->id;
    }

    wp_send_json_success(ob_get_clean());
}
