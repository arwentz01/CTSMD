-- Per-member reactions permit consistent toggles across web and native clients.
CREATE TABLE IF NOT EXISTS channel_post_reactions (
 post_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 reaction VARCHAR(20) NOT NULL DEFAULT 'heart',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (post_id,user_id,reaction),
 CONSTRAINT fk_channel_reaction_post FOREIGN KEY (post_id) REFERENCES channel_posts(id) ON DELETE CASCADE,
 CONSTRAINT fk_channel_reaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
