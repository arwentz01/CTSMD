SET NAMES utf8mb4;

ALTER TABLE channel_posts
    ADD COLUMN hidden_at DATETIME NULL AFTER moderated_at,
    ADD COLUMN hidden_by_user_id BIGINT UNSIGNED NULL AFTER hidden_at,
    ADD COLUMN hidden_reason VARCHAR(255) NULL AFTER hidden_by_user_id,
    ADD COLUMN deleted_at DATETIME NULL AFTER hidden_reason,
    ADD COLUMN deleted_by_user_id BIGINT UNSIGNED NULL AFTER deleted_at,
    ADD COLUMN deleted_reason VARCHAR(255) NULL AFTER deleted_by_user_id,
    ADD KEY idx_channel_post_visibility (channel_id, moderation_status, hidden_at, deleted_at, created_at),
    ADD CONSTRAINT fk_channel_post_hidden_by FOREIGN KEY (hidden_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_channel_post_deleted_by FOREIGN KEY (deleted_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
