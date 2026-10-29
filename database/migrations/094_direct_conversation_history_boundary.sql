SET NAMES utf8mb4;
-- Direct participant sets are immutable once content exists. A new safeguarded
-- thread is required; no Student/guardian can inherit older Adult-only messages.
DELIMITER $$
CREATE TRIGGER direct_conversation_no_late_participant BEFORE INSERT ON conversation_participants
FOR EACH ROW BEGIN
  IF EXISTS(SELECT 1 FROM conversations c JOIN messages m ON m.conversation_id=c.id
    WHERE c.id=NEW.conversation_id AND c.conversation_type='direct') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Start a new conversation instead of exposing previous direct-message history.';
  END IF;
END$$
CREATE TRIGGER direct_conversation_no_participant_replacement BEFORE UPDATE ON conversation_participants
FOR EACH ROW BEGIN
  IF (NEW.user_id<>OLD.user_id OR NEW.conversation_id<>OLD.conversation_id)
    AND EXISTS(SELECT 1 FROM conversations c JOIN messages m ON m.conversation_id=c.id
      WHERE c.id=NEW.conversation_id AND c.conversation_type='direct') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct conversation participant identities cannot be replaced after messages exist.';
  END IF;
END$$
DELIMITER ;
