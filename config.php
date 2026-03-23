<?php
// config.php - Main configuration file (Database Driven)

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Africa/Lagos');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'vtu_system');

// Site configuration - Defaults (will be overridden by database)
define('SITE_URL', 'http://localhost:8081');
define('SITE_NAME_DEFAULT', 'VTU Hub');
define('SITE_TAGLINE_DEFAULT', 'Pay Bills Instantly');
define('SITE_EMAIL_DEFAULT', 'support@vtuhub.com');
define('SITE_PHONE_DEFAULT', '+234 800 000 0000');

// Currency
define('CURRENCY_DEFAULT', '₦');
define('CURRENCY_CODE_DEFAULT', 'NGN');

// Security
define('SECRET_KEY', 'your-secret-key-here-change-this-2024');
define('SESSION_NAME', 'vtu_session');
define('SESSION_LIFETIME', 7200); // 2 hours

// API Keys (replace with your actual keys) - Will be overridden by database
define('VTPASS_API_KEY_DEFAULT', 'your-vtpass-api-key');
define('VTPASS_SECRET_DEFAULT', 'your-vtpass-secret');
define('VTPASS_URL_DEFAULT', 'https://api-service.vtpass.com/api');

define('PAYSTACK_PUBLIC_KEY_DEFAULT', 'your-paystack-public-key');
define('PAYSTACK_SECRET_KEY_DEFAULT', 'your-paystack-secret-key');

// File uploads
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Limits - Defaults
define('MIN_AIRTIME_DEFAULT', 50);
define('MAX_AIRTIME_DEFAULT', 50000);
define('MIN_DATA_DEFAULT', 100);
define('MIN_ELECTRICITY_DEFAULT', 500);
define('REFERRAL_BONUS_DEFAULT', 100);
define('REFERRAL_PERCENTAGE_DEFAULT', 5);

// Global variables for settings (will be loaded from database)
$site_settings = null;

/**
 * Load settings from database
 * This function should be called after database connection is established
 */
function loadSiteSettings() {
    global $site_settings;
    
    if ($site_settings !== null) {
        return $site_settings;
    }
    
    try {
        require_once __DIR__ . '/includes/db_connection.php';
        $db = Database::getInstance()->getConnection();
        
        $result = $db->query("SELECT `key`, `value` FROM settings");
        $site_settings = [];
        
        while ($row = $result->fetch_assoc()) {
            $site_settings[$row['key']] = $row['value'];
        }
        
        return $site_settings;
    } catch (Exception $e) {
        // If database not available yet, return empty array
        $site_settings = [];
        return $site_settings;
    }
}

/**
 * Get site setting by key
 */
function getSiteSetting($key, $default = null) {
    global $site_settings;
    
    if ($site_settings === null) {
        loadSiteSettings();
    }
    
    return isset($site_settings[$key]) ? $site_settings[$key] : $default;
}

/**
 * Get site name from database or default
 */
function getSiteName() {
    return getSiteSetting('site_name', SITE_NAME_DEFAULT);
}

/**
 * Get site tagline from database or default
 */
function getSiteTagline() {
    return getSiteSetting('site_tagline', SITE_TAGLINE_DEFAULT);
}

/**
 * Get site email from database or default
 */
function getSiteEmail() {
    return getSiteSetting('site_email', SITE_EMAIL_DEFAULT);
}

/**
 * Get site phone from database or default
 */
function getSitePhone() {
    return getSiteSetting('site_phone', SITE_PHONE_DEFAULT);
}

/**
 * Get currency symbol from database or default
 */
function getCurrency() {
    return getSiteSetting('currency', CURRENCY_DEFAULT);
}

/**
 * Get currency code from database or default
 */
function getCurrencyCode() {
    return getSiteSetting('currency_code', CURRENCY_CODE_DEFAULT);
}

/**
 * Get minimum airtime amount
 */
function getMinAirtime() {
    return (float)getSiteSetting('min_airtime', MIN_AIRTIME_DEFAULT);
}

/**
 * Get maximum airtime amount
 */
function getMaxAirtime() {
    return (float)getSiteSetting('max_airtime', MAX_AIRTIME_DEFAULT);
}

/**
 * Get minimum data amount
 */
function getMinData() {
    return (float)getSiteSetting('min_data', MIN_DATA_DEFAULT);
}

/**
 * Get minimum electricity amount
 */
function getMinElectricity() {
    return (float)getSiteSetting('min_electricity', MIN_ELECTRICITY_DEFAULT);
}

/**
 * Get referral bonus amount
 */
function getReferralBonus() {
    return (float)getSiteSetting('referral_bonus', REFERRAL_BONUS_DEFAULT);
}

/**
 * Get referral percentage
 */
function getReferralPercentage() {
    return (float)getSiteSetting('referral_percentage', REFERRAL_PERCENTAGE_DEFAULT);
}

/**
 * Get VTpass API key
 */
function getVTPassApiKey() {
    return getSiteSetting('vtpass_api_key', VTPASS_API_KEY_DEFAULT);
}

/**
 * Get VTpass API secret
 */
function getVTPassSecret() {
    return getSiteSetting('vtpass_secret', VTPASS_SECRET_DEFAULT);
}

/**
 * Get VTpass API URL
 */
function getVTPassUrl() {
    return getSiteSetting('vtpass_api_url', VTPASS_URL_DEFAULT);
}

/**
 * Get Paystack public key
 */
function getPaystackPublicKey() {
    return getSiteSetting('paystack_public_key', PAYSTACK_PUBLIC_KEY_DEFAULT);
}

/**
 * Get Paystack secret key
 */
function getPaystackSecretKey() {
    return getSiteSetting('paystack_secret_key', PAYSTACK_SECRET_KEY_DEFAULT);
}

/**
 * Get site URL
 */
function getSiteUrl() {
    return getSiteSetting('site_url', SITE_URL);
}

/**
 * Get maintenance mode status
 */
function isMaintenanceMode() {
    return getSiteSetting('maintenance_mode', 'false') === 'true';
}

/**
 * Check if registration is allowed
 */
function isRegistrationAllowed() {
    return getSiteSetting('allow_registration', 'true') === 'true';
}

/**
 * Get theme color
 */
function getPrimaryColor() {
    return getSiteSetting('primary_color', '#6366f1');
}

/**
 * Get secondary color
 */
function getSecondaryColor() {
    return getSiteSetting('secondary_color', '#8b5cf6');
}

// Load settings immediately
loadSiteSettings();

// Define constants from database (with fallbacks)
define('SITE_NAME', getSiteName());
define('SITE_TAGLINE', getSiteTagline());
define('SITE_EMAIL', getSiteEmail());
define('SITE_PHONE', getSitePhone());
define('CURRENCY', getCurrency());
define('CURRENCY_CODE', getCurrencyCode());
define('MIN_AIRTIME', getMinAirtime());
define('MAX_AIRTIME', getMaxAirtime());
define('MIN_DATA', getMinData());
define('MIN_ELECTRICITY', getMinElectricity());
define('REFERRAL_BONUS', getReferralBonus());
define('REFERRAL_PERCENTAGE', getReferralPercentage());

// API constants
define('VTPASS_API_KEY', getVTPassApiKey());
define('VTPASS_SECRET', getVTPassSecret());
define('VTPASS_URL', getVTPassUrl());
define('PAYSTACK_PUBLIC_KEY', getPaystackPublicKey());
define('PAYSTACK_SECRET_KEY', getPaystackSecretKey());

// Optional: Load dynamic site URL
if (!defined('SITE_URL')) {
    define('SITE_URL', getSiteUrl());
}

// Session configuration
session_name(SESSION_NAME);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>