SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS volunteer_coordinator_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    production_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    category VARCHAR(100) NULL,
    status ENUM('active','completed','inactive') NOT NULL DEFAULT 'active',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    assigned_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_volunteer_coordinator_user (user_id,status),
    KEY idx_volunteer_coordinator_production (production_id,status),
    CONSTRAINT fk_volunteer_coordinator_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_volunteer_coordinator_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE SET NULL,
    CONSTRAINT fk_volunteer_coordinator_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS volunteer_service_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    verification_code VARCHAR(50) NULL UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    verified_minutes INT UNSIGNED NOT NULL,
    purpose VARCHAR(190) NULL,
    service_snapshot_json JSON NOT NULL,
    verified_by_user_id BIGINT UNSIGNED NOT NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('issued','void') NOT NULL DEFAULT 'issued',
    voided_by_user_id BIGINT UNSIGNED NULL,
    voided_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_volunteer_verification_user (user_id,status,period_end),
    CONSTRAINT fk_volunteer_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_volunteer_verification_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_volunteer_verification_voider FOREIGN KEY (voided_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
