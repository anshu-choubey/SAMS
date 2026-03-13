<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$menuItems = [
    ['page' => 'index.php', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'tooltip' => 'Dashboard'],
    ['page' => 'users.php', 'icon' => 'bi-people', 'label' => 'Users', 'tooltip' => 'Users'],
    ['page' => 'departments.php', 'icon' => 'bi-building', 'label' => 'Departments', 'tooltip' => 'Departments'],
    ['page' => 'subjects.php', 'icon' => 'bi-book', 'label' => 'Subjects', 'tooltip' => 'Subjects'],
    ['page' => 'teacher-assignments.php', 'icon' => 'bi-person-badge', 'label' => 'Assignments', 'tooltip' => 'Teacher Assignments'],
    ['page' => 'schedules.php', 'icon' => 'bi-calendar-week', 'label' => 'Schedules', 'tooltip' => 'Schedules'],
    ['page' => 'notifications.php', 'icon' => 'bi-bell', 'label' => 'Notifications', 'tooltip' => 'Send Notifications'],
    ['page' => 'reports.php', 'icon' => 'bi-file-earmark-bar-graph', 'label' => 'Reports', 'tooltip' => 'Reports'],
    ['page' => 'settings.php', 'icon' => 'bi-gear', 'label' => 'Settings', 'tooltip' => 'Settings'],
];
?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="bi bi-calendar-check-fill"></i>
        </div>
        <h4>SAMS</h4>
        <p class="text-muted small mb-0">Admin Panel</p>
    </div>
    
    <ul class="nav flex-column">
        <?php foreach ($menuItems as $item): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $currentPage === $item['page'] ? 'active' : ''; ?>" 
               href="<?php echo $item['page']; ?>" 
               data-tooltip="<?php echo $item['tooltip']; ?>">
                <i class="bi <?php echo $item['icon']; ?>"></i> 
                <span><?php echo $item['label']; ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    
    <div class="sidebar-footer">
        <a href="#" onclick="logout(); return false;" class="btn btn-logout w-100">
            <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
        </a>
    </div>
</div>
