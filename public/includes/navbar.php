<?php
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <div class="d-flex align-items-center">
            <button class="btn btn-toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
                <i class="bi bi-list"></i>
            </button>
            <nav aria-label="breadcrumb" class="d-none d-md-block">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($pageTitle); ?></li>
                </ol>
            </nav>
            <h5 class="page-title d-md-none mb-0"><?php echo htmlspecialchars($pageTitle); ?></h5>
        </div>
        
        <div class="d-flex align-items-center navbar-actions">
            <!-- Date Display -->
            <div class="navbar-datetime d-none d-md-flex">
                <div class="datetime-box">
                    <i class="bi bi-calendar3"></i>
                    <span><?php echo date('M d, Y'); ?></span>
                </div>
            </div>
            
            <!-- User Menu -->
            <div class="dropdown user-dropdown">
                <button class="btn btn-user" data-bs-toggle="dropdown" title="User Menu">
                    <div class="user-avatar-sm">
                        <i class="bi bi-person"></i>
                    </div>
                    <span class="d-none d-lg-inline ms-2"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <i class="bi bi-chevron-down ms-1 d-none d-lg-inline"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header">
                        <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong>
                        <small class="text-muted d-block"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></small>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="profile.php" class="dropdown-item">
                        <i class="bi bi-person me-2"></i> My Profile
                    </a>
                    <a href="settings.php" class="dropdown-item">
                        <i class="bi bi-gear me-2"></i> Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item text-danger" onclick="logout(); return false;">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
