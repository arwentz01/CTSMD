SET NAMES utf8mb4;

-- Presentation-only actions. Never delete message bodies, attachments or safeguarding evidence.
ALTER TABLE conversation_participants
  ADD COLUMN archived_at DATETIME NULL AFTER history_through_message_id,
  ADD KEY idx_conversation_archive (user_id,archived_at,conversation_id);

ALTER TABLE messages
  ADD COLUMN recalled_at DATETIME NULL AFTER hidden_at,
  ADD COLUMN recalled_by_user_id BIGINT UNSIGNED NULL AFTER recalled_at,
  ADD KEY idx_message_recall (conversation_id,recalled_at,id),
  ADD CONSTRAINT fk_messages_recalled_by FOREIGN KEY (recalled_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS message_personal_hides (
  message_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  hidden_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id,user_id),
  KEY idx_personal_hides_user (user_id,message_id),
  CONSTRAINT fk_message_personal_hide_message FOREIGN KEY (message_id) REFERENCES messages(id),
  CONSTRAINT fk_message_personal_hide_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
