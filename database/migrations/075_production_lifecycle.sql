-- Add one operational lifecycle without changing established permissions during migration.
-- Old is_active remains the authorization gate until all web/mobile access paths are migrated.
ALTER TABLE productions
 ADD COLUMN lifecycle_stage ENUM('upcoming','planning','in_production','performing','completed','archived') NULL,
 ADD COLUMN audition_starts_at DATETIME NULL,
 ADD COLUMN audition_ends_at DATETIME NULL,
 ADD COLUMN performance_starts_at DATETIME NULL,
 ADD COLUMN performance_ends_at DATETIME NULL,
 ADD COLUMN completed_at DATETIME NULL,
 ADD COLUMN archive_after_at DATETIME NULL,
 ADD COLUMN lifecycle_override_note VARCHAR(500) NULL;

-- Preserve all existing active production access; avoid silently hiding families.
UPDATE productions SET lifecycle_stage=CASE
 WHEN status='archived' THEN 'archived'
 WHEN is_active=1 THEN 'in_production'
 ELSE 'upcoming' END
WHERE lifecycle_stage IS NULL;

ALTER TABLE productions MODIFY COLUMN lifecycle_stage ENUM('upcoming','planning','in_production','performing','completed','archived') NOT NULL DEFAULT 'upcoming';
CREATE INDEX idx_productions_lifecycle_dates ON productions (lifecycle_stage,performance_starts_at,performance_ends_at,archive_after_at);
