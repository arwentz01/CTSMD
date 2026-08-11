SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_channel_post_pending_publish_guard;
DELIMITER $$
CREATE TRIGGER trg_channel_post_pending_publish_guard
BEFORE UPDATE ON channel_posts
FOR EACH ROW
BEGIN
    IF OLD.moderation_status = 'pending' AND NEW.moderation_status = 'published' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM users u
            WHERE u.id = NEW.author_user_id
              AND u.active = 1
              AND u.account_status <> 'disabled'
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Pending Community posts cannot be published for an unavailable author.';
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM channels c
            WHERE c.id = NEW.channel_id
              AND c.archived_at IS NULL
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Pending Community posts cannot be published into an archived channel.';
        END IF;
    END IF;
END$$
DELIMITER ;
