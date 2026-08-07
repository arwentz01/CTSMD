SET NAMES utf8mb4;

ALTER TABLE playbills
    ADD COLUMN display_title VARCHAR(190) NULL AFTER production_id,
    ADD COLUMN subtitle VARCHAR(255) NULL AFTER display_title,
    ADD COLUMN cover_note VARCHAR(500) NULL AFTER subtitle,
    ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL AFTER public_slug,
    ADD COLUMN published_at DATETIME NULL AFTER created_by_user_id,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER published_at,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD CONSTRAINT fk_playbill_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS playbill_sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playbill_id BIGINT UNSIGNED NOT NULL,
    section_type VARCHAR(80) NOT NULL DEFAULT 'custom',
    heading VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_playbill_sections_order (playbill_id, active, sort_order, id),
    CONSTRAINT fk_playbill_section_playbill FOREIGN KEY (playbill_id) REFERENCES playbills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE playbills pb
JOIN productions p ON p.id = pb.production_id
SET pb.display_title = COALESCE(pb.display_title, p.title),
    pb.subtitle = COALESCE(pb.subtitle, p.season)
WHERE pb.display_title IS NULL OR pb.subtitle IS NULL;
