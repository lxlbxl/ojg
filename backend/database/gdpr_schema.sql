-- GDPR & NDPR Compliance Database Schema
-- Run this to add compliance tables to your database

-- Consent Records Table
-- Stores audit trail of all user consents (required by NDPR)
CREATE TABLE IF NOT EXISTS consent_records (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT NULL,
    session_id VARCHAR(100) NULL,
    email VARCHAR(255) NULL,
    category VARCHAR(50) NOT NULL,
    consent_given TINYINT(1) NOT NULL DEFAULT 0,
    consent_version VARCHAR(20) NOT NULL DEFAULT '1.0',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    page_url TEXT NULL,
    withdrawn_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_email (email),
    INDEX idx_user_id (user_id),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at)
);

-- Data Subject Access Requests Table
-- Tracks user requests for data access, deletion, etc.
CREATE TABLE IF NOT EXISTS data_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    request_type VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    requested_data TEXT NULL,
    processed_by INT NULL,
    processed_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_request_type (request_type)
);

-- Data Breaches Log Table
-- Required by GDPR for 72-hour breach notification compliance
CREATE TABLE IF NOT EXISTS data_breaches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    breach_type VARCHAR(100) NOT NULL,
    severity VARCHAR(50) NOT NULL,
    affected_users INT NULL,
    description TEXT NULL,
    discovered_at DATETIME NOT NULL,
    reported_to_authority TINYINT(1) DEFAULT 0,
    reported_at DATETIME NULL,
    notified_users TINYINT(1) DEFAULT 0,
    notification_sent_at DATETIME NULL,
    resolved_at DATETIME NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'investigating',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_status (status),
    INDEX idx_severity (severity)
);

-- Compliance Settings Table
-- Stores privacy configuration
CREATE TABLE IF NOT EXISTS compliance_settings (
    id INT PRIMARY KEY,
    data_retention_days INT NOT NULL DEFAULT 730,
    cookie_consent_required TINYINT(1) NOT NULL DEFAULT 1,
    marketing_consent_required TINYINT(1) NOT NULL DEFAULT 1,
    privacy_policy_version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
    dpo_email VARCHAR(255) NULL,
    consent_record_retention INT NOT NULL DEFAULT 1095,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Add deleted_at column to users table for soft deletes (GDPR erasure)
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

-- Add consent_recorded column to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS consent_recorded TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS consent_date DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS marketing_opt_in TINYINT(1) DEFAULT 0;

-- Insert default compliance settings
INSERT OR IGNORE INTO compliance_settings (id, data_retention_days, cookie_consent_required, marketing_consent_required, privacy_policy_version, dpo_email, consent_record_retention)
VALUES (1, 730, 1, 1, '1.0.0', '', 1095);

-- SQLite version (for SQLite deployments)
-- Run this separately if using SQLite instead of MySQL

-- SQLite Consent Records
CREATE TABLE IF NOT EXISTS consent_records_sqlite (
    id TEXT PRIMARY KEY,
    user_id INTEGER NULL,
    session_id TEXT NULL,
    email TEXT NULL,
    category TEXT NOT NULL,
    consent_given INTEGER NOT NULL DEFAULT 0,
    consent_version TEXT NOT NULL DEFAULT '1.0',
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    page_url TEXT NULL,
    withdrawn_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_consent_email ON consent_records_sqlite(email);
CREATE INDEX IF NOT EXISTS idx_consent_user_id ON consent_records_sqlite(user_id);
CREATE INDEX IF NOT EXISTS idx_consent_category ON consent_records_sqlite(category);
CREATE INDEX IF NOT EXISTS idx_consent_created ON consent_records_sqlite(created_at);

-- SQLite Data Requests
CREATE TABLE IF NOT EXISTS data_requests_sqlite (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    request_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    requested_data TEXT NULL,
    processed_by INTEGER NULL,
    processed_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_req_email ON data_requests_sqlite(email);
CREATE INDEX IF NOT EXISTS idx_req_status ON data_requests_sqlite(status);

-- SQLite Data Breaches
CREATE TABLE IF NOT EXISTS data_breaches_sqlite (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    breach_type TEXT NOT NULL,
    severity TEXT NOT NULL,
    affected_users INTEGER NULL,
    description TEXT NULL,
    discovered_at DATETIME NOT NULL,
    reported_to_authority INTEGER DEFAULT 0,
    reported_at DATETIME NULL,
    notified_users INTEGER DEFAULT 0,
    notification_sent_at DATETIME NULL,
    resolved_at DATETIME NULL,
    status TEXT NOT NULL DEFAULT 'investigating',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

-- SQLite Compliance Settings
CREATE TABLE IF NOT EXISTS compliance_settings_sqlite (
    id INTEGER PRIMARY KEY,
    data_retention_days INTEGER NOT NULL DEFAULT 730,
    cookie_consent_required INTEGER NOT NULL DEFAULT 1,
    marketing_consent_required INTEGER NOT NULL DEFAULT 1,
    privacy_policy_version TEXT NOT NULL DEFAULT '1.0.0',
    dpo_email TEXT NULL,
    consent_record_retention INTEGER NOT NULL DEFAULT 1095,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default settings for SQLite
INSERT OR IGNORE INTO compliance_settings_sqlite (id, data_retention_days, cookie_consent_required, marketing_consent_required, privacy_policy_version, dpo_email, consent_record_retention)
VALUES (1, 730, 1, 1, '1.0.0', '', 1095);