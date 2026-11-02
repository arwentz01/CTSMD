SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS communication_read_state_meta (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO communication_read_state_meta (id) VALUES (1);

ALTER TABLE conversation_participants
    ADD COLUMN last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER guardian_required,
    ADD COLUMN last_read_at DATETIME NULL AFTER last_read_message_id;

-- Existing conversations are baseline-read at migration time so historical
-- messages do not become artificial unread debt.
UPDATE conversation_participants cp
LEFT JOIN (
    SELECT conversation_id, MAX(id) AS latest_message_id
    FROM messages
    WHERE hidden_at IS NULL
    GROUP BY conversation_id
) latest ON latest.conversation_id = cp.conversation_id
SET cp.last_read_message_id = COALESCE(latest.latest_message_id, 0),
    cp.last_read_at = CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS channel_read_states (
    channel_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    last_read_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (channel_id, user_id),
    KEY idx_channel_read_user (user_id, updated_at),
    CONSTRAINT fk_channel_read_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_channel_read_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
