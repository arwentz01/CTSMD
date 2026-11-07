SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS moderation_message_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id BIGINT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED NOT NULL,
  reported_user_id BIGINT UNSIGNED NOT NULL,
  reported_by_user_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(80) NOT NULL,
  detail VARCHAR(1000) NULL,
  message_snapshot TEXT NOT NULL,
  reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at DATETIME NULL,
  acknowledged_by_user_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_message_reporter (message_id, reported_by_user_id),
  KEY idx_message_report_ack (acknowledged_at,reported_at),
  KEY idx_message_report_subject (reported_user_id,reported_at),
  CONSTRAINT fk_message_report_message FOREIGN KEY (message_id) REFERENCES messages(id),
  CONSTRAINT fk_message_report_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id),
  CONSTRAINT fk_message_report_subject FOREIGN KEY (reported_user_id) REFERENCES users(id),
  CONSTRAINT fk_message_report_reporter FOREIGN KEY (reported_by_user_id) REFERENCES users(id),
  CONSTRAINT fk_message_report_ack_by FOREIGN KEY (acknowledged_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
