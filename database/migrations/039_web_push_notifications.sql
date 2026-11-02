-- CTSMD Connect migration 039: cross-platform Web Push notifications

ALTER TABLE notification_preferences
    ADD COLUMN push_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER email_account_security,
    ADD COLUMN push_schedule TINYINT(1) NOT NULL DEFAULT 1 AFTER push_enabled,
    ADD COLUMN push_forms TINYINT(1) NOT NULL DEFAULT 1 AFTER push_schedule,
    ADD COLUMN push_volunteer TINYINT(1) NOT NULL DEFAULT 1 AFTER push_forms,
    ADD COLUMN push_community TINYINT(1) NOT NULL DEFAULT 1 AFTER push_volunteer,
    ADD COLUMN push_messages TINYINT(1) NOT NULL DEFAULT 1 AFTER push_community;

CREATE TABLE push_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth_secret VARCHAR(255) NOT NULL,
    user_agent VARCHAR(500) NULL,
    device_label VARCHAR(120) NULL,
    platform VARCHAR(32) NULL,
    status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',
    last_seen_at DATETIME NULL,
    last_success_at DATETIME NULL,
    last_failure_at DATETIME NULL,
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_push_endpoint_hash (endpoint_hash),
    KEY idx_push_user_status (user_id,status),
    CONSTRAINT fk_push_subscription_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE push_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    category ENUM('schedule','forms','volunteer','community','messages','general') NOT NULL DEFAULT 'general',
    title VARCHAR(190) NOT NULL,
    body VARCHAR(1000) NOT NULL,
    action_path VARCHAR(500) NULL,
    icon_path VARCHAR(500) NULL,
    badge_path VARCHAR(500) NULL,
    tag VARCHAR(64) NULL,
    urgency ENUM('very-low','low','normal','high') NOT NULL DEFAULT 'normal',
    status ENUM('queued','processing','sent','partial','failed','suppressed') NOT NULL DEFAULT 'queued',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at DATETIME NULL,
    completed_at DATETIME NULL,
    last_error VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_push_queue_claim (status,available_at,id),
    KEY idx_push_queue_user (user_id,created_at),
    CONSTRAINT fk_push_queue_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE push_delivery_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    http_status SMALLINT UNSIGNED NULL,
    result ENUM('sent','expired','rejected','error') NOT NULL,
    error_message VARCHAR(1000) NULL,
    delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_push_delivery_queue (queue_id),
    KEY idx_push_delivery_subscription (subscription_id),
    CONSTRAINT fk_push_delivery_queue FOREIGN KEY (queue_id) REFERENCES push_queue(id),
    CONSTRAINT fk_push_delivery_subscription FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id)
);
