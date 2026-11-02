SET NAMES utf8mb4;

ALTER TABLE forms
    ADD COLUMN production_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL AFTER review_required,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by_user_id,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD CONSTRAINT fk_forms_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_forms_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE form_assignments
    DROP INDEX uq_form_user,
    ADD COLUMN production_id BIGINT UNSIGNED NULL AFTER form_id,
    ADD COLUMN assigned_by_user_id BIGINT UNSIGNED NULL AFTER completed_at,
    ADD COLUMN assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER assigned_by_user_id,
    ADD KEY idx_form_assignment_context (form_id, user_id, production_id, status),
    ADD KEY idx_form_assignment_production (production_id, status, due_at),
    ADD CONSTRAINT fk_form_assignment_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_form_assignment_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Existing seeded forms are organization-wide and their existing assignments remain organization-wide.
UPDATE forms SET production_id = NULL WHERE production_id IS NULL;
UPDATE form_assignments SET production_id = NULL WHERE production_id IS NULL;
