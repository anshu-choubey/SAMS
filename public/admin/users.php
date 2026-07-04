<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$users = [];
$departments = [];

if ($db) {
    try {
        // Fetch users with department info
        $stmt = $db->query("SELECT u.*, 
                                   CASE WHEN u.role='student' THEN s.roll_number
                                        WHEN u.role='teacher' THEN t.employee_id
                                        ELSE NULL END as identifier,
                                   CASE WHEN u.role='student' THEN d.name
                                        ELSE NULL END as department_name
                            FROM users u 
                            LEFT JOIN students s ON u.id = s.user_id
                            LEFT JOIN teachers t ON u.id = t.user_id
                            LEFT JOIN departments d ON s.department_id = d.id
                            ORDER BY u.created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $depts = $db->query("SELECT id, name FROM departments WHERE is_active = true ORDER BY name");
        $departments = $depts->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$pageTitle = 'Users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - SAMS Admin</title>
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
                <h2>Users Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus"></i> Add User
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
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Role</label>
                            <select class="form-select form-select-sm" id="filterRole">
                                <option value="">All Roles</option>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Status</label>
                            <select class="form-select form-select-sm" id="filterStatus">
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm" id="searchUser" placeholder="Search name or email...">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-primary me-1" onclick="applyFilters()"><i class="bi bi-search"></i> Filter</button>
                            <button class="btn btn-sm btn-secondary" onclick="resetFilters()"><i class="bi bi-x-circle"></i> Reset</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="mainUsersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="mainUsersTableBody">
                                <?php if(empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No users found</td>
                                </tr>
                                <?php else: foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge bg-info"><?php echo $user['role']; ?></span></td>
                                    <td><?php echo htmlspecialchars($user['department_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td>
                                        <?php echo $user['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Delete"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            Showing <span id="showingStart">1</span>-<span id="showingEnd">10</span> of <span id="totalItems"><?php echo count($users); ?></span> items
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <!-- General User Information -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3"><i class="bi bi-person"></i> User Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div class="mb-4 p-3 bg-light rounded">
                            <label class="form-label fw-bold mb-3"><i class="bi bi-briefcase"></i> User Role *</label>
                            <select class="form-select form-select-lg" name="role" id="userRole" required onchange="toggleRoleFields()">
                                <option value="">Select User Role</option>
                                <option value="admin">🛡️ Admin</option>
                                <option value="teacher">👨‍🏫 Teacher</option>
                                <option value="student">👨‍🎓 Student</option>
                            </select>
                        </div>

                        <!-- Student-specific Fields -->
                        <div id="studentFields" class="card card-body bg-info bg-opacity-10 border-info mb-3" style="display: none;">
                            <h6 class="text-info mb-3"><i class="bi bi-backpack2"></i> Student Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Roll Number *</label>
                                    <input type="text" class="form-control" name="roll_number" placeholder="e.g., CS001">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department *</label>
                                    <select class="form-select" name="department_id">
                                        <option value="">Select Department</option>
                                        <?php foreach($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Semester *</label>
                                    <select class="form-select" name="semester">
                                        <option value="">Select Semester</option>
                                        <?php for($i=1; $i<=8; $i++): ?>
                                        <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Section</label>
                                    <input type="text" class="form-control" name="section" placeholder="e.g., A, B, C">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Batch Year</label>
                                    <input type="number" class="form-control" name="batch_year" placeholder="e.g., 2024" min="2020">
                                </div>
                            </div>
                        </div>

                        <!-- Teacher-specific Fields -->
                        <div id="teacherFields" class="card card-body bg-success bg-opacity-10 border-success mb-3" style="display: none;">
                            <h6 class="text-success mb-3"><i class="bi bi-mortarboard"></i> Teacher Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Employee ID *</label>
                                    <input type="text" class="form-control" name="employee_id" placeholder="e.g., EMP001">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="teacher_department_id">
                                        <option value="">Select Department</option>
                                        <?php foreach($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Designation</label>
                                    <input type="text" class="form-control" name="designation" placeholder="e.g., Assistant Professor">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Qualification</label>
                                    <input type="text" class="form-control" name="qualification" placeholder="e.g., PhD Computer Science">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Joining Date</label>
                                    <input type="date" class="form-control" name="joining_date">
                                </div>
                            </div>
                        </div>

                        <!-- Status Field -->
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="is_active">
                                <option value="1">✓ Active</option>
                                <option value="0">✗ Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveUser()">
                        <i class="bi bi-save"></i> Save User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" name="id" id="editUserId">
                        
                        <!-- General User Information -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3"><i class="bi bi-person"></i> User Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="is_active">
                                        <option value="1">✓ Active</option>
                                        <option value="0">✗ Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div class="mb-4 p-3 bg-light rounded">
                            <label class="form-label fw-bold mb-3"><i class="bi bi-briefcase"></i> User Role *</label>
                            <select class="form-select form-select-lg" name="role" id="editUserRole" required onchange="toggleEditRoleFields()">
                                <option value="">Select User Role</option>
                                <option value="admin">🛡️ Admin</option>
                                <option value="teacher">👨‍🏫 Teacher</option>
                                <option value="student">👨‍🎓 Student</option>
                            </select>
                        </div>

                        <!-- Student-specific Fields for edit -->
                        <div id="editStudentFields" class="card card-body bg-info bg-opacity-10 border-info mb-3" style="display: none;">
                            <h6 class="text-info mb-3"><i class="bi bi-backpack2"></i> Student Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Roll Number *</label>
                                    <input type="text" class="form-control" name="roll_number" placeholder="e.g., CS001">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department *</label>
                                    <select class="form-select" name="department_id">
                                        <option value="">Select Department</option>
                                        <?php foreach($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Semester *</label>
                                    <select class="form-select" name="semester">
                                        <option value="">Select Semester</option>
                                        <?php for($i=1; $i<=8; $i++): ?>
                                        <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Section</label>
                                    <input type="text" class="form-control" name="section" placeholder="e.g., A, B, C">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Batch Year</label>
                                    <input type="number" class="form-control" name="batch_year" placeholder="e.g., 2024" min="2020">
                                </div>
                            </div>
                        </div>

                        <!-- Teacher-specific Fields for edit -->
                        <div id="editTeacherFields" class="card card-body bg-success bg-opacity-10 border-success mb-3" style="display: none;">
                            <h6 class="text-success mb-3"><i class="bi bi-mortarboard"></i> Teacher Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Employee ID *</label>
                                    <input type="text" class="form-control" name="employee_id" placeholder="e.g., EMP001">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="teacher_department_id">
                                        <option value="">Select Department</option>
                                        <?php foreach($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Designation</label>
                                    <input type="text" class="form-control" name="designation" placeholder="e.g., Assistant Professor">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Qualification</label>
                                    <input type="text" class="form-control" name="qualification" placeholder="e.g., PhD Computer Science">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Joining Date</label>
                                    <input type="date" class="form-control" name="joining_date">
                                </div>
                            </div>
                        </div>

                        <!-- Security Options -->
                        <div class="d-grid">
                            <button type="button" class="btn btn-outline-warning" onclick="openPasswordModal()">
                                <i class="bi bi-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateUser()">
                        <i class="bi bi-save"></i> Update User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key"></i> Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <input type="hidden" name="user_id" id="passwordUserId">
                        <div class="mb-3">
                            <label class="form-label">New Password *</label>
                            <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="updatePassword()">
                        <i class="bi bi-check-lg"></i> Update Password
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
        // Toggle role-specific fields in add form
        function toggleRoleFields() {
            const role = document.getElementById('userRole').value;
            const studentFields = document.getElementById('studentFields');
            const teacherFields = document.getElementById('teacherFields');

            studentFields.style.display = role === 'student' ? 'block' : 'none';
            teacherFields.style.display = role === 'teacher' ? 'block' : 'none';

            // Set required attributes
            const rollInput = studentFields.querySelector('input[name="roll_number"]');
            const deptSelect = studentFields.querySelector('select[name="department_id"]');
            const semesterSelect = studentFields.querySelector('select[name="semester"]');
            const empInput = teacherFields.querySelector('input[name="employee_id"]');

            if (role === 'student') {
                rollInput?.setAttribute('required', 'required');
                deptSelect?.setAttribute('required', 'required');
                semesterSelect?.setAttribute('required', 'required');
            } else {
                rollInput?.removeAttribute('required');
                deptSelect?.removeAttribute('required');
                semesterSelect?.removeAttribute('required');
            }

            if (role === 'teacher') {
                empInput?.setAttribute('required', 'required');
            } else {
                empInput?.removeAttribute('required');
            }
        }

        // Toggle role-specific fields in edit form
        function toggleEditRoleFields() {
            const role = document.getElementById('editUserRole').value;
            const editStudentFields = document.getElementById('editStudentFields');
            const editTeacherFields = document.getElementById('editTeacherFields');

            editStudentFields.style.display = role === 'student' ? 'block' : 'none';
            editTeacherFields.style.display = role === 'teacher' ? 'block' : 'none';

            // Set required attributes
            const rollInput = editStudentFields.querySelector('input[name="roll_number"]');
            const deptSelect = editStudentFields.querySelector('select[name="department_id"]');
            const semesterSelect = editStudentFields.querySelector('select[name="semester"]');
            const empInput = editTeacherFields.querySelector('input[name="employee_id"]');

            if (role === 'student') {
                rollInput?.setAttribute('required', 'required');
                deptSelect?.setAttribute('required', 'required');
                semesterSelect?.setAttribute('required', 'required');
            } else {
                rollInput?.removeAttribute('required');
                deptSelect?.removeAttribute('required');
                semesterSelect?.removeAttribute('required');
            }

            if (role === 'teacher') {
                empInput?.setAttribute('required', 'required');
            } else {
                empInput?.removeAttribute('required');
            }
        }

        // Save user
        async function saveUser() {
            const form = document.getElementById('addUserForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            const userName = data.full_name || data.email;
            
            // Map teacher_department_id to department_id for teachers
            if (data.role === 'teacher' && data.teacher_department_id) {
                data.department_id = data.teacher_department_id;
                delete data.teacher_department_id;
            }

            try {
                const response = await fetch('/api/admin/users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccessDialog('Success!', `User "${userName}" created successfully.`, () => location.reload());
                    bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
                } else {
                    showErrorDialog('Error!', result.message || 'Unknown error');
                }
            } catch (err) {
                showErrorDialog('Error!', err.message);
            }
        }

        // Edit user
        async function editUser(id) {
            try {
                const response = await fetch(`/api/admin/users.php?id=${id}`, {
                    credentials: 'include'
                });

                if (response.status === 401 || response.status === 403) {
                    window.location.href = '/login.php';
                    return;
                }

                const result = await response.json();

                if (result.success && result.data.user) {
                    const user = result.data.user;
                    const form = document.getElementById('editUserForm');
                    
                    form.full_name.value = user.full_name || '';
                    form.email.value = user.email || '';
                    form.phone.value = user.phone || '';
                    form.role.value = user.role || '';
                    form.is_active.value = user.is_active ? '1' : '0';
                    document.getElementById('editUserId').value = user.id;
                    
                    if (user.role === 'student' && user.student_info) {
                        form.roll_number.value = user.student_info.roll_number || '';
                        form.department_id.value = user.student_info.department_id || '';
                        form.semester.value = user.student_info.semester || '';
                        form.section.value = user.student_info.section || '';
                        form.batch_year.value = user.student_info.batch_year || '';
                    } else if (user.role === 'teacher' && user.teacher_info) {
                        form.employee_id.value = user.teacher_info.employee_id || '';
                        form.teacher_department_id.value = user.teacher_info.primary_department_id || '';
                        form.designation.value = user.teacher_info.designation || '';
                        form.qualification.value = user.teacher_info.qualification || '';
                        form.joining_date.value = user.teacher_info.joining_date || '';
                    }
                    
                    toggleEditRoleFields();
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editUserModal')).show();
                } else {
                    showErrorDialog('Error!', 'Failed to load user');
                }
            } catch (error) {
                showErrorDialog('Error!', 'Failed to load user');
            }
        }

        // Update user
        async function updateUser() {
            const form = document.getElementById('editUserForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            const userName = data.full_name || data.email;
            
            // Map teacher_department_id to department_id for teachers
            if (data.role === 'teacher' && data.teacher_department_id) {
                data.department_id = data.teacher_department_id;
                delete data.teacher_department_id;
            }

            try {
                const response = await fetch('/api/admin/users.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccessDialog('Updated!', `User "${userName}" updated successfully.`, () => location.reload());
                    bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                } else {
                    showErrorDialog('Error!', result.message || 'Failed to update user');
                }
            } catch (error) {
                showErrorDialog('Error!', 'Failed to update user');
            }
        }

        // Delete user
        async function deleteUser(id) {
            const row = event.target.closest('tr');
            const userName = row.cells[0].textContent.trim();
            
            showConfirmDialog('Delete User?', `Remove user "${userName}"?`, function() {
                fetch('/api/admin/users.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                    credentials: 'include'
                }).then(response => response.json())
                  .then(result => {
                      if (result.success) {
                          showSuccessDialog('Deleted!', `User deleted successfully.`, () => location.reload());
                      } else {
                          showErrorDialog('Error!', result.message || 'Failed to delete user');
                      }
                  });
            });
        }

        // Open password modal
        function openPasswordModal() {
            const userId = document.getElementById('editUserId').value;
            document.getElementById('passwordUserId').value = userId;
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('changePasswordModal')).show();
        }

        // Update password
        async function updatePassword() {
            const userId = document.getElementById('passwordUserId').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword.length < 6) {
                showWarningDialog('Invalid Password', 'Password must be at least 6 characters');
                return;
            }

            if (newPassword !== confirmPassword) {
                showWarningDialog('Password Mismatch', 'Passwords do not match');
                return;
            }

            try {
                const response = await fetch('/api/admin/users.php', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: userId, password: newPassword }),
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccessDialog('Success!', 'Password updated successfully.', () => {
                        bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
                    });
                } else {
                    showErrorDialog('Error!', result.message || 'Failed to update password');
                }
            } catch (error) {
                showErrorDialog('Error!', 'Failed to update password');
            }
        }

        // Apply filters
        let currentPage = 1;
        const itemsPerPage = 10;
        let allRows = [];
        let filteredRows = [];

        document.addEventListener('DOMContentLoaded', function() {
            allRows = Array.from(document.querySelectorAll('#mainUsersTableBody tr')).filter(row => !row.textContent.includes('No users found'));
            filteredRows = [...allRows];
            renderPage();
        });

        function applyFilters() {
            const department = document.getElementById('filterDepartment').value;
            const role = document.getElementById('filterRole').value;
            const status = document.getElementById('filterStatus').value;
            const search = document.getElementById('searchUser').value.toLowerCase();

            filteredRows = allRows.filter(row => {
                let show = true;

                // Filter by department
                if (department && show) {
                    const deptText = row.cells[3].textContent.trim();
                    const selectedDeptName = document.querySelector(`#filterDepartment option[value="${department}"]`)?.textContent;
                    if (deptText !== selectedDeptName) show = false;
                }

                // Filter by role
                if (role && show) {
                    const roleText = row.cells[2].textContent.trim().toLowerCase();
                    if (roleText !== role) show = false;
                }

                // Filter by status
                if (status !== '' && show) {
                    const statusText = row.cells[5].textContent.trim();
                    const isActive = statusText.includes('Active') && !statusText.includes('Inactive');
                    if (status === '1' && !isActive) show = false;
                    if (status === '0' && isActive) show = false;
                }

                // Filter by search
                if (search && show) {
                    const name = row.cells[0].textContent.toLowerCase();
                    const email = row.cells[1].textContent.toLowerCase();
                    if (!name.includes(search) && !email.includes(search)) show = false;
                }

                return show;
            });

            currentPage = 1;
            renderPage();
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('filterDepartment').value = '';
            document.getElementById('filterRole').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('searchUser').value = '';
            
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
    </script>
</body>
</html>
