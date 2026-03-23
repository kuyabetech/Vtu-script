<?php
// auth/verify.php - Email Verification
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Get verification code from URL
$code = $_GET['code'] ?? '';

if (empty($code)) {
    Session::setError('Invalid verification link');
    redirect('login.php');
}

$db = db();

// Verify the code
$stmt = $db->prepare("SELECT id FROM users WHERE verification_code = ? AND email_verified = 0");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    // Update user as verified
    $stmt = $db->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    
    Session::setSuccess('Email verified successfully! You can now login.');
} else {
    Session::setError('Invalid or expired verification link');
}

redirect('login.php');
?>