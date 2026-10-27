SET NAMES utf8mb4;
-- Preserve deployed production-video rows while enabling organization-wide videos.
ALTER TABLE rehearsal_videos MODIFY COLUMN production_id BIGINT UNSIGNED NULL;
ALTER TABLE rehearsal_videos ADD COLUMN audience_scope ENUM('platform','production','group') NOT NULL DEFAULT 'production' AFTER description;
-- Existing videos remain scoped to their original production/group.
UPDATE rehearsal_videos SET audience_scope=IF(group_id IS NULL,'production','group');
