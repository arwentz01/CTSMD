SET NAMES utf8mb4;

-- Separate participant classification from RBAC. No existing person is reclassified.
ALTER TABLE users ADD COLUMN adult_participant TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE production_memberships
  MODIFY audience_type ENUM('student','guardian','staff','adult') NOT NULL;
ALTER TABLE productions
  ADD COLUMN participant_category ENUM('students','adults','mixed') NOT NULL DEFAULT 'students',
  ADD COLUMN student_minimum_age TINYINT UNSIGNED NULL,
  ADD COLUMN student_maximum_age TINYINT UNSIGNED NULL,
  ADD COLUMN participant_eligibility_date DATE NULL;

CREATE TABLE production_age_exceptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  production_id BIGINT UNSIGNED NOT NULL,
  student_user_id BIGINT UNSIGNED NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NOT NULL,
  approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason VARCHAR(1000) NOT NULL,
  revoked_by_user_id BIGINT UNSIGNED NULL,
  revoked_at DATETIME NULL,
  KEY idx_age_exception_subject (production_id,student_user_id,revoked_at),
  FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE RESTRICT,
  FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Functions describe work, never permissions. Multiple functions may coexist.
CREATE TABLE production_participant_functions (
  production_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  function_type ENUM('cast','crew','creative') NOT NULL,
  title VARCHAR(190) NOT NULL,
  assigned_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (production_id,user_id,function_type,title),
  FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE RESTRICT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
