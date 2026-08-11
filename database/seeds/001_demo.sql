SET NAMES utf8mb4;

-- Demo reset. Apply all migrations before running this seed.
-- Use DELETE rather than TRUNCATE so foreign-key relationships remain enforced.
-- Child/dependent records are removed before their parents.
DELETE FROM push_delivery_log;
DELETE FROM push_queue;
DELETE FROM push_subscriptions;
DELETE FROM push_event_cursors;
DELETE FROM playbill_sponsors;
DELETE FROM channel_post_attachments;
DELETE FROM message_attachments;
DELETE FROM safeguarding_case_events;
DELETE FROM safeguarding_cases;
DELETE FROM external_theatre_credits;
DELETE FROM theatre_history_credits;
DELETE FROM production_cast_publications;
DELETE FROM production_casting_records;
DELETE FROM production_day_briefs;
DELETE FROM production_checklist_items;
DELETE FROM volunteer_service_verifications;
DELETE FROM volunteer_coordinator_assignments;
DELETE FROM volunteer_training_completions;
DELETE FROM volunteer_hour_entries;
DELETE FROM form_requirement_mappings;
DELETE FROM volunteer_training_modules;
DELETE FROM registration_submission_links;
DELETE FROM registration_submissions;
DELETE FROM registration_opportunities;
DELETE FROM email_delivery_log;
DELETE FROM email_queue;
DELETE FROM notification_preferences;
DELETE FROM auth_email_verifications;
DELETE FROM auth_password_resets;
DELETE FROM auth_invitations;
DELETE FROM auth_user_roles;
DELETE FROM calendar_subscriptions;
DELETE FROM channel_read_states;
DELETE FROM communication_read_state_meta;
DELETE FROM production_files;
DELETE FROM organization_resources;
DELETE FROM student_profiles;
DELETE FROM sponsors;
DELETE FROM stored_file_versions;
DELETE FROM stored_files;
DELETE FROM form_submission_answers;
DELETE FROM form_fields;
DELETE FROM attendance_absence_reports;
DELETE FROM attendance_records;
DELETE FROM schedule_item_groups;
DELETE FROM production_group_members;
DELETE FROM channel_teams;
DELETE FROM channel_members;
DELETE FROM team_members;
DELETE FROM production_groups;
DELETE FROM teams;
DELETE FROM playbill_sections;
DELETE FROM production_resources;
DELETE FROM schedule_notice_deliveries;
DELETE FROM app_notifications;
DELETE FROM audit_events;
DELETE FROM messages;
DELETE FROM conversation_participants;
DELETE FROM conversations;
DELETE FROM playbills;
DELETE FROM form_submissions;
DELETE FROM form_assignments;
DELETE FROM forms;
DELETE FROM volunteer_shift_approval_requests;
DELETE FROM volunteer_shift_signups;
DELETE FROM volunteer_shift_requirements;
DELETE FROM volunteer_shifts;
DELETE FROM volunteer_credentials;
DELETE FROM volunteer_requirements;
DELETE FROM volunteer_profiles;
DELETE FROM channel_posts;
DELETE FROM channels;
DELETE FROM moderation_terms;
DELETE FROM schedule_change_notices;
DELETE FROM schedule_items;
DELETE FROM announcements;
DELETE FROM production_memberships;
DELETE FROM productions;
DELETE FROM family_relationships;
DELETE FROM users;

