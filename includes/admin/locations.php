<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'buukly_locations';

$edit = null;

// Bearbeiten (Formular anzeigen)
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));
}

// Speichern (neu oder bearbeiten)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_location'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'street' => sanitize_text_field($_POST['street']),
        'house_number' => sanitize_text_field($_POST['house_number']),
        'zip' => sanitize_text_field($_POST['zip']),
        'city' => sanitize_text_field($_POST['city']),
    ];

    if ($id > 0) {
        $wpdb->update($table, $data, ['id' => $id]);
        echo '<div class="updated"><p>Standort aktualisiert.</p></div>';
        $edit = null;
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table, $data);
        echo '<div class="updated"><p>Neuer Standort gespeichert.</p></div>';
    }
}


// Standort löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $wpdb->delete($table, ['id' => $delete_id]);
    echo '<div class="updated"><p>Standort gelöscht.</p></div>';
}


// Standorte laden
$locations = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1>Standorte verwalten</h1>

    <h2><?php echo $edit ? 'Standort bearbeiten' : 'Neuen Standort hinzufügen'; ?></h2>
    <form method="post">
        <input type="hidden" name="id" value="<?php echo esc_attr($edit->id ?? ''); ?>">
        <table class="form-table">
            <tr>
                <th><label for="name">Name</label></th>
                <td><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr($edit->name ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="street">Straße</label></th>
                <td><input type="text" name="street" class="regular-text" value="<?php echo esc_attr($edit->street ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="house_number">Hausnummer</label></th>
                <td><input type="text" name="house_number" class="small-text" value="<?php echo esc_attr($edit->house_number ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="zip">PLZ</label></th>
                <td><input type="text" name="zip" class="small-text" value="<?php echo esc_attr($edit->zip ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="city">Ort</label></th>
                <td><input type="text" name="city" class="regular-text" value="<?php echo esc_attr($edit->city ?? ''); ?>"></td>
            </tr>
        </table>
        <?php submit_button($edit ? 'Änderungen speichern' : 'Standort speichern', 'primary', 'save_location'); ?>
    </form>

    <h2>Bestehende Standorte</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Straße</th>
                <th>Nr.</th>
                <th>PLZ</th>
                <th>Ort</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($locations as $l): ?>
                <tr>
                    <td><?= $l->id ?></td>
                    <td><?= esc_html($l->name) ?></td>
                    <td><?= esc_html($l->street) ?></td>
                    <td><?= esc_html($l->house_number) ?></td>
                    <td><?= esc_html($l->zip) ?></td>
                    <td><?= esc_html($l->city) ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=buukly_locations&edit=' . $l->id); ?>">Bearbeiten</a>
                          <a href="<?php echo admin_url('admin.php?page=buukly_locations&delete=' . $l->id); ?>"
       onclick="return confirm('Diesen Standort wirklich löschen?');">
        Löschen
    </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<td>
