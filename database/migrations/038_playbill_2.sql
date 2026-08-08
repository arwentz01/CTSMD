SET NAMES utf8mb4;

ALTER TABLE playbills
    ADD COLUMN artwork_stored_file_id BIGINT UNSIGNED NULL AFTER cover_note,
    ADD CONSTRAINT fk_playbill_artwork_file FOREIGN KEY (artwork_stored_file_id) REFERENCES stored_files(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS sponsors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    website_url VARCHAR(1000) NULL,
    blurb VARCHAR(500) NULL,
    logo_stored_file_id BIGINT UNSIGNED NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sponsors_status_name (status,name),
    CONSTRAINT fk_sponsor_logo FOREIGN KEY (logo_stored_file_id) REFERENCES stored_files(id) ON DELETE SET NULL,
    CONSTRAINT fk_sponsor_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playbill_sponsors (
    playbill_id BIGINT UNSIGNED NOT NULL,
    sponsor_id BIGINT UNSIGNED NOT NULL,
    placement_label VARCHAR(100) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (playbill_id,sponsor_id),
    CONSTRAINT fk_playbill_sponsor_playbill FOREIGN KEY (playbill_id) REFERENCES playbills(id) ON DELETE CASCADE,
    CONSTRAINT fk_playbill_sponsor_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
