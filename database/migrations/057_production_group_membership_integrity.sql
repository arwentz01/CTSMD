SET NAMES utf8mb4;

-- Active Production Group membership must always reference an available user,
-- an active production membership, and the same production as the Group.

DROP TRIGGER IF EXISTS trg_production_group_live_member_insert;
DROP TRIGGER IF EXISTS trg_production_group_live_member_update;

DELIMITER $$

CREATE TRIGGER trg_production_group_live_member_insert
BEFORE INSERT ON production_group_members
FOR EACH ROW
BEGIN
    IF NEW.status='active' AND NOT EXISTS (
        SELECT 1
        FROM production_memberships pm
        JOIN production_groups pg ON pg.id=NEW.group_id
        JOIN users u ON u.id=pm.user_id
        WHERE pm.id=NEW.production_membership_id
          AND pm.production_id=pg.production_id
          AND pm.status='active'
          AND u.active=1
          AND u.account_status<>'disabled'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Active Production Group members must be available active members of the same production.';
    END IF;
END$$

CREATE TRIGGER trg_production_group_live_member_update
BEFORE UPDATE ON production_group_members
FOR EACH ROW
BEGIN
    IF NEW.status='active' AND NOT EXISTS (
        SELECT 1
        FROM production_memberships pm
        JOIN production_groups pg ON pg.id=NEW.group_id
        JOIN users u ON u.id=pm.user_id
        WHERE pm.id=NEW.production_membership_id
          AND pm.production_id=pg.production_id
          AND pm.status='active'
          AND u.active=1
          AND u.account_status<>'disabled'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Active Production Group members must be available active members of the same production.';
    END IF;
END$$

DELIMITER ;
