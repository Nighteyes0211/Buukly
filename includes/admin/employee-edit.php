<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Mitarbeiter-ID aus URL
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$employee_id) {
    wp_die('Mitarbeiter-ID fehlt.');
}

// Mitarbeiter laden
$table = $wpdb->prefix . 'buukly_employees';
$employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $employee_id));

if (!$employee) {
    wp_die('Mitarbeiter nicht gefunden.');
}
?>

<div class="wrap">
    <script>
    var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
</script>
    <h1>Mitarbeiter bearbeiten: <?php echo esc_html($employee->name); ?></h1>

        <p><a href="<?php echo admin_url('admin.php?page=buukly_employees'); ?>">← Zurück zur Übersicht</a></p>


    <h2 class="nav-tab-wrapper" id="buukly-tabs">
        <a href="#" class="nav-tab nav-tab-active" data-tab="general">Allgemein</a>
        <a href="#" class="nav-tab" data-tab="availability">Verfügbarkeiten</a>
    </h2>

    <div id="buukly-tab-content">
        <p>Lade Inhalt...</p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function loadTab(tab) {
        $('#buukly-tab-content').html('<p>Lade...</p>');
        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');

        $.post(ajaxurl, {
            action: 'buukly_load_tab',
            tab: tab,
            employee_id: <?php echo $employee_id; ?>,
            _ajax_nonce: '<?php echo wp_create_nonce('buukly_tab_nonce'); ?>'
        }, function(response) {
            $('#buukly-tab-content').html(response);
        });
    }

    loadTab('general');

    $('#buukly-tabs').on('click', '.nav-tab', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        loadTab(tab);
    });
});
</script>