ALTER TABLE push_delivery_log AUTO_INCREMENT = 1;
ALTER TABLE push_queue AUTO_INCREMENT = 1;
ALTER TABLE push_subscriptions AUTO_INCREMENT = 1;
ALTER TABLE sponsors AUTO_INCREMENT = 1;
ALTER TABLE external_theatre_credits AUTO_INCREMENT = 1;
ALTER TABLE message_attachments AUTO_INCREMENT = 1;
ALTER TABLE channel_post_attachments AUTO_INCREMENT = 1;
ALTER TABLE safeguarding_case_events AUTO_INCREMENT = 1;
ALTER TABLE safeguarding_cases AUTO_INCREMENT = 1;
ALTER TABLE theatre_history_credits AUTO_INCREMENT = 1;
ALTER TABLE production_casting_records AUTO_INCREMENT = 1;
ALTER TABLE production_day_briefs AUTO_INCREMENT = 1;
ALTER TABLE production_checklist_items AUTO_INCREMENT = 1;
ALTER TABLE volunteer_service_verifications AUTO_INCREMENT = 1;
ALTER TABLE volunteer_coordinator_assignments AUTO_INCREMENT = 1;
ALTER TABLE volunteer_training_completions AUTO_INCREMENT = 1;
ALTER TABLE volunteer_hour_entries AUTO_INCREMENT = 1;
ALTER TABLE volunteer_training_modules AUTO_INCREMENT = 1;
ALTER TABLE registration_submissions AUTO_INCREMENT = 1;
ALTER TABLE registration_opportunities AUTO_INCREMENT = 1;
ALTER TABLE email_delivery_log AUTO_INCREMENT = 1;
ALTER TABLE email_queue AUTO_INCREMENT = 1;
ALTER TABLE auth_email_verifications AUTO_INCREMENT = 1;
ALTER TABLE auth_password_resets AUTO_INCREMENT = 1;
ALTER TABLE auth_invitations AUTO_INCREMENT = 1;
ALTER TABLE calendar_subscriptions AUTO_INCREMENT = 1;
ALTER TABLE production_files AUTO_INCREMENT = 1;
ALTER TABLE organization_resources AUTO_INCREMENT = 1;
ALTER TABLE stored_file_versions AUTO_INCREMENT = 1;
ALTER TABLE stored_files AUTO_INCREMENT = 1;
ALTER TABLE form_submission_answers AUTO_INCREMENT = 1;
ALTER TABLE form_fields AUTO_INCREMENT = 1;
ALTER TABLE attendance_absence_reports AUTO_INCREMENT = 1;
ALTER TABLE attendance_records AUTO_INCREMENT = 1;
ALTER TABLE production_groups AUTO_INCREMENT = 1;
ALTER TABLE teams AUTO_INCREMENT = 1;
ALTER TABLE playbill_sections AUTO_INCREMENT = 1;
ALTER TABLE production_resources AUTO_INCREMENT = 1;
ALTER TABLE schedule_notice_deliveries AUTO_INCREMENT = 1;
ALTER TABLE app_notifications AUTO_INCREMENT = 1;
ALTER TABLE audit_events AUTO_INCREMENT = 1;
ALTER TABLE messages AUTO_INCREMENT = 1;
ALTER TABLE conversations AUTO_INCREMENT = 1;
ALTER TABLE playbills AUTO_INCREMENT = 1;
ALTER TABLE form_submissions AUTO_INCREMENT = 1;
ALTER TABLE form_assignments AUTO_INCREMENT = 1;
ALTER TABLE forms AUTO_INCREMENT = 1;
ALTER TABLE volunteer_shift_approval_requests AUTO_INCREMENT = 1;
ALTER TABLE volunteer_shift_signups AUTO_INCREMENT = 1;
ALTER TABLE volunteer_shifts AUTO_INCREMENT = 1;
ALTER TABLE volunteer_credentials AUTO_INCREMENT = 1;
ALTER TABLE volunteer_requirements AUTO_INCREMENT = 1;
ALTER TABLE channel_posts AUTO_INCREMENT = 1;
ALTER TABLE channels AUTO_INCREMENT = 1;
ALTER TABLE moderation_terms AUTO_INCREMENT = 1;
ALTER TABLE schedule_change_notices AUTO_INCREMENT = 1;
ALTER TABLE schedule_items AUTO_INCREMENT = 1;
ALTER TABLE announcements AUTO_INCREMENT = 1;
ALTER TABLE production_memberships AUTO_INCREMENT = 1;
ALTER TABLE productions AUTO_INCREMENT = 1;
ALTER TABLE family_relationships AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

