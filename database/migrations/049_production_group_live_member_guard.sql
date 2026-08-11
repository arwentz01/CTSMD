SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_production_group_live_member_insert;
DROP TRIGGER IF EXISTS trg_production_group_live_member_update;

DELIMITER $$

CREATE TRIGGER trg_production_group_live_member_insert
BEFORE INSERT ON production_group_members
FOR EACH ROW
BEGIN
    DECLARE member_status VARCHAR(32) DEFAULT NULL;

    IF NEW.status='active' THEN
        SELECT u.account_status
          INTO member_status
          FROM production_memberships pm
          JOIN users u ON u.id=pm.user_id
         WHERE pm.id=NEW.production_membership_id
         LIMIT 1;

        IF member_status='disabled' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Disabled accounts cannot be added to active Production Groups.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_production_group_live_member_update
BEFORE UPDATE ON production_group_members
FOR EACH ROW
BEGIN
    DECLARE member_status VARCHAR(32) DEFAULT NULL;

    IF NEW.status='active' THEN
        SELECT u.account_status
          INTO member_status
          FROM production_memberships pm
          JOIN users u ON u.id=pm.user_id
         WHERE pm.id=NEW.production_membership_id
         LIMIT 1;

        IF member_status='disabled' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Disabled accounts cannot be added to active Production Groups.';
        END IF;
    END IF;
END$$

DELIMITER ;
