SET NAMES utf8mb4;

ALTER TABLE users
    MODIFY COLUMN account_status ENUM('pending_verification','invited','managed','active','disabled') NOT NULL DEFAULT 'invited',
    ADD COLUMN email_verified_at DATETIME NULL AFTER password_changed_at,
    ADD COLUMN self_registered_at DATETIME NULL AFTER email_verified_at,
    ADD COLUMN onboarding_completed_at DATETIME NULL AFTER self_registered_at;

CREATE TABLE IF NOT EXISTS auth_email_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auth_email_verification_user (user_id,verified_at,expires_at),
    CONSTRAINT fk_auth_email_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE users
SET email_verified_at = COALESCE(email_verified_at, created_at)
WHERE account_status='active' AND email IS NOT NULL;
