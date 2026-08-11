SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_user_disable_operational_cleanup;

DELIMITER $$

CREATE TRIGGER trg_user_disable_operational_cleanup
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.account_status='disabled' AND OLD.account_status<>'disabled' THEN
        UPDATE volunteer_shift_signups vss
        JOIN volunteer_shifts vs ON vs.id=vss.shift_id
        SET vss.status='cancelled'
        WHERE vss.user_id=NEW.id
          AND vss.status='signed_up'
          AND vs.starts_at>NOW();

        UPDATE volunteer_shift_approval_requests r
        JOIN volunteer_shifts vs ON vs.id=r.shift_id
        SET r.status='withdrawn',r.updated_at=CURRENT_TIMESTAMP
        WHERE r.user_id=NEW.id
          AND r.status='pending'
          AND vs.starts_at>NOW();
    END IF;
END$$

DELIMITER ;
