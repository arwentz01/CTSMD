-- Per-student pickup list and pending account relationship requests.
CREATE TABLE IF NOT EXISTS student_pickup_people (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 student_user_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(190) NOT NULL,
 relationship_label VARCHAR(100) NOT NULL,
 phone VARCHAR(60) NULL,
 notes VARCHAR(500) NULL,
 linked_guardian_user_id BIGINT UNSIGNED NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 updated_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_pickup_student (student_user_id,active),
 CONSTRAINT fk_pickup_student FOREIGN KEY (student_user_id) REFERENCES users(id),
 CONSTRAINT fk_pickup_linked FOREIGN KEY (linked_guardian_user_id) REFERENCES users(id),
 CONSTRAINT fk_pickup_actor FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_relationship_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 student_user_id BIGINT UNSIGNED NOT NULL,
 adult_email VARCHAR(190) NOT NULL,
 adult_user_id BIGINT UNSIGNED NULL,
 relationship_type ENUM('parent','guardian','caregiver') NOT NULL,
 relationship_label VARCHAR(100) NOT NULL,
 pickup_authorized TINYINT(1) NOT NULL DEFAULT 0,
 status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
 requested_by_user_id BIGINT UNSIGNED NOT NULL,
 reviewed_by_user_id BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_rel_req_student (student_user_id,status),
 CONSTRAINT fk_rel_req_student FOREIGN KEY (student_user_id) REFERENCES users(id),
 CONSTRAINT fk_rel_req_adult FOREIGN KEY (adult_user_id) REFERENCES users(id),
 CONSTRAINT fk_rel_req_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(id),
 CONSTRAINT fk_rel_req_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
