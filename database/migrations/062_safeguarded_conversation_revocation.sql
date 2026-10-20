SET NAMES utf8mb4;

ALTER TABLE conversation_participants
    ADD COLUMN access_revoked_at DATETIME NULL AFTER guardian_required,
    ADD COLUMN history_through_message_id BIGINT UNSIGNED NULL AFTER access_revoked_at,
    ADD KEY idx_conversation_participant_live (user_id,access_revoked_at,conversation_id);

-- Existing safeguarded threads inherit every active, available guardian.
INSERT INTO conversation_participants
    (conversation_id,user_id,participant_role,guardian_required,access_revoked_at,history_through_message_id)
SELECT DISTINCT c.id,fr.guardian_user_id,'guardian',1,NULL,NULL
FROM conversations c
JOIN conversation_participants student_cp
  ON student_cp.conversation_id=c.id
 AND student_cp.participant_role='student'
JOIN family_relationships fr
  ON fr.student_user_id=student_cp.user_id
 AND fr.status='active'
JOIN users guardian
  ON guardian.id=fr.guardian_user_id
 AND guardian.active=1
 AND guardian.account_status='active'
WHERE c.conversation_type='safeguarded'
ON DUPLICATE KEY UPDATE
    participant_role='guardian',
    guardian_required=1,
    access_revoked_at=NULL,
    history_through_message_id=NULL;

-- Preserve old messages while cutting off future visibility for no-longer-eligible guardians.
UPDATE conversation_participants guardian_cp
JOIN conversations c
  ON c.id=guardian_cp.conversation_id
 AND c.conversation_type='safeguarded'
JOIN conversation_participants student_cp
  ON student_cp.conversation_id=c.id
 AND student_cp.participant_role='student'
LEFT JOIN family_relationships fr
  ON fr.student_user_id=student_cp.user_id
 AND fr.guardian_user_id=guardian_cp.user_id
 AND fr.status='active'
LEFT JOIN users guardian
  ON guardian.id=guardian_cp.user_id
 AND guardian.active=1
 AND guardian.account_status='active'
SET guardian_cp.access_revoked_at=COALESCE(guardian_cp.access_revoked_at,CURRENT_TIMESTAMP),
    guardian_cp.history_through_message_id=COALESCE(
        guardian_cp.history_through_message_id,
        (SELECT COALESCE(MAX(m.id),0) FROM messages m WHERE m.conversation_id=guardian_cp.conversation_id)
    )
WHERE guardian_cp.participant_role='guardian'
  AND (fr.id IS NULL OR guardian.id IS NULL);

DROP TRIGGER IF EXISTS trg_family_conversation_guardian_insert;
DROP TRIGGER IF EXISTS trg_family_conversation_guardian_update;
DROP TRIGGER IF EXISTS trg_user_disable_operational_cleanup;

DELIMITER $$

CREATE TRIGGER trg_family_conversation_guardian_insert
AFTER INSERT ON family_relationships
FOR EACH ROW
BEGIN
    IF NEW.status='active' THEN
        INSERT INTO conversation_participants
            (conversation_id,user_id,participant_role,guardian_required,access_revoked_at,history_through_message_id)
        SELECT DISTINCT c.id,NEW.guardian_user_id,'guardian',1,NULL,NULL
        FROM conversations c
        JOIN conversation_participants student_cp
          ON student_cp.conversation_id=c.id
         AND student_cp.user_id=NEW.student_user_id
        JOIN users guardian
          ON guardian.id=NEW.guardian_user_id
         AND guardian.active=1
         AND guardian.account_status='active'
        WHERE c.conversation_type='safeguarded'
        ON DUPLICATE KEY UPDATE
            participant_role='guardian',
            guardian_required=1,
            access_revoked_at=NULL,
            history_through_message_id=NULL;
    END IF;
END$$

