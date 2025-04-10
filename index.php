<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the database connection file
require_once(__DIR__ . '/includes/db_connect.php');

// Initialize variables to store statistics
$total_pupils = 0;
$total_teachers = 0;
$total_parents = 0;
$total_classes = 0;
$pupils_by_class = [];
$enrollment_by_month = [];
$pupils_per_teacher = 0;

// Fetch total counts
$sql_pupils = "SELECT COUNT(*) as count FROM Pupils";
if ($result = $mysqli->query($sql_pupils)) {
    $row = $result->fetch_assoc();
    $total_pupils = $row['count'];
    $result->free();
}

$sql_teachers = "SELECT COUNT(*) as count FROM Teachers";
if ($result = $mysqli->query($sql_teachers)) {
    $row = $result->fetch_assoc();
    $total_teachers = $row['count'];
    $result->free();
}

$sql_parents = "SELECT COUNT(*) as count FROM Parents";
if ($result = $mysqli->query($sql_parents)) {
    $row = $result->fetch_assoc();
    $total_parents = $row['count'];
    $result->free();
}

$sql_classes = "SELECT COUNT(*) as count FROM Classes";
if ($result = $mysqli->query($sql_classes)) {
    $row = $result->fetch_assoc();
    $total_classes = $row['count'];
    $result->free();
}

// Fetch pupils by class data
$sql_pupils_by_class = "SELECT c.class_name, COUNT(p.pupil_id) as pupil_count 
                       FROM Classes c 
                       LEFT JOIN Pupils p ON c.class_id = p.class_id 
                       GROUP BY c.class_id 
                       ORDER BY c.class_name";
if ($result = $mysqli->query($sql_pupils_by_class)) {
    while ($row = $result->fetch_assoc()) {
        $pupils_by_class[$row['class_name']] = $row['pupil_count'];
    }
    $result->free();
}

// Fetch enrollment by month (last 12 months)
$sql_enrollment = "SELECT DATE_FORMAT(enrollment_date, '%Y-%m') as month, 
                   COUNT(*) as count 
                   FROM Pupils 
                   WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
                   GROUP BY month 
                   ORDER BY month";
if ($result = $mysqli->query($sql_enrollment)) {
    while ($row = $result->fetch_assoc()) {
        $enrollment_by_month[$row['month']] = $row['count'];
    }
    $result->free();
}

// Calculate pupils per teacher ratio
if ($total_teachers > 0) {
    $pupils_per_teacher = round($total_pupils / $total_teachers, 1);
}

// Close the database connection
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>St Alphonsus Primary School - Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/chartStyles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <h1>St Alphonsus Primary School - Dashboard</h1>
        
        <div class="main-navigation">
            <a href="classes/view_classes.php" class="nav-item">Classes</a>
            <a href="teachers/view_teachers.php" class="nav-item">Teachers</a>
            <a href="pupil/view_pupils.php" class="nav-item">Pupils</a>
            <a href="parents/view_parents.php" class="nav-item">Parents</a>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <h3>Total Pupils</h3>
                <div class="stat-value"><?php echo $total_pupils; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Teachers</h3>
                <div class="stat-value"><?php echo $total_teachers; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Parents</h3>
                <div class="stat-value"><?php echo $total_parents; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Classes</h3>
                <div class="stat-value"><?php echo $total_classes; ?></div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-card">
                <h3>Pupils per Class</h3>
                <canvas id="pupilsByClassChart"></canvas>
            </div>
            
            <div class="chart-card">
                <h3>Enrollment Trends (Last 12 Months)</h3>
                <canvas id="enrollmentTrendChart"></canvas>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-card">
                <h3>School Statistics</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="stat-card">
                        <h3>Pupils per Teacher</h3>
                        <div class="stat-value"><?php echo $pupils_per_teacher; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Parents per Pupil</h3>
                        <div class="stat-value"><?php echo $total_pupils > 0 ? round($total_parents / $total_pupils, 1) : 0; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>Entity Distribution</h3>
                <canvas id="entityDistributionChart"></canvas>
            </div>
        </div>
    </div>
    
    <script>
//The code is based on Chart.js documentation and examples. The specific implementation was created for the St Alphonsus Primary School dashboard.

// Sources:
// - Chart.js documentation: https://www.chartjs.org/docs/latest/
// - Bar chart example: https://www.chartjs.org/docs/latest/charts/bar.html
// - Line chart example: https://www.chartjs.org/docs/latest/charts/line.html
// - Pie chart example: https://www.chartjs.org/docs/latest/charts/pie.html

// The PHP integration with Chart.js follows standard practices for encoding PHP data to JavaScript using json_encode().
        

// Chart.js initialization
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Pupils by Class Chart
            const pupilsByClassCtx = document.getElementById('pupilsByClassChart').getContext('2d');
            const pupilsByClassChart = new Chart(pupilsByClassCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_keys($pupils_by_class)); ?>,
                    datasets: [{
                        label: 'Number of Pupils',
                        data: <?php echo json_encode(array_values($pupils_by_class)); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Pupils'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Class'
                            }
                        }
                    }
                }
            });
            
            // 2. Enrollment Trend Chart
            const enrollmentTrendCtx = document.getElementById('enrollmentTrendChart').getContext('2d');
            const enrollmentTrendChart = new Chart(enrollmentTrendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_keys($enrollment_by_month)); ?>,
                    datasets: [{
                        label: 'New Enrollments',
                        data: <?php echo json_encode(array_values($enrollment_by_month)); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Enrollments'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    }
                }
            });
            
            // 3. Entity Distribution Chart
            const entityDistributionCtx = document.getElementById('entityDistributionChart').getContext('2d');
            const entityDistributionChart = new Chart(entityDistributionCtx, {
                type: 'pie',
                data: {
                    labels: ['Pupils', 'Teachers', 'Parents', 'Classes'],
                    datasets: [{
                        data: [
                            <?php echo $total_pupils; ?>,
                            <?php echo $total_teachers; ?>,
                            <?php echo $total_parents; ?>,
                            <?php echo $total_classes; ?>
                        ],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
