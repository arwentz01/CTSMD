SET NAMES utf8mb4;

ALTER TABLE productions
    ADD COLUMN casting_public_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN casting_public_slug VARCHAR(64) NULL AFTER casting_public_enabled,
    ADD UNIQUE KEY uq_productions_casting_public_slug (casting_public_slug);
