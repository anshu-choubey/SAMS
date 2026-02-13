<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$assignments = [];
$teachers = [];
$departments = [];
$subjects = [];

if ($db) {
    try {
        // Get assignments
        $stmt = $db->query("SELECT ta.*, u.full_name, sub.name as subject_name, d.name as dept_name 
                           FROM teacher_assignments ta
                           LEFT JOIN teachers t ON ta.teacher_id = t.id
                           LEFT JOIN users u ON t.user_id = u.id
                           LEFT JOIN subjects sub ON ta.subject_id = sub.id
                           LEFT JOIN departments d ON ta.department_id = d.id
                           ORDER BY u.full_name");
        $assignments = $stmt->fetchAll();
        
        // Get teachers for dropdown
        $stmt = $db->query("SELECT t.id, u.full_name, t.employee_id FROM teachers t LEFT JOIN users u ON t.user_id = u.id ORDER BY u.full_name");
        $teachers = $stmt->fetchAll();
        
        // Get departments for dropdown
        $stmt = $db->query("SELECT id, name FROM departments ORDER BY name");
        $departments = $stmt->fetchAll();
        
        // Get subjects for dropdown
        $stmt = $db->query("SELECT id, name, department_id FROM subjects ORDER BY name");
        $subjects = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$pageTitle = 'Assignments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Assignments - SAMS Admin</title>
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
                <h2><i class="bi bi-person-badge"></i> Teacher Assignments</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                    <i class="bi bi-plus-circle"></i> Add Assignment
                </button>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0"><i class="bi bi-funnel"></i> Filters</h6>
                </div>
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Department</label>
                            <select class="form-select form-select-sm" id="filterDepartment">
                                <option value="">All Departments</option>
                                <?php foreach($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['name']); ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
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

            <!-- Assignments Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Teacher Assignments List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="assignmentsTable">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Subject</th>
                                    <th>Department</th>
                                    <th>Semester</th>
                                    <th>Academic Year</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($assignments) > 0): ?>
                                    <?php foreach ($assignments as $assign): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assign['full_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($assign['subject_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($assign['dept_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($assign['semester'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($assign['academic_year'] ?? '-'); ?></td>
                                            <td>
                                                <?php echo $assign['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editAssignment(<?php echo $assign['id']; ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteAssignment(<?php echo $assign['id']; ?>)" title="Delete"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No assignments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            Showing <span id="showingStart">1</span>-<span id="showingEnd">10</span> of <span id="totalItems"><?php echo count($assignments); ?></span> items
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Assignment Modal -->
    <div class="modal fade" id="addAssignmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-badge"></i> Add Teacher Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addAssignmentForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Teacher *</label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name']) . ' (' . $teacher['employee_id'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject *</label>
                            <select class="form-select" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department *</label>
                            <select class="form-select" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Semester *</label>
                            <input type="text" class="form-control" name="semester" placeholder="e.g., 1st, 2nd" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Academic Year *</label>
                            <input type="text" class="form-control" name="academic_year" placeholder="e.g., 2023-2024" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="saveAssignment()">Save Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Assignment Modal -->
    <div class="modal fade" id="editAssignmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Teacher Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editAssignmentForm">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editAssignmentId">
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <input type="text" class="form-control" id="editTeacherName" readonly disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject *</label>
                            <select class="form-select" name="subject_id" id="editSubjectId" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department *</label>
                            <select class="form-select" name="department_id" id="editDepartmentId" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Semester *</label>
                            <input type="text" class="form-control" name="semester" id="editSemester" placeholder="e.g., 1st, 2nd" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Section</label>
                            <input type="text" class="form-control" name="section" id="editSection" placeholder="e.g., A, B">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Academic Year *</label>
                            <input type="text" class="form-control" name="academic_year" id="editAcademicYear" placeholder="e.g., 2023-2024" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="is_active" id="editIsActive">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="updateAssignment()">Update Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Pagination & Filter Variables
        let currentPage = 1;
        const itemsPerPage = 10;
        let allRows = [];
        let filteredRows = [];

        document.addEventListener('DOMContentLoaded', function() {
            allRows = Array.from(document.querySelectorAll('#assignmentsTable tbody tr'));
            filteredRows = [...allRows];
            renderPage();
        });

        function applyFilters() {
            const dept = document.getElementById('filterDepartment').value;
            const status = document.getElementById('filterStatus').value;
            const search = document.getElementById('searchInput').value.toLowerCase();

            filteredRows = allRows.filter(row => {
                if (row.textContent.includes('No assignments found')) return false;
                
                let show = true;
                
                // Department filter
                if (dept && show) {
                    const deptCell = row.cells[2].textContent.trim();
                    if (deptCell !== dept) show = false;
                }
                
                // Status filter
                if (status !== '' && show) {
                    const statusCell = row.cells[5];
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
            document.getElementById('filterDepartment').value = '';
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

        async function saveAssignment() {
            const form = document.getElementById('addAssignmentForm');
            const formData = new FormData(form);
            const data = {
                teacher_id: formData.get('teacher_id'),
                subject_id: formData.get('subject_id'),
                department_id: formData.get('department_id'),
                semester: formData.get('semester'),
                academic_year: formData.get('academic_year')
            };

            try {
                const response = await fetch('/api/admin/teacher-assignments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', 'Assignment created successfully');
                    bootstrap.Modal.getInstance(document.getElementById('addAssignmentModal')).hide();
                    form.reset();
                    location.reload();
                } else {
                    showAlert('error', 'Error: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                showAlert('error', 'Error: ' + error.message);
            }
        }

        function deleteAssignment(id) {
            if (confirm('Are you sure you want to delete this assignment?')) {
                fetch('/api/admin/teacher-assignments.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                    credentials: 'include'
                }).then(response => response.json())
                  .then(result => {
                      if (result.success) {
                          showAlert('success', 'Assignment deleted successfully');
                          location.reload();
                      } else {
                          showAlert('error', 'Error: ' + (result.message || 'Unknown error'));
                      }
                  });
            }
        }

        async function editAssignment(id) {
            try {
                const response = await fetch(`/api/admin/teacher-assignments.php?id=${id}`, {
                    credentials: 'include'
                });
                const result = await response.json();

                if (result.success && result.data.assignment) {
                    const assign = result.data.assignment;
                    document.getElementById('editAssignmentId').value = assign.id;
                    document.getElementById('editTeacherName').value = assign.full_name || 'Unknown Teacher';
                    document.getElementById('editSubjectId').value = assign.subject_id;
                    document.getElementById('editDepartmentId').value = assign.department_id;
                    document.getElementById('editSemester').value = assign.semester || '';
                    document.getElementById('editSection').value = assign.section || '';
                    document.getElementById('editAcademicYear').value = assign.academic_year || '';
                    document.getElementById('editIsActive').value = assign.is_active ? '1' : '0';

                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editAssignmentModal')).show();
                } else {
                    showAlert('error', 'Failed to load assignment');
                }
            } catch (error) {
                showAlert('error', 'Error: ' + error.message);
            }
        }

        async function updateAssignment() {
            const form = document.getElementById('editAssignmentForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('/api/admin/teacher-assignments.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', 'Assignment updated successfully');
                    bootstrap.Modal.getInstance(document.getElementById('editAssignmentModal')).hide();
                    location.reload();
                } else {
                    showAlert('error', 'Error: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                showAlert('error', 'Error: ' + error.message);
            }
        }
    </script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
