-- database.sql
CREATE DATABASE IF NOT EXISTS vtu_platform;
USE vtu_platform;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    wallet_balance DECIMAL(15,2) DEFAULT 0.00,
    bonus_balance DECIMAL(15,2) DEFAULT 0.00,
    referral_code VARCHAR(20) UNIQUE,
    referred_by INT,
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'suspended') DEFAULT 'active',
    last_login DATETIME,
    last_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Wallets table
CREATE TABLE wallets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wallet transactions
CREATE TABLE wallet_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reference VARCHAR(100) UNIQUE NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_before DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Transactions table
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    type ENUM('airtime', 'data', 'electricity', 'cable', 'wallet_funding') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    fee DECIMAL(15,2) DEFAULT 0.00,
    network VARCHAR(50),
    phone_number VARCHAR(15),
    meter_number VARCHAR(50),
    smart_card VARCHAR(50),
    data_plan VARCHAR(100),
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    api_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Settings table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin (password: Admin@123)
INSERT INTO users (username, email, phone, password, first_name, last_name, role, email_verified, status, wallet_balance) VALUES 
('admin', 'admin@vtuplatform.com', '08000000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super', 'Admin', 'admin', TRUE, 'active', 1000000.00),
('demo', 'demo@vtuplatform.com', '08011111111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo', 'User', 'user', TRUE, 'active', 5000.00);

-- Create wallets for default users
INSERT INTO wallets (user_id, balance) VALUES 
(1, 1000000.00),
(2, 5000.00);

-- Insert default settings
INSERT INTO settings (`key`, `value`) VALUES
('site_name', 'VTU Platform'),
('site_email', 'support@vtuplatform.com'),
('site_phone', '08012345678'),
('min_airtime', '50'),
('max_airtime', '50000'),
('referral_bonus', '100'),
('referral_percentage', '5');



-- Table for funding requests
CREATE TABLE IF NOT EXISTS funding_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reference VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'bank_transfer',
    payment_details TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT,
    approved_by INT,
    approved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('min_funding_amount', '100', 'number', 'Minimum amount users can fund'),
('max_funding_amount', '1000000', 'number', 'Maximum amount users can fund'),
('funding_instructions', 'Please make payment to:\n\nBank: Example Bank\nAccount Number: 1234567890\nAccount Name: VTU Platform\n\nAfter payment, upload your payment proof.', 'textarea', 'Instructions for users when funding wallet'),
('auto_approve_under', '0', 'number', 'Auto-approve funding requests under this amount (0 = disabled)'),
('funding_enabled', '1', 'boolean', 'Enable/disable manual funding');

-- Table for payment proofs
CREATE TABLE IF NOT EXISTS payment_proofs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    funding_request_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255),
    file_type VARCHAR(50),
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funding_request_id) REFERENCES funding_requests(id) ON DELETE CASCADE,
    INDEX idx_request (funding_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add funding_request_id to wallet_transactions if not exists
ALTER TABLE wallet_transactions 
ADD COLUMN IF NOT EXISTS funding_request_id INT NULL,
ADD INDEX idx_funding_request (funding_request_id);