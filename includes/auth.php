<?php
// includes/auth.php - FIXED VERSION

// Make sure session is started first
require_once __DIR__ . '/session.php';

class Auth {
    
    /**
     * Attempt to login user
     */
    public static function login($username, $password, $remember = false) {
        require_once __DIR__ . '/db_connection.php';
        require_once __DIR__ . '/functions.php';
        
        $db = Database::getInstance()->getConnection();
        
        // Check if connection is valid
        if (!$db || !$db->ping()) {
            error_log("Database connection failed in Auth::login");
            return ['success' => false, 'message' => 'System error. Please try again.'];
        }
        
        // Find user
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1");
        if (!$stmt) {
            error_log("Prepare failed: " . $db->error);
            return ['success' => false, 'message' => 'System error. Please try again.'];
        }
        
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Increment login attempts
            $attempts = $user['login_attempts'] + 1;
            $lockUntil = null;
            
            if ($attempts >= 5) {
                $lockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            }
            
            $updateStmt = $db->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
            $updateStmt->bind_param("isi", $attempts, $lockUntil, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Account is locked. Please try again later.'];
        }
        
        // Reset login attempts on successful login
        $updateStmt = $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW(), last_ip = ? WHERE id = ?");
        $ip = getUserIP();
        $updateStmt->bind_param("si", $ip, $user['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Set session
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('user_email', $user['email']);
        Session::set('user_role', $user['role']);
        Session::set('user_name', trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        
        // Handle remember me
        if ($remember) {
            self::setRememberToken($user['id']);
        }
        
        // Log activity
        if (function_exists('logActivity')) {
            logActivity($user['id'], 'login', 'User logged in successfully');
        }
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Register new user - FIXED: No passing by reference issues
     */
    public static function register($data) {
        require_once __DIR__ . '/db_connection.php';
        require_once __DIR__ . '/functions.php';
        
        $db = Database::getInstance()->getConnection();
        
        // Validate required fields
        $required = ['username', 'email', 'phone', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => ucfirst($field) . ' is required'];
            }
        }
        
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $data['username']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Username already taken'];
        }
        $stmt->close();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Email already registered'];
        }
        $stmt->close();
        
        // Check if phone exists
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->bind_param("s", $data['phone']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Phone number already registered'];
        }
        $stmt->close();
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Generate referral code
        $referralCode = strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Begin transaction
        $db->begin_transaction();
        
        try {
            // Get referred by - FIXED: Store in variable first
            $referredBy = null;
            if (!empty($data['referral_code'])) {
                $referredBy = self::getUserIdByReferral($data['referral_code']);
            }
            
            // Insert user
            $stmt = $db->prepare("INSERT INTO users (username, email, phone, password, first_name, last_name, referral_code, referred_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            
            $firstName = $data['first_name'] ?? '';
            $lastName = $data['last_name'] ?? '';
            
            $stmt->bind_param("sssssssi", 
                $data['username'],
                $data['email'],
                $data['phone'],
                $hashedPassword,
                $firstName,
                $lastName,
                $referralCode,
                $referredBy
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create user");
            }
            
            $userId = $stmt->insert_id;
            $stmt->close();
            
            // Create wallet
            $stmt = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0)");
            $stmt->bind_param("i", $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create wallet");
            }
            $stmt->close();
            
            // Handle referral bonus
            if ($referredBy) {
                self::processReferralBonus($referredBy, $userId);
            }
            
            $db->commit();
            
            return ['success' => true, 'user_id' => $userId];
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        require_once __DIR__ . '/db_connection.php';
        require_once __DIR__ . '/functions.php';
        
        // Clear remember token
        if (isset($_COOKIE['remember_token'])) {
            $db = Database::getInstance()->getConnection();
            $token = $_COOKIE['remember_token'];
            $stmt = $db->prepare("DELETE FROM remember_tokens WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Log activity if user was logged in
        if (Session::isLoggedIn() && function_exists('logActivity')) {
            logActivity(Session::userId(), 'logout', 'User logged out');
        }
        
        Session::destroy();
    }
    
    /**
     * Check if user is authenticated
     */
    public static function check() {
        if (Session::isLoggedIn()) {
            return true;
        }
        
        // Check remember token
        if (isset($_COOKIE['remember_token'])) {
            return self::loginWithToken($_COOKIE['remember_token']);
        }
        
        return false;
    }
    
    /**
     * Require authentication
     */
    public static function requireLogin() {
        if (!self::check()) {
            Session::setError('Please login to continue');
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        }
    }
    
    /**
     * Require admin role
     */
    public static function requireAdmin() {
        self::requireLogin();
        if (Session::userRole() !== 'admin' && Session::userRole() !== 'super_admin') {
            Session::setError('Access denied. Admin privileges required.');
            header('Location: ' . SITE_URL . '/user/dashboard.php');
            exit();
        }
    }
    
    /**
     * Get current user data
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }
        
        require_once __DIR__ . '/db_connection.php';
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $userId = Session::userId();
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user;
    }
    
    /**
     * Set remember me token
     */
    private static function setRememberToken($userId) {
        require_once __DIR__ . '/db_connection.php';
        require_once __DIR__ . '/functions.php';
        
        $db = Database::getInstance()->getConnection();
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $token, $expires);
        $stmt->execute();
        $stmt->close();
        
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
    }
    
    /**
     * Login with remember token
     */
    private static function loginWithToken($token) {
        require_once __DIR__ . '/db_connection.php';
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $userId = $row['user_id'];
            $stmt->close();
            
            // Get user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($user) {
                Session::set('user_id', $user['id']);
                Session::set('username', $user['username']);
                Session::set('user_email', $user['email']);
                Session::set('user_role', $user['role']);
                Session::set('user_name', trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
                return true;
            }
        } else {
            $stmt->close();
        }
        
        return false;
    }
    
    /**
     * Get user ID by referral code - FIXED: No passing by reference issues
     */
    private static function getUserIdByReferral($code) {
        require_once __DIR__ . '/db_connection.php';
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // FIXED: Store in variable first
        $userId = null;
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userId = $user['id'];
        }
        $stmt->close();
        
        return $userId;
    }
    
