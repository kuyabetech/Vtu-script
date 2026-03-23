-- =====================================================
-- Professional VTU Platform Database
-- Version: 2.0
-- Description: Complete database for VTU and Bill Payment System
-- =====================================================

-- Drop database if exists (be careful with this in production!)
DROP DATABASE IF EXISTS vtu_platform;
CREATE DATABASE vtu_platform;
USE vtu_platform;

-- =====================================================
-- Users Management
-- =====================================================

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    country VARCHAR(50) DEFAULT 'Nigeria',
    wallet_balance DECIMAL(15,2) DEFAULT 0.00,
    bonus_balance DECIMAL(15,2) DEFAULT 0.00,
    referral_code VARCHAR(20) UNIQUE,
    referred_by INT,
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    email_verified_at DATETIME,
    phone_verified_at DATETIME,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    two_factor_backup_codes TEXT,
    role ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
    status ENUM('active', 'suspended', 'banned', 'pending') DEFAULT 'pending',
    last_login DATETIME,
    last_ip VARCHAR(45),
    login_attempts INT DEFAULT 0,
    locked_until DATETIME,
    avatar VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_referral (referral_code),
    INDEX idx_status (status),
    INDEX idx_role (role),
    INDEX idx_created (created_at),
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Wallet Management
-- =====================================================

-- Wallets table (multi-currency support)
CREATE TABLE wallets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    currency VARCHAR(10) DEFAULT 'NGN',
    balance DECIMAL(15,2) DEFAULT 0.00,
    locked_balance DECIMAL(15,2) DEFAULT 0.00,
    last_credited_at DATETIME,
    last_debited_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_currency (currency),
    UNIQUE KEY unique_user_currency (user_id, currency),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wallet transactions
CREATE TABLE wallet_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    wallet_id INT NOT NULL,
    user_id INT NOT NULL,
    reference VARCHAR(100) UNIQUE NOT NULL,
    type ENUM('credit', 'debit', 'lock', 'unlock') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_before DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed', 'failed', 'reversed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    metadata JSON,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_wallet (wallet_id),
    INDEX idx_user (user_id),
    INDEX idx_reference (reference),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Transactions Management
-- =====================================================

