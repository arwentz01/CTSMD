SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_volunteer_signup_eligibility_guard;

DELIMITER $$

CREATE TRIGGER trg_volunteer_signup_eligibility_guard
BEFORE UPDATE ON volunteer_shift_signups
FOR EACH ROW
BEGIN
    IF NEW.status IN ('checked_in','completed')
       AND NEW.status <> OLD.status
       AND EXISTS (
            SELECT 1
            FROM volunteer_shift_requirements vsr
            LEFT JOIN volunteer_credentials vc
              ON vc.requirement_id=vsr.requirement_id
             AND vc.user_id=NEW.user_id
            WHERE vsr.shift_id=NEW.shift_id
              AND (
                  vc.id IS NULL
                  OR vc.status <> 'approved'
                  OR (vc.expires_at IS NOT NULL AND vc.expires_at < NOW())
              )
       ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Volunteer requirements must be current before check-in or completion.';
    END IF;
END$$

DELIMITER ;
