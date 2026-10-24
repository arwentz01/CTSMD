-- Channel owners choose whether published posts may appear in the combined Community Feed.
-- Existing announcements and event channels remain visible by default; discussion channels opt in.
ALTER TABLE channels ADD COLUMN include_in_feed TINYINT(1) NOT NULL DEFAULT 0 AFTER channel_type;
UPDATE channels SET include_in_feed = 1 WHERE LOWER(channel_type) IN ('announcement','announcements','event','events');