INSERT INTO users (id, first_name, last_name, email, initials, display_role, is_demo_current_user) VALUES
(1, 'Jamie', 'Carter', 'jamie@example.test', 'JC', 'Parent + Volunteer', 1),
(2, 'Emma', 'Carter', 'emma@example.test', 'EC', 'Student', 0),
(3, 'Maya', 'Rivera', 'maya@example.test', 'MR', 'Production Manager', 0),
(4, 'Jordan', 'Lee', 'jordan@example.test', 'JL', 'Director', 0),
(5, 'Taylor', 'Brooks', 'taylor@example.test', 'TB', 'Volunteer', 0),
(6, 'Alex', 'Morgan', 'alex@example.test', 'AM', 'Parent + Volunteer', 0),
(7, 'Casey', 'Nguyen', 'casey@example.test', 'CN', 'Volunteer', 0),
(8, 'Robin', 'Patel', 'robin@example.test', 'RP', 'Volunteer', 0);

-- Re-establish post-authentication account state after recreating demo people.
UPDATE users
SET account_status = CASE WHEN id = 2 THEN 'managed' ELSE 'active' END,
    email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP),
    onboarding_completed_at = COALESCE(onboarding_completed_at, CURRENT_TIMESTAMP),
    organization_membership_status = 'approved',
    organization_membership_reviewed_at = CURRENT_TIMESTAMP,
    organization_membership_reviewed_by_user_id = CASE WHEN id = 3 THEN NULL ELSE 3 END;

-- Restore current RBAC role assignments from the stable role codes seeded by migration 017.
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='student' WHERE LOWER(u.display_role) LIKE '%student%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='volunteer' WHERE LOWER(u.display_role) LIKE '%volunteer%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='member' WHERE LOWER(u.display_role) NOT LIKE '%student%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='production_staff' WHERE LOWER(u.display_role) LIKE '%director%' OR LOWER(u.display_role) LIKE '%manager%' OR LOWER(u.display_role) LIKE '%staff%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='moderator' WHERE LOWER(u.display_role) LIKE '%director%' OR LOWER(u.display_role) LIKE '%manager%' OR LOWER(u.display_role) LIKE '%staff%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='safeguarding' WHERE LOWER(u.display_role) LIKE '%director%' OR LOWER(u.display_role) LIKE '%manager%' OR LOWER(u.display_role) LIKE '%staff%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='administrator' WHERE LOWER(u.display_role) LIKE '%manager%' OR LOWER(u.display_role) LIKE '%admin%';

INSERT INTO family_relationships (id, guardian_user_id, student_user_id, relationship_type, is_primary, status, created_by_user_id) VALUES
(1, 1, 2, 'parent', 1, 'active', 3);

INSERT INTO productions (id, title, season, status) VALUES
(1, 'Matilda Jr.', 'Summer 2026', 'current'),
(2, 'The Lion, the Witch and the Wardrobe', 'Spring 2026', 'archived'),
(3, 'Frozen Jr.', 'Winter 2025', 'archived');

INSERT INTO production_memberships (production_id, user_id, audience_type, participation_role) VALUES
(1,2,'student','Cast'),
(1,1,'guardian','Parent / Guardian'),
(1,3,'staff','Production Manager'),
(1,4,'staff','Director');

INSERT INTO announcements (production_id, author_user_id, title, body, context_label, tone, published_at, pinned) VALUES
(1, 3, 'Tech week schedule updated', 'Thursday call time moved to 5:30 PM. Please review the updated family notes before arrival.', 'Current Production', 'urgent', '2026-08-06 16:45:00', 1),
(1, 4, 'Costume fitting reminders', 'Students with remaining fittings should arrive 20 minutes before rehearsal this Saturday.', 'Costumes', 'info', '2026-08-06 09:00:00', 0);

