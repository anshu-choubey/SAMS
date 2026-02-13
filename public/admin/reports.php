<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$departments = [];

if ($db) {
    try {
        $stmt = $db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$pageTitle = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - SAMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include '../includes/navbar.php'; ?>

        <!-- Page Content -->
        <div class="content-wrapper">
            <h2 class="mb-4">Reports</h2>
            
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-graph-up text-primary"></i> Attendance Report
                            </h5>
                            <p class="card-text small">View attendance statistics and trends</p>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#attendanceModal">Generate</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-people text-success"></i> Student Report
                            </h5>
                            <p class="card-text small">Student enrollment and performance</p>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#studentModal">Generate</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-person-badge text-warning"></i> Teacher Report
                            </h5>
                            <p class="card-text small">Teacher assignments and schedules</p>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#teacherModal">Generate</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-file-earmark text-danger"></i> System Report
                            </h5>
                            <p class="card-text small">System statistics and audit</p>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#systemModal">Generate</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-check"></i> Recently Generated Reports</h5>
                </div>
                <div class="card-body">
                    <div id="reportList">
                        <p class="text-muted text-center py-4">No reports generated yet. Generate a report using the buttons above.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Report Modal -->
    <div class="modal fade" id="attendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendance Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="attendanceForm">
                        <div class="mb-3">
                            <label class="form-label">Date From *</label>
                            <input type="date" class="form-control" name="date_from" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date To *</label>
                            <input type="date" class="form-control" name="date_to" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="generateAttendanceReport()">
                        <i class="bi bi-download"></i> Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Report Modal -->
    <div class="modal fade" id="studentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="studentForm">
                        <div class="mb-3">
                            <label class="form-label">Department *</label>
                            <select class="form-select" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Semester *</label>
                            <select class="form-select" name="semester" required>
                                <option value="">Select Semester</option>
                                <?php for($i=1;$i<=8;$i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Report Type *</label>
                            <select class="form-select" name="report_type" required>
                                <option value="">Select Type</option>
                                <option value="enrollment">Enrollment Summary</option>
                                <option value="performance">Performance</option>
                                <option value="all">Complete Report</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="generateStudentReport()">
                        <i class="bi bi-download"></i> Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher Report Modal -->
    <div class="modal fade" id="teacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Teacher Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="teacherForm">
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Academic Year *</label>
                            <input type="text" class="form-control" name="academic_year" value="<?php echo date('Y'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Report Type *</label>
                            <select class="form-select" name="report_type" required>
                                <option value="">Select Type</option>
                                <option value="assignments">Assignments</option>
                                <option value="schedules">Schedules</option>
                                <option value="all">Complete Report</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" onclick="generateTeacherReport()">
                        <i class="bi bi-download"></i> Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- System Report Modal -->
    <div class="modal fade" id="systemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">System Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="systemForm">
                        <div class="mb-3">
                            <label class="form-label">Report Type *</label>
                            <select class="form-select" name="report_type" required>
                                <option value="">Select Type</option>
                                <option value="overview">System Overview</option>
                                <option value="users">User Statistics</option>
                                <option value="activity">Activity Log</option>
                                <option value="all">Complete Audit</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date From *</label>
                            <input type="date" class="form-control" name="date_from" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date To *</label>
                            <input type="date" class="form-control" name="date_to" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" onclick="generateSystemReport()">
                        <i class="bi bi-download"></i> Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                if (input.name === 'date_from') {
                    input.value = firstDay.toISOString().split('T')[0];
                } else if (input.name === 'date_to') {
                    input.value = today.toISOString().split('T')[0];
                }
            });
        });

        async function generateAttendanceReport() {
            const form = document.getElementById('attendanceForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            if (!data.date_from || !data.date_to) {
                showAlert('error', 'Please select date range');
                return;
            }
            
            try {
                let url = `/api/admin/reports.php?type=quick_stats&from=${data.date_from}&to=${data.date_to}`;
                if (data.department_id) {
                    url += `&department_id=${data.department_id}`;
                }
                
                const response = await fetch(url, { credentials: 'include' });
                const result = await response.json();
                
                bootstrap.Modal.getInstance(document.getElementById('attendanceModal')).hide();
                
                if (result.success) {
                    const stats = result.data;
                    displayAttendanceReport(stats, data.date_from, data.date_to);
                    addReportToList('Attendance Report', `${data.date_from} to ${data.date_to}`, 'HTML', stats);
                } else {
                    showAlert('error', 'Error: ' + result.message);
                }
            } catch (error) {
                showAlert('error', 'Error generating report: ' + error.message);
            }
        }

        function displayAttendanceReport(stats, fromDate, toDate) {
            const reportList = document.getElementById('reportList');
            
            const reportHTML = `
                <div class="card mb-3 report-item">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-graph-up text-primary"></i> Attendance Report (${fromDate} to ${toDate})</h6>
                        <small class="text-muted">Generated: ${new Date().toLocaleString()}</small>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-primary">${stats.overall_attendance || 0}%</h4>
                                    <small class="text-muted">Overall Attendance</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-success">${stats.students_above_75 || 0}</h4>
                                    <small class="text-muted">Students Above 75%</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-danger">${stats.low_attendance_count || 0}</h4>
                                    <small class="text-muted">Low Attendance</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-info">${stats.avg_face_confidence || 0}%</h4>
                                    <small class="text-muted">Avg Face Confidence</small>
                                </div>
                            </div>
                        </div>
                        ${stats.verification_data ? `
                        <hr>
                        <h6>Verification Stats</h6>
                        <div class="row g-2">
                            <div class="col-6 col-md-3"><span class="badge bg-success">Success: ${stats.verification_data.success || 0}</span></div>
                            <div class="col-6 col-md-3"><span class="badge bg-warning">GPS Failed: ${stats.verification_data.gps_failed || 0}</span></div>
                            <div class="col-6 col-md-3"><span class="badge bg-danger">Face Failed: ${stats.verification_data.face_failed || 0}</span></div>
                            <div class="col-6 col-md-3"><span class="badge bg-dark">Both Failed: ${stats.verification_data.both_failed || 0}</span></div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            if (reportList.querySelector('p.text-muted')) {
                reportList.innerHTML = '';
            }
            reportList.insertAdjacentHTML('afterbegin', reportHTML);
        }

        async function generateStudentReport() {
            const form = document.getElementById('studentForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            if (!data.department_id || !data.semester || !data.report_type) {
                showAlert('error', 'Please fill all required fields');
                return;
            }
            
            try {
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                
                const response = await fetch(`/api/admin/reports.php?type=student&from=${firstDay.toISOString().split('T')[0]}&to=${today.toISOString().split('T')[0]}&department_id=${data.department_id}`, { credentials: 'include' });
                const result = await response.json();
                
                bootstrap.Modal.getInstance(document.getElementById('studentModal')).hide();
                
                if (result.success && result.data.report) {
                    displayStudentReport(result.data.report, data);
                } else {
                    showAlert('error', 'No data found or error: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                showAlert('error', 'Error generating report: ' + error.message);
            }
        }

        function displayStudentReport(report, formData) {
            const reportList = document.getElementById('reportList');
            
            let tableRows = '';
            report.forEach((student, index) => {
                const statusClass = student.percentage >= 75 ? 'success' : (student.percentage >= 50 ? 'warning' : 'danger');
                tableRows += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${student.roll_number}</td>
                        <td>${student.full_name}</td>
                        <td>${student.total_classes || 0}</td>
                        <td>${student.attended || 0}</td>
                        <td><span class="badge bg-${statusClass}">${student.percentage || 0}%</span></td>
                    </tr>
                `;
            });
            
            const reportHTML = `
                <div class="card mb-3 report-item">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-people text-success"></i> Student Report - Semester ${formData.semester}</h6>
                        <small class="text-muted">Generated: ${new Date().toLocaleString()}</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Roll No</th>
                                        <th>Name</th>
                                        <th>Total Classes</th>
                                        <th>Attended</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableRows || '<tr><td colspan="6" class="text-center text-muted">No data found</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">Total Students: ${report.length}</small>
                    </div>
                </div>
            `;
            
            if (reportList.querySelector('p.text-muted')) {
                reportList.innerHTML = '';
            }
            reportList.insertAdjacentHTML('afterbegin', reportHTML);
        }

        async function generateTeacherReport() {
            const form = document.getElementById('teacherForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            if (!data.report_type || !data.academic_year) {
                showAlert('error', 'Please fill all required fields');
                return;
            }
            
            try {
                let url = `/api/admin/teacher-assignments.php?`;
                if (data.department_id) {
                    url += `department_id=${data.department_id}&`;
                }
                
                const response = await fetch(url, { credentials: 'include' });
                const result = await response.json();
                
                bootstrap.Modal.getInstance(document.getElementById('teacherModal')).hide();
                
                if (result.success && result.data.assignments) {
                    displayTeacherReport(result.data.assignments, data);
                } else {
                    showAlert('error', 'No data found');
                }
            } catch (error) {
                showAlert('error', 'Error generating report: ' + error.message);
            }
        }

        function displayTeacherReport(assignments, formData) {
            const reportList = document.getElementById('reportList');
            
            let tableRows = '';
            assignments.forEach((assign, index) => {
                tableRows += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${assign.full_name || '-'}</td>
                        <td>${assign.subject_name || '-'}</td>
                        <td>${assign.dept_name || '-'}</td>
                        <td>${assign.semester || '-'}</td>
                        <td><span class="badge bg-${assign.is_active ? 'success' : 'danger'}">${assign.is_active ? 'Active' : 'Inactive'}</span></td>
                    </tr>
                `;
            });
            
            const reportHTML = `
                <div class="card mb-3 report-item">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-person-badge text-warning"></i> Teacher Report - ${formData.academic_year}</h6>
                        <small class="text-muted">Generated: ${new Date().toLocaleString()}</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Teacher</th>
                                        <th>Subject</th>
                                        <th>Department</th>
                                        <th>Semester</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableRows || '<tr><td colspan="6" class="text-center text-muted">No assignments found</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">Total Assignments: ${assignments.length}</small>
                    </div>
                </div>
            `;
            
            if (reportList.querySelector('p.text-muted')) {
                reportList.innerHTML = '';
            }
            reportList.insertAdjacentHTML('afterbegin', reportHTML);
        }

        async function generateSystemReport() {
            const form = document.getElementById('systemForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            if (!data.report_type || !data.date_from || !data.date_to) {
                showAlert('error', 'Please fill all required fields');
                return;
            }
            
            try {
                // Get counts from various endpoints
                const [usersRes, deptsRes, subjectsRes] = await Promise.all([
                    fetch('/api/admin/users.php', { credentials: 'include' }),
                    fetch('/api/admin/departments.php', { credentials: 'include' }),
                    fetch('/api/admin/subjects.php', { credentials: 'include' })
                ]);
                
                const users = await usersRes.json();
                const depts = await deptsRes.json();
                const subjects = await subjectsRes.json();
                
                bootstrap.Modal.getInstance(document.getElementById('systemModal')).hide();
                
                displaySystemReport({
                    users: users.data?.users?.length || users.data?.total || 0,
                    departments: depts.data?.departments?.length || 0,
                    subjects: subjects.data?.subjects?.length || 0,
                    dateFrom: data.date_from,
                    dateTo: data.date_to,
                    reportType: data.report_type
                });
            } catch (error) {
                showAlert('error', 'Error generating report: ' + error.message);
            }
        }

        function displaySystemReport(stats) {
            const reportList = document.getElementById('reportList');
            
            const reportHTML = `
                <div class="card mb-3 report-item">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-file-earmark text-danger"></i> System Report (${stats.dateFrom} to ${stats.dateTo})</h6>
                        <small class="text-muted">Generated: ${new Date().toLocaleString()}</small>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-primary">${stats.users}</h4>
                                    <small class="text-muted">Total Users</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-success">${stats.departments}</h4>
                                    <small class="text-muted">Departments</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-info">${stats.subjects}</h4>
                                    <small class="text-muted">Subjects</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (reportList.querySelector('p.text-muted')) {
                reportList.innerHTML = '';
            }
            reportList.insertAdjacentHTML('afterbegin', reportHTML);
        }

        function addReportToList(name, date, format, data) {
            // Reports are now displayed inline, this function is kept for compatibility
            console.log('Report generated:', name, date);
        }
    </script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
