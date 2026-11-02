SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS production_day_briefs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_id BIGINT UNSIGNED NOT NULL,
    service_date DATE NOT NULL,
    day_status ENUM('planning','live','closed') NOT NULL DEFAULT 'planning',
    headline VARCHAR(190) NULL,
    operations_note TEXT NULL,
    arrival_note TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_production_day (production_id, service_date),
    KEY idx_production_day_status (production_id, day_status, service_date),
    CONSTRAINT fk_production_day_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
    CONSTRAINT fk_production_day_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_production_day_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
