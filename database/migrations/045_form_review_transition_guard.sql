SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_form_review_transition_guard;

DELIMITER $$

CREATE TRIGGER trg_form_review_transition_guard
BEFORE UPDATE ON form_submissions
FOR EACH ROW
BEGIN
    IF NEW.status IN ('approved','rejected')
       AND NEW.status <> OLD.status
       AND OLD.status <> 'submitted' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Form review transition requires submitted status.';
    END IF;
END$$

DELIMITER ;
