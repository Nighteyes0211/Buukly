<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Mitarbeiter-ID sichern
$employee_id = intval($_POST['employee_id'] ?? 0);
if (!$employee_id) {
    wp_die('Fehlende Mitarbeiter-ID.');
}

$table = $wpdb->prefix . 'buukly_employees';
$employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $employee_id));

if (!$employee) {
    wp_die('Mitarbeiter nicht gefunden.');
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    check_admin_referer('buukly_update_employee_' . $employee_id);

    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $ms_user_id = sanitize_text_field($_POST['ms_user_id']);

    $wpdb->update($table, [
        'name' => $name,
        'email' => $email,
        'outlook_user_id' => $ms_user_id
    ], ['id' => $employee_id]);

    echo '<div class="updated"><p>Daten gespeichert.</p></div>';

    // Reload nach Save
    $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $employee_id));
}
?>

<form method="post" id="buukly-general-form">
    <?php wp_nonce_field('buukly_update_employee_' . $employee_id); ?>
    <input type="hidden" name="employee_id" value="<?php echo esc_attr($employee_id); ?>">
    <table class="form-table">
        <tr>
            <th><label for="name">Name</label></th>
            <td><input type="text" name="name" required class="regular-text" value="<?php echo esc_attr($employee->name); ?>"></td>
        </tr>
        <tr>
            <th><label for="email">E-Mail</label></th>
            <td><input type="email" name="email" required class="regular-text" value="<?php echo esc_attr($employee->email); ?>"></td>
        </tr>
        <tr>
            <th><label for="ms_user_id">Microsoft-ID</label></th>
            <td><input type="text" name="ms_user_id" class="regular-text" value="<?php echo esc_attr($employee->outlook_user_id); ?>"></td>
        </tr>
    </table>
    <?php submit_button('Änderungen speichern', 'primary', 'update_employee'); ?>
</form>
