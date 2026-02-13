<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user = [];
$message = '';
$messageType = '';

// Get current user
if ($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        
        if (empty($full_name) || empty($email)) {
            $message = 'Full name and email are required';
            $messageType = 'error';
        } else {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $_SESSION['user_id']]);
            $message = 'Profile updated successfully';
            $messageType = 'success';
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        }
    } catch (Exception $e) {
        $message = 'Error updating profile: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'All password fields are required';
            $messageType = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match';
            $messageType = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = 'Password must be at least 6 characters';
            $messageType = 'error';
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password_hash'])) {
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $_SESSION['user_id']]);
                $message = 'Password changed successfully';
                $messageType = 'success';
            } else {
                $message = 'Current password is incorrect';
                $messageType = 'error';
            }
        }
    } catch (Exception $e) {
        $message = 'Error changing password: ' . $e->getMessage();
        $messageType = 'error';
    }
}

$pageTitle = 'My Profile';
$currentPage = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - SAMS Admin</title>
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
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Information -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-person-circle"></i> Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['role'] ?? ''); ?>" disabled>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Account Status</label>
                                    <input type="text" class="form-control" value="<?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>" disabled>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Member Since</label>
                                    <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($user['created_at'] ?? '')); ?>" disabled>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-check-circle"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-arrow-repeat"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Statistics -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Account Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6 class="text-muted">Account Created</h6>
                                        <p class="fs-5"><?php echo date('M d, Y', strtotime($user['created_at'] ?? '')); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6 class="text-muted">Total Users</h6>
                                        <p class="fs-5"><?php 
                                            try {
                                                $stmt = $db->query("SELECT COUNT(*) as count FROM users");
                                                $data = $stmt->fetch();
                                                echo $data['count'];
                                            } catch (Exception $e) {
                                                echo '0';
                                            }
                                        ?></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6 class="text-muted">Status</h6>
                                        <p class="fs-5"><?php echo $user['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <!-- Alert Container for Toast Notifications -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>
</body>
</html>
