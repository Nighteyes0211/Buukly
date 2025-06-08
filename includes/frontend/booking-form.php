<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$calendar_table           = $wpdb->prefix . 'buukly_calendar';
$locations_table          = $wpdb->prefix . 'buukly_locations';
$calendar_locations_table = $wpdb->prefix . 'buukly_calendar_locations';

// Kalender aktivieren
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    $wpdb->query("UPDATE $calendar_table SET is_active = 0");
    $wpdb->update($calendar_table, ['is_active' => 1], ['id' => intval($_GET['activate'])]);
    echo '<div class="updated"><p>Kalender wurde aktiviert.</p></div>';
}

// Kalender speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_calendar'])) {
    $wpdb->insert($calendar_table, [
        'name'       => sanitize_text_field($_POST['calendar_name']),
        'year'       => date('Y'),
        'is_active'  => 0,
        'created_at' => current_time('mysql'),
    ]);
    echo '<div class="updated"><p>Neuer Kalender erstellt.</p></div>';
}

// Standorte zuweisen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_locations'])) {
    $calendar_id = intval($_POST['calendar_id']);
    $wpdb->delete($calendar_locations_table, ['calendar_id' => $calendar_id]);

    if (!empty($_POST['location_ids'])) {
        foreach ($_POST['location_ids'] as $loc_id) {
            $wpdb->insert($calendar_locations_table, [
                'calendar_id' => $calendar_id,
                'location_id' => intval($loc_id)
            ]);
        }
    }

    echo '<div class="updated"><p>Standorte zugewiesen.</p></div>';
}

// Daten laden
$calendars = $wpdb->get_results("SELECT * FROM $calendar_table ORDER BY year DESC");
$locations = $wpdb->get_results("SELECT * FROM $locations_table ORDER BY name ASC");
?>

<div class="wrap">
    <h1>Kalenderverwaltung</h1>

    <h2>Neuen Kalender erstellen</h2>
    <form method="post">
        <input type="text" name="calendar_name" required placeholder="z. B. Kalender 2025" class="regular-text">
        <?php submit_button('Erstellen', 'primary', 'create_calendar'); ?>
    </form>

    <h2>Vorhandene Kalender</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Jahr</th>
                <th>Aktiv</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($calendars as $cal): ?>
                <tr>
                    <td><?= esc_html($cal->name) ?></td>
                    <td><?= esc_html($cal->year) ?></td>
                    <td><?= $cal->is_active ? '✅' : '❌' ?></td>
                    <td>
                        <?php if (!$cal->is_active): ?>
                            <a href="<?= admin_url('admin.php?page=buukly_calendar_create&activate=' . $cal->id); ?>">Aktivieren</a>
                        <?php else: ?>
                            <em>Aktiv</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Standorte zu Kalender zuweisen</h2>
    <form method="post">
        <label>Kalender wählen:</label><br>
        <select name="calendar_id" required>
            <option value="">– auswählen –</option>
            <?php foreach ($calendars as $cal): ?>
                <option value="<?= $cal->id ?>"><?= esc_html($cal->name) ?> (<?= $cal->year ?>)</option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Standorte:</label><br>
        <?php foreach ($locations as $l): ?>
            <label><input type="checkbox" name="location_ids[]" value="<?= $l->id ?>"> <?= esc_html($l->name) ?></label><br>
        <?php endforeach; ?>

        <?php submit_button('Standorte zuweisen', 'primary', 'assign_locations'); ?>
    </form>
</div>