    /**
     * Process referral bonus - FIXED: Added total field
     */
    private static function processReferralBonus($referrerId, $newUserId) {
        require_once __DIR__ . '/db_connection.php';
        require_once __DIR__ . '/functions.php';
        
        $db = Database::getInstance()->getConnection();
        
        // Check if REFERRAL_BONUS constant exists
        $bonus = defined('REFERRAL_BONUS') ? REFERRAL_BONUS : 100;
        
        // Add bonus to referrer
        $stmt = $db->prepare("UPDATE users SET bonus_balance = bonus_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $bonus, $referrerId);
        $stmt->execute();
        $stmt->close();
        
        // Record referral
        $stmt = $db->prepare("INSERT INTO referrals (referrer_id, referred_id, commission_amount, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iid", $referrerId, $newUserId, $bonus);
        $stmt->execute();
        $stmt->close();
        
        // Create transaction - FIXED: Added total field
        $reference = generate_reference('REF');
        $total = $bonus; // Set total equal to amount
        
        $stmt = $db->prepare("INSERT INTO transactions (user_id, transaction_id, type, amount, total, status) VALUES (?, ?, 'referral_bonus', ?, ?, 'success')");
        $stmt->bind_param("isdd", $referrerId, $reference, $bonus, $total);
        $stmt->execute();
        $stmt->close();
        
        // Create notification
        $title = "Referral Bonus Received";
        $message = "You have received a referral bonus of " . format_money($bonus);
        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'bonus', ?, ?)");
        $stmt->bind_param("iss", $referrerId, $title, $message);
        $stmt->execute();
        $stmt->close();
    }
}

// Initialize auth check
$isLoggedIn = Auth::check();

// Don't close the database connection here - let PHP handle it
?>