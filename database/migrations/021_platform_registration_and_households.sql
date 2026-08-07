SET NAMES utf8mb4;

ALTER TABLE users
    MODIFY COLUMN account_status ENUM('pending_verification','invited','managed','active','disabled') NOT NULL DEFAULT 'invited',
    ADD COLUMN email_verified_at DATETIME NULL AFTER password_changed_at,
    ADD COLUMN self_registered_at DATETIME NULL AFTER email_verified_at,
    ADD COLUMN onboarding_completed_at DATETIME NULL AFTER self_registered_at,
    ADD COLUMN organization_membership_status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending' AFTER onboarding_completed_at,
    ADD COLUMN organization_membership_reviewed_at DATETIME NULL AFTER organization_membership_status,
    ADD COLUMN organization_membership_reviewed_by_user_id BIGINT UNSIGNED NULL AFTER organization_membership_reviewed_at,
    ADD KEY idx_users_org_membership (organization_membership_status,active),
    ADD CONSTRAINT fk_users_org_membership_reviewer FOREIGN KEY (organization_membership_reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

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

-- Existing CTSMD people predate self-registration and are treated as approved members.
UPDATE users
SET email_verified_at = COALESCE(email_verified_at, created_at),
    organization_membership_status='approved',
    organization_membership_reviewed_at=COALESCE(organization_membership_reviewed_at,CURRENT_TIMESTAMP)
WHERE self_registered_at IS NULL;
