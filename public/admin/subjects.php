<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$subjects = [];
$departments = [];

if ($db) {
    try {
        $stmt = $db->query("SELECT s.*, d.name as department_name
                           FROM subjects s
                           LEFT JOIN departments d ON s.department_id = d.id
                           ORDER BY s.name");
        $subjects = $stmt->fetchAll();
        
        $depts = $db->query("SELECT id, name FROM departments WHERE is_active = true ORDER BY name");
        $departments = $depts->fetchAll();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$pageTitle = 'Subjects';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects - SAMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/js/admin.js"></script>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include '../includes/navbar.php'; ?>

        <!-- Alert Container -->
        <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>

        <!-- Page Content -->
        <div class="content-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Subjects</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="bi bi-plus-circle"></i> Add Subject
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
                            <label class="form-label small mb-1">Semester</label>
                            <select class="form-select form-select-sm" id="filterSemester">
                                <option value="">All</option>
                                <?php for($i=1; $i<=8; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
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
                            <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Name or code...">
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
                                    <th>Subject Name</th>
                                    <th>Code</th>
                                    <th>Department</th>
                                    <th>Semester</th>
                                    <th>Credits</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($subjects)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No subjects found</td>
                                </tr>
                                <?php else: foreach($subjects as $subject): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($subject['code']); ?></span></td>
                                    <td><?php echo htmlspecialchars($subject['department_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($subject['semester'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($subject['credits'] ?? '-'); ?></td>
                                    <td>
                                        <?php echo $subject['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editSubject(<?php echo $subject['id']; ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteSubject(<?php echo $subject['id']; ?>)" title="Delete"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            Showing <span id="showingStart">1</span>-<span id="showingEnd">10</span> of <span id="totalItems"><?php echo count($subjects); ?></span> items
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addSubjectForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Subject Name *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject Code *</label>
                                <input type="text" class="form-control" name="code" required style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department *</label>
                                <select class="form-select" name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Semester *</label>
                                <select class="form-select" name="semester" required>
                                    <option value="">Select Semester</option>
                                    <?php for($i=1; $i<=8; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Credits *</label>
                                <input type="number" class="form-control" name="credits" min="1" max="10" value="3" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Subject description..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveSubject()">
                        <i class="bi bi-save"></i> Save Subject
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div class="modal fade" id="editSubjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editSubjectForm">
                        <input type="hidden" name="id" id="editSubjectId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Subject Name *</label>
                                <input type="text" class="form-control" name="name" id="editSubjectName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject Code *</label>
                                <input type="text" class="form-control" name="code" id="editSubjectCode" required style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department *</label>
                                <select class="form-select" name="department_id" id="editSubjectDepartment" required>
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Semester *</label>
                                <select class="form-select" name="semester" id="editSubjectSemester" required>
                                    <option value="">Select Semester</option>
                                    <?php for($i=1; $i<=8; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Credits *</label>
                                <input type="number" class="form-control" name="credits" id="editSubjectCredits" min="1" max="10" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="is_active" id="editSubjectStatus">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="editSubjectDescription" rows="3" placeholder="Subject description..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateSubject()">
                        <i class="bi bi-save"></i> Update Subject
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script src="../assets/js/admin.js"></script>
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
            const dept = document.getElementById('filterDepartment').value;
            const semester = document.getElementById('filterSemester').value;
            const status = document.getElementById('filterStatus').value;
            const search = document.getElementById('searchInput').value.toLowerCase();

            filteredRows = allRows.filter(row => {
                if (row.textContent.includes('No subjects found')) return false;
                
                let show = true;
                
                // Department filter
                if (dept && show) {
                    const deptCell = row.cells[2].textContent.trim();
                    if (deptCell !== dept) show = false;
                }
                
                // Semester filter
                if (semester && show) {
                    const semCell = row.cells[3].textContent.trim();
                    if (semCell !== semester) show = false;
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
                    const name = row.cells[0].textContent.toLowerCase();
                    const code = row.cells[1].textContent.toLowerCase();
                    if (!name.includes(search) && !code.includes(search)) show = false;
                }
                
                return show;
            });

            currentPage = 1;
            renderPage();
        }

        function resetFilters() {
            document.getElementById('filterDepartment').value = '';
            document.getElementById('filterSemester').value = '';
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

        // Save new subject
        function saveSubject() {
            const form = document.getElementById('addSubjectForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            fetch('/api/admin/subjects.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showSuccessDialog('Success!', 'Subject "' + data.name + '" has been added successfully.', function() {
                        bootstrap.Modal.getInstance(document.getElementById('addSubjectModal')).hide();
                        location.reload();
                    });
                } else {
                    showErrorDialog('Error!', result.message || 'Failed to add subject. Please try again.');
                }
            })
            .catch(err => {
                showErrorDialog('Error!', err.message || 'An error occurred while adding the subject.');
            });
        }

        // Load subject data for editing
        async function editSubject(id) {
            try {
                const response = await fetch(`/api/admin/subjects.php?id=${id}`, {
                    credentials: 'include'
                });

                if (response.status === 401 || response.status === 403) {
                    showErrorDialog('Session Expired', 'Your session has expired. Please login again.');
                    window.location.href = '/login.php';
                    return;
                }

                const result = await response.json();

                if (result.success && result.data && result.data.subject) {
                    const subject = result.data.subject;
                    
                    // Populate form fields
                    document.getElementById('editSubjectId').value = subject.id;
                    document.getElementById('editSubjectName').value = subject.name || '';
                    document.getElementById('editSubjectCode').value = subject.code || '';
                    document.getElementById('editSubjectDepartment').value = subject.department_id || '';
                    document.getElementById('editSubjectSemester').value = subject.semester || '';
                    document.getElementById('editSubjectCredits').value = subject.credits || '3';
                    document.getElementById('editSubjectStatus').value = subject.is_active ? '1' : '0';
                    document.getElementById('editSubjectDescription').value = subject.description || '';

                    // Show modal
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editSubjectModal')).show();
                } else {
                    showErrorDialog('Error!', result.message || 'Failed to load subject details. Please try again.');
                }
            } catch (error) {
                showErrorDialog('Error!', 'An error occurred while loading subject details.');
            }
        }

        // Update subject
        async function updateSubject() {
            const form = document.getElementById('editSubjectForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            const subjectName = document.getElementById('editSubjectName').value;

            try {
                const response = await fetch('/api/admin/subjects.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccessDialog('Updated!', 'Subject "' + subjectName + '" has been updated successfully.', function() {
                        bootstrap.Modal.getInstance(document.getElementById('editSubjectModal')).hide();
                        location.reload();
                    });
                } else {
                    showErrorDialog('Error!', result.message || 'Failed to update the subject.');
                }
            } catch (error) {
                showErrorDialog('Error!', error.message || 'An error occurred while updating the subject.');
            }
        }

        // Delete subject
        function deleteSubject(id) {
            const subjectName = event.target.closest('tr').cells[0].textContent;
            showConfirmDialog(
                'Delete Subject?',
                'Are you sure you want to delete "' + subjectName + '"? This action cannot be undone.',
                function() {
                    fetch('/api/admin/subjects.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id }),
                        credentials: 'include'
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            showSuccessDialog('Deleted!', '"' + subjectName + '" has been deleted successfully.', function() {
                                location.reload();
                            });
                        } else {
                            showErrorDialog('Error!', result.message || 'Failed to delete the subject.');
                        }
                    })
                    .catch(err => {
                        showErrorDialog('Error!', err.message || 'An error occurred while deleting the subject.');
                    });
                }
            );
        }
    </script>
</body>
</html>
