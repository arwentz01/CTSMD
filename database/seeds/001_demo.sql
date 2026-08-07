SET NAMES utf8mb4;

-- Demo reset. Apply all migrations before running this seed.
-- Use DELETE rather than TRUNCATE so foreign-key relationships remain enforced.
-- Child/dependent records are removed before their parents.
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
DELETE FROM schedule_change_notices;
DELETE FROM schedule_items;
DELETE FROM announcements;
DELETE FROM production_memberships;
DELETE FROM productions;
DELETE FROM family_relationships;
DELETE FROM users;

-- Reset demo IDs so explicit IDs and relational references remain deterministic.
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
ALTER TABLE volunteer_profiles AUTO_INCREMENT = 1;
ALTER TABLE channel_posts AUTO_INCREMENT = 1;
ALTER TABLE channels AUTO_INCREMENT = 1;
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

INSERT INTO channels (id, production_id, name, channel_type, description, sort_order) VALUES
(1, NULL, 'Announcements', 'announcement', 'Organization-wide updates', 10),
(2, NULL, 'General', 'discussion', 'General community conversation', 20),
(3, NULL, 'Parent Questions', 'parent', 'Questions and answers for parents and guardians', 30),
(4, 1, 'Current Production', 'production', 'Cast, family, and production updates for Matilda Jr.', 40),
(5, 1, 'Cast Updates', 'production', 'Cast-specific updates', 50),
(6, 1, 'Tech and Crew', 'production', 'Technical and crew coordination', 60),
(7, 1, 'Costumes', 'production', 'Costume information and reminders', 70),
(8, NULL, 'Volunteer Opportunities', 'volunteer', 'Open volunteer opportunities and coordination', 80),
(9, NULL, 'Resources', 'resource', 'Read-only community resources', 90);

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
(4, 'adult_18_plus', 'Adult volunteer', 'eligibility', 0);

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

INSERT INTO volunteer_shifts (id, production_id, title, category, starts_at, ends_at, location, required_slots, approval_required) VALUES
(1,1,'Front of House','front_of_house','2026-08-07 17:30:00','2026-08-07 21:00:00','Lobby',4,0),
(2,1,'Dressing Room Monitor','dressing_room','2026-08-08 13:00:00','2026-08-08 17:00:00','Backstage',2,1),
(3,1,'Set Build Day','set_build','2026-08-08 10:00:00','2026-08-08 14:00:00','Scene Shop',8,0),
(4,1,'Concessions','concessions','2026-08-09 12:30:00','2026-08-09 16:30:00','Lobby',5,0),
(5,1,'Strike Crew','strike','2026-08-09 17:00:00','2026-08-09 20:00:00','Main Stage',6,0);

INSERT INTO volunteer_shift_requirements (shift_id, requirement_id) VALUES
(1,1),
(2,2),(2,3),
(3,4);

INSERT INTO volunteer_shift_signups (shift_id, user_id, status) VALUES
(1,5,'signed_up'),(1,6,'signed_up'),
(2,5,'signed_up'),
(3,5,'signed_up'),(3,6,'signed_up'),(3,7,'signed_up'),(3,8,'signed_up'),
(4,5,'signed_up'),(4,6,'signed_up');

INSERT INTO forms (id, title, form_type, instructions, completion_mode, review_required) VALUES
(1,'Parent Handbook Acknowledgment','acknowledgment','Review the Parent Handbook and confirm that you understand and agree to follow CTSMD participation expectations.','acknowledgment',0),
(2,'Media / Photo Release','release','Review the media and photo release terms and provide your typed signature to record your consent.','signature',0),
(3,'Emergency Information','medical','Provide the current emergency information requested for your CTSMD household. Staff will review it before marking the assignment complete.','submission',1),
(4,'Volunteer Facility Education','training_acknowledgment','Confirm that you completed the assigned facility education. Staff will review the acknowledgment before completion.','acknowledgment',1);

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
