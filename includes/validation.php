<?php
// includes/validation.php - Input Validation Class

class Validator {
    
    /**
     * Validate email
     */
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number (Nigerian)
     */
    public static function phone($phone) {
        return preg_match('/^[0-9]{11}$/', $phone);
    }
    
    /**
     * Validate password strength
     */
    public static function password($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Validate username
     */
    public static function username($username) {
        if (strlen($username) < 3) {
            return 'Username must be at least 3 characters';
        }
        if (strlen($username) > 50) {
            return 'Username must be less than 50 characters';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return 'Username can only contain letters, numbers and underscores';
        }
        return true;
    }
    
    /**
     * Validate amount
     */
    public static function amount($amount, $min = 0, $max = null) {
        if (!is_numeric($amount) || $amount <= 0) {
            return 'Amount must be a positive number';
        }
        if ($min > 0 && $amount < $min) {
            return "Amount must be at least " . format_money($min);
        }
        if ($max !== null && $amount > $max) {
            return "Amount cannot exceed " . format_money($max);
        }
        return true;
    }
    
    /**
     * Validate meter number
     */
    public static function meterNumber($meter) {
        return strlen($meter) >= 6 && strlen($meter) <= 20;
    }
    
    /**
     * Validate smart card number
     */
    public static function smartCard($card) {
        return strlen($card) >= 6 && strlen($card) <= 20;
    }
    
    /**
     * Sanitize input
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate required fields
     */
    public static function required($data, $fields) {
        $errors = [];
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        return $errors;
    }
    
    /**
     * Validate date
     */
    public static function date($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
?>