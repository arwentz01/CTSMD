SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    email_schedule TINYINT(1) NOT NULL DEFAULT 1,
    email_forms TINYINT(1) NOT NULL DEFAULT 1,
    email_volunteer TINYINT(1) NOT NULL DEFAULT 1,
    email_community TINYINT(1) NOT NULL DEFAULT 1,
    email_account_security TINYINT(1) NOT NULL DEFAULT 1,
    digest_mode ENUM('immediate','daily') NOT NULL DEFAULT 'immediate',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(190) NOT NULL,
    recipient_name VARCHAR(190) NULL,
    category ENUM('account_security','schedule','forms','volunteer','community','digest','system') NOT NULL DEFAULT 'system',
    subject VARCHAR(255) NOT NULL,
    text_body MEDIUMTEXT NOT NULL,
    html_body MEDIUMTEXT NULL,
    status ENUM('queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    last_attempt_at DATETIME NULL,
    last_error VARCHAR(1000) NULL,
    dedupe_key VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_queue_dedupe (dedupe_key),
    KEY idx_email_queue_delivery (status,available_at,id),
    KEY idx_email_queue_user (user_id,created_at),
    CONSTRAINT fk_email_queue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_delivery_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_queue_id BIGINT UNSIGNED NOT NULL,
    transport VARCHAR(40) NOT NULL,
    outcome ENUM('sent','failed') NOT NULL,
    detail VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_email_delivery_queue (email_queue_id,created_at),
    CONSTRAINT fk_email_delivery_queue FOREIGN KEY (email_queue_id) REFERENCES email_queue(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
