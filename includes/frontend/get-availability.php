<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$date = sanitize_text_field($_POST['date']);
$location_id = intval($_POST['location_id']);
$employee_id = intval($_POST['employee_id']);

require_once plugin_dir_path(__FILE__) . '/outlook-sync.php';
$slots = buukly_get_available_slots($employee_id, $date);

?>
<div class="buukly-time-slots">
    <h3>Verfügbare Zeiten für <?php echo esc_html(date_i18n('l, d. F Y', strtotime($date))); ?></h3>
    <ul>
        <?php if (empty($slots)): ?>
            <li>Keine freien Zeiten verfügbar.</li>
        <?php else: ?>
            <?php foreach ($slots as $slot): ?>
                <li>
                    <button class="buukly-slot" data-start="<?php echo $slot['start']; ?>" data-end="<?php echo $slot['end']; ?>">
                        <?php echo date('H:i', strtotime($slot['start'])) . ' – ' . date('H:i', strtotime($slot['end'])); ?>
                    </button>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>
