SET NAMES utf8mb4;
ALTER TABLE users ADD COLUMN identity_revision INT UNSIGNED NOT NULL DEFAULT 0;
CREATE TABLE participant_transition_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason VARCHAR(1000) NOT NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_reason VARCHAR(1000) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  KEY idx_transition_subject (user_id,status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve the live-Student last-guardian guard. An approved transition may
-- retire family management only after the Student role is explicitly retired.
DROP TRIGGER IF EXISTS trg_family_relationship_live_guardian_update;
DELIMITER $$
CREATE TRIGGER trg_family_relationship_live_guardian_update
BEFORE UPDATE ON family_relationships
FOR EACH ROW
BEGIN
  DECLARE student_available INT DEFAULT 0;
  DECLARE other_guardians INT DEFAULT 0;
  IF OLD.status='active' AND NEW.status<>'active' THEN
    SELECT COUNT(*) INTO student_available FROM users u
      WHERE u.id=OLD.student_user_id AND u.active=1 AND u.account_status<>'disabled'
      AND NOT EXISTS (
        SELECT 1 FROM participant_transition_reviews tr
        WHERE tr.user_id=u.id AND tr.status='approved'
          AND tr.reviewed_by_user_id<>tr.requested_by_user_id
          AND u.adult_participant=1
          AND NOT EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.code='student')
      );
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
