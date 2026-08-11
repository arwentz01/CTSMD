SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_schedule_notice_publish_guard;

DELIMITER $$

CREATE TRIGGER trg_schedule_notice_publish_guard
BEFORE UPDATE ON schedule_change_notices
FOR EACH ROW
BEGIN
    IF NEW.status='published' AND OLD.status<>'published' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM schedule_items si
            JOIN productions p ON p.id=si.production_id AND p.is_active=1
            WHERE si.id=NEW.schedule_item_id
              AND si.production_id=NEW.production_id
              AND si.status='active'
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Only an active schedule item in an active production can publish a schedule update.';
        END IF;
    END IF;
END$$

DELIMITER ;