CREATE TRIGGER trg_family_conversation_guardian_update
AFTER UPDATE ON family_relationships
FOR EACH ROW
BEGIN
    IF OLD.status='active' AND NEW.status<>'active' THEN
        UPDATE conversation_participants guardian_cp
        JOIN conversations c
          ON c.id=guardian_cp.conversation_id
         AND c.conversation_type='safeguarded'
        JOIN conversation_participants student_cp
          ON student_cp.conversation_id=c.id
         AND student_cp.user_id=OLD.student_user_id
        SET guardian_cp.access_revoked_at=COALESCE(guardian_cp.access_revoked_at,CURRENT_TIMESTAMP),
            guardian_cp.history_through_message_id=COALESCE(
                guardian_cp.history_through_message_id,
                (SELECT COALESCE(MAX(m.id),0) FROM messages m WHERE m.conversation_id=guardian_cp.conversation_id)
            )
        WHERE guardian_cp.user_id=OLD.guardian_user_id
          AND guardian_cp.participant_role='guardian';
    END IF;

    IF OLD.status<>'active' AND NEW.status='active' THEN
        INSERT INTO conversation_participants
            (conversation_id,user_id,participant_role,guardian_required,access_revoked_at,history_through_message_id)
        SELECT DISTINCT c.id,NEW.guardian_user_id,'guardian',1,NULL,NULL
        FROM conversations c
        JOIN conversation_participants student_cp
          ON student_cp.conversation_id=c.id
         AND student_cp.user_id=NEW.student_user_id
        JOIN users guardian
          ON guardian.id=NEW.guardian_user_id
         AND guardian.active=1
         AND guardian.account_status='active'
        WHERE c.conversation_type='safeguarded'
        ON DUPLICATE KEY UPDATE
            participant_role='guardian',
            guardian_required=1,
            access_revoked_at=NULL,
            history_through_message_id=NULL;
    END IF;
END$$

CREATE TRIGGER trg_user_disable_operational_cleanup
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.account_status='disabled' AND OLD.account_status<>'disabled' THEN
        UPDATE volunteer_shift_signups vss
        JOIN volunteer_shifts vs ON vs.id=vss.shift_id
        SET vss.status='cancelled'
        WHERE vss.user_id=NEW.id
          AND vss.status='signed_up'
          AND vs.starts_at>NOW();

        UPDATE volunteer_shift_approval_requests r
        JOIN volunteer_shifts vs ON vs.id=r.shift_id
        SET r.status='withdrawn',r.updated_at=CURRENT_TIMESTAMP
        WHERE r.user_id=NEW.id
          AND r.status='pending'
          AND vs.starts_at>NOW();

        UPDATE conversation_participants cp
        JOIN conversations c
          ON c.id=cp.conversation_id
         AND c.conversation_type='safeguarded'
        SET cp.access_revoked_at=COALESCE(cp.access_revoked_at,CURRENT_TIMESTAMP),
            cp.history_through_message_id=COALESCE(
                cp.history_through_message_id,
                (SELECT COALESCE(MAX(m.id),0) FROM messages m WHERE m.conversation_id=cp.conversation_id)
            )
        WHERE cp.user_id=NEW.id
          AND cp.participant_role='guardian';
    END IF;

    IF NEW.account_status='active' AND OLD.account_status='disabled' THEN
        UPDATE conversation_participants cp
        JOIN conversations c
          ON c.id=cp.conversation_id
         AND c.conversation_type='safeguarded'
        JOIN conversation_participants student_cp
          ON student_cp.conversation_id=c.id
         AND student_cp.participant_role='student'
        JOIN family_relationships fr
          ON fr.student_user_id=student_cp.user_id
         AND fr.guardian_user_id=NEW.id
         AND fr.status='active'
        SET cp.access_revoked_at=NULL,
            cp.history_through_message_id=NULL,
            cp.participant_role='guardian',
            cp.guardian_required=1
        WHERE cp.user_id=NEW.id;
    END IF;
END$$

DELIMITER ;
