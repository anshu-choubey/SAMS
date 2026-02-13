<?php
// Redirect to login if not authenticated
session_start();

if (isset($_SESSION['user_id'])) {
    // Redirect to appropriate dashboard based on user role
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: /admin/index.php');
    } elseif ($_SESSION['user_role'] === 'teacher') {
        header('Location: /teacher/index.php');
    } elseif ($_SESSION['user_role'] === 'student') {
        header('Location: /student/index.php');
    }
    exit;
} else {
    // Redirect to login page
    header('Location: /login.php');
    exit;
}
?>
