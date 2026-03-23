-- =====================================================================
--                  VTU Platform – All Tables (clean version 2025/2026)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────────────
-- Users & Authentication
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE users (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username            VARCHAR(50)  NOT NULL UNIQUE,
    email               VARCHAR(100) NOT NULL UNIQUE,
    phone               VARCHAR(15)  NOT NULL UNIQUE,
    password            VARCHAR(255) NOT NULL,
    first_name          VARCHAR(50)           DEFAULT NULL,
    last_name           VARCHAR(50)           DEFAULT NULL,
    address             TEXT                  DEFAULT NULL,
    city                VARCHAR(50)           DEFAULT NULL,
    state               VARCHAR(50)           DEFAULT NULL,
    country             VARCHAR(50)           DEFAULT 'Nigeria',
    wallet_balance      DECIMAL(15,2)         DEFAULT 0.00,
    bonus_balance       DECIMAL(15,2)         DEFAULT 0.00,
    referral_code       VARCHAR(20)           DEFAULT NULL UNIQUE,
    referred_by         INT UNSIGNED          DEFAULT NULL,
    email_verified      TINYINT(1)            DEFAULT 0,
    phone_verified      TINYINT(1)            DEFAULT 0,
    email_verified_at   DATETIME              DEFAULT NULL,
    phone_verified_at   DATETIME              DEFAULT NULL,
    two_factor_enabled  TINYINT(1)            DEFAULT 0,
    two_factor_secret   VARCHAR(255)          DEFAULT NULL,
    two_factor_backup_codes TEXT              DEFAULT NULL,
    role                ENUM('user','admin','super_admin') DEFAULT 'user',
    status              ENUM('pending','active','suspended','banned') DEFAULT 'pending',
    last_login          DATETIME              DEFAULT NULL,
    last_ip             VARCHAR(45)           DEFAULT NULL,
    login_attempts      INT                   DEFAULT 0,
    locked_until        DATETIME              DEFAULT NULL,
    avatar              VARCHAR(255)          DEFAULT NULL,
    created_at          TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP             DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP             NULL DEFAULT NULL,

    PRIMARY KEY (id),
    INDEX idx_email       (email),
    INDEX idx_phone       (phone),
    INDEX idx_referral    (referral_code),
    INDEX idx_status      (status),
    INDEX idx_role        (role),
    INDEX idx_referred_by (referred_by),
    CONSTRAINT fk_users_referred_by FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- Wallets
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE wallets (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    currency        VARCHAR(10)  NOT NULL DEFAULT 'NGN',
    balance         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    locked_balance  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    last_credited_at DATETIME     DEFAULT NULL,
    last_debited_at  DATETIME     DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_wallet_user_currency (user_id, currency),
    INDEX idx_user (user_id),
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- Funding Requests (manual & online)
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE funding_requests (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL,
    wallet_id           INT UNSIGNED          DEFAULT NULL,
    reference           VARCHAR(100) NOT NULL UNIQUE,
    amount              DECIMAL(15,2) NOT NULL,
    fee                 DECIMAL(15,2) DEFAULT 0.00,
    total               DECIMAL(15,2) DEFAULT NULL,
    currency            VARCHAR(10)   DEFAULT 'NGN',
    payment_method      VARCHAR(50)   NOT NULL,
    payment_gateway     VARCHAR(50)           DEFAULT NULL,
    payment_reference   VARCHAR(100)          DEFAULT NULL,
    gateway_response    TEXT                  DEFAULT NULL,
    status              ENUM('pending','processing','success','failed','cancelled','rejected') DEFAULT 'pending',
    paid_at             DATETIME              DEFAULT NULL,
    expires_at          DATETIME              DEFAULT NULL,
    metadata            JSON                  DEFAULT NULL,
    ip_address          VARCHAR(45)           DEFAULT NULL,
    user_agent          TEXT                  DEFAULT NULL,
    bank_name           VARCHAR(100)          DEFAULT NULL,
    account_name        VARCHAR(100)          DEFAULT NULL,
    account_number      VARCHAR(50)           DEFAULT NULL,
    deposit_slip        VARCHAR(255)          DEFAULT NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_funding_reference (reference),
    INDEX idx_user_status (user_id, status),
    INDEX idx_reference   (reference),
    INDEX idx_status      (status),
    CONSTRAINT fk_funding_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_funding_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- Transactions (all kinds: purchases + funding + bonuses + withdrawals)
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE transactions (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL,
    wallet_id           INT UNSIGNED NOT NULL,
    transaction_id      VARCHAR(50)  NOT NULL UNIQUE,
    reference           VARCHAR(100)          DEFAULT NULL UNIQUE,
    type                ENUM('airtime','data','electricity','cable','exam','wallet_funding','wallet_transfer','referral_bonus','commission','withdrawal') NOT NULL,
    service             VARCHAR(50)           DEFAULT NULL,
    category            VARCHAR(50)           DEFAULT NULL,
    amount              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    fee                 DECIMAL(15,2)         DEFAULT 0.00,
    discount            DECIMAL(15,2)         DEFAULT 0.00,
    total               DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency            VARCHAR(10)           DEFAULT 'NGN',
    network             VARCHAR(50)           DEFAULT NULL,
    phone_number        VARCHAR(15)           DEFAULT NULL,
    meter_number        VARCHAR(50)           DEFAULT NULL,
    smart_card          VARCHAR(50)           DEFAULT NULL,
    data_plan           VARCHAR(100)          DEFAULT NULL,
    variation_code      VARCHAR(100)          DEFAULT NULL,
    status              ENUM('pending','processing','success','failed','reversed','refunded') DEFAULT 'pending',
    provider            VARCHAR(50)           DEFAULT NULL,
    provider_reference  VARCHAR(100)          DEFAULT NULL,
    api_request         TEXT                  DEFAULT NULL,
    api_response        TEXT                  DEFAULT NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_tx_transaction_id (transaction_id),
    INDEX idx_user_status     (user_id, status),
    INDEX idx_type_status     (type, status),
    INDEX idx_created         (created_at),
    CONSTRAINT fk_tx_user     FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_tx_wallet   FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- API Providers
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE api_providers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    code            VARCHAR(50)  NOT NULL UNIQUE,
    api_key         TEXT         NOT NULL,
    api_secret      TEXT                  DEFAULT NULL,
    api_url         VARCHAR(255)          DEFAULT NULL,
    sandbox_url     VARCHAR(255)          DEFAULT NULL,
    wallet_balance  DECIMAL(15,2)         DEFAULT 0.00,
    priority        INT                   DEFAULT 1,
    is_active       TINYINT(1)            DEFAULT 1,
    is_default      TINYINT(1)            DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_code     (code),
    INDEX idx_active   (is_active),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- Categories & Services
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE categories (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    code            VARCHAR(50)  NOT NULL UNIQUE,
    icon            VARCHAR(50)           DEFAULT 'fa-folder',
    display_order   INT                   DEFAULT 0,
    is_active       TINYINT(1)            DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE services (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    code            VARCHAR(50)  NOT NULL UNIQUE,
    category_id     INT UNSIGNED          DEFAULT NULL,
    provider_id     INT UNSIGNED          DEFAULT NULL,
    min_amount      DECIMAL(15,2)         DEFAULT NULL,
    max_amount      DECIMAL(15,2)         DEFAULT NULL,
    is_active       TINYINT(1)            DEFAULT 1,
    is_popular      TINYINT(1)            DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_code         (code),
    INDEX idx_category     (category_id),
    INDEX idx_provider     (provider_id),
    CONSTRAINT fk_service_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_service_provider FOREIGN KEY (provider_id) REFERENCES api_providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- Service Variations (plans, packages, pins…)
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE service_variations (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id          INT UNSIGNED NOT NULL,
    variation_code      VARCHAR(100) NOT NULL,
    name                VARCHAR(200) NOT NULL,
    amount              DECIMAL(15,2) NOT NULL,
    wholesale_price     DECIMAL(15,2)         DEFAULT NULL,
    retail_price        DECIMAL(15,2)         DEFAULT NULL,
    validity            VARCHAR(50)           DEFAULT NULL,
    network             VARCHAR(50)           DEFAULT NULL,
    is_active           TINYINT(1)            DEFAULT 1,
    is_popular          TINYINT(1)            DEFAULT 0,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_variation_service (service_id, variation_code),
    INDEX idx_service   (service_id),
    INDEX idx_amount    (amount),
    CONSTRAINT fk_variation_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- Banks & User Bank Accounts
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE banks (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    short_code  VARCHAR(10)           DEFAULT NULL,
    is_active   TINYINT(1)            DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE user_banks (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    bank_id         INT UNSIGNED NOT NULL,
    account_name    VARCHAR(100) NOT NULL,
    account_number  VARCHAR(20)  NOT NULL,
    bank_code       VARCHAR(20)           DEFAULT NULL,
    is_default      TINYINT(1)            DEFAULT 0,
    is_verified     TINYINT(1)            DEFAULT 0,
    verified_at     DATETIME              DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_user_account (user_id, account_number),
    INDEX idx_user (user_id),
    CONSTRAINT fk_userbank_user FOREIGN KEY (user_id) REFERENCES users(id)     ON DELETE CASCADE,
    CONSTRAINT fk_userbank_bank FOREIGN KEY (bank_id) REFERENCES banks(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- Referrals
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE referrals (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    referrer_id     INT UNSIGNED NOT NULL,
    referred_id     INT UNSIGNED NOT NULL,
    commission_amount DECIMAL(15,2) DEFAULT 0.00,
    level           INT          DEFAULT 1,
    status          ENUM('pending','active','paid','cancelled') DEFAULT 'pending',
    earned_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    paid_at         DATETIME              DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_referral_pair (referrer_id, referred_id),
    INDEX idx_referrer (referrer_id),
    INDEX idx_referred (referred_id),
    CONSTRAINT fk_ref_referrer FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ref_referred FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- Activity / Audit Logs (basic version)
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE activity_logs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED          DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    details     TEXT                  DEFAULT NULL,
    ip_address  VARCHAR(45)           DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_user_action (user_id, action),
    INDEX idx_created     (created_at),
    CONSTRAINT fk_actlog_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- Settings (global configuration)
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE settings (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`       VARCHAR(100) NOT NULL UNIQUE,
    `value`     TEXT,
    type        ENUM('text','number','boolean','json') DEFAULT 'text',
    group_name  VARCHAR(50)           DEFAULT 'general',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_key       (`key`),
    INDEX idx_group     (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;