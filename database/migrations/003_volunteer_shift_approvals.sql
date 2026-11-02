SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS volunteer_shift_approval_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shift_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','approved','declined','withdrawn') NOT NULL DEFAULT 'pending',
    request_note VARCHAR(500) NULL,
    decision_note VARCHAR(500) NULL,
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shift_approval_user (shift_id, user_id),
    KEY idx_shift_approval_status (status, requested_at),
    KEY idx_shift_approval_user (user_id, status),
    CONSTRAINT fk_shift_approval_shift FOREIGN KEY (shift_id) REFERENCES volunteer_shifts(id) ON DELETE CASCADE,
    CONSTRAINT fk_shift_approval_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_shift_approval_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
