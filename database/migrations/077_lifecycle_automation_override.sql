-- Explicit staff control over scheduled lifecycle transitions.
ALTER TABLE productions ADD COLUMN lifecycle_automation_paused TINYINT(1) NOT NULL DEFAULT 0;
