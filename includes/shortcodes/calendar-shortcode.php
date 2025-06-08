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
        <p class="buukly-title">In welcher Kanzlei wünschen Sie eine Beratung?</p>
        <form id="buukly-location-form" class="buukly-location-form">
            <?php foreach ($locations as $loc): ?>
                <div class="buukly-location-entry">
                    <label>
                        <input type="radio" name="buukly_selected_location" value="<?= esc_attr($loc->id); ?>">
                        <strong><?= esc_html($loc->name); ?></strong><br>
                        <small><?= esc_html($loc->street . ' ' . $loc->house_number . ', ' . $loc->zip . ' ' . $loc->city); ?></small>
                    </label>
                </div>
            <?php endforeach; ?>

            <div class="buukly-button-wrapper">
                <button type="button" id="buukly-submit-location" class="buukly-button">Weiter</button>
            </div>
        </form>
    </div>

    <style>
        .buukly-calendar-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            font-family: 'Merriweather', Georgia, "Times New Roman", serif !important;
            color: #000;
        }

        .buukly-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .buukly-location-entry {
            margin-bottom: 20px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f9f9f9;
        }

        .buukly-location-entry input[type="radio"] {
            margin-right: 10px;
        }

        .buukly-button-wrapper {
            text-align: right;
        }

        .buukly-button {
            background-color: #9E8256;
            color: #fff;
            padding: 10px 20px;
            font-size: 16px;
            font-family: 'Merriweather', Georgia, "Times New Roman", serif !important;
            border: none;
            border-radius: 5px;
            transition: background-color 0.2s ease-in-out;
            cursor: pointer;
        }

        .buukly-button:hover {
            background-color: #bca689;
        }

        @media (max-width: 768px) {
            .buukly-calendar-wrapper {
                padding: 10px;
            }

            .buukly-button-wrapper {
                text-align: center;
            }

            .buukly-button {
                width: 100%;
                margin-top: 15px;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

add_shortcode('buukly_calendar', 'buukly_render_calendar_shortcode');
