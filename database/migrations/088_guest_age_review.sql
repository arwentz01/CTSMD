SET NAMES utf8mb4;
ALTER TABLE registration_submissions
  ADD COLUMN age_verified_at DATETIME NULL,
  ADD COLUMN age_verified_by_user_id BIGINT UNSIGNED NULL,
  ADD COLUMN age_verification_method ENUM('guardian_attestation','document_inspected') NULL,
  ADD CONSTRAINT fk_registration_age_reviewer FOREIGN KEY (age_verified_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
