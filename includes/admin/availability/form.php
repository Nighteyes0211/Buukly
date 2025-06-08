<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$employee_id = intval($_GET['id'] ?? $_POST['employee_id'] ?? 0);
if (!$employee_id) wp_die('Keine Mitarbeiter-ID.');

$table_availability = $wpdb->prefix . 'buukly_employee_availability';

// Speichern bei POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Logge alles frühzeitig
    error_log('Verfügbarkeiten POST-Daten: ' . print_r($_POST, true));

    if (isset($_POST['availability_data'])) {
        check_admin_referer('buukly_save_availability_' . $employee_id);

        $data = json_decode(stripslashes($_POST['availability_data']), true);

        // Alte löschen
        $wpdb->delete($table_availability, ['employee_id' => $employee_id]);

        foreach ($data as $entry) {
            $wpdb->insert($table_availability, [
                'employee_id' => $employee_id,
                'location_id' => intval($entry['location']),
                'weekday'     => intval($entry['weekday']),
                'start_time'  => sanitize_text_field($entry['start']),
                'end_time'    => sanitize_text_field($entry['end']),
            ]);
        }

        echo '<div class="updated"><p>Verfügbarkeiten gespeichert.</p></div>';
    } else {
        error_log('❌ availability_data fehlt im POST!');
    }
}


// Standort laden
$locations = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}buukly_locations ORDER BY name ASC");

// Bestehende Verfügbarkeiten (zum Initialisieren von JS)
$existing = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_availability WHERE employee_id = %d ORDER BY weekday ASC, start_time ASC",
    $employee_id
));
?>

<style>
    .buukly-availability-table td, .buukly-availability-table th {
        padding: 5px;
    }
</style>

<h3>Verfügbarkeiten</h3>
<form id="availability-form" onsubmit="return false;">
    <?php wp_nonce_field('buukly_save_availability_' . $employee_id); ?>
    <input type="hidden" name="employee_id" value="<?php echo esc_attr($employee_id); ?>">
    <input type="hidden" name="availability_data" id="availability_data">

    <table class="form-table">
        <tr>
            <th>Wochentag</th>
            <td>
                <select id="weekday">
                    <?php
                    $days = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
                    foreach ($days as $i => $day) {
                        echo "<option value='" . ($i + 1) . "'>$day</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th>Uhrzeit</th>
            <td>
                <input type="time" id="start_time"> bis <input type="time" id="end_time">
            </td>
        </tr>
        <tr>
            <th>Standort</th>
            <td>
                <select id="location_id">
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= esc_attr($l->id) ?>"><?= esc_html($l->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>

    <p><button type="button" class="button" id="add_availability">+ Hinzufügen</button></p>

    <h4>Gesammelte Verfügbarkeiten:</h4>
    <table class="widefat buukly-availability-table" id="availability_list">
        <thead>
            <tr>
                <th>Tag</th>
                <th>Von</th>
                <th>Bis</th>
                <th>Standort</th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <button type="button" class="button button-primary" id="save_availability">Verfügbarkeiten speichern</button>

</form>

<script>
const availabilityList = document.getElementById('availability_list').querySelector('tbody');
const dataField = document.getElementById('availability_data');
const state = [];

const weekdays = ["Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag", "Sonntag"];
const locations = {
    <?php foreach ($locations as $l): ?>
        <?= $l->id ?>: "<?= esc_js($l->name) ?>",
    <?php endforeach; ?>
};

function renderList() {
    availabilityList.innerHTML = '';
    state.forEach((entry, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${weekdays[entry.weekday - 1]}</td>
            <td>${entry.start}</td>
            <td>${entry.end}</td>
            <td>${locations[entry.location]}</td>
            <td><button type="button" class="button-link delete-entry" data-index="${index}">✖</button></td>
        `;
        availabilityList.appendChild(row);
    });
    dataField.value = JSON.stringify(state);
}

document.getElementById('add_availability').addEventListener('click', () => {
    const weekday = parseInt(document.getElementById('weekday').value);
    const start = document.getElementById('start_time').value;
    const end = document.getElementById('end_time').value;
    const location = parseInt(document.getElementById('location_id').value);

    if (!start || !end) {
        alert('Bitte Start- und Endzeit angeben.');
        return;
    }

    state.push({ weekday, start, end, location });
    renderList();
});

availabilityList.addEventListener('click', function (e) {
    if (e.target.classList.contains('delete-entry')) {
        const index = parseInt(e.target.dataset.index);
        state.splice(index, 1);
        renderList();

        // Automatisch speichern nach Löschen
        document.getElementById('save_availability').click();
    }
});


// Bestehende Verfügbarkeiten einlesen
(function preload() {
    <?php foreach ($existing as $e): ?>
    state.push({
        weekday: <?= $e->weekday ?>,
        start: "<?= esc_js($e->start_time) ?>",
        end: "<?= esc_js($e->end_time) ?>",
        location: <?= $e->location_id ?>
    });
    <?php endforeach; ?>
    renderList();
})();
</script>


<script>
    document.getElementById('save_availability').addEventListener('click', () => {
    // JSON-Daten setzen
    dataField.value = JSON.stringify(state);

    const formData = new FormData(document.getElementById('availability-form'));
    formData.append('action', 'buukly_save_availability');

    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(response => {
        if (response.success) {
            alert('✅ Verfügbarkeiten gespeichert');
        } else {
            alert('❌ Fehler: ' + (response.data || 'Unbekannter Fehler'));
        }
    });
});
</script>
