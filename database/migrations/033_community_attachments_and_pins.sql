SET NAMES utf8mb4;

ALTER TABLE channel_posts
    ADD COLUMN pinned_at DATETIME NULL AFTER pinned,
    ADD COLUMN pinned_by_user_id BIGINT UNSIGNED NULL AFTER pinned_at,
    ADD CONSTRAINT fk_channel_post_pinner FOREIGN KEY (pinned_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS channel_post_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    stored_file_id BIGINT UNSIGNED NOT NULL,
    attached_by_user_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_channel_post_attachments_post (post_id,sort_order,id),
    CONSTRAINT fk_channel_post_attachment_post FOREIGN KEY (post_id) REFERENCES channel_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_channel_post_attachment_file FOREIGN KEY (stored_file_id) REFERENCES stored_files(id) ON DELETE RESTRICT,
    CONSTRAINT fk_channel_post_attachment_actor FOREIGN KEY (attached_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
