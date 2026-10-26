CREATE TABLE IF NOT EXISTS production_cast_roles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 production_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL,
 category ENUM('principal','supporting','minor','ensemble','other') NOT NULL DEFAULT 'other',
 sort_order INT NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_production_cast_role (production_id,title),
 KEY idx_production_cast_roles_order (production_id,active,sort_order),
 CONSTRAINT fk_cast_role_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_cast_role_assignments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 role_id BIGINT UNSIGNED NOT NULL,
 casting_record_id BIGINT UNSIGNED NULL,
 manual_credit_id BIGINT UNSIGNED NULL,
 assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_role_registered (role_id,casting_record_id),
 UNIQUE KEY uq_role_manual (role_id,manual_credit_id),
 CONSTRAINT fk_cast_assignment_role FOREIGN KEY (role_id) REFERENCES production_cast_roles(id) ON DELETE CASCADE,
 CONSTRAINT fk_cast_assignment_record FOREIGN KEY (casting_record_id) REFERENCES production_casting_records(id) ON DELETE CASCADE,
 CONSTRAINT chk_cast_assignment_target CHECK ((casting_record_id IS NULL) <> (manual_credit_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
