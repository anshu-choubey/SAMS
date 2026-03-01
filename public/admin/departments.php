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
        $stmt = $db->query("SELECT * FROM departments ORDER BY name");
        $departments = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$pageTitle = 'Departments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - SAMS Admin</title>
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
                <h2>Departments</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="bi bi-plus-circle"></i> Add Department
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
                            <label class="form-label small mb-1">Status</label>
                            <select class="form-select form-select-sm" id="filterStatus">
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search name or code...">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-sm btn-primary me-1" onclick="applyFilters()"><i class="bi bi-search"></i> Filter</button>
                            <button class="btn btn-sm btn-secondary" onclick="resetFilters()"><i class="bi bi-x-circle"></i> Reset</button>
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
                                    <th>Department Name</th>
                                    <th>Code</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($departments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No departments found</td>
                                </tr>
                                <?php else: foreach($departments as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($dept['code']); ?></span></td>
                                    <td><?php echo htmlspecialchars($dept['description'] ?? '-'); ?></td>
                                    <td>
                                        <?php echo $dept['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editDept(<?php echo $dept['id']; ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteDept(<?php echo $dept['id']; ?>)" title="Delete"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            Showing <span id="showingStart">1</span>-<span id="showingEnd">10</span> of <span id="totalItems"><?php echo count($departments); ?></span> items
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addDeptForm">
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Code *</label>
                            <input type="text" class="form-control" name="code" required style="text-transform: uppercase;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveDept()">
                        <i class="bi bi-save"></i> Save Department
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editDeptForm">
                        <input type="hidden" name="id" id="editDeptId">
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" class="form-control" name="name" id="editDeptName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Code *</label>
                            <input type="text" class="form-control" name="code" id="editDeptCode" required style="text-transform: uppercase;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editDeptDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="is_active" id="editDeptStatus">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateDept()">
                        <i class="bi bi-save"></i> Update Department
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
            const status = document.getElementById('filterStatus').value;
            const search = document.getElementById('searchInput').value.toLowerCase();

            filteredRows = allRows.filter(row => {
                if (row.textContent.includes('No departments found')) return false;
                
                let show = true;
                
                // Status filter
                if (status !== '') {
                    const statusCell = row.cells[3];
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

            // Previous
            pagination.innerHTML += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;">&laquo;</a></li>`;

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    pagination.innerHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a></li>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    pagination.innerHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            // Next
            pagination.innerHTML += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;">&raquo;</a></li>`;
        }

        function goToPage(page) {
            const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            renderPage();
        }

        // Save new department
        function saveDept() {
            const form = document.getElementById('addDeptForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            fetch('/api/admin/departments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showSuccessDialog('Success!', 'Department "' + data.name + '" has been added successfully.', function() {
                        bootstrap.Modal.getInstance(document.getElementById('addDepartmentModal')).hide();
                        location.reload();
                    });
                } else {
                    showErrorDialog('Error!', result.message || 'Failed to add department. Please try again.');
                }
            })
            .catch(err => {
                showErrorDialog('Error!', err.message || 'An error occurred while adding the department.');
            });
        }

        // Load department data for editing
        function editDept(id) {
            fetch(`/api/admin/departments.php?id=${id}`, {
                credentials: 'include'
            })
            .then(response => {
                if (response.status === 401 || response.status === 403) {
                    showErrorDialog('Session Expired', 'Your session has expired. Please login again.');
                    setTimeout(() => window.location.href = '/login.php', 2000);
                    return null;
                }
                return response.json();
            })
            .then(result => {
                if (!result) return;
                
                if (result.success && result.data && result.data.department) {
                    const dept = result.data.department;
                    
                    // Populate form fields
                    document.getElementById('editDeptId').value = dept.id;
                    document.getElementById('editDeptName').value = dept.name || '';
                    document.getElementById('editDeptCode').value = dept.code || '';
                    document.getElementById('editDeptDescription').value = dept.description || '';
                    document.getElementById('editDeptStatus').value = dept.is_active ? '1' : '0';

                    // Show modal
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editDepartmentModal')).show();
                } else {
                    showErrorDialog('Error!', result.message || 'Failed to load department details. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorDialog('Error!', 'An error occurred while loading department details.');
            });
        }

        // Update department
        async function updateDept() {
            const form = document.getElementById('editDeptForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            const deptName = document.getElementById('editDeptName').value;

            try {
                const response = await fetch('/api/admin/departments.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccessDialog('Updated!', 'Department "' + deptName + '" has been updated successfully.', function() {
                        bootstrap.Modal.getInstance(document.getElementById('editDepartmentModal')).hide();
                        location.reload();
                    });
                } else {
                    showErrorDialog('Error!', result.message || 'Failed to update the department.');
                }
            } catch (error) {
                showErrorDialog('Error!', error.message || 'An error occurred while updating the department.');
            }
        }

        // Delete department
        function deleteDept(id) {
            // Fetch department name first
            fetch(`/api/admin/departments.php?id=${id}`, {
                credentials: 'include'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data && result.data.department) {
                    const deptName = result.data.department.name;
                    showConfirmDialog(
                        'Delete Department?',
                        'Are you sure you want to delete "' + deptName + '"? This action cannot be undone.',
                        function() {
                            fetch('/api/admin/departments.php', {
                                method: 'DELETE',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: id }),
                                credentials: 'include'
                            })
                            .then(response => response.json())
                            .then(result => {
                                if (result.success) {
                                    showSuccessDialog('Deleted!', '"' + deptName + '" has been deleted successfully.', function() {
                                        location.reload();
                                    });
                                } else {
                                    showErrorDialog('Error!', result.message || 'Failed to delete the department.');
                                }
                            })
                            .catch(err => {
                                showErrorDialog('Error!', err.message || 'An error occurred while deleting the department.');
                            });
                        }
                    );
                } else {
                    showErrorDialog('Error!', 'Failed to load department information.');
                }
            })
            .catch(err => {
                showErrorDialog('Error!', 'An error occurred while loading department information.');
            });
        }
    </script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
