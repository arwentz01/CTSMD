SET NAMES utf8mb4;

-- Acknowledgement clears shared alerts without changing publishing decisions.
-- Historical moderation matches remain on channel_posts for longitudinal review.
ALTER TABLE channel_posts
  ADD COLUMN moderation_acknowledged_at DATETIME NULL AFTER moderated_at,
  ADD COLUMN moderation_acknowledged_by_user_id BIGINT UNSIGNED NULL AFTER moderation_acknowledged_at,
  ADD KEY idx_moderation_ack (moderation_status, moderation_acknowledged_at, created_at),
  ADD CONSTRAINT fk_moderation_acknowledger FOREIGN KEY (moderation_acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
