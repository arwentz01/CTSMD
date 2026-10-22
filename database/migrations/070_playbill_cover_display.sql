-- Cover presentation is per Playbill. Preserve existing artwork and publication status.
ALTER TABLE playbills
    ADD COLUMN cover_display ENUM('artwork_and_text','artwork_only') NOT NULL DEFAULT 'artwork_and_text' AFTER artwork_stored_file_id;
