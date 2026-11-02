SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS student_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    preferred_name VARCHAR(120) NULL,
    short_bio VARCHAR(1500) NULL,
    special_skills VARCHAR(1500) NULL,
    headshot_stored_file_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_profile_headshot FOREIGN KEY (headshot_stored_file_id) REFERENCES stored_files(id) ON DELETE SET NULL,
    CONSTRAINT fk_student_profile_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
