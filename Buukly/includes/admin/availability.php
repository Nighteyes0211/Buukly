<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$employees_table   = $wpdb->prefix . 'buukly_employees';
$locations_table   = $wpdb->prefix . 'buukly_locations';
$availability_table = $wpdb->prefix . 'buukly_employee_availability';

$weekdays = [
    1 => 'Montag',
    2 => 'Dienstag',
    3 => 'Mittwoch',
    4 => 'Donnerstag',
    5 => 'Freitag',
    6 => 'Samstag',
    0 => 'Sonntag'
];

// ✅ Speichern
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['buukly_availability_nonce']) &&
    wp_verify_nonce($_POST['buukly_availability_nonce'], 'buukly_save_availability')
) {
    $employee_id = intval($_POST['employee_id']);
    $location_id = intval($_POST['location_id']);

    $wpdb->delete($availability_table, ['employee_id' => $employee_id, 'location_id' => $location_id]);

    if (!empty($_POST['availability'])) {
        foreach ($_POST['availability'] as $weekday => $slot) {
            if (!empty($slot['active']) && !empty($slot['start']) && !empty($slot['end'])) {
                $wpdb->insert($availability_table, [
                    'employee_id' => $employee_id,
                    'location_id' => $location_id,
                    'weekday'     => intval($weekday),
                    'start_time'  => sanitize_text_field($slot['start']),
                    'end_time'    => sanitize_text_field($slot['end']),
                ]);
            }
        }
    }

    echo '<div class="updated"><p>Verfügbarkeiten gespeichert.</p></div>';
}

// 🔁 Daten laden
$employees = $wpdb->get_results("SELECT id, name FROM $employees_table ORDER BY name ASC");
$locations = $wpdb->get_results("SELECT id, name FROM $locations_table ORDER BY name ASC");

$selected_employee = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
$selected_location = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;

$current_availability = [];
if ($selected_employee && $selected_location) {
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $availability_table WHERE employee_id = %d AND location_id = %d",
        $selected_employee,
        $selected_location
    ));
    foreach ($results as $row) {
        $current_availability[$row->weekday] = [
            'start' => $row->start_time,
            'end'   => $row->end_time,
        ];
    }
}
?>

<div class="wrap">
    <h1>Mitarbeiter-Verfügbarkeiten nach Standort</h1>

    <form method="post">
        <?php wp_nonce_field('buukly_save_availability', 'buukly_availability_nonce'); ?>

        <table class="form-table">
            <tr>
                <th><label for="employee_id">Mitarbeiter</label></th>
                <td>
                    <select name="employee_id" onchange="this.form.submit()" required>
                        <option value="">– bitte wählen –</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= esc_attr($e->id) ?>" <?= selected($selected_employee, $e->id, false) ?>>
                                <?= esc_html($e->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="location_id">Standort</label></th>
                <td>
                    <select name="location_id" onchange="this.form.submit()" required>
                        <option value="">– bitte wählen –</option>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= esc_attr($l->id) ?>" <?= selected($selected_location, $l->id, false) ?>>
                                <?= esc_html($l->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <?php if ($selected_employee && $selected_location): ?>
            <h2>Verfügbarkeit festlegen</h2>

            <div class="availability-wrapper">
                <?php foreach ($weekdays as $num => $label): ?>
                    <?php
                        $active = isset($current_availability[$num]);
                        $start  = $current_availability[$num]['start'] ?? '';
                        $end    = $current_availability[$num]['end'] ?? '';
                    ?>
                    <div class="availability-row">
                        <label>
                            <input type="checkbox" name="availability[<?= $num ?>][active]" value="1" <?= $active ? 'checked' : '' ?> onchange="toggleRow(this)">
                            <?= esc_html($label) ?>
                        </label>
                        <input type="time" name="availability[<?= $num ?>][start]" value="<?= esc_attr($start) ?>" <?= $active ? '' : 'disabled' ?>>
                        <input type="time" name="availability[<?= $num ?>][end]" value="<?= esc_attr($end) ?>" <?= $active ? '' : 'disabled' ?>>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php submit_button('Verfügbarkeiten speichern'); ?>
        <?php endif; ?>
    </form>
</div>

<style>
    .availability-wrapper {
        max-width: 700px;
        margin-top: 20px;
    }
    .availability-row {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 6px 0;
    }
    .availability-row label {
        width: 150px;
        font-weight: bold;
    }
    .availability-row input[type="time"] {
        flex: 1;
        padding: 4px;
    }
    .availability-row input[type="checkbox"] {
        transform: scale(1.3);
        margin-right: 8px;
    }
</style>

<script>
function toggleRow(checkbox) {
    const row = checkbox.closest('.availability-row');
    const inputs = row.querySelectorAll('input[type="time"]');
    inputs.forEach(input => input.disabled = !checkbox.checked);
}
</script>
