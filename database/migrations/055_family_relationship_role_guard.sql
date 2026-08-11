SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_family_relationship_role_insert;
DROP TRIGGER IF EXISTS trg_family_relationship_role_update;

DELIMITER $$

CREATE TRIGGER trg_family_relationship_role_insert
BEFORE INSERT ON family_relationships
FOR EACH ROW
BEGIN
    IF NEW.status='active' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM users student
            JOIN auth_user_roles ur ON ur.user_id=student.id
            JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code='student'
            WHERE student.id=NEW.student_user_id
              AND student.active=1
              AND student.account_status<>'disabled'
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='An active family relationship requires an available Student identity.';
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM users guardian
            WHERE guardian.id=NEW.guardian_user_id
              AND guardian.active=1
              AND guardian.account_status<>'disabled'
              AND NOT EXISTS (
                  SELECT 1
                  FROM auth_user_roles gur
                  JOIN auth_roles gr ON gr.id=gur.role_id AND gr.active=1 AND gr.code='student'
                  WHERE gur.user_id=guardian.id
              )
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='An active family relationship requires an available non-Student guardian identity.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_family_relationship_role_update
BEFORE UPDATE ON family_relationships
FOR EACH ROW
BEGIN
    IF NEW.status='active' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM users student
            JOIN auth_user_roles ur ON ur.user_id=student.id
            JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code='student'
            WHERE student.id=NEW.student_user_id
              AND student.active=1
              AND student.account_status<>'disabled'
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='An active family relationship requires an available Student identity.';
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM users guardian
            WHERE guardian.id=NEW.guardian_user_id
              AND guardian.active=1
              AND guardian.account_status<>'disabled'
              AND NOT EXISTS (
                  SELECT 1
                  FROM auth_user_roles gur
                  JOIN auth_roles gr ON gr.id=gur.role_id AND gr.active=1 AND gr.code='student'
                  WHERE gur.user_id=guardian.id
              )
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='An active family relationship requires an available non-Student guardian identity.';
        END IF;
    END IF;
END$$

DELIMITER ;