INSERT INTO schedule_items (production_id, title, starts_at, ends_at, family_call_at, location, visibility, item_type) VALUES
(1, 'Full Cast Rehearsal', '2026-08-06 17:30:00', '2026-08-06 20:30:00', '2026-08-06 17:15:00', 'Main Stage', 'all', 'rehearsal'),
(1, 'Parent Volunteer Orientation', '2026-08-06 18:45:00', '2026-08-06 19:15:00', NULL, 'Studio B', 'family', 'orientation'),
(1, 'Set Build Day', '2026-08-08 10:00:00', '2026-08-08 14:00:00', NULL, 'Scene Shop', 'all', 'volunteer');

INSERT INTO channels (id, production_id, name, channel_type, description, read_scope, post_scope, sort_order, created_by_user_id) VALUES
(1, NULL, 'Announcements', 'announcement', 'Organization-wide updates', 'all_members', 'staff', 10, 3),
(2, NULL, 'General', 'discussion', 'General community conversation', 'all_members', 'all_members', 20, 3),
(3, NULL, 'Parent Questions', 'parent', 'Questions and answers for parents and guardians', 'all_members', 'all_members', 30, 3),
(4, 1, 'Current Production', 'production', 'Cast, family, and production updates for Matilda Jr.', 'production_members', 'production_members', 40, 3),
(5, 1, 'Cast Updates', 'production', 'Cast-specific updates', 'production_members', 'production_members', 50, 3),
(6, 1, 'Tech and Crew', 'production', 'Technical and crew coordination', 'production_members', 'production_members', 60, 3),
(7, 1, 'Costumes', 'production', 'Costume information and reminders', 'production_members', 'production_members', 70, 3),
(8, NULL, 'Volunteer Opportunities', 'volunteer', 'Open volunteer opportunities and coordination', 'all_members', 'staff', 80, 3),
(9, NULL, 'Resources', 'resource', 'Read-only community resources', 'all_members', 'staff', 90, 3);

INSERT INTO channel_posts (channel_id, author_user_id, body, pinned, reactions_json, created_at) VALUES
(4, 3, 'Reminder: Thursday rehearsal begins 30 minutes earlier. Updated call times are now in the schedule.', 1, '{"thumbs_up":18,"heart":7}', '2026-08-06 16:12:00'),
(4, 4, 'Great work on Act II last night. Please review the choreography video before Saturday.', 0, '{"theatre":12,"clap":9}', '2026-08-06 15:44:00'),
(4, 6, 'Will the costume team need students to bring their show shoes Saturday?', 0, '{"thumbs_up":3}', '2026-08-06 14:08:00');

INSERT INTO volunteer_profiles (user_id, active) VALUES
(1,1),(5,1),(6,1),(7,1),(8,1);

INSERT INTO volunteer_requirements (id, code, name, category, expires) VALUES
(1, 'facility_orientation', 'Facility orientation', 'orientation', 0),
(2, 'background_check', 'Approved background check', 'background_check', 1),
(3, 'child_safety_training', 'Child safety training', 'training', 1),
(4, 'adult_18_plus', 'Adult volunteer', 'eligibility', 0),
(5, 'box_office_training', 'Box Office training', 'training', 0);

INSERT INTO volunteer_credentials (user_id, requirement_id, status, completed_at, expires_at, verified_by_user_id) VALUES
(1,1,'approved','2026-06-01 10:00:00',NULL,3),
(1,2,'approved','2026-06-01 10:00:00','2027-06-01 23:59:59',3),
(1,3,'approved','2026-06-05 10:00:00','2027-06-05 23:59:59',3),
(1,4,'approved','2026-06-01 10:00:00',NULL,3),
(5,1,'approved','2026-05-20 10:00:00',NULL,3),
(5,2,'approved','2026-05-20 10:00:00','2027-05-20 23:59:59',3),
(5,3,'approved','2026-05-22 10:00:00','2027-05-22 23:59:59',3),
(6,1,'approved','2026-07-10 10:00:00',NULL,3),
(6,2,'pending',NULL,NULL,NULL),
(7,1,'approved','2026-07-12 10:00:00',NULL,3),
(7,2,'approved','2026-07-12 10:00:00','2027-07-12 23:59:59',3),
(7,3,'missing',NULL,NULL,NULL),
(8,1,'approved','2026-07-15 10:00:00',NULL,3),
(8,2,'pending',NULL,NULL,NULL),
(8,3,'review','2026-07-15 10:00:00',NULL,NULL);

