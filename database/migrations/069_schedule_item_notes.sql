-- Staff-editable instructions for one schedule item; visibility inherits schedule audience.
ALTER TABLE schedule_items ADD COLUMN notes TEXT NULL AFTER location;
