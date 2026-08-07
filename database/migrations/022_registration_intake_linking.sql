SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS registration_submission_links (
    submission_id BIGINT UNSIGNED PRIMARY KEY,
    participant_user_id BIGINT UNSIGNED NOT NULL,
    guardian_user_id BIGINT UNSIGNED NULL,
    linked_by_user_id BIGINT UNSIGNED NULL,
    link_method ENUM('existing','created','mixed') NOT NULL DEFAULT 'existing',
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_registration_link_participant (participant_user_id),
    KEY idx_registration_link_guardian (guardian_user_id),
    CONSTRAINT fk_registration_link_submission FOREIGN KEY (submission_id) REFERENCES registration_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_registration_link_participant FOREIGN KEY (participant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_registration_link_guardian FOREIGN KEY (guardian_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_registration_link_actor FOREIGN KEY (linked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
