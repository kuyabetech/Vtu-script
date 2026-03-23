<?php
// admin/index.php - Redirect to login or dashboard
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';

// If already logged in as admin, go to dashboard
if (Session::isLoggedIn() && Session::isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

// Otherwise redirect to login page
header('Location: ../auth/login.php');
exit();
?>