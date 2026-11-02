-- Capture explicit production context for push jobs; never infer child-related authorization from notification text.
ALTER TABLE push_queue ADD COLUMN production_id BIGINT UNSIGNED NULL;
CREATE INDEX idx_push_queue_production ON push_queue (production_id,status);
