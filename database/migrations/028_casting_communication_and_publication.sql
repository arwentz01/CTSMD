SET NAMES utf8mb4;

ALTER TABLE production_casting_records
    ADD COLUMN result_communicated_at DATETIME NULL AFTER rostered_at,
    ADD COLUMN result_communicated_by_user_id BIGINT UNSIGNED NULL AFTER result_communicated_at,
    ADD CONSTRAINT fk_casting_result_communicator FOREIGN KEY (result_communicated_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS production_cast_publications (
    production_id BIGINT UNSIGNED PRIMARY KEY,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    headline VARCHAR(190) NULL,
    member_note VARCHAR(2000) NULL,
    cast_snapshot_json JSON NULL,
    published_by_user_id BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cast_publication_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
    CONSTRAINT fk_cast_publication_publisher FOREIGN KEY (published_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
