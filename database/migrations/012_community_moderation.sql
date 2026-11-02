SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS moderation_terms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term VARCHAR(190) NOT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'profanity',
    action ENUM('block','review') NOT NULL DEFAULT 'review',
    match_mode ENUM('exact','normalized') NOT NULL DEFAULT 'normalized',
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    aliases_json JSON NULL,
    notes VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_moderation_term (term),
    KEY idx_moderation_terms_active (active, category, severity),
    CONSTRAINT fk_moderation_term_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_moderation_term_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE channel_posts
    ADD COLUMN moderation_status ENUM('published','pending','rejected') NOT NULL DEFAULT 'published' AFTER body,
    ADD COLUMN moderation_term_id BIGINT UNSIGNED NULL AFTER moderation_status,
    ADD COLUMN moderation_reason VARCHAR(255) NULL AFTER moderation_term_id,
    ADD COLUMN moderated_by_user_id BIGINT UNSIGNED NULL AFTER moderation_reason,
    ADD COLUMN moderated_at DATETIME NULL AFTER moderated_by_user_id,
    ADD KEY idx_channel_post_moderation (channel_id, moderation_status, created_at),
    ADD CONSTRAINT fk_channel_post_moderation_term FOREIGN KEY (moderation_term_id) REFERENCES moderation_terms(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_channel_post_moderator FOREIGN KEY (moderated_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
