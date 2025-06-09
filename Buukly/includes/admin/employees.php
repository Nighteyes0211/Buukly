<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$table = $wpdb->prefix . 'buukly_employees';

// ✅ Erfolgsnachricht bei Outlook-Callback
if (isset($_GET['connected']) && $_GET['connected'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>✅ Outlook-Verbindung erfolgreich hergestellt.</p></div>';
}

// ✅ Mitarbeiter speichern (neu)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_employee'])) {
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $ms_user_id = sanitize_text_field($_POST['ms_user_id']);

    // Duplikat prüfen
    $exists = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE email = %s", $email)
    );

    if ($exists > 0) {
        echo '<div class="notice notice-error"><p>Ein Mitarbeiter mit dieser E-Mail-Adresse existiert bereits.</p></div>';
    } else {
        $wpdb->insert($table, [
            'name' => $name,
            'email' => $email,
            'outlook_user_id' => $ms_user_id,
            'created_at' => current_time('mysql')
        ]);
        echo '<div class="updated"><p>Neuer Mitarbeiter hinzugefügt.</p></div>';
    }
}

// ✅ Outlook-Verbindungsstart
if (isset($_POST['outlook_connect_employee'])) {
    $employee_id = intval($_POST['outlook_connect_employee']);

    // OAuth-URL erzeugen
    require_once plugin_dir_path(__FILE__) . '/../oauth/outlook-connect.php';
    $oauth_url = buukly_get_oauth_url($employee_id);

    wp_redirect($oauth_url);
    exit;
}

// ✅ Mitarbeiter löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $wpdb->delete($table, ['id' => intval($_GET['delete'])]);
    echo '<div class="updated"><p>Mitarbeiter gelöscht.</p></div>';
}

// ✅ Alle Mitarbeiter laden
$employees = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
?>

<div class="wrap">
    <h1>Mitarbeiter verwalten</h1>

    <h2>Neuen Mitarbeiter hinzufügen</h2>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="name">Name</label></th>
                <td><input type="text" name="name" required class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="email">E-Mail</label></th>
                <td><input type="email" name="email" required class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="ms_user_id">Microsoft-ID (optional)</label></th>
                <td><input type="text" name="ms_user_id" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button('Mitarbeiter speichern', 'primary', 'save_employee'); ?>
    </form>

    <h2>Alle Mitarbeiter</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>E-Mail</th>
                <th>Outlook</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $e): ?>
                <tr>
                    <td><?php echo esc_html($e->name); ?></td>
                    <td><?php echo esc_html($e->email); ?></td>
                    <td>
                        <?php if (!empty($e->outlook_access_token)): ?>
                            ✅ Verbunden<br>
                            <small><strong>Microsoft ID:</strong> <?php echo esc_html($e->outlook_user_id); ?></small>
                        <?php else: ?>
                            ❌ Nicht verbunden
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <input type="hidden" name="action" value="connect_employee_outlook">
                                <input type="hidden" name="outlook_connect_employee" value="<?= esc_attr($e->id); ?>">
                                <?php submit_button('🔌 Mit Outlook verbinden', 'secondary small', 'connect_outlook', false); ?>
                            </form>

                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=buukly_edit_employee&id=' . $e->id); ?>" class="button">✏️ Bearbeiten</a>
                        <a href="<?php echo admin_url('admin.php?page=buukly_employees&delete=' . $e->id); ?>" onclick="return confirm('Wirklich löschen?');" class="button">🗑 Löschen</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
