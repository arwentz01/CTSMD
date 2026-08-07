SET NAMES utf8mb4;

SET @has_cast_snapshot_json := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'production_cast_publications'
      AND COLUMN_NAME = 'cast_snapshot_json'
);

SET @cast_snapshot_sql := IF(
    @has_cast_snapshot_json = 0,
    'ALTER TABLE production_cast_publications ADD COLUMN cast_snapshot_json JSON NULL AFTER member_note',
    'SELECT 1'
);

PREPARE cast_snapshot_stmt FROM @cast_snapshot_sql;
EXECUTE cast_snapshot_stmt;
DEALLOCATE PREPARE cast_snapshot_stmt;
