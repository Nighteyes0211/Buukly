<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// Tabellen definieren
$buukly_locations         = $wpdb->prefix . 'buukly_locations';
$buukly_employees         = $wpdb->prefix . 'buukly_employees';
$buukly_availability      = $wpdb->prefix . 'buukly_employee_availability';
$buukly_calendar          = $wpdb->prefix . 'buukly_calendar';
$buukly_calendar_locations= $wpdb->prefix . 'buukly_calendar_locations';
$buukly_bookings          = $wpdb->prefix . 'buukly_bookings';
$buukly_outlook_events    = $wpdb->prefix . 'buukly_outlook_events';
$buukly_outlook_sync      = $wpdb->prefix . 'buukly_outlook_sync';

// 1. Standorte
dbDelta("CREATE TABLE $buukly_locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    street VARCHAR(255),
    house_number VARCHAR(50),
    zip VARCHAR(20),
    city VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) $charset_collate;");

// 2. Mitarbeiter
dbDelta("CREATE TABLE $buukly_employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    outlook_access_token TEXT,
    outlook_refresh_token TEXT,
    outlook_token_expires DATETIME,
    outlook_user_id VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) $charset_collate;");

// 3. Mitarbeiter-Verfügbarkeiten
dbDelta("CREATE TABLE $buukly_availability (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL
) $charset_collate;");

// 4. Outlook-Ereignisse
dbDelta("CREATE TABLE $buukly_outlook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    outlook_event_id VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    is_private BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event (employee_id, outlook_event_id)
) $charset_collate;");

// 5. Synchronisierungsdaten für Outlook
dbDelta("CREATE TABLE $buukly_outlook_sync (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    ms_email VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expires DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) $charset_collate;");

// 6. Kalender
dbDelta("CREATE TABLE $buukly_calendar (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    year YEAR NOT NULL,
    is_active BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) $charset_collate;");

// 7. Kalender ↔ Standorte
dbDelta("CREATE TABLE $buukly_calendar_locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    calendar_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL
) $charset_collate;");

// 8. Kundenbuchungen (neu und vollständig)
dbDelta("CREATE TABLE $buukly_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    customer_first_name VARCHAR(100),
    customer_last_name VARCHAR(100),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(100),
    customer_message TEXT,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) $charset_collate;");
