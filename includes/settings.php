<?php
// includes/settings.php - Settings Helper Functions
require_once __DIR__ . '/db_connection.php';

class Settings {
    private static $cache = [];
    private static $db = null;
    
    /**
     * Initialize database connection
     */
    private static function getDB() {
        if (self::$db === null) {
            global $db;
            self::$db = $db;
        }
        return self::$db;
    }
    
    /**
     * Get a setting value by key
     */
    public static function get($key, $default = null) {
        $db = self::getDB();
        
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        // Query database
        $stmt = $db->prepare("SELECT `value`, `type` FROM `settings` WHERE `key` = ?");
        if (!$stmt) {
            return $default;
        }
        
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $value = $row['value'];
            $type = $row['type'];
            
            // Cast value based on type
            switch ($type) {
                case 'number':
                case 'integer':
                    $value = floatval($value);
                    break;
                case 'boolean':
                case 'bool':
                    $value = (bool)$value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
                case 'textarea':
                case 'text':
                default:
                    $value = (string)$value;
            }
            
            self::$cache[$key] = $value;
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Get all settings in a group
     */
    public static function getGroup($groupName) {
        $db = self::getDB();
        
        $stmt = $db->prepare("SELECT `key`, `value`, `type` FROM `settings` WHERE `group_name` = ?");
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param("s", $groupName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $value = $row['value'];
            switch ($row['type']) {
                case 'number':
                case 'integer':
                    $value = floatval($value);
                    break;
                case 'boolean':
                case 'bool':
                    $value = (bool)$value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            $settings[$row['key']] = $value;
        }
        
        return $settings;
    }
    
    /**
     * Update a setting
     */
    public static function set($key, $value, $updatedBy = null) {
        $db = self::getDB();
        
        // Get current setting type
        $stmt = $db->prepare("SELECT `type` FROM `settings` WHERE `key` = ?");
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Convert value based on type
            switch ($row['type']) {
                case 'json':
                    $value = json_encode($value);
                    break;
                case 'boolean':
                case 'bool':
                    $value = $value ? '1' : '0';
                    break;
                default:
                    $value = (string)$value;
            }
            
            $stmt = $db->prepare("UPDATE `settings` SET `value` = ?, `updated_by` = ?, `updated_at` = NOW() WHERE `key` = ?");
            if (!$stmt) {
                return false;
            }
            
            $stmt->bind_param("sis", $value, $updatedBy, $key);
            $stmt->execute();
            
            // Clear cache
            unset(self::$cache[$key]);
            
            return $stmt->affected_rows > 0;
        }
        
        return false;
    }
    
    /**
     * Check if funding is enabled
     */
    public static function isFundingEnabled() {
        return (bool)self::get('funding_enabled', true);
    }
    
    /**
     * Get funding instructions with bank details
     */
    public static function getFundingInstructions() {
        $instructions = self::get('funding_instructions', '');
        $bankName = self::get('bank_name', '');
        $bankAccount = self::get('bank_account_number', '');
        $bankAccountName = self::get('bank_account_name', '');
        $paymentNote = self::get('payment_note', '');
        
        // Build complete instructions with bank details
        $bankDetails = '';
        if ($bankName && $bankAccount && $bankAccountName) {
            $bankDetails = "\n\n=========================================\n";
            $bankDetails .= "BANK DETAILS FOR PAYMENT\n";
            $bankDetails .= "=========================================\n";
            $bankDetails .= "Bank: {$bankName}\n";
            $bankDetails .= "Account Number: {$bankAccount}\n";
            $bankDetails .= "Account Name: {$bankAccountName}\n";
            $bankDetails .= "=========================================\n";
        }
        
        $note = $paymentNote ? "\n\nNOTE: {$paymentNote}" : '';
        
        return trim($instructions . $bankDetails . $note);
    }
    
    /**
     * Get all financial settings
     */
    public static function getFinancialSettings() {
        return self::getGroup('financial');
    }
    
    /**
     * Clear all cached settings
     */
    public static function clearCache() {
        self::$cache = [];
    }
}
?>