SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS production_resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    resource_type ENUM('link','note') NOT NULL DEFAULT 'link',
    resource_url VARCHAR(1000) NULL,
    body TEXT NULL,
    audiences_json JSON NOT NULL,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_production_resources_lookup (production_id, status, pinned, category, updated_at),
    CONSTRAINT fk_production_resource_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
    CONSTRAINT fk_production_resource_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
