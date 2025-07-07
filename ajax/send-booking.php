<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_buukly_send_booking', 'buukly_send_booking');
add_action('wp_ajax_nopriv_buukly_send_booking', 'buukly_send_booking');

function buukly_send_booking() {
    check_ajax_referer('buukly_nonce');
    
    $is_existing_client = isset($_POST['is_existing_client']) ? filter_var($_POST['is_existing_client'], FILTER_VALIDATE_BOOLEAN) : false;
    
    error_log('is_existing_client = ' . var_export($_POST['is_existing_client'], true));

    if (empty($_POST['accept_privacy']) || (!$is_existing_client && empty($_POST['accept_mandate']))) {
        wp_send_json_error('Bitte bestätigen Sie die rechtlichen Hinweise und die Datenschutzerklärung.');
    }

    $required_fields = ['employee_id', 'location_id', 'date', 'start_time', 'end_time', 'first_name', 'last_name', 'email'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error("Feld '$field' fehlt.");
        }
    }

    $employee_id = intval($_POST['employee_id']);
    $location_id = intval($_POST['location_id']);
    $date        = sanitize_text_field($_POST['date']);
    $start_time  = sanitize_text_field($_POST['start_time']);
    $end_time    = sanitize_text_field($_POST['end_time']);
    $first_name  = sanitize_text_field($_POST['first_name']);
    $last_name   = sanitize_text_field($_POST['last_name']);
    $email       = sanitize_email($_POST['email']);
    $phone       = sanitize_text_field($_POST['phone'] ?? '');
    $message     = sanitize_textarea_field($_POST['message'] ?? '');
    $aktenzeichen = sanitize_text_field($_POST['aktenzeichen'] ?? '');
    $accept_mandate = !empty($_POST['accept_mandate']) ? 'Ja' : 'Nein';
    $accept_privacy = !empty($_POST['accept_privacy']) ? 'Ja' : 'Nein';



    

    global $wpdb;

    $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}buukly_employees WHERE id = %d", $employee_id));
    $location = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}buukly_locations WHERE id = %d", $location_id));

    if (!$employee || !$location) {
        wp_send_json_error('Mitarbeiter oder Standort nicht gefunden.');
    }

    $kanzlei_email = get_option('buukly_admin_email');

    $subject = "📅 Neue Buchung am {$date} um " . date('H:i', strtotime($start_time));
    $body = "
        <h2>Neue Terminbuchung</h2>
        <p><strong>Datum:</strong> {$date}</p>
        <p><strong>Uhrzeit:</strong> " . date('H:i', strtotime($start_time)) . " – " . date('H:i', strtotime($end_time)) . "</p>
        <p><strong>Mitarbeiter:</strong> {$employee->name} ({$employee->email})</p>
        <p><strong>Standort:</strong> {$location->name}</p>
        <hr>
        <p><strong>Name:</strong> {$first_name} {$last_name}</p>
        <p><strong>E-Mail:</strong> {$email}</p>
        <p><strong>Telefon:</strong> {$phone}</p>
        <p><strong>Aktenzeichen:</strong> {$aktenzeichen}</p>
        <p><strong>Zustimmungen:</strong></p>
            <ul>
              <li>Mandatsunterlagen: {$accept_mandate}</li>
              <li>Datenschutzerklärung: {$accept_privacy}</li>
            </ul>
        <p><strong>Nachricht:</strong><br>" . nl2br($message) . "</p>
    ";

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // Mails senden
    $recipients = [$employee->email];
    if (!empty($kanzlei_email)) $recipients[] = $kanzlei_email;
    foreach ($recipients as $to) {
        wp_mail($to, $subject, $body, $headers);
    }

    // Mail an Kunden
    $client_subject = "📅 Ihre Buchung am {$date}";
    $client_body = "
        <h2>Vielen Dank für Ihre Buchung!</h2>
        <p>Hier Ihre Terminübersicht:</p>
        <ul>
            <li><strong>Datum:</strong> {$date}</li>
            <li><strong>Uhrzeit:</strong> " . date('H:i', strtotime($start_time)) . " – " . date('H:i', strtotime($end_time)) . "</li>
            <li><strong>Mitarbeiter:</strong> {$employee->name}</li>
            <li><strong>Standort:</strong> {$location->name}</li>
                <p>Bitte beachten Sie, dass die Kosten einer Erstberatung sich auf <b>249,90€</b> belaufen. Bei bereits bestehendem Mandat, gilt die bereits unterzeichnete Mandats-/ Vergütungsvereinbarung.</p>
            <p><strong>Zustimmungen:</strong></p>
            <ul>
              <li>Mandatsunterlagen: {$accept_mandate}</li>
              <li>Datenschutzerklärung: {$accept_privacy}</li>
            </ul>
        </ul>
        <p><strong>Aktenzeichen:</strong> {$aktenzeichen}</p>
        <p>Wir melden uns bei Ihnen, falls Rückfragen bestehen.</p>
    ";
    wp_mail($email, $client_subject, $client_body, $headers);

    // 🗓️ Outlook-Termin erstellen
    if (!empty($employee->outlook_access_token) && !empty($employee->outlook_user_id)) {
        // Falls Uhrzeit ohne Datum übergeben wurde, ergänzen
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) {
            $start_time = "{$date} {$start_time}";
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) {
            $end_time = "{$date} {$end_time}";
        }

        // Zeitobjekte erzeugen
        $start_datetime = new DateTime($start_time, new DateTimeZone('Europe/Berlin'));
        $end_datetime   = new DateTime($end_time, new DateTimeZone('Europe/Berlin'));

        $graph_url = "https://graph.microsoft.com/v1.0/users/{$employee->outlook_user_id}/events";
        $event_data = [
            'subject' => "🗓️ Termin mit {$first_name} {$last_name}",
            'body' => [
                'contentType' => 'HTML',
                'content' => "
                    <p>Ein neuer Termin wurde gebucht.</p>
                    <p><strong>Name:</strong> {$first_name} {$last_name}<br>
                    <strong>E-Mail:</strong> {$email}<br>
                    <strong>Telefon:</strong> {$phone}<br>
                    <strong>Nachricht:</strong><br>" . nl2br($message) . "</p>"
            ],
            'start' => [
                'dateTime' => $start_datetime->format('Y-m-d\TH:i:s'),
                'timeZone' => 'Europe/Berlin'
            ],
            'end' => [
                'dateTime' => $end_datetime->format('Y-m-d\TH:i:s'),
                'timeZone' => 'Europe/Berlin'
            ],
            'location' => [
                'displayName' => $location->name
            ],
            'attendees' => [[
                'emailAddress' => [
                    'address' => $employee->email,
                    'name' => $employee->name
                ],
                'type' => 'required'
            ]],
            'showAs' => 'busy',
            'sensitivity' => 'private'
        ];


        $response = wp_remote_post($graph_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $employee->outlook_access_token,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode($event_data)
        ]);

        $response_body = wp_remote_retrieve_body($response);
        error_log("📆 Kalendereintrag-Ergebnis: $response_body");

        if (is_wp_error($response)) {
            error_log("❌ Fehler beim Erstellen des Kalendereintrags");
        }
    }

        // ❗ Verfügbarkeitsprüfung pro Standort (nur 1 Raum!)
        $overlap_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}buukly_bookings
             WHERE location_id = %d
             AND date = %s
             AND (
                 (start_time < %s AND end_time > %s) -- Überschneidung
             )",
            $location_id,
            $date,
            $end_time,
            $start_time
        ));

        if ($overlap_exists > 0) {
            wp_send_json_error('Dieser Zeitraum ist am gewählten Standort bereits belegt.');
        }



    // 📝 In Datenbank speichern
    $wpdb->insert(
        $wpdb->prefix . 'buukly_bookings',
        [
            'employee_id' => $employee_id,
            'location_id' => $location_id,
            'date'        => $date,
            'start_time'  => $start_time,
            'end_time'    => $end_time,
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'email'       => $email,
            'phone'       => $phone,
            'message'     => $message,
            'created_at'  => current_time('mysql', 1)


        ],
        ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    // 🔄 Outlook-Sync direkt nach Buchung
    if (function_exists('buukly_sync_employee_outlook_events')) {
        buukly_sync_employee_outlook_events($employee_id);
    }

    wp_send_json_success('Buchung erfolgreich übermittelt.');
}
