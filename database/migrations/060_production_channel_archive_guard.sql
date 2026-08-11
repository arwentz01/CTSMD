SET NAMES utf8mb4;

UPDATE channels c
JOIN productions p ON p.id=c.production_id
SET c.archived_at=COALESCE(c.archived_at,CURRENT_TIMESTAMP)
WHERE p.is_active=0
  AND c.archived_at IS NULL;

DROP TRIGGER IF EXISTS trg_production_deactivate_archive_channels;

DELIMITER $$

CREATE TRIGGER trg_production_deactivate_archive_channels
AFTER UPDATE ON productions
FOR EACH ROW
BEGIN
    IF OLD.is_active=1 AND NEW.is_active=0 THEN
        UPDATE channels
        SET archived_at=COALESCE(archived_at,CURRENT_TIMESTAMP)
        WHERE production_id=NEW.id
          AND archived_at IS NULL;
    END IF;
END$$

DELIMITER ;
