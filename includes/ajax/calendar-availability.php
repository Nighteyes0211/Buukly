<?php
add_action('wp_ajax_buukly_get_availability', 'buukly_get_availability');
add_action('wp_ajax_nopriv_buukly_get_availability', 'buukly_get_availability');

function buukly_get_availability() {
    $location_id = intval($_POST['location_id']);

    ob_start(); ?>
    <div class="buukly-booking-layout">
        <!-- Linke Spalte: Kalender -->
        <div class="buukly-column buukly-calendar-column">
            <input type="hidden" id="buukly-location-id" value="<?php echo esc_attr($location_id); ?>">
            <div id="buukly-calendar"></div>
        </div>

        <!-- Rechte Spalte: Auswahl -->
        <div class="buukly-column buukly-selection-column">
            <div id="buukly-employees-container">
                <p>Bitte wähle ein Datum.</p>
            </div>
            <div id="buukly-slots-container" style="margin-top:20px;">
                <p>Bitte zuerst einen Mitarbeiter wählen.</p>
            </div>
        </div>
    </div>

    <style>
        .buukly-booking-layout {
            display: flex;
            gap: 30px;
        }

        .buukly-column {
            flex: 1;
        }

        .buukly-calendar-column {
            max-width: 300px;
        }

        #buukly-employees-container select {
            width: 100%;
            padding: 8px;
            font-size: 16px;
        }

        #buukly-slots-container button {
            margin: 5px 5px 0 0;
            padding: 8px 12px;
            font-size: 14px;
            cursor: pointer;
        }

        #buukly-slots-container button:hover {
            background-color: #007cba;
            color: white;
        }
    </style>
    <?php

    echo ob_get_clean();
    wp_die();
}
