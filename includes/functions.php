<?php
// includes/functions.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db_connection.php';

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: " . SITE_URL . "/" . ltrim($url, '/'));
    exit();
}

/**
 * Generate full URL
 */
function url($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

/**
 * Generate asset URL
 */
function asset($path) {
    return SITE_URL . '/assets/' . ltrim($path, '/');
}

/**
 * Get CSRF field
 */
function csrf_field() {
    return Session::csrfField();
}

/**
 * Verify CSRF token
 */
function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 
                (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
        
        if (!Session::verifyCSRF($token)) {
            die('Invalid CSRF token');
        }
    }
}

/**
 * Format money
 */
function format_money($amount) {
    return CURRENCY . number_format($amount, 2);
}

/**
 * Format date
 */
function format_date($date, $format = 'M d, Y h:i A') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Time ago
 */
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

/**
 * Generate random string
 */
function generate_string($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate transaction reference
 */
function generate_reference($prefix = 'TXN') {
    return $prefix . time() . rand(1000, 9999);
}

/**
 * Get user IP address - FIXED: Return directly, no references
 */
function getUserIP() {
    $ip = '0.0.0.0';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Can contain multiple IPs, take first
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return $ip;
}

/**
 * Get user agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Return JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Log activity
 */
function logActivity($userId, $action, $details = null) {
    require_once __DIR__ . '/db_connection.php';
    
    $db = Database::getInstance()->getConnection();
    $ip = getUserIP();
    $agent = getUserAgent();
    
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $action, $details, $ip, $agent);
    return $stmt->execute();
}

/**
 * Get user balance
 */
function getUserBalance($userId) {
    require_once __DIR__ . '/db_connection.php';
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    return $user['wallet_balance'] ?? 0;
}

/**
 * Update user balance
 */
function updateUserBalance($userId, $amount, $type = 'add') {
    require_once __DIR__ . '/db_connection.php';
    
    $db = Database::getInstance()->getConnection();
    
    if ($type === 'add') {
        $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $userId);
    } else {
        $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?");
        $stmt->bind_param("dii", $amount, $userId, $amount);
    }
    
    return $stmt->execute();
}

/**
 * Get setting value
 */
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        require_once __DIR__ . '/db_connection.php';
        $db = Database::getInstance()->getConnection();
        $result = $db->query("SELECT `key`, `value` FROM settings");
        while ($row = $result->fetch_assoc()) {
            $settings[$row['key']] = $row['value'];
        }
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Update setting
 */
function updateSetting($key, $value) {
    require_once __DIR__ . '/db_connection.php';
    
    $db = Database::getInstance()->getConnection();
    $value = $db->real_escape_string($value);
    
    $db->query("INSERT INTO settings (`key`, `value`) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE `value` = '$value'");
    return true;
}

/**
 * Check if user is logged in (alias for backward compatibility)
 */
function isLoggedIn() {
    return Session::isLoggedIn();
}

/**
 * Check if user is admin (alias for backward compatibility)
 */
function isAdmin() {
    return Session::isAdmin();
}

/**
 * Get current user ID (alias for backward compatibility)
 */
function currentUserId() {
    return Session::userId();
}

/**
 * Get current username (alias for backward compatibility)
 */
function currentUsername() {
    return Session::username();
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone
 */
function validatePhone($phone) {
    return preg_match('/^[0-9]{11}$/', $phone);
}

/**
 * Generate random password
 */
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    return substr(str_shuffle($chars), 0, $length);
}


/**
 * Get user's wallet ID or create if not exists
 */
function getUserWalletId($userId) {
    $db = db();
    
    // Try to get existing wallet
    $stmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($wallet = $result->fetch_assoc()) {
        return $wallet['id'];
    }
    
    // Create wallet if not exists
    $stmt = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0)");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    return $stmt->insert_id;
}



/**
 * Update user balance in both tables (users and wallets)
 * 
 * @param int $userId User ID
 * @param float $amount Amount to add/deduct
 * @param string $type 'add' or 'deduct'
 * @param string $reference Optional reference for transaction
 * @return bool Success or failure
 */
function updateUserBalanceBothTables($userId, $amount, $type = 'add', $reference = null) {
    $db = Database::getInstance()->getConnection();
    
    // Validate amount
    if (!is_numeric($amount) || $amount <= 0) {
        error_log("Invalid amount for balance update: $amount");
        return false;
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        if ($type === 'add') {
            // Update users table
            $stmt1 = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt1->bind_param("di", $amount, $userId);
            $stmt1->execute();
            
            // Update or create wallet record
            $stmt2 = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE balance = balance + ?");
            $stmt2->bind_param("idd", $userId, $amount, $amount);
            $stmt2->execute();
            
        } else {
            // Check sufficient balance first
            $currentBalance = getUserBalanceFromWallets($userId);
            if ($currentBalance < $amount) {
                throw new Exception("Insufficient balance");
            }
            
            // Update users table
            $stmt1 = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?");
            $stmt1->bind_param("did", $amount, $userId, $amount);
            $stmt1->execute();
            
            if ($stmt1->affected_rows === 0) {
                throw new Exception("Failed to update user balance - insufficient funds");
            }
            
            // Update wallets table
            $stmt2 = $db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND balance >= ?");
            $stmt2->bind_param("did", $amount, $userId, $amount);
            $stmt2->execute();
            
            if ($stmt2->affected_rows === 0) {
                throw new Exception("Failed to update wallet balance - insufficient funds");
            }
        }
        
        // Log transaction if reference provided
        if ($reference) {
            logActivity($userId, $type === 'add' ? 'balance_added' : 'balance_deducted', 
                       "Amount: " . format_money($amount) . ", Ref: $reference");
        }
        
        // Commit transaction
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $db->rollback();
        error_log("Balance update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user balance from wallets table
 */
function getUserBalanceFromWallets($userId) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return (float) $row['balance'];
    }
    
    return 0;
}

/**
 * Sync balances between users and wallets tables
 * This fixes any inconsistencies
 */
function syncUserBalances($userId = null) {
    $db = Database::getInstance()->getConnection();
    
    if ($userId) {
        // Sync specific user
        $stmt = $db->prepare("SELECT id, wallet_balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            $stmt = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE balance = ?");
            $stmt->bind_param("idd", $userId, $user['wallet_balance'], $user['wallet_balance']);
            return $stmt->execute();
        }
        
        return false;
        
    } else {
        // Sync all users
        $users = $db->query("SELECT id, wallet_balance FROM users");
        $success = true;
        
        while ($user = $users->fetch_assoc()) {
            $stmt = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE balance = ?");
            $stmt->bind_param("idd", $user['id'], $user['wallet_balance'], $user['wallet_balance']);
            if (!$stmt->execute()) {
                $success = false;
                error_log("Failed to sync balance for user: " . $user['id']);
            }
        }
        
        return $success;
    }
}

?>