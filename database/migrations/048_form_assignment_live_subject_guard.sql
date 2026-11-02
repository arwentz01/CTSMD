SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_form_assignment_live_subject_guard;

DELIMITER $$

CREATE TRIGGER trg_form_assignment_live_subject_guard
BEFORE INSERT ON form_assignments
FOR EACH ROW
BEGIN
    DECLARE subject_status VARCHAR(32) DEFAULT NULL;

    SELECT account_status
      INTO subject_status
      FROM users
     WHERE id=COALESCE(NEW.subject_user_id,NEW.user_id)
     LIMIT 1;

    IF subject_status='disabled' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Disabled accounts cannot receive new form assignments.';
    END IF;
END$$

DELIMITER ;