-- Main transactions table
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    wallet_id INT,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    reference VARCHAR(100) UNIQUE,
    type ENUM('airtime', 'data', 'electricity', 'cable', 'exam', 'wallet_funding', 'wallet_transfer', 'referral_bonus', 'commission', 'withdrawal') NOT NULL,
    service VARCHAR(50),
    category VARCHAR(50),
    amount DECIMAL(15,2) NOT NULL,
    fee DECIMAL(15,2) DEFAULT 0.00,
    discount DECIMAL(15,2) DEFAULT 0.00,
    total DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'NGN',
    network VARCHAR(50),
    phone_number VARCHAR(15),
    meter_number VARCHAR(50),
    smart_card VARCHAR(50),
    data_plan VARCHAR(100),
    variation_code VARCHAR(100),
    customer_name VARCHAR(100),
    customer_address TEXT,
    customer_number VARCHAR(50),
    token VARCHAR(255),
    units DECIMAL(10,2),
    status ENUM('pending', 'processing', 'success', 'failed', 'reversed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    provider VARCHAR(50),
    provider_reference VARCHAR(100),
    provider_status VARCHAR(50),
    api_request TEXT,
    api_response TEXT,
    api_status_code VARCHAR(10),
    retry_count INT DEFAULT 0,
    webhook_url TEXT,
    webhook_sent BOOLEAN DEFAULT FALSE,
    webhook_response TEXT,
    webhook_attempts INT DEFAULT 0,
    callback_url TEXT,
    callback_sent BOOLEAN DEFAULT FALSE,
    metadata JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    processed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_wallet_id (wallet_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_reference (reference),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_provider (provider),
    INDEX idx_phone (phone_number),
    INDEX idx_created (created_at),
    INDEX idx_processed (processed_at),
    INDEX idx_user_status (user_id, status),
    FULLTEXT idx_search (transaction_id, reference, provider_reference),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction logs for auditing
CREATE TABLE transaction_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    old_data JSON,
    new_data JSON,
    notes TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_transaction (transaction_id),
    INDEX idx_action (action),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- API Providers Management
-- =====================================================

-- API Providers table
CREATE TABLE api_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    api_key TEXT NOT NULL,
    api_secret TEXT,
    api_username VARCHAR(100),
    api_password VARCHAR(255),
    api_url VARCHAR(255),
    sandbox_url VARCHAR(255),
    wallet_balance DECIMAL(15,2) DEFAULT 0.00,
    balance_threshold DECIMAL(15,2) DEFAULT 10000.00,
    balance_alert_sent BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 1,
    timeout INT DEFAULT 30,
    retry_count INT DEFAULT 3,
    retry_delay INT DEFAULT 5,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    settings JSON,
    headers JSON,
    last_checked DATETIME,
    last_success DATETIME,
    last_error TEXT,
    error_count INT DEFAULT 0,
    success_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_priority (priority),
    INDEX idx_active (is_active),
    FULLTEXT idx_search (name, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Services table
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    category ENUM('airtime', 'data', 'electricity', 'cable', 'exam', 'giftcard', 'insurance', 'betting', 'education') NOT NULL,
    provider_id INT,
    description TEXT,
    logo VARCHAR(255),
    min_amount DECIMAL(15,2),
    max_amount DECIMAL(15,2),
    commission_rate DECIMAL(5,2) DEFAULT 0.00,
    discount_rate DECIMAL(5,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    is_popular BOOLEAN DEFAULT FALSE,
    requires_verification BOOLEAN DEFAULT FALSE,
    verification_fields JSON,
    settings JSON,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_provider (provider_id),
    INDEX idx_category (category),
    INDEX idx_code (code),
    INDEX idx_active (is_active),
    FOREIGN KEY (provider_id) REFERENCES api_providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service variations (data plans, cable packages, exam pins, etc.)
CREATE TABLE service_variations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    provider_variation_id VARCHAR(100),
    variation_code VARCHAR(100) NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    amount DECIMAL(15,2) NOT NULL,
    wholesale_price DECIMAL(15,2),
    retail_price DECIMAL(15,2),
    commission_amount DECIMAL(15,2) DEFAULT 0.00,
    bonus_amount DECIMAL(15,2) DEFAULT 0.00,
    bonus_percentage DECIMAL(5,2) DEFAULT 0.00,
    validity VARCHAR(50),
    size VARCHAR(20),
    network VARCHAR(50),
    category VARCHAR(100),
    subcategory VARCHAR(100),
    region VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    is_popular BOOLEAN DEFAULT FALSE,
    is_recommended BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0,
    min_quantity INT DEFAULT 1,
    max_quantity INT DEFAULT 1,
    stock INT DEFAULT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_service (service_id),
    INDEX idx_variation (variation_code),
    INDEX idx_provider_var (provider_variation_id),
    INDEX idx_network (network),
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_price (amount),
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Wallet Funding
-- =====================================================

-- Funding requests
CREATE TABLE funding_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    wallet_id INT,
    reference VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    fee DECIMAL(15,2) DEFAULT 0.00,
    total DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'NGN',
    payment_method VARCHAR(50) NOT NULL,
    payment_gateway VARCHAR(50) NOT NULL,
    payment_reference VARCHAR(100),
    gateway_response TEXT,
    status ENUM('pending', 'processing', 'success', 'failed', 'cancelled') DEFAULT 'pending',
    paid_at DATETIME,
    expires_at DATETIME,
    metadata JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_wallet (wallet_id),
    INDEX idx_reference (reference),
    INDEX idx_status (status),
    INDEX idx_gateway (payment_gateway),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Referrals System
-- =====================================================

-- Referrals tracking
CREATE TABLE referrals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    referrer_id INT NOT NULL,
    referred_id INT NOT NULL,
    commission_type ENUM('fixed', 'percentage') DEFAULT 'fixed',
    commission_amount DECIMAL(15,2) DEFAULT 0.00,
    commission_rate DECIMAL(5,2) DEFAULT 0.00,
    total_commission DECIMAL(15,2) DEFAULT 0.00,
    level INT DEFAULT 1,
    status ENUM('pending', 'active', 'paid', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_referrer (referrer_id),
    INDEX idx_referred (referred_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_referral (referrer_id, referred_id),
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Referral earnings
CREATE TABLE referral_earnings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    referral_id INT NOT NULL,
    user_id INT NOT NULL,
    transaction_id INT,
    amount DECIMAL(15,2) NOT NULL,
    type ENUM('signup_bonus', 'purchase_commission', 'level_commission') NOT NULL,
    level INT DEFAULT 1,
    status ENUM('pending', 'credited', 'failed') DEFAULT 'pending',
    credited_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_referral (referral_id),
    INDEX idx_user (user_id),
    INDEX idx_transaction (transaction_id),
    FOREIGN KEY (referral_id) REFERENCES referrals(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Notifications
-- =====================================================

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    action_url VARCHAR(255),
    action_text VARCHAR(100),
    is_read BOOLEAN DEFAULT FALSE,
    is_sent BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    sent_at DATETIME,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email queue
CREATE TABLE email_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    to_email VARCHAR(100) NOT NULL,
    to_name VARCHAR(100),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    alt_body TEXT,
    attachments JSON,
    priority INT DEFAULT 1,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_attempt DATETIME,
    error_message TEXT,
    sent_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMS queue
CREATE TABLE sms_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    phone VARCHAR(15) NOT NULL,
    message TEXT NOT NULL,
    sender_id VARCHAR(20),
    priority INT DEFAULT 1,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_attempt DATETIME,
    error_message TEXT,
    provider_response TEXT,
    sent_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_phone (phone),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- System Management
-- =====================================================

-- System settings
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    type ENUM('text', 'number', 'boolean', 'json', 'file', 'email', 'phone', 'url') DEFAULT 'text',
    group_name VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key (`key`),
    INDEX idx_group (group_name),
    INDEX idx_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API logs
CREATE TABLE api_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50),
    endpoint VARCHAR(255),
    method VARCHAR(10),
    request_headers TEXT,
    request_body TEXT,
    response_headers TEXT,
    response_body TEXT,
    status_code VARCHAR(10),
    response_time INT,
    ip_address VARCHAR(45),
    user_id INT,
    transaction_id VARCHAR(50),
    reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_provider (provider),
    INDEX idx_status (status_code),
    INDEX idx_user (user_id),
    INDEX idx_transaction (transaction_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_method VARCHAR(10),
    request_url VARCHAR(255),
    request_data TEXT,
    response_code INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_ip (ip_address),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Error logs
CREATE TABLE error_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    error_type VARCHAR(100),
    error_message TEXT,
    error_file VARCHAR(255),
    error_line INT,
    stack_trace TEXT,
    user_id INT,
    ip_address VARCHAR(45),
    request_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_type (error_type),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions table (for database session handling)
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_last_activity (last_activity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remember tokens (for "remember me" functionality)
CREATE TABLE remember_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Banks and Withdrawals
-- =====================================================

-- Banks table
CREATE TABLE banks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    short_code VARCHAR(10),
    logo VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User bank accounts
CREATE TABLE user_banks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    bank_id INT NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(20) NOT NULL,
    bank_code VARCHAR(20),
    recipient_code VARCHAR(100),
    is_default BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_bank (bank_id),
    UNIQUE KEY unique_account (user_id, account_number),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Withdrawal requests
CREATE TABLE withdrawals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_bank_id INT NOT NULL,
    reference VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    fee DECIMAL(15,2) DEFAULT 0.00,
    total DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'NGN',
    status ENUM('pending', 'processing', 'success', 'failed', 'cancelled') DEFAULT 'pending',
    provider VARCHAR(50),
    provider_reference VARCHAR(100),
    provider_response TEXT,
    approved_by INT,
    approved_at DATETIME,
    processed_at DATETIME,
    notes TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_bank (user_bank_id),
    INDEX idx_reference (reference),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_bank_id) REFERENCES user_banks(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Support Tickets
-- =====================================================

-- Support tickets
CREATE TABLE support_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ticket_id VARCHAR(50) UNIQUE NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    category VARCHAR(50),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'pending', 'resolved', 'closed') DEFAULT 'open',
    attachments JSON,
    assigned_to INT,
    resolved_at DATETIME,
    closed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket replies
CREATE TABLE ticket_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    attachments JSON,
    is_staff_reply BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_ticket (ticket_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insert Default Data
-- =====================================================

-- Insert default admin user (password: Admin@123)
INSERT INTO users (username, email, phone, password, first_name, last_name, role, email_verified, phone_verified, status, wallet_balance) VALUES 
('admin', 'admin@vtuplatform.com', '08000000000', '$2y$10$YourHashedPasswordHere', 'Super', 'Admin', 'super_admin', TRUE, TRUE, 'active', 1000000.00),
('demo', 'demo@vtuplatform.com', '08011111111', '$2y$10$YourHashedPasswordHere', 'Demo', 'User', 'user', TRUE, TRUE, 'active', 5000.00);

-- Create wallets for default users
INSERT INTO wallets (user_id, currency, balance) VALUES 
(1, 'NGN', 1000000.00),
(2, 'NGN', 5000.00);

-- Insert default settings
INSERT INTO settings (`key`, `value`, type, group_name, description) VALUES
('site_name', 'VTU Platform', 'text', 'general', 'Website name'),
('site_title', 'Best VTU Platform in Nigeria', 'text', 'general', 'Website title'),
('site_description', 'Buy airtime, data, pay bills instantly', 'text', 'general', 'Website description'),
('site_keywords', 'vtu, airtime, data, electricity, cable tv', 'text', 'general', 'SEO keywords'),
('site_email', 'support@vtuplatform.com', 'email', 'contact', 'Support email'),
('site_phone', '08012345678', 'phone', 'contact', 'Support phone'),
('site_address', 'Lagos, Nigeria', 'text', 'contact', 'Office address'),
('currency', '₦', 'text', 'financial', 'Currency symbol'),
('currency_code', 'NGN', 'text', 'financial', 'Currency code'),
('min_airtime', '50', 'number', 'limits', 'Minimum airtime purchase'),
('max_airtime', '50000', 'number', 'limits', 'Maximum airtime purchase'),
('min_data', '100', 'number', 'limits', 'Minimum data purchase'),
('min_electricity', '500', 'number', 'limits', 'Minimum electricity payment'),
('max_electricity', '100000', 'number', 'limits', 'Maximum electricity payment'),
('referral_bonus', '100', 'number', 'referral', 'Referral signup bonus'),
('referral_percentage', '5', 'number', 'referral', 'Referral commission percentage'),
('referral_levels', '3', 'number', 'referral', 'Number of referral levels'),
('maintenance_mode', 'false', 'boolean', 'system', 'Maintenance mode'),
('version', '2.0.0', 'text', 'system', 'System version'),
('allow_registration', 'true', 'boolean', 'system', 'Allow new registrations'),
('email_verification', 'true', 'boolean', 'security', 'Require email verification'),
('phone_verification', 'false', 'boolean', 'security', 'Require phone verification'),
('two_factor_auth', 'false', 'boolean', 'security', 'Enable 2FA'),
('session_lifetime', '120', 'number', 'security', 'Session lifetime in minutes'),
('max_login_attempts', '5', 'number', 'security', 'Maximum login attempts'),
('lockout_duration', '30', 'number', 'security', 'Lockout duration in minutes'),
('timezone', 'Africa/Lagos', 'text', 'system', 'Default timezone'),
('date_format', 'M d, Y h:i A', 'text', 'system', 'Date format'),
('items_per_page', '20', 'number', 'system', 'Items per page'),
('enable_api', 'true', 'boolean', 'api', 'Enable API access'),
('api_rate_limit', '60', 'number', 'api', 'API rate limit per minute'),
('enable_referrals', 'true', 'boolean', 'referral', 'Enable referral system'),
('enable_withdrawals', 'true', 'boolean', 'financial', 'Enable withdrawals'),
('min_withdrawal', '1000', 'number', 'financial', 'Minimum withdrawal amount'),
('max_withdrawal', '500000', 'number', 'financial', 'Maximum withdrawal amount'),
('withdrawal_fee', '50', 'number', 'financial', 'Withdrawal fee');

-- Insert API providers
INSERT INTO api_providers (name, code, api_url, sandbox_url, priority, is_active, is_default) VALUES
('VTpass', 'vtpass', 'https://api-service.vtpass.com/api', 'https://sandbox.vtpass.com/api', 1, true, true),
('VTU.ng', 'vtung', 'https://vtu.ng/api/v1', 'https://sandbox.vtu.ng/api/v1', 2, true, false),
('RapidBills', 'rapidbills', 'https://rapidbills.com/api', 'https://sandbox.rapidbills.com/api', 3, true, false),
('ClubKonnect', 'clubkonnect', 'https://clubkonnect.com/api', 'https://sandbox.clubkonnect.com/api', 4, false, false);

-- Insert services
INSERT INTO services (name, code, category, provider_id, min_amount, max_amount, is_active, is_popular) VALUES
('MTN Airtime', 'mtn_airtime', 'airtime', 1, 50, 50000, true, true),
('Glo Airtime', 'glo_airtime', 'airtime', 1, 50, 50000, true, true),
('Airtel Airtime', 'airtel_airtime', 'airtime', 1, 50, 50000, true, true),
('9mobile Airtime', '9mobile_airtime', 'airtime', 1, 50, 50000, true, true),
('MTN Data', 'mtn_data', 'data', 1, 100, 50000, true, true),
('Glo Data', 'glo_data', 'data', 1, 100, 50000, true, true),
('Airtel Data', 'airtel_data', 'data', 1, 100, 50000, true, true),
('9mobile Data', '9mobile_data', 'data', 1, 100, 50000, true, true),
('IKEDC Electricity', 'ikedc', 'electricity', 1, 500, 100000, true, true),
('EKEDC Electricity', 'ekedc', 'electricity', 1, 500, 100000, true, true),
('AEDC Electricity', 'aedc', 'electricity', 1, 500, 100000, true, true),
('PHED Electricity', 'phed', 'electricity', 1, 500, 100000, true, true),
('DStv', 'dstv', 'cable', 1, 1000, 50000, true, true),
('GOtv', 'gotv', 'cable', 1, 500, 20000, true, true),
('StarTimes', 'startimes', 'cable', 1, 500, 30000, true, true),
('WAEC PIN', 'waec', 'exam', 2, 5000, 50000, true, true),
('NECO PIN', 'neco', 'exam', 2, 5000, 50000, true, true),
('JAMB PIN', 'jamb', 'exam', 2, 5000, 50000, true, true);

-- Insert service variations (data plans)
INSERT INTO service_variations (service_id, provider_variation_id, variation_code, name, amount, wholesale_price, retail_price, network, validity, size) VALUES
-- MTN Data Plans
(5, 'mtn-1gb', 'mtn-1gb', 'MTN 1GB Daily', 500, 450, 500, 'mtn', '1 day', '1GB'),
(5, 'mtn-2gb', 'mtn-2gb', 'MTN 2GB Weekly', 1000, 900, 1000, 'mtn', '7 days', '2GB'),
(5, 'mtn-3gb', 'mtn-3gb', 'MTN 3GB Weekly', 1500, 1350, 1500, 'mtn', '7 days', '3GB'),
(5, 'mtn-5gb', 'mtn-5gb', 'MTN 5GB Monthly', 2500, 2250, 2500, 'mtn', '30 days', '5GB'),
(5, 'mtn-10gb', 'mtn-10gb', 'MTN 10GB Monthly', 5000, 4500, 5000, 'mtn', '30 days', '10GB'),
(5, 'mtn-20gb', 'mtn-20gb', 'MTN 20GB Monthly', 9500, 8550, 9500, 'mtn', '30 days', '20GB'),

-- Glo Data Plans
(6, 'glo-1gb', 'glo-1gb', 'Glo 1GB Daily', 450, 405, 450, 'glo', '1 day', '1GB'),
(6, 'glo-2gb', 'glo-2gb', 'Glo 2GB Weekly', 900, 810, 900, 'glo', '7 days', '2GB'),
(6, 'glo-3gb', 'glo-3gb', 'Glo 3GB Weekly', 1350, 1215, 1350, 'glo', '7 days', '3GB'),
(6, 'glo-5gb', 'glo-5gb', 'Glo 5GB Monthly', 2200, 1980, 2200, 'glo', '30 days', '5GB'),
(6, 'glo-10gb', 'glo-10gb', 'Glo 10GB Monthly', 4500, 4050, 4500, 'glo', '30 days', '10GB'),

-- Airtel Data Plans
(7, 'airtel-1gb', 'airtel-1gb', 'Airtel 1GB Daily', 480, 432, 480, 'airtel', '1 day', '1GB'),
(7, 'airtel-2gb', 'airtel-2gb', 'Airtel 2GB Weekly', 950, 855, 950, 'airtel', '7 days', '2GB'),
(7, 'airtel-3gb', 'airtel-3gb', 'Airtel 3GB Weekly', 1450, 1305, 1450, 'airtel', '7 days', '3GB'),
(7, 'airtel-5gb', 'airtel-5gb', 'Airtel 5GB Monthly', 2400, 2160, 2400, 'airtel', '30 days', '5GB'),
(7, 'airtel-10gb', 'airtel-10gb', 'Airtel 10GB Monthly', 4800, 4320, 4800, 'airtel', '30 days', '10GB'),

-- 9mobile Data Plans
(8, '9mobile-1gb', '9mobile-1gb', '9mobile 1GB Daily', 460, 414, 460, '9mobile', '1 day', '1GB'),
(8, '9mobile-2gb', '9mobile-2gb', '9mobile 2GB Weekly', 920, 828, 920, '9mobile', '7 days', '2GB'),
(8, '9mobile-3gb', '9mobile-3gb', '9mobile 3GB Weekly', 1380, 1242, 1380, '9mobile', '7 days', '3GB'),
(8, '9mobile-5gb', '9mobile-5gb', '9mobile 5GB Monthly', 2300, 2070, 2300, '9mobile', '30 days', '5GB'),
(8, '9mobile-10gb', '9mobile-10gb', '9mobile 10GB Monthly', 4600, 4140, 4600, '9mobile', '30 days', '10GB');

-- Insert cable TV packages
INSERT INTO service_variations (service_id, provider_variation_id, variation_code, name, amount, wholesale_price, retail_price, category) VALUES
-- DStv Packages
(13, 'dstv-padi', 'dstv-padi', 'DStv Padi', 2500, 2300, 2500, 'cable'),
(13, 'dstv-yanga', 'dstv-yanga', 'DStv Yanga', 4200, 3900, 4200, 'cable'),
(13, 'dstv-confam', 'dstv-confam', 'DStv Confam', 6200, 5800, 6200, 'cable'),
(13, 'dstv-asia', 'dstv-asia', 'DStv Asia', 7500, 7000, 7500, 'cable'),
(13, 'dstv-premium', 'dstv-premium', 'DStv Premium', 18500, 17500, 18500, 'cable'),
(13, 'dstv-pawa', 'dstv-pawa', 'DStv Pawa', 1800, 1650, 1800, 'cable'),

-- GOtv Packages
(14, 'gotv-small', 'gotv-small', 'GOtv Smallie', 1500, 1350, 1500, 'cable'),
(14, 'gotv-jinja', 'gotv-jinja', 'GOtv Jinja', 2500, 2300, 2500, 'cable'),
(14, 'gotv-max', 'gotv-max', 'GOtv Max', 3700, 3400, 3700, 'cable'),
(14, 'gotv-supa', 'gotv-supa', 'GOtv Supa', 5700, 5300, 5700, 'cable'),

-- StarTimes Packages
(15, 'startimes-nova', 'startimes-nova', 'StarTimes Nova', 1500, 1350, 1500, 'cable'),
(15, 'startimes-basic', 'startimes-basic', 'StarTimes Basic', 2500, 2300, 2500, 'cable'),
(15, 'startimes-classic', 'startimes-classic', 'StarTimes Classic', 3700, 3400, 3700, 'cable'),
(15, 'startimes-super', 'startimes-super', 'StarTimes Super', 5700, 5300, 5700, 'cable');

-- Insert banks
INSERT INTO banks (name, code, short_code) VALUES
('Access Bank', '044', 'ACCESS'),
('Citibank', '023', 'CITI'),
('Ecobank', '050', 'ECOBANK'),
('Fidelity Bank', '070', 'FIDELITY'),
('First Bank', '011', 'FIRST'),
('First City Monument Bank', '214', 'FCMB'),
('Guaranty Trust Bank', '058', 'GTB'),
('Heritage Bank', '030', 'HERITAGE'),
('Keystone Bank', '082', 'KEYSTONE'),
('Polaris Bank', '076', 'POLARIS'),
('Providus Bank', '101', 'PROVIDUS'),
('Stanbic IBTC Bank', '221', 'STANBIC'),
('Standard Chartered', '068', 'SCB'),
('Sterling Bank', '232', 'STERLING'),
('Suntrust Bank', '100', 'SUNTRUST'),
('Union Bank', '032', 'UNION'),
('United Bank for Africa', '033', 'UBA'),
('Unity Bank', '215', 'UNITY'),
('Wema Bank', '035', 'WEMA'),
('Zenith Bank', '057', 'ZENITH');

-- =====================================================
-- Create Views for Reporting
-- =====================================================

-- View for daily sales
CREATE VIEW daily_sales AS
SELECT 
    DATE(created_at) as sale_date,
    type,
    COUNT(*) as transaction_count,
    SUM(amount) as total_amount,
    SUM(fee) as total_fee,
    SUM(discount) as total_discount,
    SUM(amount) - SUM(fee) - SUM(discount) as net_amount
FROM transactions
WHERE status = 'success'
GROUP BY DATE(created_at), type;

-- View for user statistics
CREATE VIEW user_statistics AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.phone,
    u.created_at as registration_date,
    u.wallet_balance,
    u.bonus_balance,
    (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) as total_transactions,
    (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND status = 'success') as total_spent,
    (SELECT COUNT(*) FROM referrals WHERE referrer_id = u.id) as total_referrals,
    (SELECT SUM(commission_amount) FROM referrals WHERE referrer_id = u.id AND status = 'paid') as total_commission
FROM users u;

-- View for transaction summary
CREATE VIEW transaction_summary AS
SELECT 
    type,
    COUNT(*) as total_count,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(amount) as total_amount,
    SUM(fee) as total_fee,
    AVG(amount) as average_amount,
    MIN(amount) as min_amount,
    MAX(amount) as max_amount
FROM transactions
GROUP BY type;

-- =====================================================
-- Create Stored Procedures
-- =====================================================

DELIMITER $$

-- Procedure to process daily settlements
CREATE PROCEDURE process_daily_settlements()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_user_id INT;
    DECLARE v_total_commission DECIMAL(15,2);
    
    DECLARE cur CURSOR FOR 
        SELECT referrer_id, SUM(commission_amount) as total
        FROM referrals 
        WHERE status = 'pending' 
        AND DATE(earned_at) = CURDATE() - INTERVAL 1 DAY
        GROUP BY referrer_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    START TRANSACTION;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_user_id, v_total_commission;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Update user bonus balance
        UPDATE users 
        SET bonus_balance = bonus_balance + v_total_commission 
        WHERE id = v_user_id;
        
        -- Update referral status
        UPDATE referrals 
        SET status = 'paid', paid_at = NOW() 
        WHERE referrer_id = v_user_id 
        AND status = 'pending' 
        AND DATE(earned_at) = CURDATE() - INTERVAL 1 DAY;
        
        -- Create transaction record
        INSERT INTO transactions (user_id, transaction_id, type, amount, status, created_at)
        VALUES (v_user_id, CONCAT('BONUS-', UNIX_TIMESTAMP()), 'referral_bonus', v_total_commission, 'success', NOW());
    END LOOP;
    
    CLOSE cur;
    
    COMMIT;
END$$

-- Procedure to clean up old logs
CREATE PROCEDURE cleanup_old_logs(IN days INT)
BEGIN
    DELETE FROM api_logs WHERE created_at < NOW() - INTERVAL days DAY;
    DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL days DAY;
    DELETE FROM error_logs WHERE created_at < NOW() - INTERVAL days DAY;
    DELETE FROM sessions WHERE last_activity < UNIX_TIMESTAMP(NOW() - INTERVAL days DAY);
    DELETE FROM remember_tokens WHERE expires_at < NOW();
END$$

-- Procedure to get dashboard statistics
CREATE PROCEDURE get_dashboard_stats(IN p_user_id INT)
BEGIN
    -- User stats
    SELECT 
        (SELECT COUNT(*) FROM transactions WHERE user_id = p_user_id) as total_transactions,
        (SELECT SUM(amount) FROM transactions WHERE user_id = p_user_id AND status = 'success') as total_spent,
        (SELECT COUNT(*) FROM transactions WHERE user_id = p_user_id AND DATE(created_at) = CURDATE()) as today_transactions,
        (SELECT SUM(amount) FROM transactions WHERE user_id = p_user_id AND DATE(created_at) = CURDATE()) as today_spent,
        (SELECT COUNT(*) FROM referrals WHERE referrer_id = p_user_id) as total_referrals;
    
    -- Recent transactions
    SELECT * FROM transactions 
    WHERE user_id = p_user_id 
    ORDER BY created_at DESC 
    LIMIT 10;
END$$

DELIMITER ;

-- =====================================================
-- Create Triggers
-- =====================================================

-- Trigger to update wallet balance after transaction
DELIMITER $$
CREATE TRIGGER after_transaction_insert
AFTER INSERT ON transactions
FOR EACH ROW
BEGIN
    IF NEW.status = 'success' AND NEW.type IN ('wallet_funding') THEN
        UPDATE wallets 
        SET balance = balance + NEW.amount 
        WHERE user_id = NEW.user_id AND currency = NEW.currency;
    END IF;
    
    IF NEW.status = 'success' AND NEW.type IN ('airtime', 'data', 'electricity', 'cable') THEN
        UPDATE wallets 
        SET balance = balance - NEW.total 
        WHERE user_id = NEW.user_id AND currency = NEW.currency;
    END IF;
END$$

-- Trigger to log transaction status changes
CREATE TRIGGER after_transaction_update
AFTER UPDATE ON transactions
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO transaction_logs (transaction_id, action, old_status, new_status, old_data, new_data, ip_address, user_agent)
        VALUES (NEW.id, 'status_change', OLD.status, NEW.status, 
                JSON_OBJECT('amount', OLD.amount, 'reference', OLD.reference),
                JSON_OBJECT('amount', NEW.amount, 'reference', NEW.reference),
                NEW.ip_address, NEW.user_agent);
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- Create Indexes for Performance
-- =====================================================

-- Additional indexes for better query performance
CREATE INDEX idx_transactions_composite ON transactions(user_id, status, created_at);
CREATE INDEX idx_transactions_date ON transactions(DATE(created_at));
CREATE INDEX idx_users_referral ON users(referral_code) WHERE referral_code IS NOT NULL;
CREATE INDEX idx_service_variations_price ON service_variations(service_id, amount, is_active);
CREATE INDEX idx_api_logs_composite ON api_logs(provider, created_at, status_code);

-- =====================================================
-- Add Foreign Key Constraints
-- =====================================================

-- Add missing foreign key for transactions to api_providers
ALTER TABLE transactions 
ADD CONSTRAINT fk_transactions_provider 
FOREIGN KEY (provider) REFERENCES api_providers(code) ON DELETE SET NULL;

-- =====================================================
-- Grant Permissions (adjust as needed)
-- =====================================================

-- Create application user
-- CREATE USER 'vtu_user'@'localhost' IDENTIFIED BY 'secure_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON vtu_platform.* TO 'vtu_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE vtu_platform.process_daily_settlements TO 'vtu_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE vtu_platform.cleanup_old_logs TO 'vtu_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE vtu_platform.get_dashboard_stats TO 'vtu_user'@'localhost';
-- FLUSH PRIVILEGES;

-- =====================================================
-- Database Optimization
-- =====================================================

-- Optimize tables
OPTIMIZE TABLE users;
OPTIMIZE TABLE transactions;
OPTIMIZE TABLE wallets;
OPTIMIZE TABLE api_providers;
OPTIMIZE TABLE service_variations;

-- Analyze tables
ANALYZE TABLE users;
ANALYZE TABLE transactions;
ANALYZE TABLE service_variations;

-- =====================================================
-- End of Database Schema
-- =====================================================

-- Success message
SELECT 'VTU Platform Database created successfully!' as 'Status';