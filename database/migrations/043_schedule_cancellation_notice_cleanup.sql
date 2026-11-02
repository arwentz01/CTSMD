SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_schedule_cancel_stale_notices;

DELIMITER $$

CREATE TRIGGER trg_schedule_cancel_stale_notices
AFTER UPDATE ON schedule_items
FOR EACH ROW
BEGIN
    IF NEW.status='cancelled' AND OLD.status<>'cancelled' THEN
        UPDATE schedule_change_notices
        SET status='cancelled'
        WHERE schedule_item_id=NEW.id
          AND status='draft';
    END IF;
END$$

DELIMITER ;
