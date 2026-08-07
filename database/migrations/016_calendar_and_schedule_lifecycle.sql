SET NAMES utf8mb4;

ALTER TABLE schedule_items
    ADD COLUMN status ENUM('active','cancelled') NOT NULL DEFAULT 'active' AFTER item_type,
    ADD COLUMN cancelled_at DATETIME NULL AFTER status,
    ADD COLUMN cancelled_by_user_id BIGINT UNSIGNED NULL AFTER cancelled_at,
    ADD COLUMN duplicate_of_id BIGINT UNSIGNED NULL AFTER cancelled_by_user_id,
    ADD KEY idx_schedule_status_start (status,starts_at),
    ADD CONSTRAINT fk_schedule_cancelled_by FOREIGN KEY (cancelled_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_schedule_duplicate_of FOREIGN KEY (duplicate_of_id) REFERENCES schedule_items(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS calendar_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rotated_at DATETIME NULL,
    UNIQUE KEY uq_calendar_subscription_user (user_id),
    KEY idx_calendar_subscription_token (token,active),
    CONSTRAINT fk_calendar_subscription_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
