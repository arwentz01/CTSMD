-- OPTIONAL, STAGING ONLY. Add after 001_demo.sql on an isolated demo database.
-- This does not reset data, create passwords, or modify existing people.
-- All inserts require the exact fake demo student, guardian and production.
SET NAMES utf8mb4;
START TRANSACTION;
SET @board_student = (SELECT id FROM users WHERE email='emma@example.test');
SET @board_guardian = (SELECT id FROM users WHERE email='jamie@example.test');
SET @board_staff = (SELECT id FROM users WHERE email='maya@example.test');
SET @board_show = (SELECT p.id FROM productions p JOIN production_memberships pm ON pm.production_id=p.id AND pm.user_id=@board_student AND pm.status='active' WHERE p.title='Matilda Jr.' AND p.is_active=1 ORDER BY p.id LIMIT 1);

INSERT INTO production_checklist_items (production_id,title,category,status,created_by_user_id)
SELECT @board_show,'DEMO: confirm rehearsal room','Board demo','open',@board_staff
WHERE @board_show IS NOT NULL AND @board_staff IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM production_checklist_items WHERE production_id=@board_show AND title='DEMO: confirm rehearsal room');

INSERT INTO production_casting_records (production_id,user_id,casting_status,role_title,created_by_user_id,production_membership_id,rostered_at)
SELECT @board_show,@board_student,'cast','DEMO Ensemble',@board_staff,(SELECT id FROM production_memberships WHERE production_id=@board_show AND user_id=@board_student AND audience_type='student' AND status='active' LIMIT 1),CURRENT_TIMESTAMP
WHERE @board_show IS NOT NULL AND @board_staff IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM production_casting_records WHERE production_id=@board_show AND user_id=@board_student);

INSERT INTO app_notifications (recipient_user_id,source_type,title,body,action_path)
SELECT @board_guardian,'board_demo','DEMO: rehearsal reminder','This is fake Board demonstration content.','/calendar'
WHERE @board_show IS NOT NULL AND @board_guardian IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM app_notifications WHERE recipient_user_id=@board_guardian AND title='DEMO: rehearsal reminder');
COMMIT;
-- Publish the cast with the staff casting workflow to capture the correct snapshot.
-- Student in 001 is managed (no login). Use account activation in demo staging.
