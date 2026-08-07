SET NAMES utf8mb4;

ALTER TABLE productions
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN activated_at DATETIME NULL AFTER is_active,
    ADD COLUMN deactivated_at DATETIME NULL AFTER activated_at;

UPDATE productions
SET is_active = CASE WHEN status = 'current' THEN 1 ELSE 0 END,
    activated_at = CASE WHEN status = 'current' THEN CURRENT_TIMESTAMP ELSE NULL END;

ALTER TABLE channels
    ADD COLUMN read_audiences_json JSON NULL AFTER read_scope,
    ADD COLUMN post_audiences_json JSON NULL AFTER post_scope;

-- Preserve the behavior established by migration 005 while moving to a flexible audience model.
UPDATE channels
SET read_audiences_json = CASE read_scope
        WHEN 'all_members' THEN JSON_ARRAY('all_members')
        WHEN 'production_members' THEN JSON_ARRAY('production_members')
        WHEN 'staff' THEN JSON_ARRAY('staff')
        ELSE JSON_ARRAY('staff')
    END,
    post_audiences_json = CASE post_scope
        WHEN 'all_members' THEN JSON_ARRAY('all_members')
        WHEN 'production_members' THEN JSON_ARRAY('production_members')
        WHEN 'staff' THEN JSON_ARRAY('staff')
        ELSE JSON_ARRAY('staff')
    END;
