SET NAMES utf8mb4;

INSERT INTO production_memberships (production_id,user_id,audience_type,participation_role,status)
SELECT DISTINCT
    student_pm.production_id,
    fr.guardian_user_id,
    'guardian',
    CASE fr.relationship_type
        WHEN 'parent' THEN 'Parent'
        WHEN 'caregiver' THEN 'Caregiver'
        ELSE 'Guardian'
    END,
    'active'
FROM family_relationships fr
JOIN users guardian
  ON guardian.id=fr.guardian_user_id
 AND guardian.active=1
 AND guardian.account_status<>'disabled'
JOIN users student
  ON student.id=fr.student_user_id
 AND student.active=1
 AND student.account_status<>'disabled'
JOIN production_memberships student_pm
  ON student_pm.user_id=fr.student_user_id
 AND student_pm.audience_type='student'
 AND student_pm.status='active'
JOIN productions p
  ON p.id=student_pm.production_id
 AND p.is_active=1
WHERE fr.status='active'
ON DUPLICATE KEY UPDATE
    participation_role=VALUES(participation_role),
    status='active',
    updated_at=CURRENT_TIMESTAMP;

-- Materialize stale membership IDs before updating production_memberships.
-- MySQL rejects an UPDATE that reads the same target table through a correlated
-- subquery (#1093), even though the read is logically separate.
DROP TEMPORARY TABLE IF EXISTS tmp_stale_guardian_memberships;
CREATE TEMPORARY TABLE tmp_stale_guardian_memberships (
    id BIGINT UNSIGNED PRIMARY KEY
) ENGINE=MEMORY
AS
SELECT guardian_pm.id
FROM production_memberships guardian_pm
JOIN productions p
  ON p.id=guardian_pm.production_id
 AND p.is_active=1
LEFT JOIN (
    SELECT DISTINCT
        student_pm.production_id,
        fr.guardian_user_id
    FROM family_relationships fr
    JOIN users guardian
      ON guardian.id=fr.guardian_user_id
     AND guardian.active=1
     AND guardian.account_status<>'disabled'
    JOIN users student
      ON student.id=fr.student_user_id
     AND student.active=1
     AND student.account_status<>'disabled'
    JOIN production_memberships student_pm
      ON student_pm.user_id=fr.student_user_id
     AND student_pm.audience_type='student'
     AND student_pm.status='active'
    WHERE fr.status='active'
) valid_guardian
  ON valid_guardian.production_id=guardian_pm.production_id
 AND valid_guardian.guardian_user_id=guardian_pm.user_id
WHERE guardian_pm.audience_type='guardian'
  AND guardian_pm.status='active'
  AND valid_guardian.guardian_user_id IS NULL;

UPDATE production_memberships guardian_pm
JOIN tmp_stale_guardian_memberships stale
  ON stale.id=guardian_pm.id
SET guardian_pm.status='inactive',
    guardian_pm.updated_at=CURRENT_TIMESTAMP;

DROP TEMPORARY TABLE IF EXISTS tmp_stale_guardian_memberships;

DROP TRIGGER IF EXISTS trg_family_production_guardian_insert;
DROP TRIGGER IF EXISTS trg_family_production_guardian_update;

DELIMITER $$

CREATE TRIGGER trg_family_production_guardian_insert
AFTER INSERT ON family_relationships
FOR EACH ROW
BEGIN
    IF NEW.status='active' THEN
        INSERT INTO production_memberships (production_id,user_id,audience_type,participation_role,status)
        SELECT DISTINCT
            student_pm.production_id,
            NEW.guardian_user_id,
            'guardian',
            CASE NEW.relationship_type
                WHEN 'parent' THEN 'Parent'
                WHEN 'caregiver' THEN 'Caregiver'
                ELSE 'Guardian'
            END,
            'active'
        FROM production_memberships student_pm
        JOIN productions p
          ON p.id=student_pm.production_id
         AND p.is_active=1
        JOIN users guardian
          ON guardian.id=NEW.guardian_user_id
         AND guardian.active=1
         AND guardian.account_status<>'disabled'
        JOIN users student
          ON student.id=NEW.student_user_id
         AND student.active=1
         AND student.account_status<>'disabled'
        WHERE student_pm.user_id=NEW.student_user_id
          AND student_pm.audience_type='student'
          AND student_pm.status='active'
        ON DUPLICATE KEY UPDATE
            participation_role=VALUES(participation_role),
            status='active',
            updated_at=CURRENT_TIMESTAMP;
    END IF;
END$$

CREATE TRIGGER trg_family_production_guardian_update
AFTER UPDATE ON family_relationships
FOR EACH ROW
BEGIN
    IF NEW.status='active' THEN
        INSERT INTO production_memberships (production_id,user_id,audience_type,participation_role,status)
        SELECT DISTINCT
            student_pm.production_id,
            NEW.guardian_user_id,
            'guardian',
            CASE NEW.relationship_type
                WHEN 'parent' THEN 'Parent'
                WHEN 'caregiver' THEN 'Caregiver'
                ELSE 'Guardian'
            END,
            'active'
        FROM production_memberships student_pm
        JOIN productions p
          ON p.id=student_pm.production_id
         AND p.is_active=1
        JOIN users guardian
          ON guardian.id=NEW.guardian_user_id
         AND guardian.active=1
         AND guardian.account_status<>'disabled'
        JOIN users student
          ON student.id=NEW.student_user_id
         AND student.active=1
         AND student.account_status<>'disabled'
        WHERE student_pm.user_id=NEW.student_user_id
          AND student_pm.audience_type='student'
          AND student_pm.status='active'
        ON DUPLICATE KEY UPDATE
            participation_role=VALUES(participation_role),
            status='active',
            updated_at=CURRENT_TIMESTAMP;
    END IF;

    IF OLD.status='active' AND NEW.status='inactive' THEN
        UPDATE production_memberships guardian_pm
        LEFT JOIN (
            SELECT production_id
            FROM (
                SELECT DISTINCT student_pm.production_id
                FROM family_relationships fr
                JOIN users guardian
                  ON guardian.id=fr.guardian_user_id
                 AND guardian.active=1
                 AND guardian.account_status<>'disabled'
                JOIN users student
                  ON student.id=fr.student_user_id
                 AND student.active=1
                 AND student.account_status<>'disabled'
                JOIN production_memberships student_pm
                  ON student_pm.user_id=fr.student_user_id
                 AND student_pm.audience_type='student'
                 AND student_pm.status='active'
                JOIN productions active_production
                  ON active_production.id=student_pm.production_id
                 AND active_production.is_active=1
                WHERE fr.guardian_user_id=NEW.guardian_user_id
                  AND fr.status='active'
            ) remaining_guardian_productions_materialized
        ) remaining_guardian_productions
          ON remaining_guardian_productions.production_id=guardian_pm.production_id
        JOIN productions p
          ON p.id=guardian_pm.production_id
         AND p.is_active=1
        SET guardian_pm.status='inactive',
            guardian_pm.updated_at=CURRENT_TIMESTAMP
        WHERE guardian_pm.user_id=NEW.guardian_user_id
          AND guardian_pm.audience_type='guardian'
          AND guardian_pm.status='active'
          AND remaining_guardian_productions.production_id IS NULL;
    END IF;
END$$

DELIMITER ;
