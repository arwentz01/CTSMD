ALTER TABLE attendance_records ADD COLUMN arrived_at DATETIME NULL AFTER marked_at;
UPDATE attendance_records SET arrived_at=marked_at WHERE status IN ('present','late') AND arrived_at IS NULL;
