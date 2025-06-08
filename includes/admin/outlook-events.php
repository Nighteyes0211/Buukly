<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Alle Mitarbeiter abrufen
$employees = $wpdb->get_results("SELECT id, name, email FROM {$wpdb->prefix}buukly_employees ORDER BY name ASC");

// Wenn ein Mitarbeiter ausgewählt wurde
$selected_employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$events = [];

if ($selected_employee_id > 0) {
    $events = $wpdb->get_results($wpdb->prepare("
        SELECT subject, start_time, end_time, is_private
        FROM {$wpdb->prefix}buukly_outlook_events
        WHERE employee_id = %d
        ORDER BY start_time ASC
    ", $selected_employee_id));
}
?>

<div class="wrap">
    <h1>📅 Outlook-Termine</h1>

    <form method="get" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="buukly_outlook_events">
        <label for="employee_id">Mitarbeiter wählen:</label>
        <select name="employee_id" id="employee_id" onchange="this.form.submit()">
            <option value="">-- bitte wählen --</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp->id ?>" <?= selected($emp->id, $selected_employee_id) ?>>
                    <?= esc_html($emp->name . ' (' . $emp->email . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selected_employee_id && $events): ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Betreff</th>
                    <th>Start</th>
                    <th>Ende</th>
                    <th>Privat?</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $e): ?>
                    <tr>
                        <td><?= esc_html($e->subject) ?></td>
                        <td><?= esc_html(date('d.m.Y H:i', strtotime($e->start_time))) ?></td>
                        <td><?= esc_html(date('d.m.Y H:i', strtotime($e->end_time))) ?></td>
                        <td><?= $e->is_private ? '✅' : '❌' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($selected_employee_id): ?>
        <p>Keine Termine gefunden.</p>
    <?php endif; ?>
</div>
