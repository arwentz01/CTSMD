SET NAMES utf8mb4;

-- Live volunteer roster states must always resolve to an available account,
-- an active volunteer profile, and current shift requirements. Historical
-- states such as completed/no_show/cancelled remain available for recordkeeping.

DROP TRIGGER IF EXISTS trg_volunteer_signup_live_insert_guard;
DROP TRIGGER IF EXISTS trg_volunteer_signup_eligibility_guard;

DELIMITER $$

CREATE TRIGGER trg_volunteer_signup_live_insert_guard
BEFORE INSERT ON volunteer_shift_signups
FOR EACH ROW
BEGIN
    IF NEW.status IN ('signed_up','checked_in') THEN
        IF NOT EXISTS (
            SELECT 1
            FROM users u
            JOIN volunteer_profiles vp ON vp.user_id=u.id AND vp.active=1
            WHERE u.id=NEW.user_id
              AND u.active=1
              AND u.account_status='active'
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='An available active volunteer account is required for a live shift signup.';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM volunteer_shift_requirements vsr
            LEFT JOIN volunteer_credentials vc
              ON vc.requirement_id=vsr.requirement_id
             AND vc.user_id=NEW.user_id
            WHERE vsr.shift_id=NEW.shift_id
              AND (
                  vc.id IS NULL
                  OR vc.status<>'approved'
                  OR (vc.expires_at IS NOT NULL AND vc.expires_at<NOW())
              )
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Volunteer requirements must be current before signup or check-in.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_volunteer_signup_eligibility_guard
BEFORE UPDATE ON volunteer_shift_signups
FOR EACH ROW
BEGIN
    IF NEW.status IN ('signed_up','checked_in')
       AND NEW.status<>OLD.status THEN
        IF NOT EXISTS (
            SELECT 1
            FROM users u
            JOIN volunteer_profiles vp ON vp.user_id=u.id AND vp.active=1
            WHERE u.id=NEW.user_id
              AND u.active=1
              AND u.account_status='active'
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='An available active volunteer account is required for a live shift signup.';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM volunteer_shift_requirements vsr
            LEFT JOIN volunteer_credentials vc
              ON vc.requirement_id=vsr.requirement_id
             AND vc.user_id=NEW.user_id
            WHERE vsr.shift_id=NEW.shift_id
              AND (
                  vc.id IS NULL
                  OR vc.status<>'approved'
                  OR (vc.expires_at IS NOT NULL AND vc.expires_at<NOW())
              )
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Volunteer requirements must be current before signup or check-in.';
        END IF;
    END IF;

    IF NEW.status='completed'
       AND NEW.status<>OLD.status
       AND EXISTS (
            SELECT 1
            FROM volunteer_shift_requirements vsr
            LEFT JOIN volunteer_credentials vc
              ON vc.requirement_id=vsr.requirement_id
             AND vc.user_id=NEW.user_id
            WHERE vsr.shift_id=NEW.shift_id
              AND (
                  vc.id IS NULL
                  OR vc.status<>'approved'
                  OR (vc.expires_at IS NOT NULL AND vc.expires_at<NOW())
              )
       ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Volunteer requirements must be current before completion.';
    END IF;
END$$

DELIMITER ;
