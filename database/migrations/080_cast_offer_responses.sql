CREATE TABLE IF NOT EXISTS production_cast_offers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 production_id BIGINT UNSIGNED NOT NULL,
 casting_record_id BIGINT UNSIGNED NOT NULL,
 student_user_id BIGINT UNSIGNED NOT NULL,
 role_snapshot VARCHAR(500) NOT NULL,
 status ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
 offered_by_user_id BIGINT UNSIGNED NOT NULL,
 responded_by_user_id BIGINT UNSIGNED NULL,
 response_note TEXT NULL,
 offered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 responded_at DATETIME NULL,
 UNIQUE KEY uq_cast_offer_record (casting_record_id),
 KEY idx_cast_offer_student (student_user_id,status),
 CONSTRAINT fk_cast_offer_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
 CONSTRAINT fk_cast_offer_record FOREIGN KEY (casting_record_id) REFERENCES production_casting_records(id) ON DELETE CASCADE,
 CONSTRAINT fk_cast_offer_student FOREIGN KEY (student_user_id) REFERENCES users(id),
 CONSTRAINT fk_cast_offer_creator FOREIGN KEY (offered_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
