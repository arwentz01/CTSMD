SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS attendance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_item_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('unmarked','present','absent','late','excused','left_early') NOT NULL DEFAULT 'unmarked',
    staff_note VARCHAR(1000) NULL,
    marked_by_user_id BIGINT UNSIGNED NULL,
    marked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_item_user (schedule_item_id, user_id),
    KEY idx_attendance_item_status (schedule_item_id, status),
    KEY idx_attendance_user (user_id, schedule_item_id),
    CONSTRAINT fk_attendance_item FOREIGN KEY (schedule_item_id) REFERENCES schedule_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_marker FOREIGN KEY (marked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_absence_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_item_id BIGINT UNSIGNED NOT NULL,
    student_user_id BIGINT UNSIGNED NOT NULL,
    reported_by_user_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(1500) NOT NULL,
    status ENUM('submitted','acknowledged','cancelled') NOT NULL DEFAULT 'submitted',
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_absence_item_status (schedule_item_id, status, submitted_at),
    KEY idx_absence_student (student_user_id, schedule_item_id),
    CONSTRAINT fk_absence_item FOREIGN KEY (schedule_item_id) REFERENCES schedule_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_absence_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_absence_reporter FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_absence_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
