SET NAMES utf8mb4;

-- Guardian preference for optional push alerts about safeguarded conversations.
-- Does not affect conversation participation, reading, or safeguarding records.
ALTER TABLE notification_preferences
    ADD COLUMN push_safeguarded_messages TINYINT(1) NOT NULL DEFAULT 1 AFTER push_messages;
