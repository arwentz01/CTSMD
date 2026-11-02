SET NAMES utf8mb4;

-- Forward correction of 093/095. Preserve legacy data and audit history.
INSERT INTO auth_roles(code,name,description,system_role,active)
VALUES('adult_performer','Adult Performer','Adult participant eligible for casting, production rosters, and theatre credits. Does not grant staff or guardian permissions.',1,1)
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description);
-- This role intentionally has NO auth_role_permissions grants.

CREATE TABLE IF NOT EXISTS adult_performer_legacy_mappings (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 decision ENUM('mapped','skipped_student_or_child') NOT NULL,
 recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- One-time ledger prevents a later migration retry from restoring a revoked role.
CREATE TEMPORARY TABLE migration_097_candidates(user_id BIGINT UNSIGNED PRIMARY KEY,eligible TINYINT NOT NULL);
INSERT INTO migration_097_candidates
SELECT u.id,NOT EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.code='student')
 AND NOT EXISTS(SELECT 1 FROM family_relationships fr WHERE fr.student_user_id=u.id AND fr.status='active')
FROM users u WHERE u.adult_participant=1
 AND NOT EXISTS(SELECT 1 FROM adult_performer_legacy_mappings m WHERE m.user_id=u.id);
START TRANSACTION;
INSERT INTO audit_events(actor_user_id,event_type,subject_type,subject_id,summary,metadata_json)
SELECT NULL,'adult_performer.legacy_role_mapped','user',c.user_id,
 'Mapped legacy Adult participation to the existing role framework.',JSON_OBJECT('migration','097','legacy_adult_participant',1)
FROM migration_097_candidates c WHERE c.eligible=1;
INSERT IGNORE INTO auth_user_roles(user_id,role_id)
SELECT c.user_id,r.id FROM migration_097_candidates c JOIN auth_roles r ON r.code='adult_performer' WHERE c.eligible=1;
INSERT INTO adult_performer_legacy_mappings(user_id,decision)
SELECT user_id,IF(eligible=1,'mapped','skipped_student_or_child') FROM migration_097_candidates;
COMMIT;
DROP TEMPORARY TABLE migration_097_candidates;

-- Retire 095's separate review-based exception; restore the ordinary guardian guard.
DROP TRIGGER IF EXISTS trg_family_relationship_live_guardian_update;
DELIMITER $$
CREATE TRIGGER trg_family_relationship_live_guardian_update
BEFORE UPDATE ON family_relationships
FOR EACH ROW
BEGIN
 DECLARE student_available INT DEFAULT 0;
 DECLARE other_guardians INT DEFAULT 0;
 IF OLD.status='active' AND NEW.status<>'active' THEN
  SELECT COUNT(*) INTO student_available FROM users
   WHERE id=OLD.student_user_id AND active=1 AND account_status<>'disabled';
  IF student_available>0 THEN
   SELECT COUNT(DISTINCT fr.guardian_user_id) INTO other_guardians
    FROM family_relationships fr JOIN users guardian ON guardian.id=fr.guardian_user_id
     AND guardian.active=1 AND guardian.account_status<>'disabled'
    WHERE fr.student_user_id=OLD.student_user_id AND fr.status='active' AND fr.id<>OLD.id;
   IF other_guardians<1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A live Student must retain at least one available guardian.';
   END IF;
  END IF;
 END IF;
END$$
DELIMITER ;
