<?php
if (!defined('ABSPATH')) exit;

$timestamp = wp_next_scheduled('buukly_sync_outlook_events');
$intervals = wp_get_schedules();
$hook = 'buukly_sync_outlook_events';
$interval = $intervals['every_ten_minutes']['display'] ?? 'Unbekannt';

?>

<div class="wrap">
    <h1>🕒 Outlook Synchronisation</h1>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>Status</th>
                <th>Wert</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Nächster geplanter Lauf</td>
                <td>
                    <?php 
                        if ($timestamp) {
                            echo date_i18n('d.m.Y H:i:s', $timestamp);
                        } else {
                            echo '❌ Kein Lauf geplant';
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <td>Intervall</td>
                <td><?php echo esc_html($interval); ?></td>
            </tr>
            <tr>
                <td>Hook</td>
                <td><code><?php echo esc_html($hook); ?></code></td>
            </tr>
        </tbody>
    </table>

    <form method="post" class="mt-4">
        <?php wp_nonce_field('buukly_manual_sync'); ?>
        <input type="submit" name="buukly_manual_sync" class="button button-primary" value="🔄 Jetzt synchronisieren">
    </form>
</div>

<?php
if (isset($_POST['buukly_manual_sync']) && check_admin_referer('buukly_manual_sync')) {
    do_action('buukly_sync_outlook_events');
    echo '<div class="updated notice is-dismissible"><p>✅ Manuelle Synchronisation ausgeführt.</p></div>';
}
?>
