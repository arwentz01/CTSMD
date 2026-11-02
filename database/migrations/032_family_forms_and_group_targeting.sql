SET NAMES utf8mb4;

ALTER TABLE form_assignments
    ADD COLUMN subject_user_id BIGINT UNSIGNED NULL AFTER user_id,
    ADD COLUMN source_group_id BIGINT UNSIGNED NULL AFTER production_id,
    ADD KEY idx_form_assignment_subject (subject_user_id,status,due_at),
    ADD KEY idx_form_assignment_source_group (source_group_id,form_id,status),
    ADD CONSTRAINT fk_form_assignment_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_form_assignment_source_group FOREIGN KEY (source_group_id) REFERENCES production_groups(id) ON DELETE SET NULL;

UPDATE form_assignments SET subject_user_id=user_id WHERE subject_user_id IS NULL;

ALTER TABLE form_submissions
    ADD COLUMN submitted_for_user_id BIGINT UNSIGNED NULL AFTER submitted_by_user_id,
    ADD CONSTRAINT fk_form_submission_for_user FOREIGN KEY (submitted_for_user_id) REFERENCES users(id) ON DELETE SET NULL;

UPDATE form_submissions fs
JOIN form_assignments fa ON fa.id=fs.assignment_id
SET fs.submitted_for_user_id=COALESCE(fa.subject_user_id,fa.user_id)
WHERE fs.submitted_for_user_id IS NULL;
