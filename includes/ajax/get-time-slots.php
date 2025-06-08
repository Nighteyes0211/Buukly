<?php
if (!defined('ABSPATH')) exit;

// 🚀 AJAX registrieren
add_action('wp_ajax_buukly_get_time_slots', 'buukly_get_time_slots');
add_action('wp_ajax_nopriv_buukly_get_time_slots', 'buukly_get_time_slots');

function buukly_get_time_slots() {
    global $wpdb;

    $date        = sanitize_text_field($_POST['date'] ?? '');
    $location_id = intval($_POST['location_id'] ?? 0);
    $employee_id = intval($_POST['employee_id'] ?? 0);

    if (!$date || !$location_id || !$employee_id) {
        wp_send_json_error('Fehlende Parameter.');
    }

    require_once plugin_dir_path(__FILE__) . '../sync/outlook-sync.php';

    // 👉 Verfügbare Slots anhand der Verfügbarkeiten und Outlook ermitteln
    $slots = buukly_get_available_slots($employee_id, $date);

    $berlin = new DateTimeZone('Europe/Berlin');

    // 👉 Outlook-Termine aus der lokalen Tabelle (die durch Outlook Sync aktualisiert wird)
    $start_of_day = $date . ' 00:00:00';
    $end_of_day   = $date . ' 23:59:59';

    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT start_time, end_time FROM {$wpdb->prefix}buukly_outlook_events
         WHERE employee_id = %d AND start_time BETWEEN %s AND %s",
        $employee_id,
        $start_of_day,
        $end_of_day
    ));

    $busy_intervals = [];
    foreach ($events as $e) {
        $busy_intervals[] = [
            'start' => (new DateTime($e->start_time, $berlin))->getTimestamp(),
            'end'   => (new DateTime($e->end_time, $berlin))->getTimestamp(),
        ];
    }

    // 👉 Slots filtern
    $final_slots = [];

    foreach ($slots as $slot) {
        $slot_start = (new DateTime($slot['start'], $berlin))->getTimestamp();
        $slot_end   = (new DateTime($slot['end'], $berlin))->getTimestamp();
        $overlap = false;

        foreach ($busy_intervals as $busy) {
            if ($slot_start < $busy['end'] && $slot_end > $busy['start']) {
                // Wenn Start exakt mit Ende übereinstimmt, ist das ok
                if ($slot_end == $busy['start']) {
                    continue;
                }
                $overlap = true;
                break;
            }
        }

        if (!$overlap) {
            $final_slots[] = $slot;
        }
    }

    // 👉 HTML-Ausgabe
    ob_start();
    ?>
    <div class="buukly-time-slots">
        <h3>Verfügbare Zeiten für <?php echo esc_html(date_i18n('l, d. F Y', strtotime($date))); ?></h3>
        <ul>
            <?php if (empty($final_slots)): ?>
                <li>Keine freien Zeiten verfügbar.</li>
            <?php else: ?>
                <?php foreach ($final_slots as $slot): ?>
                    <li>
                        <button class="buukly-slot"
                            data-start="<?php echo esc_attr($slot['start']); ?>"
                            data-end="<?php echo esc_attr($slot['end']); ?>">
                            <?php
                            $startTime = (new DateTime($slot['start'], $berlin))->format('H:i');
                            $endTime   = (new DateTime($slot['end'], $berlin))->format('H:i');
                            echo esc_html("$startTime – $endTime");
                            ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
    <?php

    wp_send_json_success(ob_get_clean());
}
