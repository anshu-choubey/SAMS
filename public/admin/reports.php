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
        $stmt = $db->query("SELECT id, name FROM departments WHERE is_active = true ORDER BY name");
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
    <script src="../assets/js/notifications.js"></script>
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

        // Store report data globally for CSV export
        let lastReportData = {
            type: null,
            data: null,
            formData: null
        };

        async function generateAttendanceReport() {
            const form = document.getElementById('attendanceForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            if (!data.date_from || !data.date_to) {
                showWarningDialog('Missing Data', 'Please select date range');
                return;
            }
            
            try {
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
                
                let url = `../api/admin/reports.php?type=quick_stats&from=${encodeURIComponent(data.date_from)}&to=${encodeURIComponent(data.date_to)}`;
                if (data.department_id) {
                    url += `&department_id=${encodeURIComponent(data.department_id)}`;
                }
                
                const response = await fetch(url, { 
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                bootstrap.Modal.getInstance(document.getElementById('attendanceModal')).hide();
                
                if (result.success && result.data) {
                    const stats = result.data;
                    displayAttendanceReport(stats, data.date_from, data.date_to);
                    showSuccessDialog('Success!', 'Attendance report generated successfully.');
                    addReportToList('Attendance Report', `${data.date_from} to ${data.date_to}`, 'HTML', stats);
                } else {
                    showErrorDialog('Error!', result.message || 'Failed to generate report');
                }
            } catch (error) {
                console.error('Report generation error:', error);
                showErrorDialog('Error!', 'Error generating report: ' + error.message);
            } finally {
                const btn = form.parentElement.querySelector('button[onclick="generateAttendanceReport()"]');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-download"></i> Generate Report';
                }
            }
        }

        function displayAttendanceReport(stats, fromDate, toDate) {
            const reportList = document.getElementById('reportList');
            
            const verificationData = stats.verification_data || {
                success: 0,
                gps_failed: 0,
                face_failed: 0,
                both_failed: 0,
                no_data: 0
            };
            
            const reportHTML = `
                <div class="card mb-3 report-item">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-graph-up text-primary"></i> Attendance Report (${fromDate} to ${toDate})</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <small class="text-muted">Generated: ${new Date().toLocaleString()}</small>
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadAttendanceReportCSV()" title="Download as CSV">
                                <i class="bi bi-download"></i> CSV
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-primary">${parseFloat(stats.overall_attendance || 0).toFixed(2)}%</h4>
                                    <small class="text-muted">Overall Attendance</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-success">${parseInt(stats.students_above_75 || 0)}</h4>
                                    <small class="text-muted">Students Above 75%</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-danger">${parseInt(stats.low_attendance_count || 0)}</h4>
                                    <small class="text-muted">Low Attendance</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-info">${parseFloat(stats.avg_face_confidence || 0).toFixed(2)}%</h4>
                                    <small class="text-muted">Avg Face Confidence</small>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <h6>Verification Stats</h6>
                        <div class="row g-2">
                            <div class="col-6 col-md-3"><span class="badge bg-success">Success: ${parseInt(verificationData.success)}</span></div>
                            <div class="col-6 col-md-3"><span class="badge bg-warning">GPS Failed: ${parseInt(verificationData.gps_failed)}</span></div>
                            <div class="col-6 col-md-3"><span class="badge bg-danger">Face Failed: ${parseInt(verificationData.face_failed)}</span></div>
                            <div class="col-6 col-md-3"><span class="badge bg-dark">Both Failed: ${parseInt(verificationData.both_failed)}</span></div>
                        </div>
                        ${stats.trend_data && stats.trend_data.labels && stats.trend_data.labels.length > 0 ? `
                        <hr>
                        <h6>7-Day Trend</h6>
                        <div class="row g-2">
                            ${stats.trend_data.labels.map((label, idx) => `
                                <div class="col-6 col-md-3">
                                    <small class="text-muted">${label}</small><br>
                                    <strong>${parseFloat(stats.trend_data.values[idx] || 0).toFixed(1)}%</strong>
                                </div>
                            `).join('')}
                        </div>
                        ` : '<small class="text-muted d-block mt-2">No trend data available</small>'}
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
                showWarningDialog('Missing Data', 'Please fill all required fields');
                return;
            }
            
            try {
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
                
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                const fromDate = firstDay.toISOString().split('T')[0];
                const toDate = today.toISOString().split('T')[0];
                
                const url = `../api/admin/reports.php?type=student&from=${encodeURIComponent(fromDate)}&to=${encodeURIComponent(toDate)}&department_id=${encodeURIComponent(data.department_id)}`;
                
                const response = await fetch(url, { 
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                bootstrap.Modal.getInstance(document.getElementById('studentModal')).hide();
                
                if (result.success && result.data && result.data.report) {
                    const reportData = Array.isArray(result.data.report) ? result.data.report : [];
                    displayStudentReport(reportData, data);
                    showSuccessDialog('Success!', 'Student report generated successfully.');
                } else {
                    const message = result.message || 'No data found';
                    showErrorDialog('Notice', message);
                }
            } catch (error) {
                console.error('Report generation error:', error);
                showErrorDialog('Error!', 'Error generating report: ' + error.message);
            } finally {
                const btn = form.parentElement.querySelector('button[onclick="generateStudentReport()"]');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-download"></i> Generate Report';
                }
            }
        }

        function displayStudentReport(report, formData) {
            const reportList = document.getElementById('reportList');
            
            if (!report || report.length === 0) {
                showWarningDialog('No Data', 'No student records found for the selected criteria');
                return;
            }
            
            // Store for CSV export
            lastReportData = {
                type: 'student',
                data: report,
                formData: formData
            };
            
            let tableRows = '';
            report.forEach((student, index) => {
                const percentage = parseFloat(student.percentage || 0);
                const statusClass = percentage >= 75 ? 'success' : (percentage >= 50 ? 'warning' : 'danger');
                const attended = parseInt(student.attended || 0);
                const totalClasses = parseInt(student.total_classes || 0);
                
                tableRows += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${student.roll_number || '-'}</td>
                        <td>${student.full_name || '-'}</td>
                        <td>${totalClasses}</td>
                        <td>${attended}</td>
                        <td><span class="badge bg-${statusClass}">${percentage.toFixed(2)}%</span></td>
                    </tr>
                `;
            });
            
            const reportHTML = `
                <div class="card mb-3 report-item">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-people text-success"></i> Student Report - Semester ${formData.semester}</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <small class="text-muted">Generated: ${new Date().toLocaleString()}</small>
                            <button class="btn btn-sm btn-outline-success" onclick="downloadStudentReportCSV()" title="Download as CSV">
                                <i class="bi bi-download"></i> CSV
                            </button>
                        </div>
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
                showWarningDialog('Missing Data', 'Please fill all required fields');
                return;
            }
            
            try {
                const btn = event.target;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
                
                let url = `../api/admin/teacher-assignments.php?`;
                if (data.department_id) {
                    url += `department_id=${encodeURIComponent(data.department_id)}&`;
                }
                
                const response = await fetch(url, { 
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                bootstrap.Modal.getInstance(document.getElementById('teacherModal')).hide();
                
                if (result.success && result.data) {
                    const assignments = Array.isArray(result.data.assignments) ? result.data.assignments : 
                                       Array.isArray(result.data) ? result.data : [];
                    displayTeacherReport(assignments, data);
                    showSuccessDialog('Success!', 'Teacher report generated successfully.');
                } else {
                    showErrorDialog('Notice', result.message || 'No data found');
                }
            } catch (error) {
                console.error('Report generation error:', error);
                showErrorDialog('Error!', 'Error generating report: ' + error.message);
            } finally {
                const btn = form.parentElement.querySelector('button[onclick="generateTeacherReport()"]');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-download"></i> Generate Report';
                }
            }
        }

        function displayTeacherReport(assignments, formData) {
            const reportList = document.getElementById('reportList');
            
            if (!assignments || assignments.length === 0) {
                showWarningDialog('No Data', 'No teacher assignments found for the selected criteria');
                return;
            }
            
            // Store for CSV export
            lastReportData = {
                type: 'teacher',
                data: assignments,
                formData: formData
            };
            
            let tableRows = '';
            assignments.forEach((assign, index) => {
                const fullName = assign.full_name || '-';
                const subjectName = assign.subject_name || '-';
                const deptName = assign.dept_name || '-';
                const semester = assign.semester || '-';
                const isActive = Boolean(assign.is_active);
                
                tableRows += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${fullName}</td>
                        <td>${subjectName}</td>
                        <td>${deptName}</td>
                        <td>${semester}</td>
                        <td><span class="badge bg-${isActive ? 'success' : 'danger'}">${isActive ? 'Active' : 'Inactive'}</span></td>
                    </tr>
                `;
            });
            
            const reportHTML = `
                <div class="card mb-3 report-item">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-person-badge text-warning"></i> Teacher Report - ${formData.academic_year || 'N/A'}</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <small class="text-muted">Generated: ${new Date().toLocaleString()}</small>
                            <button class="btn btn-sm btn-outline-warning" onclick="downloadTeacherReportCSV()" title="Download as CSV">
                                <i class="bi bi-download"></i> CSV
                            </button>
                        </div>
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
            const btn = form.querySelector('button[type="submit"]');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            if (!data.report_type || !data.date_from || !data.date_to) {
                showWarningDialog('Missing Data', 'Please fill all required fields');
                return;
            }
            
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
            
            try {
                // Fetch system statistics from API
                const responses = await Promise.all([
                    fetch('../api/admin/users.php', { credentials: 'include' }),
                    fetch('../api/admin/departments.php', { credentials: 'include' }),
                    fetch('../api/admin/subjects.php', { credentials: 'include' })
                ]);
                
                for (const response of responses) {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                }
                
                const users = await responses[0].json();
                const depts = await responses[1].json();
                const subjects = await responses[2].json();
                
                // Extract data safely
                const userCount = parseInt(users?.data?.total || users?.data?.users?.length || 0);
                const deptCount = parseInt(depts?.data?.departments?.length || depts?.data?.total || 0);
                const subjectCount = parseInt(subjects?.data?.subjects?.length || subjects?.data?.total || 0);
                
                bootstrap.Modal.getInstance(document.getElementById('systemModal')).hide();
                
                displaySystemReport({
                    users: userCount,
                    departments: deptCount,
                    subjects: subjectCount,
                    dateFrom: data.date_from,
                    dateTo: data.date_to,
                    reportType: data.report_type
                });
                
                showSuccessDialog('Success!', 'System report generated successfully.');
            } catch (error) {
                console.error('System report generation error:', error);
                showErrorDialog('Error!', 'Error generating report: ' + (error.message || 'Unknown error'));
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        function displaySystemReport(stats) {
            const reportList = document.getElementById('reportList');
            
            // Ensure values are safe to display
            const users = parseInt(stats?.users || 0);
            const departments = parseInt(stats?.departments || 0);
            const subjects = parseInt(stats?.subjects || 0);
            const dateFrom = stats?.dateFrom || 'N/A';
            const dateTo = stats?.dateTo || 'N/A';
            
            // Store for CSV export
            lastReportData = {
                type: 'system',
                data: stats,
                formData: null
            };
            
            const reportHTML = `
                <div class="card mb-3 report-item">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-file-earmark text-danger"></i> System Report (${dateFrom} to ${dateTo})</h6>
                        <div class="d-flex gap-2 align-items-center">
                            <small class="text-muted">Generated: ${new Date().toLocaleString()}</small>
                            <button class="btn btn-sm btn-outline-danger" onclick="downloadSystemReportCSV()" title="Download as CSV">
                                <i class="bi bi-download"></i> CSV
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-primary">${users}</h4>
                                    <small class="text-muted">Total Users</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-success">${departments}</h4>
                                    <small class="text-muted">Departments</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-1 text-info">${subjects}</h4>
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

        // CSV Export Functions
        function convertToCSV(data, headers) {
            if (!data || data.length === 0) return '';
            
            // Create CSV header
            let csv = headers.map(h => `"${h}"`).join(',') + '\n';
            
            // Add data rows
            data.forEach(row => {
                const values = headers.map(header => {
                    const value = row[header] !== undefined && row[header] !== null ? String(row[header]) : '';
                    // Escape quotes and wrap in quotes
                    return `"${value.replace(/"/g, '""')}"`;
                });
                csv += values.join(',') + '\n';
            });
            
            return csv;
        }

        function downloadCSV(filename, csvContent) {
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function downloadAttendanceReportCSV() {
            if (!lastReportData.data || lastReportData.type !== 'attendance') {
                showWarningDialog('No Data', 'No attendance report data available');
                return;
            }
            
            const stats = lastReportData.data;
            const csvContent = `ATTENDANCE REPORT
Generated: ${new Date().toLocaleString()}

Overall Attendance,${parseFloat(stats.overall_attendance || 0).toFixed(2)}%
Students Above 75%,${parseInt(stats.students_above_75 || 0)}
Low Attendance Count,${parseInt(stats.low_attendance_count || 0)}
Average Face Confidence,${parseFloat(stats.avg_face_confidence || 0).toFixed(2)}%

Verification Stats
Type,Count
Successful,${parseInt(stats.verification_data?.success || 0)}
GPS Failed,${parseInt(stats.verification_data?.gps_failed || 0)}
Face Failed,${parseInt(stats.verification_data?.face_failed || 0)}
Both Failed,${parseInt(stats.verification_data?.both_failed || 0)}
`;
            
            const filename = `Attendance_Report_${new Date().getTime()}.csv`;
            downloadCSV(filename, csvContent);
            showSuccessDialog('Success!', `Report downloaded as ${filename}`);
        }

        function downloadStudentReportCSV() {
            if (!lastReportData.data || lastReportData.type !== 'student' || !Array.isArray(lastReportData.data)) {
                showWarningDialog('No Data', 'No student report data available');
                return;
            }
            
            const students = lastReportData.data;
            const headers = ['Roll Number', 'Full Name', 'Total Classes', 'Attended', 'Percentage'];
            
            const data = students.map(student => ({
                'Roll Number': student.roll_number || '-',
                'Full Name': student.full_name || '-',
                'Total Classes': parseInt(student.total_classes || 0),
                'Attended': parseInt(student.attended || 0),
                'Percentage': parseFloat(student.percentage || 0).toFixed(2) + '%'
            }));
            
            const csvContent = convertToCSV(data, headers);
            const filename = `Student_Report_${new Date().getTime()}.csv`;
            downloadCSV(filename, csvContent);
            showSuccessDialog('Success!', `Report downloaded as ${filename}`);
        }

        function downloadTeacherReportCSV() {
            if (!lastReportData.data || lastReportData.type !== 'teacher' || !Array.isArray(lastReportData.data)) {
                showWarningDialog('No Data', 'No teacher report data available');
                return;
            }
            
            const teachers = lastReportData.data;
            const headers = ['Teacher', 'Subject', 'Department', 'Semester', 'Status'];
            
            const data = teachers.map(teacher => ({
                'Teacher': teacher.full_name || '-',
                'Subject': teacher.subject_name || '-',
                'Department': teacher.dept_name || '-',
                'Semester': teacher.semester || '-',
                'Status': teacher.is_active ? 'Active' : 'Inactive'
            }));
            
            const csvContent = convertToCSV(data, headers);
            const filename = `Teacher_Report_${new Date().getTime()}.csv`;
            downloadCSV(filename, csvContent);
            showSuccessDialog('Success!', `Report downloaded as ${filename}`);
        }

        function downloadSystemReportCSV() {
            if (!lastReportData.data || lastReportData.type !== 'system') {
                showWarningDialog('No Data', 'No system report data available');
                return;
            }
            
            const stats = lastReportData.data;
            const csvContent = `SYSTEM REPORT
Generated: ${new Date().toLocaleString()}
Period: ${stats.dateFrom} to ${stats.dateTo}

Metric,Count
Total Users,${parseInt(stats.users || 0)}
Departments,${parseInt(stats.departments || 0)}
Subjects,${parseInt(stats.subjects || 0)}
`;
            
            const filename = `System_Report_${new Date().getTime()}.csv`;
            downloadCSV(filename, csvContent);
            showSuccessDialog('Success!', `Report downloaded as ${filename}`);
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
