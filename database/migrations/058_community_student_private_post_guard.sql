SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_community_student_private_post_guard;

DELIMITER $$

CREATE TRIGGER trg_community_student_private_post_guard
BEFORE INSERT ON channel_posts
FOR EACH ROW
BEGIN
    DECLARE channel_mode VARCHAR(32) DEFAULT NULL;
    DECLARE live_members INT DEFAULT 0;
    DECLARE live_students INT DEFAULT 0;
    DECLARE live_staff INT DEFAULT 0;

    SELECT access_mode
      INTO channel_mode
      FROM channels
     WHERE id=NEW.channel_id
     LIMIT 1;

    IF channel_mode='selected' THEN
        SELECT COUNT(DISTINCT cm.user_id),
               COUNT(DISTINCT CASE WHEN EXISTS (
                   SELECT 1
                     FROM auth_user_roles sur
                     JOIN auth_roles sr ON sr.id=sur.role_id AND sr.active=1 AND sr.code='student'
                    WHERE sur.user_id=cm.user_id
               ) THEN cm.user_id END),
               COUNT(DISTINCT CASE WHEN EXISTS (
                   SELECT 1
                     FROM auth_user_roles aur
                     JOIN auth_roles ar ON ar.id=aur.role_id AND ar.active=1 AND ar.code IN ('administrator','production_staff')
                    WHERE aur.user_id=cm.user_id
               ) THEN cm.user_id END)
          INTO live_members,live_students,live_staff
          FROM channel_members cm
          JOIN users u ON u.id=cm.user_id AND u.active=1 AND u.account_status<>'disabled'
         WHERE cm.channel_id=NEW.channel_id
           AND cm.status='active'
           AND cm.can_post=1;
    ELSEIF channel_mode='team' THEN
        SELECT COUNT(DISTINCT tm.user_id),
               COUNT(DISTINCT CASE WHEN EXISTS (
                   SELECT 1
                     FROM auth_user_roles sur
                     JOIN auth_roles sr ON sr.id=sur.role_id AND sr.active=1 AND sr.code='student'
                    WHERE sur.user_id=tm.user_id
               ) THEN tm.user_id END),
               COUNT(DISTINCT CASE WHEN EXISTS (
                   SELECT 1
                     FROM auth_user_roles aur
                     JOIN auth_roles ar ON ar.id=aur.role_id AND ar.active=1 AND ar.code IN ('administrator','production_staff')
                    WHERE aur.user_id=tm.user_id
               ) THEN tm.user_id END)
          INTO live_members,live_students,live_staff
          FROM channel_teams ct
          JOIN teams t ON t.id=ct.team_id AND t.active=1
          JOIN team_members tm ON tm.team_id=t.id AND tm.status='active'
          JOIN users u ON u.id=tm.user_id AND u.active=1 AND u.account_status<>'disabled'
         WHERE ct.channel_id=NEW.channel_id
           AND ct.can_post=1;
    END IF;

    IF channel_mode IN ('selected','team')
       AND live_students>0
       AND (live_members<3 OR live_staff<1) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Student-inclusive private rooms require at least three available people and current CTSMD staff before posting.';
    END IF;
END$$

DELIMITER ;
