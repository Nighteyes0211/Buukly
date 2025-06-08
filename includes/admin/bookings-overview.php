<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$employees_table = $wpdb->prefix . 'buukly_employees';
$bookings_table  = $wpdb->prefix . 'buukly_bookings';

// Filterwerte aus der URL oder Default
$year        = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month       = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

// Zeitraum berechnen
$start_date = "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-01";
$end_date   = date('Y-m-t', strtotime($start_date));

// Alle Mitarbeiter laden
$employees = $wpdb->get_results("SELECT id, name FROM $employees_table ORDER BY name ASC");

// SQL WHERE-Bedingung
$where = "start_time BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
if ($employee_id) {
    $where .= $wpdb->prepare(" AND employee_id = %d", $employee_id);
}

// Buchungen holen
$bookings = $wpdb->get_results("SELECT * FROM $bookings_table WHERE $where ORDER BY start_time ASC");

// Nach Datum gruppieren
$grouped = [];
foreach ($bookings as $b) {
    $day = date('Y-m-d', strtotime($b->start_time));
    $grouped[$day][] = $b;
}

?>

<div class="wrap">
    <h1>📅 Buchungsübersicht</h1>

    <form method="get" action="">
        <input type="hidden" name="page" value="buukly_bookings_overview">
        <table class="form-table">
            <tr>
                <th scope="row">Mitarbeiter:</th>
                <td>
                    <select name="employee_id">
                        <option value="0">– Alle –</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= esc_attr($e->id) ?>" <?= selected($employee_id, $e->id, false) ?>>
                                <?= esc_html($e->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <th scope="row">Monat:</th>
                <td>
                    <select name="month">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= selected($month, $i, false) ?>>
                                <?= date_i18n('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <input type="number" name="year" value="<?= esc_attr($year) ?>" style="width: 80px;">
                    <?php submit_button('Filtern', 'secondary', '', false); ?>
                </td>
            </tr>
        </table>
    </form>

    <h2><?= esc_html(date_i18n('F Y', strtotime($start_date))) ?></h2>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Uhrzeit</th>
                <th>Mitarbeiter</th>
                <th>Kunde</th>
                <th>E-Mail</th>
                <th>Telefon</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr><td colspan="6">Keine Buchungen im gewählten Zeitraum.</td></tr>
            <?php else: ?>
                <?php foreach ($grouped as $date => $entries): ?>
                    <?php foreach ($entries as $b): ?>
                        <tr>
                            <td><?= esc_html(date_i18n('d.m.Y', strtotime($b->start_time))) ?></td>
                            <td><?= esc_html(date_i18n('H:i', strtotime($b->start_time)) . '–' . date_i18n('H:i', strtotime($b->end_time))) ?></td>
                            <td>
                                <?php
                                $emp = array_filter($employees, fn($e) => $e->id == $b->employee_id);
                                echo esc_html($emp ? reset($emp)->name : '–');
                                ?>
                            </td>
                            <td><?= esc_html($b->first_name . ' ' . $b->last_name) ?></td>
                            <td><?= esc_html($b->email) ?></td>
                            <td><?= esc_html($b->phone) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
