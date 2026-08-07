SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS app_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(80) NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    action_path VARCHAR(255) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_source_recipient (source_type, source_id, recipient_user_id),
    KEY idx_notification_recipient (recipient_user_id, read_at, created_at),
    CONSTRAINT fk_app_notification_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_notice_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notice_id BIGINT UNSIGNED NOT NULL,
    destination_type ENUM('in_app','channel') NOT NULL,
    destination_id BIGINT UNSIGNED NULL,
    recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notice_destination (notice_id, destination_type, destination_id),
    CONSTRAINT fk_schedule_delivery_notice FOREIGN KEY (notice_id) REFERENCES schedule_change_notices(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_delivery_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
