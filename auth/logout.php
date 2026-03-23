<?php
// auth/logout.php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';

Auth::logout();
redirect('auth/login.php');
?>