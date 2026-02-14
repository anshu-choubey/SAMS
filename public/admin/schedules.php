<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$schedules = [];
$teachers = [];
$days = [];

if ($db) {
    try {
        $stmt = $db->query("SELECT s.*, ta.section as class_name, u.full_name, sub.name as subject_name
                           FROM schedules s
                           LEFT JOIN teacher_assignments ta ON s.assignment_id = ta.id
                           LEFT JOIN teachers t ON ta.teacher_id = t.id
                           LEFT JOIN users u ON t.user_id = u.id
                           LEFT JOIN subjects sub ON ta.subject_id = sub.id
                           ORDER BY s.day_of_week, s.start_time");
        $schedules = $stmt->fetchAll();
        
        // Get teachers for dropdown
        $stmt = $db->query("SELECT t.id, u.full_name, t.employee_id FROM teachers t LEFT JOIN users u ON t.user_id = u.id ORDER BY u.full_name");
        $teachers = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$pageTitle = 'Schedules';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedules - SAMS Admin</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Class Schedules</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="bi bi-calendar-plus"></i> Add Schedule
                </button>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="bi bi-funnel"></i> Filters</h6>
                </div>
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Day</label>
                            <select class="form-select form-select-sm" id="filterDay">
                                <option value="">All Days</option>
                                <?php foreach($days as $day): ?>
                                <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Status</label>
                            <select class="form-select form-select-sm" id="filterStatus">
                                <option value="">All</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Teacher or subject...">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-primary me-1" onclick="applyFilters()"><i class="bi bi-search"></i></button>
                            <button class="btn btn-sm btn-secondary" onclick="resetFilters()"><i class="bi bi-x-circle"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Teacher</th>
                                    <th>Subject</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Classroom</th>
                                    <th>Building</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($schedules)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No schedules found</td>
                                </tr>
                                <?php else: foreach($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['full_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['subject_name'] ?? '-'); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $schedule['day_of_week']; ?></span></td>
                                    <td><?php echo date('H:i', strtotime($schedule['start_time'])) . ' - ' . date('H:i', strtotime($schedule['end_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['classroom'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['building'] ?? '-'); ?></td>
                                    <td>
                                        <?php echo $schedule['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editSchedule(<?php echo $schedule['id']; ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)" title="Delete"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            Showing <span id="showingStart">1</span>-<span id="showingEnd">10</span> of <span id="totalItems"><?php echo count($schedules); ?></span> items
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script>
        // Pagination & Filter Variables
        let currentPage = 1;
        const itemsPerPage = 10;
        let allRows = [];
        let filteredRows = [];

        document.addEventListener('DOMContentLoaded', function() {
            allRows = Array.from(document.querySelectorAll('tbody tr'));
            filteredRows = [...allRows];
            renderPage();
        });

        function applyFilters() {
            const day = document.getElementById('filterDay').value;
            const status = document.getElementById('filterStatus').value;
            const search = document.getElementById('searchInput').value.toLowerCase();

            filteredRows = allRows.filter(row => {
                if (row.textContent.includes('No schedules found')) return false;
                
                let show = true;
                
                // Day filter
                if (day && show) {
                    const dayCell = row.cells[2].textContent.trim();
                    if (dayCell !== day) show = false;
                }
                
                // Status filter
                if (status !== '' && show) {
                    const statusCell = row.cells[6];
                    const isActive = statusCell.textContent.includes('Active') && !statusCell.textContent.includes('Inactive');
                    if (status === '1' && !isActive) show = false;
                    if (status === '0' && isActive) show = false;
                }
                
                // Search filter
                if (search && show) {
                    const teacher = row.cells[0].textContent.toLowerCase();
                    const subject = row.cells[1].textContent.toLowerCase();
                    if (!teacher.includes(search) && !subject.includes(search)) show = false;
                }
                
                return show;
            });

            currentPage = 1;
            renderPage();
        }

        function resetFilters() {
            document.getElementById('filterDay').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('searchInput').value = '';
            filteredRows = [...allRows];
            currentPage = 1;
            renderPage();
        }

        function renderPage() {
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageRows = filteredRows.slice(start, end);

            allRows.forEach(row => row.style.display = 'none');
            pageRows.forEach(row => row.style.display = '');

            document.getElementById('showingStart').textContent = filteredRows.length > 0 ? start + 1 : 0;
            document.getElementById('showingEnd').textContent = Math.min(end, filteredRows.length);
            document.getElementById('totalItems').textContent = filteredRows.length;

            renderPagination();
        }

        function renderPagination() {
            const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';

            if (totalPages <= 1) return;

            pagination.innerHTML += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;">&laquo;</a></li>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    pagination.innerHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a></li>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    pagination.innerHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            pagination.innerHTML += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;">&raquo;</a></li>`;
        }

        function goToPage(page) {
            const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            renderPage();
        }

        function saveSchedule() {
            const form = document.getElementById('addScheduleForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            fetch('/api/admin/schedules.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showAlert('success', 'Schedule created successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('addScheduleModal')).hide();
                    location.reload();
                } else {
                    showAlert('error', 'Error: ' + result.message);
                }
            })
            .catch(err => showAlert('error', 'Error: ' + err.message));
        }

        function editSchedule(id) {
            fetch(`/api/admin/schedules.php?id=${id}`, { credentials: 'include' })
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data.schedule) {
                        const schedule = result.data.schedule;
                        document.getElementById('editScheduleId').value = schedule.id;
                        document.getElementById('editDayOfWeek').value = schedule.day_of_week;
                        document.getElementById('editStartTime').value = schedule.start_time.substring(0, 5);
                        document.getElementById('editEndTime').value = schedule.end_time.substring(0, 5);
                        document.getElementById('editClassroom').value = schedule.classroom || '';
                        document.getElementById('editBuilding').value = schedule.building || '';
                        document.getElementById('editIsActive').value = schedule.is_active ? '1' : '0';
                        
                        // Load teacher and assignment
                        if (schedule.teacher_id) {
                            document.getElementById('editScheduleTeacher').value = schedule.teacher_id;
                            loadEditTeacherAssignments(schedule.teacher_id, schedule.assignment_id);
                        }
                        
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('editScheduleModal')).show();
                    } else {
                        showAlert('error', 'Failed to load schedule');
                    }
                })
                .catch(err => showAlert('error', 'Error: ' + err.message));
        }

        async function loadEditTeacherAssignments(teacherId, selectedAssignmentId = null) {
            const assignmentSelect = document.getElementById('editScheduleAssignment');
            assignmentSelect.innerHTML = '<option value="">Loading...</option>';

            if (!teacherId) {
                assignmentSelect.innerHTML = '<option value="">Select Teacher First</option>';
                return;
            }

            try {
                const response = await fetch(`/api/admin/teacher-assignments.php?teacher_id=${teacherId}`);
                const result = await response.json();

                if (result.success && result.data.assignments.length > 0) {
                    assignmentSelect.innerHTML = '<option value="">Select Assignment</option>';
                    result.data.assignments.forEach(assign => {
                        const option = document.createElement('option');
                        option.value = assign.id;
                        option.textContent = `${assign.subject_name} - ${assign.department_name} (Sem ${assign.semester}, Sec ${assign.section || 'All'})`;
                        if (selectedAssignmentId && assign.id == selectedAssignmentId) {
                            option.selected = true;
                        }
                        assignmentSelect.appendChild(option);
                    });
                } else {
                    assignmentSelect.innerHTML = '<option value="">No assignments found</option>';
                }
            } catch (error) {
                assignmentSelect.innerHTML = '<option value="">Error loading</option>';
            }
        }

        function updateSchedule() {
            const form = document.getElementById('editScheduleForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            fetch('/api/admin/schedules.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showAlert('success', 'Schedule updated successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('editScheduleModal')).hide();
                    location.reload();
                } else {
                    showAlert('error', 'Error: ' + result.message);
                }
            })
            .catch(err => showAlert('error', 'Error: ' + err.message));
        }

        function deleteSchedule(id) {
            if (!confirm('Are you sure?')) return;
            fetch('/api/admin/schedules.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showAlert('success', 'Schedule deleted!');
                    location.reload();
                } else {
                    showAlert('error', 'Error: ' + result.message);
                }
            });
        }
    </script>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Class Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addScheduleForm">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Teacher *</label>
                                <select class="form-select" id="scheduleTeacher" required onchange="loadTeacherAssignments(this.value)">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['full_name']) . ' (' . $teacher['employee_id'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Assignment (Subject + Department) *</label>
                                <select class="form-select" name="assignment_id" id="scheduleAssignment" required>
                                    <option value="">Select Teacher First</option>
                                </select>
                                <small class="text-muted">Select the subject-department combination to schedule</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Day *</label>
                                <select class="form-select" name="day_of_week" required>
                                    <?php foreach ($days as $day): ?>
                                        <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Start Time *</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Time *</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Classroom</label>
                                <input type="text" class="form-control" name="classroom" placeholder="e.g., CSE-101">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Building</label>
                                <input type="text" class="form-control" name="building" placeholder="e.g., Main Block">
                            </div>
                        </div>
                    </form>
                    <div id="conflictWarning" class="alert alert-danger mt-3" style="display: none;">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Schedule Conflict:</strong>
                        <span id="conflictMessage"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveSchedule()">
                        <i class="bi bi-save"></i> Save Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editScheduleForm">
                        <input type="hidden" name="id" id="editScheduleId">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Teacher *</label>
                                <select class="form-select" id="editScheduleTeacher" required onchange="loadEditTeacherAssignments(this.value)">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['full_name']) . ' (' . $teacher['employee_id'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Assignment (Subject + Department) *</label>
                                <select class="form-select" name="assignment_id" id="editScheduleAssignment" required>
                                    <option value="">Select Teacher First</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Day *</label>
                                <select class="form-select" name="day_of_week" id="editDayOfWeek" required>
                                    <?php foreach ($days as $day): ?>
                                        <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Start Time *</label>
                                <input type="time" class="form-control" name="start_time" id="editStartTime" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Time *</label>
                                <input type="time" class="form-control" name="end_time" id="editEndTime" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Classroom</label>
                                <input type="text" class="form-control" name="classroom" id="editClassroom" placeholder="e.g., CSE-101">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Building</label>
                                <input type="text" class="form-control" name="building" id="editBuilding" placeholder="e.g., Main Block">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="is_active" id="editIsActive">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateSchedule()">
                        <i class="bi bi-save"></i> Update Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        const days = <?php echo json_encode($days); ?>;
        const timeSlots = [
            '08:00-09:00', '09:00-10:00', '10:00-11:00', '11:00-12:00',
            '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00'
        ];

        async function loadTeacherAssignments(teacherId) {
            const assignmentSelect = document.getElementById('scheduleAssignment');
            assignmentSelect.innerHTML = '<option value="">Loading...</option>';

            if (!teacherId) {
                assignmentSelect.innerHTML = '<option value="">Select Teacher First</option>';
                return;
            }

            try {
                const response = await fetch(`/api/admin/teacher-assignments.php?teacher_id=${teacherId}`);
                const result = await response.json();

                if (result.success && result.data.assignments.length > 0) {
                    assignmentSelect.innerHTML = '<option value="">Select Assignment</option>';
                    result.data.assignments.forEach(assign => {
                        const option = document.createElement('option');
                        option.value = assign.id;
                        option.textContent = `${assign.subject_name} - ${assign.department_name} (Sem ${assign.semester}, Sec ${assign.section || 'All'})`;
                        assignmentSelect.appendChild(option);
                    });
                } else {
                    assignmentSelect.innerHTML = '<option value="">No assignments found for this teacher</option>';
                }
            } catch (error) {
                assignmentSelect.innerHTML = '<option value="">Error loading assignments</option>';
            }
        }

        async function loadSchedules() {
            const teacherId = document.getElementById('teacherSelect').value;
            const container = document.getElementById('scheduleContainer');

            if (!teacherId) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-week" style="font-size: 3rem; color: #cbd5e1;"></i>
                        <h5 class="mt-3 text-muted">Select a teacher to view their schedule</h5>
                    </div>
                `;
                return;
            }

            try {
                container.innerHTML = '<div class="text-center py-5"><div class="spinner-border"></div></div>';

                const response = await fetch(`/api/admin/schedules.php?teacher_id=${teacherId}`);
                const result = await response.json();

                if (result.success) {
                    displayScheduleGrid(result.data.schedules);
                } else {
                    showAlert('error', result.message);
                }
            } catch (error) {
                showAlert('error', 'Failed to load schedules');
            }
        }

        function displayScheduleGrid(schedules) {
            const container = document.getElementById('scheduleContainer');
            
            // Create grid
            let html = '<div class="table-responsive schedule-grid"><table class="table table-bordered mb-0">';
            html += '<thead><tr><th>Time</th>';
            days.forEach(day => {
                html += `<th class="text-center">${day}</th>`;
            });
            html += '</tr></thead><tbody>';

            timeSlots.forEach(slot => {
                html += `<tr><td class="fw-bold">${slot}</td>`;
                days.forEach(day => {
                    const daySchedules = schedules.filter(s => 
                        s.day_of_week === day && 
                        isTimeInSlot(s.start_time, s.end_time, slot)
                    );
                    
                    html += '<td class="schedule-cell">';
                    daySchedules.forEach(schedule => {
                        html += `
                            <div class="schedule-item" onclick="viewScheduleDetails(${schedule.id})">
                                <div class="time">${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}</div>
                                <div class="subject">${schedule.subject_name}</div>
                                <div class="room"><i class="bi bi-geo-alt"></i> ${schedule.classroom || 'TBA'}</div>
                                <div class="text-muted small">${schedule.department_name}</div>
                            </div>
                        `;
                    });
                    html += '</td>';
                });
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function isTimeInSlot(startTime, endTime, slot) {
            const [slotStart, slotEnd] = slot.split('-');
            return startTime >= slotStart && startTime < slotEnd;
        }

        async function saveSchedule() {
            if (!validateForm('addScheduleForm')) {
                showAlert('error', 'Please fill all required fields');
                return;
            }

            const formData = new FormData(document.getElementById('addScheduleForm'));
            const data = Object.fromEntries(formData.entries());

            console.log('Sending schedule data:', data);

            try {
                showLoading();
                const response = await fetch('/api/admin/schedules.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });

                if (response.status === 401 || response.status === 403) {
                    hideLoading();
                    window.location.href = '/login.php';
                    return;
                }

                let result;
                try {
                    result = await response.json();
                    console.log('API Response:', result);
                } catch (parseError) {
                    console.error('Failed to parse response:', parseError);
                    hideLoading();
                    showAlert('error', 'Invalid server response. Status: ' + response.status);
                    return;
                }

                hideLoading();

                if (result && result.success) {
                    showAlert('success', 'Schedule created successfully');
                    bootstrap.Modal.getInstance(document.getElementById('addScheduleModal')).hide();
                    clearForm('addScheduleForm');
                    document.getElementById('scheduleTeacher').value = '';
                    document.getElementById('scheduleAssignment').innerHTML = '<option value="">Select Teacher First</option>';
                    location.reload();
                } else {
                    const errorMsg = result?.message || 'Failed to create schedule';
                    if (result && result.message && result.message.includes('conflict')) {
                        document.getElementById('conflictWarning').style.display = 'block';
                        document.getElementById('conflictMessage').textContent = result.message;
                    }
                    showAlert('error', errorMsg);
                }
            } catch (error) {
                hideLoading();
                console.error('Error:', error);
                showAlert('error', 'Network error: ' + error.message);
            }
        }

        async function viewScheduleDetails(id) {
            // Implementation for viewing/editing schedule details
            showAlert('info', 'Schedule details view - Feature coming soon');
        }

        function viewAllSchedules() {
            showAlert('info', 'All schedules view - Feature coming soon');
        }

        function printSchedule() {
            window.print();
        }
    </script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
