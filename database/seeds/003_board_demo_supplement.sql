-- OPTIONAL, STAGING ONLY. Apply after 001_demo.sql on an isolated demo database.
-- Repeatable fake Board-demo personas and time-relative operational records.
SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO users (first_name,last_name,email,initials,display_role,account_status,email_verified_at,onboarding_completed_at,organization_membership_status,organization_membership_reviewed_at,active)
SELECT 'Grace','Demo','board.guardian@example.test','GD','Guardian','active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,'approved',CURRENT_TIMESTAMP,1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='board.guardian@example.test');
INSERT INTO users (first_name,last_name,email,initials,display_role,account_status,email_verified_at,onboarding_completed_at,organization_membership_status,organization_membership_reviewed_at,active)
SELECT 'Sam','Demo','board.student@example.test','SD','Student','managed',NULL,CURRENT_TIMESTAMP,'approved',CURRENT_TIMESTAMP,1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='board.student@example.test');
INSERT INTO users (first_name,last_name,email,initials,display_role,account_status,email_verified_at,onboarding_completed_at,organization_membership_status,organization_membership_reviewed_at,active)
SELECT 'Val','Demo','board.volunteer@example.test','VD','Volunteer','active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,'approved',CURRENT_TIMESTAMP,1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='board.volunteer@example.test');
INSERT INTO users (first_name,last_name,email,initials,display_role,account_status,email_verified_at,onboarding_completed_at,organization_membership_status,organization_membership_reviewed_at,active)
SELECT 'Pat','Demo','board.staff@example.test','PD','Production Staff','active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,'approved',CURRENT_TIMESTAMP,1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='board.staff@example.test');
INSERT INTO users (first_name,last_name,email,initials,display_role,account_status,email_verified_at,onboarding_completed_at,organization_membership_status,organization_membership_reviewed_at,active)
SELECT 'Morgan','Demo','board.moderator@example.test','MD','Moderator','active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,'approved',CURRENT_TIMESTAMP,1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='board.moderator@example.test');
INSERT INTO users (first_name,last_name,email,initials,display_role,account_status,email_verified_at,onboarding_completed_at,organization_membership_status,organization_membership_reviewed_at,active)
SELECT 'Sky','Demo','board.safeguarding@example.test','SD','Safeguarding','active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,'approved',CURRENT_TIMESTAMP,1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='board.safeguarding@example.test');
INSERT INTO users (first_name,last_name,email,initials,display_role,account_status,email_verified_at,onboarding_completed_at,organization_membership_status,organization_membership_reviewed_at,active)
SELECT 'Avery','Demo','board.admin@example.test','AD','Administrator','active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,'approved',CURRENT_TIMESTAMP,1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='board.admin@example.test');

SET @board_guardian=(SELECT id FROM users WHERE email='board.guardian@example.test');
SET @board_student=(SELECT id FROM users WHERE email='board.student@example.test');
SET @board_volunteer=(SELECT id FROM users WHERE email='board.volunteer@example.test');
SET @board_staff=(SELECT id FROM users WHERE email='board.staff@example.test');
SET @board_moderator=(SELECT id FROM users WHERE email='board.moderator@example.test');
SET @board_safeguarding=(SELECT id FROM users WHERE email='board.safeguarding@example.test');
SET @board_admin=(SELECT id FROM users WHERE email='board.admin@example.test');

DELETE FROM auth_user_roles WHERE user_id IN (@board_guardian,@board_student,@board_volunteer,@board_staff,@board_moderator,@board_safeguarding,@board_admin);
INSERT INTO auth_user_roles (user_id,role_id) SELECT @board_guardian,id FROM auth_roles WHERE code='member';
INSERT INTO auth_user_roles (user_id,role_id) SELECT @board_student,id FROM auth_roles WHERE code='student';
INSERT INTO auth_user_roles (user_id,role_id) SELECT @board_volunteer,id FROM auth_roles WHERE code='volunteer';
INSERT INTO auth_user_roles (user_id,role_id) SELECT @board_staff,id FROM auth_roles WHERE code='production_staff';
INSERT INTO auth_user_roles (user_id,role_id) SELECT @board_moderator,id FROM auth_roles WHERE code='moderator';
INSERT INTO auth_user_roles (user_id,role_id) SELECT @board_safeguarding,id FROM auth_roles WHERE code='safeguarding';
INSERT INTO auth_user_roles (user_id,role_id) SELECT @board_admin,id FROM auth_roles WHERE code='administrator';

SET @board_show=(SELECT id FROM productions WHERE title='Matilda Jr.' AND is_active=1 ORDER BY id LIMIT 1);
INSERT INTO family_relationships (guardian_user_id,student_user_id,relationship_type,is_primary,status,created_by_user_id)
SELECT @board_guardian,@board_student,'guardian',1,'active',@board_admin WHERE NOT EXISTS (SELECT 1 FROM family_relationships WHERE guardian_user_id=@board_guardian AND student_user_id=@board_student AND status='active');