INSERT INTO volunteer_training_modules (id, requirement_id, title, description, completion_instructions, validity_days, active, created_by_user_id) VALUES
(1, 1, 'CTSMD Facility Education', 'Facility access, emergency exits, front-of-house expectations, and shared-space rules.', 'Complete the facility education session and have a coordinator verify completion.', NULL, 1, 3),
(2, 5, 'Box Office Training', 'Ticket lookup, will-call workflow, patron assistance, and box office escalation basics.', 'Complete supervised box office training before taking an independent Box Office shift.', NULL, 1, 3);

INSERT INTO volunteer_training_completions (module_id, user_id, status, completed_at, verified_by_user_id, note) VALUES
(2,5,'completed','2026-08-05 18:00:00',3,'Completed supervised box office training.');

INSERT INTO volunteer_shifts (id, production_id, title, category, starts_at, ends_at, location, required_slots, approval_required) VALUES
(1,1,'Front of House','front_of_house','2026-08-07 17:30:00','2026-08-07 21:00:00','Lobby',4,0),
(2,1,'Dressing Room Monitor','dressing_room','2026-08-08 13:00:00','2026-08-08 17:00:00','Backstage',2,1),
(3,1,'Set Build Day','set_build','2026-08-08 10:00:00','2026-08-08 14:00:00','Scene Shop',8,0),
(4,1,'Concessions','concessions','2026-08-09 12:30:00','2026-08-09 16:30:00','Lobby',5,0),
(5,1,'Strike Crew','strike','2026-08-09 17:00:00','2026-08-09 20:00:00','Main Stage',6,0),
(6,1,'Box Office','box_office','2026-08-15 12:00:00','2026-08-15 16:00:00','Lobby Box Office',3,0);

INSERT INTO volunteer_shift_requirements (shift_id, requirement_id) VALUES
(1,1),
(2,2),(2,3),
(3,4),
(6,2),(6,5);

INSERT INTO volunteer_shift_signups (shift_id, user_id, status) VALUES
(1,5,'signed_up'),(1,6,'signed_up'),
(2,5,'signed_up'),
(3,5,'signed_up'),(3,6,'signed_up'),(3,7,'signed_up'),(3,8,'signed_up'),
(4,5,'signed_up'),(4,6,'signed_up'),
(6,5,'signed_up');

INSERT INTO forms (id, title, form_type, instructions, completion_mode, review_required) VALUES
(1,'Parent Handbook Acknowledgment','acknowledgment','Review the Parent Handbook and confirm that you understand and agree to follow CTSMD participation expectations.','acknowledgment',0),
(2,'Media / Photo Release','release','Review the media and photo release terms and provide your typed signature to record your consent.','signature',0),
(3,'Emergency Information','medical','Provide the current emergency information requested for your CTSMD household. Staff will review it before marking the assignment complete.','submission',1),
(4,'Volunteer Facility Education','training_acknowledgment','Confirm that you completed the assigned facility education. Staff will review the acknowledgment before completion.','acknowledgment',1);

INSERT INTO form_requirement_mappings (form_id, requirement_id, validity_days, active, created_by_user_id) VALUES
(4,1,NULL,1,3);

INSERT INTO form_assignments (form_id, user_id, status, due_at, completed_at) VALUES
(1,1,'completed','2026-08-01 23:59:59','2026-07-29 18:00:00'),
(2,1,'due_soon','2026-08-10 23:59:59',NULL),
(3,1,'missing','2026-08-08 23:59:59',NULL),
(4,1,'requires_review','2026-08-12 23:59:59',NULL);

