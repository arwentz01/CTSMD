-- Explicit publication required: a lifecycle stage alone never publishes a student production.
ALTER TABLE productions ADD COLUMN announcement_published_at DATETIME NULL;
CREATE INDEX idx_production_announcements ON productions (lifecycle_stage,announcement_published_at);
