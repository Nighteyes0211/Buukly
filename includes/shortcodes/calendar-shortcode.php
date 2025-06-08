<?php
if (!defined('ABSPATH')) exit;

function buukly_render_calendar_shortcode($atts) {
    global $wpdb;

    $calendar_table           = $wpdb->prefix . 'buukly_calendar';
    $locations_table          = $wpdb->prefix . 'buukly_locations';
    $calendar_locations_table = $wpdb->prefix . 'buukly_calendar_locations';

    $atts = shortcode_atts([
        'id' => 0
    ], $atts);

    $calendar_id = intval($atts['id']);
    if ($calendar_id <= 0) return '<p>Ungültiger Kalender-ID.</p>';

    $calendar = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $calendar_table WHERE id = %d", $calendar_id)
    );

    if (!$calendar) return '<p>Kalender nicht gefunden.</p>';

    $location_ids = $wpdb->get_col(
        $wpdb->prepare("SELECT location_id FROM $calendar_locations_table WHERE calendar_id = %d", $calendar_id)
    );

    if (empty($location_ids)) return '<p>Keine Standorte zugewiesen.</p>';

    $placeholders = implode(',', array_fill(0, count($location_ids), '%d'));
    $locations = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $locations_table WHERE id IN ($placeholders)", ...$location_ids)
    );

    ob_start();
    ?>
    <div class="buukly-calendar-wrapper">
        <p>Bitte wähle einen Standort:</p>
        <form id="buukly-location-form">
            <?php foreach ($locations as $loc): ?>
                <label>
                    <input type="radio" name="buukly_selected_location" value="<?= esc_attr($loc->id); ?>">
                    <?= esc_html($loc->name); ?> <br>
                    <?= esc_html($loc->street . ' ' . $loc->house_number . ', ' . $loc->zip . ' ' . $loc->city); ?>
                </label><br><br>
            <?php endforeach; ?>
            <button type="button" id="buukly-submit-location" class="button button-primary">Weiter</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('buukly_calendar', 'buukly_render_calendar_shortcode');
