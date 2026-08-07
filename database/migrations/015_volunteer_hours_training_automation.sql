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
