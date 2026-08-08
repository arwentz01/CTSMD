-- CTSMD Connect migration 040: baseline event cursors for push fan-out.
-- Existing historical activity is deliberately baselined so enabling push does not replay old content.

CREATE TABLE push_event_cursors (
    source_key VARCHAR(64) PRIMARY KEY,
    last_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO push_event_cursors (source_key,last_id)
SELECT 'messages',COALESCE(MAX(id),0) FROM messages
ON DUPLICATE KEY UPDATE last_id=VALUES(last_id);

INSERT INTO push_event_cursors (source_key,last_id)
SELECT 'community_posts',COALESCE(MAX(id),0) FROM channel_posts
ON DUPLICATE KEY UPDATE last_id=VALUES(last_id);

INSERT INTO push_event_cursors (source_key,last_id)
SELECT 'app_notifications',COALESCE(MAX(id),0) FROM app_notifications
ON DUPLICATE KEY UPDATE last_id=VALUES(last_id);
