-- ============================================================
-- SRMS Security Upgrade - Modules 5 and 6
-- Adds security event logging and temporary IP blocking tables only.
-- ============================================================

CREATE TABLE IF NOT EXISTS security_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT NULL,
    ip_address VARCHAR(45) NULL,
    request_method VARCHAR(10) NOT NULL,
    request_path VARCHAR(255) NOT NULL,
    input_field VARCHAR(100) NULL,
    input_preview VARCHAR(500) NULL,
    matched_patterns TEXT NULL,
    risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    severity ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    action_taken ENUM('logged', 'blocked', 'temporarily_blocked') NOT NULL DEFAULT 'blocked',
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_security_logs_created_at (created_at),
    INDEX idx_security_logs_ip (ip_address),
    INDEX idx_security_logs_severity (severity),
    INDEX idx_security_logs_action (action_taken)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blocked_ips (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    reason VARCHAR(255) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    blocked_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    manually_blocked TINYINT(1) NOT NULL DEFAULT 0,
    unblocked_at DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_blocked_ips_address (ip_address),
    INDEX idx_blocked_ips_expiry (expires_at),
    INDEX idx_blocked_ips_status (ip_address, unblocked_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
