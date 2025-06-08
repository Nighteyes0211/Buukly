<?php
if (!defined('ABSPATH')) exit;

function buukly_fetch_outlook_events() {
    global $wpdb;
    $table = $wpdb->prefix . 'buukly_employees';
    $table_events = $wpdb->prefix . 'buukly_outlook_events';

    $employees = $wpdb->get_results("SELECT * FROM $table WHERE outlook_access_token IS NOT NULL AND outlook_user_id IS NOT NULL");

    foreach ($employees as $employee) {
        $now = time();
        $expires = strtotime($employee->outlook_token_expires);

        if ($expires <= $now) {
            $new_token = buukly_refresh_access_token($employee);
            if (!$new_token) {
                error_log("❌ Token-Erneuerung fehlgeschlagen für {$employee->email}");
                continue;
            }
            $token = $new_token;
        } else {
            $token = $employee->outlook_access_token;
        }

        $user_id = $employee->outlook_user_id;
        $start = gmdate('Y-m-d\TH:i:s\Z');
        $end   = gmdate('Y-m-d\TH:i:s\Z', strtotime('+7 days'));

        $url = "https://graph.microsoft.com/v1.0/users/$user_id/calendarView?startDateTime=$start&endDateTime=$end&\$orderby=start/dateTime";

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            error_log("❌ Anfrage-Fehler bei {$employee->email}");
            continue;
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (empty($data['value'])) {
            error_log("⚠️ Keine Termine empfangen für {$employee->email}");
            continue;
        }

        foreach ($data['value'] as $event) {
            $event_id = sanitize_text_field($event['id']);

            // 💡 Zeitstempel aus UTC in Europe/Berlin umwandeln
            $start_dt = new DateTime($event['start']['dateTime'], new DateTimeZone('UTC'));
            $start_dt->setTimezone(new DateTimeZone('Europe/Berlin'));

            $end_dt = new DateTime($event['end']['dateTime'], new DateTimeZone('UTC'));
            $end_dt->setTimezone(new DateTimeZone('Europe/Berlin'));

            $start_time = $start_dt->format('Y-m-d H:i:s');
            $end_time   = $end_dt->format('Y-m-d H:i:s');

            $subject = sanitize_text_field($event['subject'] ?? '(Ohne Betreff)');
            $is_private = ($event['sensitivity'] ?? '') === 'private' ? 1 : 0;

            $wpdb->replace($table_events, [
                'employee_id'      => $employee->id,
                'outlook_event_id' => $event_id,
                'subject'          => $subject,
                'start_time'       => $start_time,
                'end_time'         => $end_time,
                'is_private'       => $is_private,
                'created_at'       => current_time('mysql'),
            ]);
        }
    }
}






function buukly_refresh_access_token($employee) {
    $client_id     = get_option('buukly_client_id');
    $client_secret = get_option('buukly_client_secret');
    $tenant_id     = get_option('buukly_tenant_id');

    $refresh_token = $employee->outlook_refresh_token;

    if (!$refresh_token) return false;

    $response = wp_remote_post("https://login.microsoftonline.com/$tenant_id/oauth2/v2.0/token", [
        'body' => [
            'grant_type'    => 'refresh_token',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'scope'         => 'offline_access Calendars.Read User.Read',
        ]
    ]);

    if (is_wp_error($response)) return false;

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($data['access_token'])) return false;

    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'buukly_employees',
        [
            'outlook_access_token'  => sanitize_text_field($data['access_token']),
            'outlook_refresh_token' => sanitize_text_field($data['refresh_token']),
            'outlook_token_expires' => date('Y-m-d H:i:s', time() + intval($data['expires_in']))
        ],
        ['id' => $employee->id]
    );

    return $data['access_token'];
}

function buukly_get_available_slots($employee_id, $date) {
    global $wpdb;

    // 1. Tag ermitteln (1 = Montag)
    $weekday = (int) date('N', strtotime($date));

    // 2. Verfügbarkeit aus buukly_employee_availability laden
    $availability = $wpdb->get_results($wpdb->prepare(
        "SELECT start_time, end_time FROM {$wpdb->prefix}buukly_employee_availability
         WHERE employee_id = %d AND weekday = %d",
        $employee_id,
        $weekday
    ));

    if (empty($availability)) return []; // keine Arbeitszeit hinterlegt

    // 3. Outlook-Token laden
    $employee = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}buukly_employees WHERE id = %d",
    $employee_id
));

if (!$employee || !$employee->outlook_access_token || !$employee->outlook_user_id) {
    error_log("❌ Kein gültiger Outlook-Token/User für Mitarbeiter $employee_id");
    return [];
}


    // 4. Outlook-Termine für den Tag abfragen
    $start = $date . 'T00:00:00';
    $end   = $date . 'T23:59:59';

    $response = wp_remote_post("https://graph.microsoft.com/v1.0/me/calendar/getSchedule", [
        'headers' => [
            'Authorization' => 'Bearer ' . $employee->outlook_access_token,
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode([
            'schedules' => [$employee->email],
            'startTime' => [
                'dateTime' => $start,
                'timeZone' => 'Europe/Berlin'
            ],
            'endTime' => [
                'dateTime' => $end,
                'timeZone' => 'Europe/Berlin'
            ],
            'availabilityViewInterval' => 15
        ])
    ]);

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $busy = $body['value'][0]['scheduleItems'] ?? [];

    // 5. Für jede DB-Verfügbarkeit Slots berechnen
    $available_slots = [];

    foreach ($availability as $slot) {
        $start_time = strtotime("$date {$slot->start_time}");
        $end_time   = strtotime("$date {$slot->end_time}");

        while ($start_time + 3600 <= $end_time) {
            $slot_start = $start_time;
            $slot_end   = $start_time + 3600;

            $overlap = false;

            foreach ($busy as $event) {
                $busy_start = strtotime($event['start']['dateTime']);
                $busy_end   = strtotime($event['end']['dateTime']);

                if ($slot_start < $busy_end && $slot_end > $busy_start) {
                    $overlap = true;
                    break;
                }
            }

            if (!$overlap) {
                $available_slots[] = [
                    'start' => date('Y-m-d H:i:s', $slot_start),
                    'end'   => date('Y-m-d H:i:s', $slot_end)
                ];
            }

            $start_time += 1800; // 30 Min Schritt
        }
    }

    return $available_slots;
}

