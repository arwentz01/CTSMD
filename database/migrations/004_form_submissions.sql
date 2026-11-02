SET NAMES utf8mb4;

ALTER TABLE forms
    ADD COLUMN instructions TEXT NULL AFTER form_type,
    ADD COLUMN completion_mode ENUM('acknowledgment','signature','submission') NOT NULL DEFAULT 'acknowledgment' AFTER instructions,
    ADD COLUMN review_required TINYINT(1) NOT NULL DEFAULT 0 AFTER completion_mode;

UPDATE forms
SET instructions = CASE form_type
    WHEN 'acknowledgment' THEN 'Review the assigned policy or handbook and confirm that you understand and agree to follow it.'
    WHEN 'release' THEN 'Review the release terms and provide your typed signature to record your consent.'
    WHEN 'medical' THEN 'Confirm that the emergency information assigned to you is current and submit it for staff review.'
    WHEN 'training_acknowledgment' THEN 'Confirm completion of the assigned facility education. Staff will review the submission before marking it complete.'
    ELSE 'Review the assigned information and complete the requested response.'
END,
completion_mode = CASE form_type
    WHEN 'release' THEN 'signature'
    WHEN 'medical' THEN 'submission'
    ELSE 'acknowledgment'
END,
review_required = CASE form_type
    WHEN 'medical' THEN 1
    WHEN 'training_acknowledgment' THEN 1
    ELSE 0
END
WHERE instructions IS NULL;

CREATE TABLE IF NOT EXISTS form_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    form_id BIGINT UNSIGNED NOT NULL,
    submitted_by_user_id BIGINT UNSIGNED NOT NULL,
    acknowledgment TINYINT(1) NOT NULL DEFAULT 0,
    typed_signature VARCHAR(190) NULL,
    response_text TEXT NULL,
    status ENUM('submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
    reviewer_user_id BIGINT UNSIGNED NULL,
    reviewer_note TEXT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    UNIQUE KEY uq_form_submission_assignment (assignment_id),
    KEY idx_form_submission_review (status, submitted_at),
    CONSTRAINT fk_form_submission_assignment FOREIGN KEY (assignment_id) REFERENCES form_assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_form_submission_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    CONSTRAINT fk_form_submission_user FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_form_submission_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
