-- Threaded Community replies, scoped to an existing published post and its channel.
CREATE TABLE IF NOT EXISTS channel_post_replies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 post_id BIGINT UNSIGNED NOT NULL,
 author_user_id BIGINT UNSIGNED NOT NULL,
 body TEXT NOT NULL,
 moderation_status VARCHAR(20) NOT NULL DEFAULT 'published',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 deleted_at DATETIME NULL,
 INDEX idx_community_replies_post (post_id,created_at,id),
 CONSTRAINT fk_community_reply_post FOREIGN KEY (post_id) REFERENCES channel_posts(id) ON DELETE CASCADE,
 CONSTRAINT fk_community_reply_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
