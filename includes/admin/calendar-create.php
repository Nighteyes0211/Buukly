<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$calendar_table = $wpdb->prefix . 'buukly_calendar';
$calendar_locations_table = $wpdb->prefix . 'buukly_calendar_locations';
$locations_table = $wpdb->prefix . 'buukly_locations';

$show_redirect = false;
$edit_calendar = null;
$selected_locations = [];

// Kalender löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && isset($_GET['_wpnonce'])) {
    $calendar_id = intval($_GET['delete']);

    if (wp_verify_nonce($_GET['_wpnonce'], 'buukly_delete_calendar_' . $calendar_id)) {
        $wpdb->delete($calendar_locations_table, ['calendar_id' => $calendar_id]);
        $wpdb->delete($calendar_table, ['id' => $calendar_id]);
        echo '<div class="updated"><p>Kalender gelöscht.</p></div>';
    } else {
        echo '<div class="error"><p>Sicherheitsprüfung fehlgeschlagen.</p></div>';
    }
}

// Kalender bearbeiten
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $calendar_id = intval($_GET['edit']);
    $edit_calendar = $wpdb->get_row("SELECT * FROM $calendar_table WHERE id = $calendar_id");

    $selected_locations = $wpdb->get_col(
        $wpdb->prepare("SELECT location_id FROM $calendar_locations_table WHERE calendar_id = %d", $calendar_id)
    );
}

// Kalender speichern
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['buukly_calendar_nonce']) &&
    wp_verify_nonce($_POST['buukly_calendar_nonce'], 'buukly_save_calendar')
) {
    $name = sanitize_text_field($_POST['calendar_name']);
    $year = date('Y');
    $locations = isset($_POST['locations']) ? array_map('intval', $_POST['locations']) : [];

    if (!empty($_POST['calendar_id'])) {
        $calendar_id = intval($_POST['calendar_id']);

        $wpdb->update($calendar_table, [
            'name' => $name,
            'year' => $year
        ], ['id' => $calendar_id]);

        $wpdb->delete($calendar_locations_table, ['calendar_id' => $calendar_id]);

        foreach ($locations as $location_id) {
            $wpdb->insert($calendar_locations_table, [
                'calendar_id' => $calendar_id,
                'location_id' => $location_id,
            ]);
        }

    } else {
        $wpdb->insert($calendar_table, [
            'name' => $name,
            'year' => $year,
            'is_active' => 1,
            'created_at' => current_time('mysql'),
        ]);

        $calendar_id = $wpdb->insert_id;

        foreach ($locations as $location_id) {
            $wpdb->insert($calendar_locations_table, [
                'calendar_id' => $calendar_id,
                'location_id' => $location_id,
            ]);
        }
    }

    $show_redirect = true;
}

$locations = $wpdb->get_results("SELECT id, name FROM $locations_table ORDER BY name ASC");
$calendars = $wpdb->get_results("SELECT * FROM $calendar_table ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1><?php echo $edit_calendar ? 'Kalender bearbeiten' : 'Kalender erstellen'; ?></h1>

    <?php if ($show_redirect): ?>
        <script>window.location.href = "<?php echo admin_url('admin.php?page=buukly_calendar_create'); ?>";</script>
        <div class="updated"><p>Kalender gespeichert.</p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('buukly_save_calendar', 'buukly_calendar_nonce'); ?>
        <input type="hidden" name="calendar_id" value="<?php echo esc_attr($edit_calendar->id ?? ''); ?>">

        <table class="form-table">
            <tr>
                <th><label for="calendar_name">Kalendername</label></th>
                <td><input type="text" name="calendar_name" class="regular-text" required value="<?php echo esc_attr($edit_calendar->name ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="locations">Zugehörige Standorte</label></th>
                <td>
                    <?php foreach ($locations as $l): ?>
                        <label>
                            <input type="checkbox" name="locations[]" value="<?= $l->id ?>" <?php checked(in_array($l->id, $selected_locations)); ?>>
                            <?= esc_html($l->name) ?>
                        </label><br>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>

        <?php submit_button($edit_calendar ? 'Änderungen speichern' : 'Erstellen'); ?>
    </form>

    <?php if (!empty($calendars)): ?>
        <h2>Bestehende Kalender</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Jahr</th>
                    <th>Shortcode</th>
                    <th>Aktionen</th>
                    <th>Erstellt am</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($calendars as $cal): ?>
                    <tr>
                        <td><?= esc_html($cal->name) ?></td>
                        <td><?= esc_html($cal->year) ?></td>
                        <td>
                            <code id="shortcode-<?= $cal->id ?>">[buukly_calendar id="<?= $cal->id ?>"]</code>
                            <button class="button" onclick="navigator.clipboard.writeText('[buukly_calendar id=<?= $cal->id ?>]'); return false;">📋 Kopieren</button>
                        </td>
                        <td>
                            <a href="<?= admin_url('admin.php?page=buukly_calendar_create&edit=' . $cal->id); ?>">Bearbeiten</a> |
                            <a href="<?= wp_nonce_url(admin_url('admin.php?page=buukly_calendar_create&delete=' . $cal->id), 'buukly_delete_calendar_' . $cal->id); ?>" onclick="return confirm('Kalender wirklich löschen?');">Löschen</a>
                        </td>
                        <td><?= esc_html($cal->created_at) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
