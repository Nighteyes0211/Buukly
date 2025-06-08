<?php
if (!defined('ABSPATH')) exit;

function buukly_render_outlook_settings() {
    if (!current_user_can('manage_options')) return;

    // Speichern der Einstellungen
    if (
        isset($_POST['buukly_outlook_settings_nonce']) &&
        wp_verify_nonce($_POST['buukly_outlook_settings_nonce'], 'buukly_outlook_settings')
    ) {
        update_option('buukly_client_id', sanitize_text_field($_POST['client_id']));
        update_option('buukly_client_secret', sanitize_text_field($_POST['client_secret']));
        update_option('buukly_tenant_id', sanitize_text_field($_POST['tenant_id']));

        echo '<div class="updated"><p>Outlook-Daten gespeichert.</p></div>';
    }

    // Einstellungen laden
    $client_id     = get_option('buukly_client_id', '');
    $client_secret = get_option('buukly_client_secret', '');
    $tenant_id     = get_option('buukly_tenant_id', '');
    $redirect_uri  = admin_url('admin-post.php?action=buukly_outlook_callback');
    $scopes        = 'offline_access Calendars.Read';

    ?>
    <div class="wrap">
        <h1>Outlook Integration</h1>

        <form method="post">
            <?php wp_nonce_field('buukly_outlook_settings', 'buukly_outlook_settings_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th><label for="client_id">Client ID</label></th>
                    <td><input type="text" name="client_id" class="regular-text" value="<?php echo esc_attr($client_id); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="client_secret">Client Secret</label></th>
                    <td><input type="text" name="client_secret" class="regular-text" value="<?php echo esc_attr($client_secret); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="tenant_id">Tenant ID</label></th>
                    <td><input type="text" name="tenant_id" class="regular-text" value="<?php echo esc_attr($tenant_id); ?>" required></td>
                </tr>
                <tr>
                    <th>Redirect URI</th>
                    <td><code><?php echo esc_html($redirect_uri); ?></code><br><small>Diesen Wert in der Azure App registrieren.</small></td>
                </tr>
            </table>

            <?php submit_button('Speichern'); ?>
        </form>

        <?php
        if ($client_id && $tenant_id) {
            $auth_url = "https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/authorize?" . http_build_query([
                'client_id'     => $client_id,
                'response_type' => 'code',
                'redirect_uri'  => $redirect_uri,
                'response_mode' => 'query',
                'scope'         => $scopes,
                'state'         => wp_create_nonce('buukly_outlook_auth')
            ]);
            ?>
            <hr>
            <h2>Outlook verbinden</h2>
            <p>Um Outlook zu integrieren, melde dich über dein Microsoft-Konto an:</p>
            <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                🔗 Mit Outlook verbinden
            </a>
            <?php
        } else {
            echo '<p><strong>Bitte Client ID und Tenant ID eintragen und speichern, um Outlook zu verbinden.</strong></p>';
        }
        ?>
    </div>
    <?php
}
