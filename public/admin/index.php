<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get dashboard statistics - safely query with error handling
$totalStudents = 0;
$totalTeachers = 0;
$totalDepartments = 0;
$todayPresent = 0;
$todayAbsent = 0;
$weeklyAttendance = [];
$departmentStats = [];

if ($db) {
    try {
        // Total counts
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role='student'");
        $totalStudents = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role='teacher'");
        $totalTeachers = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM departments WHERE is_active = 1");
        $totalDepartments = $stmt->fetch()['count'] ?? 0;
        
        // Today's attendance breakdown
        $stmt = $db->query("SELECT 
            SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent
            FROM attendance WHERE attendance_date = CURDATE()");
        $today = $stmt->fetch();
        $todayPresent = $today['present'] ?? 0;
        $todayAbsent = $today['absent'] ?? 0;
        
        // Weekly attendance (last 7 days)
        $stmt = $db->query("SELECT 
            DATE_FORMAT(attendance_date, '%a') as day,
            attendance_date,
            SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent
            FROM attendance 
            WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY attendance_date
            ORDER BY attendance_date ASC");
        $weeklyAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Department-wise attendance
        $stmt = $db->query("SELECT d.name, d.code,
            COUNT(DISTINCT a.student_id) as students,
            SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) as present
            FROM departments d
            LEFT JOIN users u ON u.department_id = d.id AND u.role = 'student'
            LEFT JOIN attendance a ON a.student_id = u.id AND a.attendance_date = CURDATE()
            WHERE d.is_active = 1
            GROUP BY d.id
            ORDER BY d.name");
        $departmentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Dashboard error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMS - Admin Dashboard</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include '../includes/navbar.php'; ?>

        <!-- Dashboard Content -->
        <div class="content-wrapper">
            <h2 class="mb-4">Dashboard</h2>
            
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card stat-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle mb-2">Total Students</h6>
                                    <h2 class="card-title"><?php echo $totalStudents; ?></h2>
                                </div>
                                <div>
                                    <i class="bi bi-people fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card stat-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle mb-2">Total Teachers</h6>
                                    <h2 class="card-title"><?php echo $totalTeachers; ?></h2>
                                </div>
                                <div>
                                    <i class="bi bi-person-badge fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card stat-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle mb-2">Departments</h6>
                                    <h2 class="card-title"><?php echo $totalDepartments; ?></h2>
                                </div>
                                <div>
                                    <i class="bi bi-building fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card stat-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle mb-2">Today's Attendance</h6>
                                    <h2 class="card-title"><?php echo $todayStats['students_present_today'] ?? 0; ?></h2>
                                </div>
                                <div>
                                    <i class="bi bi-calendar-check fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row g-3 mb-3">
                <div class="col-lg-12">
                    <div class="card chart-card-sidebar">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Weekly Attendance</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="weeklyChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Stats & Quick Actions -->
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card chart-card-sidebar">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Department-wise Attendance</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="departmentChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card chart-card-sidebar">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-3">
                                <a href="users.php" class="btn btn-outline-primary">
                                    <i class="bi bi-person-plus me-2"></i>Add New User
                                </a>
                                <a href="schedules.php" class="btn btn-outline-success">
                                    <i class="bi bi-calendar-plus me-2"></i>Create Schedule
                                </a>
                                <a href="reports.php" class="btn btn-outline-info">
                                    <i class="bi bi-file-earmark-text me-2"></i>Generate Report
                                </a>
                                <a href="departments.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-building me-2"></i>Manage Departments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="../assets/js/admin.js"></script>
    
    <script>
        // Chart data from PHP
        const weeklyData = <?php echo json_encode($weeklyAttendance); ?>;
        const deptData = <?php echo json_encode($departmentStats); ?>;

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Weekly Attendance Chart
            const weeklyLabels = weeklyData.length > 0 
                ? weeklyData.map(d => d.day) 
                : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const weeklyPresent = weeklyData.length > 0 
                ? weeklyData.map(d => parseInt(d.present)) 
                : [0, 0, 0, 0, 0, 0, 0];
            const weeklyAbsent = weeklyData.length > 0 
                ? weeklyData.map(d => parseInt(d.absent)) 
                : [0, 0, 0, 0, 0, 0, 0];

            const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
            new Chart(weeklyCtx, {
                type: 'line',
                data: {
                    labels: weeklyLabels,
                    datasets: [{
                        label: 'Present',
                        data: weeklyPresent,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Absent',
                        data: weeklyAbsent,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            // Department Chart
            const deptLabels = deptData.length > 0 
                ? deptData.map(d => d.code || d.name.substring(0, 5)) 
                : ['No Data'];
            const deptValues = deptData.length > 0 
                ? deptData.map(d => parseInt(d.present) || 0) 
                : [0];

            const deptCtx = document.getElementById('departmentChart').getContext('2d');
            new Chart(deptCtx, {
                type: 'bar',
                data: {
                    labels: deptLabels,
                    datasets: [{
                        label: 'Present Today',
                        data: deptValues,
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(6, 182, 212, 0.8)'
                        ],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        });

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                fetch('/api/public/logout.php', {
                    method: 'POST'
                })
                .then(() => {
                    window.location.href = '../login.php';
                });
            }
        }
    </script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
