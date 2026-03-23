<?php
// includes/session.php

class Session {
    
    /**
     * Start session if not already started
     */
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['_last_regenerate'])) {
            self::regenerate();
        } else {
            $elapsed = time() - $_SESSION['_last_regenerate'];
            if ($elapsed > 1800) {
                self::regenerate();
            }
        }
        
        // Check session expiration
        if (isset($_SESSION['_last_activity'])) {
            $elapsed = time() - $_SESSION['_last_activity'];
            if ($elapsed > SESSION_LIFETIME) {
                self::destroy();
                return false;
            }
        }
        
        $_SESSION['_last_activity'] = time();
        return true;
    }
    
    /**
     * Regenerate session ID
     */
    public static function regenerate() {
        session_regenerate_id(true);
        $_SESSION['_last_regenerate'] = time();
    }
    
    /**
     * Set session value
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session value
     */
    public static function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    /**
     * Check if session key exists
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session key
     */
    public static function remove($key) {
        unset($_SESSION[$key]);
    }
    
    /**
     * Destroy session
     */
    public static function destroy() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRF() {
        // Clear old tokens to prevent accumulation
        if (isset($_SESSION['csrf_tokens']) && is_array($_SESSION['csrf_tokens'])) {
            // Remove tokens older than 2 hours
            foreach ($_SESSION['csrf_tokens'] as $token => $time) {
                if (time() - $time > 7200) {
                    unset($_SESSION['csrf_tokens'][$token]);
                }
            }
        } else {
            $_SESSION['csrf_tokens'] = [];
        }
        
        // Generate new token
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$token] = time();
        
        // Store current token for backward compatibility
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRF($token) {
        if (empty($token)) {
            error_log("CSRF verification failed: Empty token");
            return false;
        }
        
        // Check if session has tokens array
        if (isset($_SESSION['csrf_tokens']) && is_array($_SESSION['csrf_tokens'])) {
            // Check if token exists in tokens array
            if (isset($_SESSION['csrf_tokens'][$token])) {
                $age = time() - $_SESSION['csrf_tokens'][$token];
                if ($age <= 7200) {
                    // Token is valid, remove it (one-time use)
                    unset($_SESSION['csrf_tokens'][$token]);
                    return true;
                } else {
                    error_log("CSRF verification failed: Token expired ($age seconds old)");
                    // Remove expired token
                    unset($_SESSION['csrf_tokens'][$token]);
                    return false;
                }
            }
        }
        
        // Fallback to single token check for backward compatibility
        if (isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token'])) {
            $valid = hash_equals($_SESSION['csrf_token'], $token);
            
            if ($valid && isset($_SESSION['csrf_token_time'])) {
                $age = time() - $_SESSION['csrf_token_time'];
                if ($age > 7200) {
                    error_log("CSRF verification failed: Token expired ($age seconds old)");
                    return false;
                }
            }
            
            if ($valid) {
                // Clear the used token
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
            }
            
            return $valid;
        }
        
        error_log("CSRF verification failed: No token found in session");
        return false;
    }
    
    /**
     * Get CSRF token field HTML
     */
    public static function csrfField() {
        $token = self::generateCSRF();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Set flash message
     */
    public static function setFlash($key, $message) {
        $_SESSION['_flash'][$key] = $message;
    }
    
    /**
     * Get flash message
     */
    public static function getFlash($key) {
        if (isset($_SESSION['_flash'][$key])) {
            $message = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $message;
        }
        return null;
    }
    
    /**
     * Set success message
     */
    public static function setSuccess($message) {
        self::setFlash('success', $message);
    }
    
    /**
     * Get success message
     */
    public static function getSuccess() {
        return self::getFlash('success');
    }
    
    /**
     * Set error message
     */
    public static function setError($message) {
        self::setFlash('error', $message);
    }
    
    /**
     * Get error message
     */
    public static function getError() {
        return self::getFlash('error');
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::isLoggedIn() && isset($_SESSION['user_role']) && 
               ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin');
    }
    
    /**
     * Get current user ID
     */
    public static function userId() {
        return $_SESSION['user_id'] ?? 0;
    }
    
    /**
     * Get current user role
     */
    public static function userRole() {
        return $_SESSION['user_role'] ?? 'guest';
    }
    
    /**
     * Get username
     */
    public static function username() {
        return $_SESSION['username'] ?? 'Guest';
    }
    
    /**
     * Get user email (added method)
     */
    public static function userEmail() {
        return $_SESSION['user_email'] ?? '';
    }
}

// Initialize session
Session::start();