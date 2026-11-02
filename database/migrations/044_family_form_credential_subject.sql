SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_form_approval_credential_update;

DELIMITER $$

CREATE TRIGGER trg_form_approval_credential_update
AFTER UPDATE ON form_submissions
FOR EACH ROW
BEGIN
    IF NEW.status='approved' AND OLD.status <> 'approved' THEN
        INSERT INTO volunteer_credentials (user_id,requirement_id,status,completed_at,expires_at,verified_by_user_id)
        SELECT COALESCE(NEW.submitted_for_user_id,NEW.submitted_by_user_id),m.requirement_id,'approved',COALESCE(NEW.reviewed_at,CURRENT_TIMESTAMP),
               CASE WHEN m.validity_days IS NULL THEN NULL ELSE DATE_ADD(COALESCE(NEW.reviewed_at,CURRENT_TIMESTAMP),INTERVAL m.validity_days DAY) END,
               NEW.reviewer_user_id
        FROM form_requirement_mappings m
        WHERE m.form_id=NEW.form_id AND m.active=1
        ON DUPLICATE KEY UPDATE status='approved',completed_at=VALUES(completed_at),expires_at=VALUES(expires_at),verified_by_user_id=VALUES(verified_by_user_id);
    END IF;
END$$

DELIMITER ;

INSERT INTO volunteer_credentials (user_id,requirement_id,status,completed_at,expires_at,verified_by_user_id)
SELECT COALESCE(fs.submitted_for_user_id,fs.submitted_by_user_id),m.requirement_id,'approved',COALESCE(fs.reviewed_at,fs.submitted_at,CURRENT_TIMESTAMP),
       CASE WHEN m.validity_days IS NULL THEN NULL ELSE DATE_ADD(COALESCE(fs.reviewed_at,fs.submitted_at,CURRENT_TIMESTAMP),INTERVAL m.validity_days DAY) END,
       fs.reviewer_user_id
FROM form_submissions fs
JOIN form_requirement_mappings m ON m.form_id=fs.form_id AND m.active=1
WHERE fs.status='approved'
  AND NOT EXISTS (
      SELECT 1
      FROM volunteer_credentials vc
      WHERE vc.user_id=COALESCE(fs.submitted_for_user_id,fs.submitted_by_user_id)
        AND vc.requirement_id=m.requirement_id
  );