INSERT INTO production_memberships (production_id,user_id,audience_type,participation_role,status)
SELECT @board_show,@board_student,'student','DEMO Ensemble','active' WHERE NOT EXISTS (SELECT 1 FROM production_memberships WHERE production_id=@board_show AND user_id=@board_student AND status='active');
INSERT INTO production_memberships (production_id,user_id,audience_type,participation_role,status)
SELECT @board_show,@board_guardian,'guardian','DEMO Guardian','active' WHERE NOT EXISTS (SELECT 1 FROM production_memberships WHERE production_id=@board_show AND user_id=@board_guardian AND status='active');
INSERT INTO production_memberships (production_id,user_id,audience_type,participation_role,status)
SELECT @board_show,@board_staff,'staff','DEMO Production Staff','active' WHERE NOT EXISTS (SELECT 1 FROM production_memberships WHERE production_id=@board_show AND user_id=@board_staff AND status='active');

INSERT INTO schedule_items (production_id,title,starts_at,ends_at,family_call_at,location,visibility,item_type)
SELECT @board_show,'DEMO Full Cast Rehearsal',TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 2 DAY),'17:30:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 2 DAY),'20:30:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 2 DAY),'17:15:00'),'Main Stage','all','rehearsal' WHERE NOT EXISTS (SELECT 1 FROM schedule_items WHERE production_id=@board_show AND title='DEMO Full Cast Rehearsal');
UPDATE schedule_items SET starts_at=TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 2 DAY),'17:30:00'),ends_at=TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 2 DAY),'20:30:00'),family_call_at=TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 2 DAY),'17:15:00'),status='active',cancelled_at=NULL,cancelled_by_user_id=NULL WHERE production_id=@board_show AND title='DEMO Full Cast Rehearsal';

INSERT INTO volunteer_shifts (production_id,title,category,starts_at,ends_at,location,required_slots,approval_required)
SELECT @board_show,'DEMO Lobby Team','Front of House',TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 3 DAY),'17:00:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 3 DAY),'20:30:00'),'Main Lobby',4,0 WHERE NOT EXISTS (SELECT 1 FROM volunteer_shifts WHERE title='DEMO Lobby Team');
UPDATE volunteer_shifts SET production_id=@board_show,starts_at=TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 3 DAY),'17:00:00'),ends_at=TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 3 DAY),'20:30:00'),approval_required=0 WHERE title='DEMO Lobby Team';
INSERT INTO volunteer_shifts (production_id,title,category,starts_at,ends_at,location,required_slots,approval_required)
SELECT @board_show,'DEMO Student Check-in','Student Support',TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 4 DAY),'16:30:00'),TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 4 DAY),'19:30:00'),'Stage Door',2,1 WHERE NOT EXISTS (SELECT 1 FROM volunteer_shifts WHERE title='DEMO Student Check-in');
UPDATE volunteer_shifts SET production_id=@board_show,starts_at=TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 4 DAY),'16:30:00'),ends_at=TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 4 DAY),'19:30:00'),approval_required=1 WHERE title='DEMO Student Check-in';

INSERT INTO forms (production_id,title,form_type,instructions,completion_mode,review_required,created_by_user_id,active)
SELECT @board_show,'DEMO Student Participation Acknowledgment','acknowledgment','Review the fake Board-demo participation details and acknowledge completion.','acknowledgment',0,@board_staff,1 WHERE NOT EXISTS (SELECT 1 FROM forms WHERE title='DEMO Student Participation Acknowledgment');
SET @board_form=(SELECT id FROM forms WHERE title='DEMO Student Participation Acknowledgment' ORDER BY id LIMIT 1);
INSERT INTO form_assignments (form_id,production_id,user_id,subject_user_id,status,due_at,assigned_by_user_id)
SELECT @board_form,@board_show,@board_guardian,@board_student,'due_soon',TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 3 DAY),'20:00:00'),@board_staff WHERE NOT EXISTS (SELECT 1 FROM form_assignments WHERE form_id=@board_form AND user_id=@board_guardian AND subject_user_id=@board_student);

INSERT INTO production_checklist_items (production_id,title,category,status,due_at,assigned_to_user_id,created_by_user_id)
SELECT @board_show,'DEMO Confirm rehearsal room','Board demo','open',TIMESTAMP(DATE_ADD(CURDATE(),INTERVAL 1 DAY),'12:00:00'),@board_staff,@board_staff WHERE NOT EXISTS (SELECT 1 FROM production_checklist_items WHERE production_id=@board_show AND title='DEMO Confirm rehearsal room');
INSERT INTO production_casting_records (production_id,user_id,casting_status,role_title,created_by_user_id,production_membership_id,rostered_at)
SELECT @board_show,@board_student,'cast','DEMO Ensemble',@board_staff,(SELECT id FROM production_memberships WHERE production_id=@board_show AND user_id=@board_student AND audience_type='student' AND status='active' LIMIT 1),CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM production_casting_records WHERE production_id=@board_show AND user_id=@board_student);
INSERT INTO playbills (production_id,display_title,subtitle,cover_note,status,public_slug,created_by_user_id,published_at)
SELECT @board_show,'Matilda Jr.','Board demonstration','Fake staging-only Board demonstration playbill.','current','board-demo-matilda-jr',@board_staff,CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM playbills WHERE public_slug='board-demo-matilda-jr');
INSERT INTO app_notifications (recipient_user_id,source_type,title,body,action_path)
SELECT @board_guardian,'board_demo','DEMO Rehearsal reminder','This is fake Board demonstration content.','/calendar' WHERE NOT EXISTS (SELECT 1 FROM app_notifications WHERE recipient_user_id=@board_guardian AND title='DEMO Rehearsal reminder');

COMMIT;
