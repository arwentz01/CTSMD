SET NAMES utf8mb4;

ALTER TABLE push_queue
    ADD COLUMN dedupe_key VARCHAR(190) NULL AFTER tag,
    ADD UNIQUE KEY uq_push_queue_dedupe (dedupe_key);
