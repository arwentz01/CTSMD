SET NAMES utf8mb4;

-- Repeat-safe MySQL/MariaDB migration. Existing guardian preferences are preserved.
-- Avoid ADD COLUMN IF NOT EXISTS (not supported consistently across MySQL versions).
SET @ctsmd_guardian_mute_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notification_preferences'
      AND COLUMN_NAME = 'push_safeguarded_messages'
);
SET @ctsmd_guardian_mute_ddl = IF(
    @ctsmd_guardian_mute_column_exists = 0,
    'ALTER TABLE notification_preferences ADD COLUMN push_safeguarded_messages TINYINT(1) NOT NULL DEFAULT 1 AFTER push_messages',
    'SELECT 1'
);
PREPARE ctsmd_guardian_mute_stmt FROM @ctsmd_guardian_mute_ddl;
EXECUTE ctsmd_guardian_mute_stmt;
DEALLOCATE PREPARE ctsmd_guardian_mute_stmt;
