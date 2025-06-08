<?php 
add_action('wp_ajax_buukly_get_availability', 'buukly_get_availability');
add_action('wp_ajax_nopriv_buukly_get_availability', 'buukly_get_availability');

function buukly_get_availability() {
    $location_id = intval($_POST['location_id']);

    ob_start(); ?>
    <div class="buukly-wrapper">
        <div class="buukly-grid">
            <!-- 📅 Kalender -->
            <div class="buukly-column calendar">
                <input type="hidden" id="buukly-location-id" value="<?php echo esc_attr($location_id); ?>">
                <div id="buukly-calendar" class="calendar-box"></div>
            </div>

            <!-- 👤 Auswahl + 🕑 Slots -->
            <div class="buukly-column selection">
                <div id="buukly-employees-container" class="card">
                    <p>Bitte wähle ein Datum.</p>
                </div>

                <div id="buukly-slots-container" class="card">
                    <p>Bitte zuerst einen Mitarbeiter wählen.</p>
                </div>
            </div>
        </div>
    </div>

    <style>
        .buukly-wrapper {
            font-family: 'Merriweather', Georgia, "Times New Roman", serif !important;
            color: #000;
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .buukly-grid {
            display: flex;
            gap: 40px;
            align-items: flex-start;
        }

        .buukly-column {
            flex: 1;
        }

        .calendar-box {
            background: #fff;
            border: 1px solid #e3e3e3;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
            margin-bottom: 25px;
        }

        #buukly-employees-container label {
            font-weight: 600;
            display: block;
            margin-bottom: 10px;
        }

        #buukly-employees-container select {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: #fdfdfd;
        }

        #buukly-slots-container {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        #buukly-slots-container ul li {
            margin-bottom: 10px;
            list-style: none;
        }

        #buukly-slots-container button {
            background: #9E8256;
            padding: 12px 20px;
            font-size: 16px;
            font-family: 'Merriweather', Georgia, "Times New Roman", serif !important;
            border-radius: 6px;
            color: #fff;
            transition: all 0.2s ease;
            cursor: pointer;
            border:none !important;
            max-width: 160px;
            min-width: 160px;

        }

        #buukly-slots-container button:hover {
            background-color: #bca689;
            color: #fff;
            border-color: #bca689;
        }

        @media (max-width: 768px) {
            .buukly-grid {
                flex-direction: column;
            }

            .calendar-box,
            .card {
                padding: 15px;
            }

            #buukly-slots-container {
                flex-direction: column;
            }

            #buukly-slots-container button {
                width: 100%;
            }
        }

.buukly-calendar-wrapper {
    width: 100% !important;
    max-width: 100% !important;
}



.fc .fc-button-primary {
    background-color: #9E8256 !important;
}

.buukly-slot.active-slot {
    background-color: #9E8256 !important;
    color: #fff !important;
    border-color: #9E8256 !important;
    box-shadow: 0 0 0 3px rgba(158, 130, 86, 0.3);
    font-weight: bold;
    transition: all 0.2s ease-in-out;
}

.entry-content tr td {
    padding: 0px !important;

}


    </style>
    <?php

    echo ob_get_clean();
    wp_die();
}
