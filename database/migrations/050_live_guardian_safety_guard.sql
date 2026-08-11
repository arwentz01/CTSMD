SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_family_relationship_live_guardian_update;
DROP TRIGGER IF EXISTS trg_user_disable_live_guardian_guard;

DELIMITER $$

CREATE TRIGGER trg_family_relationship_live_guardian_update
BEFORE UPDATE ON family_relationships
FOR EACH ROW
BEGIN
    DECLARE student_available INT DEFAULT 0;
    DECLARE other_guardians INT DEFAULT 0;

    IF OLD.status='active' AND NEW.status<>'active' THEN
        SELECT COUNT(*)
          INTO student_available
          FROM users
         WHERE id=OLD.student_user_id
           AND active=1
           AND account_status<>'disabled';

        IF student_available>0 THEN
            SELECT COUNT(DISTINCT fr.guardian_user_id)
              INTO other_guardians
              FROM family_relationships fr
              JOIN users guardian
                ON guardian.id=fr.guardian_user_id
               AND guardian.active=1
               AND guardian.account_status<>'disabled'
             WHERE fr.student_user_id=OLD.student_user_id
               AND fr.status='active'
               AND fr.id<>OLD.id;

            IF other_guardians<1 THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT='A live Student must retain at least one available guardian.';
            END IF;
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_user_disable_live_guardian_guard
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    DECLARE unsafe_children INT DEFAULT 0;

    IF NEW.account_status='disabled' AND OLD.account_status<>'disabled' THEN
        SELECT COUNT(*)
          INTO unsafe_children
          FROM family_relationships mine
          JOIN users student
            ON student.id=mine.student_user_id
           AND student.active=1
           AND student.account_status<>'disabled'
         WHERE mine.guardian_user_id=NEW.id
           AND mine.status='active'
           AND NOT EXISTS (
                SELECT 1
                  FROM family_relationships other
                  JOIN users guardian
                    ON guardian.id=other.guardian_user_id
                   AND guardian.active=1
                   AND guardian.account_status<>'disabled'
                 WHERE other.student_user_id=mine.student_user_id
                   AND other.status='active'
                   AND other.guardian_user_id<>NEW.id
           );

        IF unsafe_children>0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='This account is the last available guardian for a live Student. Add another guardian before disabling it.';
        END IF;
    END IF;
END$$

DELIMITER ;
