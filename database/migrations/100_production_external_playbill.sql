-- Optional third-party Playbill URL. Does not publish or modify Connect Studio programs.
CREATE TABLE IF NOT EXISTS production_external_playbills (
    production_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    external_url VARCHAR(2048) NOT NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
