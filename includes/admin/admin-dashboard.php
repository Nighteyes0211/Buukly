<?php
if (!defined('ABSPATH')) exit;

// Bootstrap & Chart.js laden
function buukly_admin_enqueue_dashboard_assets($hook) {
    if ($hook === 'toplevel_page_buukly_dashboard') {
        wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }
}
add_action('admin_enqueue_scripts', 'buukly_admin_enqueue_dashboard_assets');

// Dashboard-Render-Funktion
function buukly_render_dashboard() {
    // Beispiel-Daten (später durch echte Daten aus der DB ersetzen)
    $data = [
        "dates" => ['02.06.', '03.06.', '04.06.', '05.06.', '06.06.', '07.06.', '08.06.'],
        "bookings" => [4, 0, 1, 1, 1, 3, 5],
        "locations" => ['Düsseldorf', 'Köln', 'Berlin'],
        "location_counts" => [8, 5, 6],
        "employees" => ['Max Mustermann', 'Lisa Müller', 'Tom Becker'],
        "employee_counts" => [13, 14, 13]
    ];
    ?>



    
    <div class="wrap">
        <h1 class="mb-4">📊 Buukly Dashboard</h1>

        <div class="row">
            <div class="col-md-4">
                <div class="card text-bg-light mb-3">
                    <div class="card-body">
                        <h5 class="card-title">📅 Buchungen letzte 7 Tage</h5>
                        <p class="card-text fs-1"><?php echo array_sum($data['bookings']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-bg-light mb-3">
                    <div class="card-body">
                        <h5 class="card-title">🏢 Standorte</h5>
                        <p class="card-text fs-1"><?php echo count($data['locations']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-bg-light mb-3">
                    <div class="card-body">
                        <h5 class="card-title">👤 Mitarbeiter</h5>
                        <p class="card-text fs-1"><?php echo count($data['employees']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <canvas id="bookingsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col mb-4">
                <div class="card">
                    <div class="card-body">
                        <canvas id="employeeChart"></canvas>
                    </div>
            </div>
        </div>


        </div>

        


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            new Chart(document.getElementById('bookingsChart'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($data['dates']); ?>,
                    datasets: [{
                        label: 'Buchungen',
                        data: <?php echo json_encode($data['bookings']); ?>,
                        fill: false,
                        tension: 0.3
                    }]
                }
            });

            new Chart(document.getElementById('locationChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($data['locations']); ?>,
                    datasets: [{
                        label: 'Buchungen nach Standort',
                        data: <?php echo json_encode($data['location_counts']); ?>
                    }]
                }
            });

            new Chart(document.getElementById('employeeChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($data['employees']); ?>,
                    datasets: [{
                        label: 'Buchungen nach Mitarbeiter',
                        data: <?php echo json_encode($data['employee_counts']); ?>
                    }]
                }
            });
        });
    </script>
    <?php
}
