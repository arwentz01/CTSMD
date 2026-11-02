SET NAMES utf8mb4;

-- Family-aware form submissions must remain bound to the live assignment subject
-- and an authorized submitter. Pending review submissions are immutable until
-- staff approves or returns them; rejected submissions may be corrected and
-- resubmitted through the normal assignment flow.

DROP TRIGGER IF EXISTS trg_family_form_submission_insert_guard;
DROP TRIGGER IF EXISTS trg_family_form_submission_update_guard;

DELIMITER $$

CREATE TRIGGER trg_family_form_submission_insert_guard
BEFORE INSERT ON form_submissions
FOR EACH ROW
BEGIN
    IF NEW.status='submitted' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM form_assignments fa
            JOIN users subject
              ON subject.id=COALESCE(fa.subject_user_id,fa.user_id)
             AND subject.active=1
             AND subject.account_status<>'disabled'
            JOIN users submitter
              ON submitter.id=NEW.submitted_by_user_id
             AND submitter.active=1
             AND submitter.account_status='active'
            WHERE fa.id=NEW.assignment_id
              AND COALESCE(fa.subject_user_id,fa.user_id)=NEW.submitted_for_user_id
              AND fa.status NOT IN ('completed','requires_review')
              AND (
                    fa.user_id=NEW.submitted_by_user_id
                    OR EXISTS (
                        SELECT 1
                        FROM family_relationships fr
                        WHERE fr.guardian_user_id=NEW.submitted_by_user_id
                          AND fr.student_user_id=COALESCE(fa.subject_user_id,fa.user_id)
                          AND fr.status='active'
                    )
              )
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Form submission requires a live assignment subject and authorized submitter.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_family_form_submission_update_guard
BEFORE UPDATE ON form_submissions
FOR EACH ROW
BEGIN
    IF NEW.status='submitted' THEN
        IF OLD.status='submitted' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='A form awaiting review cannot be resubmitted until staff returns it.';
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM form_assignments fa
            JOIN users subject
              ON subject.id=COALESCE(fa.subject_user_id,fa.user_id)
             AND subject.active=1
             AND subject.account_status<>'disabled'
            JOIN users submitter
              ON submitter.id=NEW.submitted_by_user_id
             AND submitter.active=1
             AND submitter.account_status='active'
            WHERE fa.id=NEW.assignment_id
              AND COALESCE(fa.subject_user_id,fa.user_id)=NEW.submitted_for_user_id
              AND fa.status NOT IN ('completed','requires_review')
              AND (
                    fa.user_id=NEW.submitted_by_user_id
                    OR EXISTS (
                        SELECT 1
                        FROM family_relationships fr
                        WHERE fr.guardian_user_id=NEW.submitted_by_user_id
                          AND fr.student_user_id=COALESCE(fa.subject_user_id,fa.user_id)
                          AND fr.status='active'
                    )
              )
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Form resubmission requires a live assignment subject and authorized submitter.';
        END IF;
    END IF;
END$$

DELIMITER ;
