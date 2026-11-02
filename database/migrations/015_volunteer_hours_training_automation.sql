SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS volunteer_hour_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    production_id BIGINT UNSIGNED NULL,
    shift_id BIGINT UNSIGNED NULL,
    minutes INT UNSIGNED NOT NULL,
    source_type ENUM('shift','manual') NOT NULL DEFAULT 'shift',
    status ENUM('verified','void') NOT NULL DEFAULT 'verified',
    note VARCHAR(1000) NULL,
    verified_by_user_id BIGINT UNSIGNED NULL,
    served_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_volunteer_hours_shift_user (shift_id,user_id),
    KEY idx_volunteer_hours_user (user_id,status,served_at),
    KEY idx_volunteer_hours_production (production_id,status,served_at),
    CONSTRAINT fk_volunteer_hours_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_volunteer_hours_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE SET NULL,
    CONSTRAINT fk_volunteer_hours_shift FOREIGN KEY (shift_id) REFERENCES volunteer_shifts(id) ON DELETE SET NULL,
    CONSTRAINT fk_volunteer_hours_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS volunteer_training_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requirement_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    completion_instructions TEXT NULL,
    validity_days INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_training_active (active,title),
    CONSTRAINT fk_training_requirement FOREIGN KEY (requirement_id) REFERENCES volunteer_requirements(id) ON DELETE SET NULL,
    CONSTRAINT fk_training_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS volunteer_training_completions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('completed','void') NOT NULL DEFAULT 'completed',
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_by_user_id BIGINT UNSIGNED NULL,
    note VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_training_module_user (module_id,user_id),
    KEY idx_training_user_status (user_id,status,completed_at),
    CONSTRAINT fk_training_completion_module FOREIGN KEY (module_id) REFERENCES volunteer_training_modules(id) ON DELETE CASCADE,
    CONSTRAINT fk_training_completion_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_training_completion_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_requirement_mappings (
    form_id BIGINT UNSIGNED NOT NULL,
    requirement_id BIGINT UNSIGNED NOT NULL,
    validity_days INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (form_id,requirement_id),
    CONSTRAINT fk_form_requirement_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    CONSTRAINT fk_form_requirement_requirement FOREIGN KEY (requirement_id) REFERENCES volunteer_requirements(id) ON DELETE CASCADE,
    CONSTRAINT fk_form_requirement_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_volunteer_signup_hours_update;
DROP TRIGGER IF EXISTS trg_training_completion_credential_insert;
DROP TRIGGER IF EXISTS trg_training_completion_credential_update;
DROP TRIGGER IF EXISTS trg_form_approval_credential_update;

DELIMITER $$

CREATE TRIGGER trg_volunteer_signup_hours_update
AFTER UPDATE ON volunteer_shift_signups
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' THEN
        INSERT INTO volunteer_hour_entries (user_id,production_id,shift_id,minutes,source_type,status,note,verified_by_user_id,served_at)
        SELECT NEW.user_id,vs.production_id,vs.id,GREATEST(TIMESTAMPDIFF(MINUTE,vs.starts_at,vs.ends_at),1),'shift','verified',CONCAT('Verified from completed shift: ',vs.title),NULL,vs.ends_at
        FROM volunteer_shifts vs WHERE vs.id=NEW.shift_id
        ON DUPLICATE KEY UPDATE minutes=VALUES(minutes),production_id=VALUES(production_id),status='verified',note=VALUES(note),served_at=VALUES(served_at),updated_at=CURRENT_TIMESTAMP;
    ELSEIF OLD.status = 'completed' AND NEW.status <> 'completed' THEN
        UPDATE volunteer_hour_entries SET status='void',updated_at=CURRENT_TIMESTAMP WHERE shift_id=NEW.shift_id AND user_id=NEW.user_id;
    END IF;
END$$

CREATE TRIGGER trg_training_completion_credential_insert
AFTER INSERT ON volunteer_training_completions
FOR EACH ROW
BEGIN
    IF NEW.status='completed' THEN
        INSERT INTO volunteer_credentials (user_id,requirement_id,status,completed_at,expires_at,verified_by_user_id)
        SELECT NEW.user_id,m.requirement_id,'approved',NEW.completed_at,
               CASE WHEN m.validity_days IS NULL THEN NULL ELSE DATE_ADD(NEW.completed_at,INTERVAL m.validity_days DAY) END,
               NEW.verified_by_user_id
        FROM volunteer_training_modules m
        WHERE m.id=NEW.module_id AND m.requirement_id IS NOT NULL
        ON DUPLICATE KEY UPDATE status='approved',completed_at=VALUES(completed_at),expires_at=VALUES(expires_at),verified_by_user_id=VALUES(verified_by_user_id);
    END IF;
END$$

CREATE TRIGGER trg_training_completion_credential_update
AFTER UPDATE ON volunteer_training_completions
FOR EACH ROW
BEGIN
    IF NEW.status='completed' THEN
        INSERT INTO volunteer_credentials (user_id,requirement_id,status,completed_at,expires_at,verified_by_user_id)
        SELECT NEW.user_id,m.requirement_id,'approved',NEW.completed_at,
               CASE WHEN m.validity_days IS NULL THEN NULL ELSE DATE_ADD(NEW.completed_at,INTERVAL m.validity_days DAY) END,
               NEW.verified_by_user_id
        FROM volunteer_training_modules m
        WHERE m.id=NEW.module_id AND m.requirement_id IS NOT NULL
        ON DUPLICATE KEY UPDATE status='approved',completed_at=VALUES(completed_at),expires_at=VALUES(expires_at),verified_by_user_id=VALUES(verified_by_user_id);
    END IF;
END$$

CREATE TRIGGER trg_form_approval_credential_update
AFTER UPDATE ON form_submissions
FOR EACH ROW
BEGIN
    IF NEW.status='approved' AND OLD.status <> 'approved' THEN
        INSERT INTO volunteer_credentials (user_id,requirement_id,status,completed_at,expires_at,verified_by_user_id)
        SELECT NEW.submitted_by_user_id,m.requirement_id,'approved',COALESCE(NEW.reviewed_at,CURRENT_TIMESTAMP),
               CASE WHEN m.validity_days IS NULL THEN NULL ELSE DATE_ADD(COALESCE(NEW.reviewed_at,CURRENT_TIMESTAMP),INTERVAL m.validity_days DAY) END,
               NEW.reviewer_user_id
        FROM form_requirement_mappings m
        WHERE m.form_id=NEW.form_id AND m.active=1
        ON DUPLICATE KEY UPDATE status='approved',completed_at=VALUES(completed_at),expires_at=VALUES(expires_at),verified_by_user_id=VALUES(verified_by_user_id);
    END IF;
END$$

DELIMITER ;
