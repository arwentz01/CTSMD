SET NAMES utf8mb4;

ALTER TABLE channels
    ADD COLUMN read_scope ENUM('all_members','production_members','staff') NOT NULL DEFAULT 'all_members' AFTER description,
    ADD COLUMN post_scope ENUM('all_members','production_members','staff') NOT NULL DEFAULT 'staff' AFTER read_scope,
    ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL AFTER sort_order,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by_user_id,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD CONSTRAINT fk_channels_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Existing channels were historically readable by every signed-in member and effectively staff-published.
UPDATE channels SET read_scope = 'all_members', post_scope = 'staff';

-- Production-specific collaboration channels are limited to active production members for reading.
UPDATE channels
SET read_scope = 'production_members'
WHERE production_id IS NOT NULL;

-- General and Parent Questions become member-discussion spaces; announcement/resource channels remain staff-published.
UPDATE channels
SET post_scope = 'all_members'
WHERE name IN ('General', 'Parent Questions');

-- Production discussion channels permit active members of that production to post.
UPDATE channels
SET post_scope = 'production_members'
WHERE production_id IS NOT NULL AND channel_type <> 'announcement';