INSERT INTO playbills (production_id, status, public_slug) VALUES
(1,'current','matilda-jr-summer-2026'),
(2,'archived','lion-witch-wardrobe-spring-2026'),
(3,'archived','frozen-jr-winter-2025');

INSERT INTO conversations (id, subject, conversation_type, created_at) VALUES
(1,'Emma Carter · Rehearsal question','safeguarded','2026-08-06 15:50:00'),
(2,'Lobby volunteer table','direct','2026-08-04 12:00:00');

INSERT INTO conversation_participants (conversation_id, user_id, participant_role, guardian_required) VALUES
(1,3,'adult',0),(1,2,'student',0),(1,1,'guardian',1),
(2,4,'adult',0),(2,1,'adult',0);

INSERT INTO messages (conversation_id, sender_user_id, body, created_at) VALUES
(1,2,'Hi Ms. Maya, I’m not sure which shoes I need for Saturday’s costume fitting.','2026-08-06 15:54:00'),
(1,3,'Bring both your black jazz shoes and character shoes, please. We’ll check both with the costume.','2026-08-06 16:02:00'),
(1,1,'Thanks! We’ll make sure she has both.','2026-08-06 16:22:00'),
(2,4,'Can you help with the lobby table?','2026-08-04 12:15:00');

-- Migration-aware runtime state. Re-establish active production and flexible audience JSON after every demo reset.
UPDATE productions
SET is_active = CASE WHEN status = 'current' THEN 1 ELSE 0 END,
    activated_at = CASE WHEN status = 'current' THEN CURRENT_TIMESTAMP ELSE NULL END,
    deactivated_at = CASE WHEN status = 'archived' THEN CURRENT_TIMESTAMP ELSE NULL END;

UPDATE channels
SET read_audiences_json = CASE read_scope
        WHEN 'all_members' THEN JSON_ARRAY('all_members')
        WHEN 'production_members' THEN JSON_ARRAY('production_members')
        WHEN 'staff' THEN JSON_ARRAY('staff')
        ELSE JSON_ARRAY('staff')
    END,
    post_audiences_json = CASE post_scope
        WHEN 'all_members' THEN JSON_ARRAY('all_members')
        WHEN 'production_members' THEN JSON_ARRAY('production_members')
        WHEN 'staff' THEN JSON_ARRAY('staff')
        ELSE JSON_ARRAY('staff')
    END;

-- Read-state/push migrations baseline existing data. Rebuild those baselines after a reset too,
-- otherwise old cursor IDs can suppress newly-seeded activity or replay it unexpectedly.
INSERT INTO communication_read_state_meta (id,started_at) VALUES (1,CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE started_at=VALUES(started_at);

UPDATE conversation_participants cp
LEFT JOIN (
    SELECT conversation_id,MAX(id) latest_message_id
    FROM messages
    WHERE hidden_at IS NULL
    GROUP BY conversation_id
) latest ON latest.conversation_id=cp.conversation_id
SET cp.last_read_message_id=COALESCE(latest.latest_message_id,0),
    cp.last_read_at=CURRENT_TIMESTAMP;

INSERT INTO push_event_cursors (source_key,last_id)
SELECT 'messages',COALESCE(MAX(id),0) FROM messages
ON DUPLICATE KEY UPDATE last_id=VALUES(last_id),updated_at=CURRENT_TIMESTAMP;
INSERT INTO push_event_cursors (source_key,last_id)
SELECT 'community_posts',COALESCE(MAX(id),0) FROM channel_posts
ON DUPLICATE KEY UPDATE last_id=VALUES(last_id),updated_at=CURRENT_TIMESTAMP;
INSERT INTO push_event_cursors (source_key,last_id)
SELECT 'app_notifications',COALESCE(MAX(id),0) FROM app_notifications
ON DUPLICATE KEY UPDATE last_id=VALUES(last_id),updated_at=CURRENT_TIMESTAMP;
