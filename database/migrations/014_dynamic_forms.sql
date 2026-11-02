SET NAMES utf8mb4;

ALTER TABLE forms
    ADD COLUMN definition_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER review_required;

CREATE TABLE IF NOT EXISTS form_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    help_text VARCHAR(1000) NULL,
    field_type ENUM('short_text','long_text','single_choice','multiple_choice','date','acknowledgment','signature') NOT NULL,
    required TINYINT(1) NOT NULL DEFAULT 0,
    options_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_form_field_key (form_id, field_key),
    KEY idx_form_fields_order (form_id, active, sort_order, id),
    CONSTRAINT fk_form_field_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE form_submissions
    ADD COLUMN definition_version INT UNSIGNED NULL AFTER response_text,
    ADD COLUMN definition_snapshot_json JSON NULL AFTER definition_version;

CREATE TABLE IF NOT EXISTS form_submission_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    field_id BIGINT UNSIGNED NULL,
    field_key VARCHAR(100) NOT NULL,
    field_label VARCHAR(255) NOT NULL,
    field_type VARCHAR(40) NOT NULL,
    answer_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_submission_field_key (submission_id, field_key),
    KEY idx_submission_answers (submission_id, id),
    CONSTRAINT fk_form_answer_submission FOREIGN KEY (submission_id) REFERENCES form_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_form_answer_field FOREIGN KEY (field_id) REFERENCES form_fields(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
