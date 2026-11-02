SET NAMES utf8mb4;
-- Repeat-safe MySQL/MariaDB schema extension. Existing video audiences remain intact.
SET @ctsmd_prod_nullable = (
 SELECT IS_NULLABLE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rehearsal_videos' AND COLUMN_NAME='production_id'
 LIMIT 1
);
SET @ctsmd_video_fk_exists = (
 SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='rehearsal_videos'
 AND CONSTRAINT_NAME='fk_rehearsal_video_production' AND CONSTRAINT_TYPE='FOREIGN KEY'
);
SET @ctsmd_drop_video_fk_sql = IF(@ctsmd_prod_nullable='NO' AND @ctsmd_video_fk_exists>0,
 'ALTER TABLE rehearsal_videos DROP FOREIGN KEY fk_rehearsal_video_production','SELECT 1');
PREPARE ctsmd_drop_video_fk FROM @ctsmd_drop_video_fk_sql;
EXECUTE ctsmd_drop_video_fk;
DEALLOCATE PREPARE ctsmd_drop_video_fk;
SET @ctsmd_nullable_sql = IF(@ctsmd_prod_nullable='NO',
 'ALTER TABLE rehearsal_videos MODIFY COLUMN production_id BIGINT UNSIGNED NULL','SELECT 1');
PREPARE ctsmd_video_prod_stmt FROM @ctsmd_nullable_sql;
EXECUTE ctsmd_video_prod_stmt;
DEALLOCATE PREPARE ctsmd_video_prod_stmt;
SET @ctsmd_add_video_fk_sql = IF(@ctsmd_prod_nullable='NO' AND @ctsmd_video_fk_exists>0,
 'ALTER TABLE rehearsal_videos ADD CONSTRAINT fk_rehearsal_video_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE','SELECT 1');
PREPARE ctsmd_add_video_fk FROM @ctsmd_add_video_fk_sql;
EXECUTE ctsmd_add_video_fk;
DEALLOCATE PREPARE ctsmd_add_video_fk;

SET @ctsmd_scope_exists = (
 SELECT COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rehearsal_videos' AND COLUMN_NAME='audience_scope'
);
SET @ctsmd_scope_sql = IF(@ctsmd_scope_exists=0,
 "ALTER TABLE rehearsal_videos ADD COLUMN audience_scope ENUM('platform','production','group') NOT NULL DEFAULT 'production' AFTER description",
 'SELECT 1');
PREPARE ctsmd_video_scope_stmt FROM @ctsmd_scope_sql;
EXECUTE ctsmd_video_scope_stmt;
DEALLOCATE PREPARE ctsmd_video_scope_stmt;

-- Only backfill on first application. Rerunning must not rewrite platform videos.
SET @ctsmd_backfill_sql = IF(@ctsmd_scope_exists=0,
 "UPDATE rehearsal_videos SET audience_scope=IF(group_id IS NULL,'production','group')",
 'SELECT 1');
PREPARE ctsmd_video_backfill_stmt FROM @ctsmd_backfill_sql;
EXECUTE ctsmd_video_backfill_stmt;
DEALLOCATE PREPARE ctsmd_video_backfill_stmt;
