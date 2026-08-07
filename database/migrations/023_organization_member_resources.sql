SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS organization_resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stored_file_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    resource_type ENUM('link','note','file') NOT NULL DEFAULT 'link',
    resource_url VARCHAR(1000) NULL,
    body TEXT NULL,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_organization_resources_member (status,pinned,category,title),
    KEY idx_organization_resources_file (stored_file_id),
    CONSTRAINT fk_organization_resources_file FOREIGN KEY (stored_file_id) REFERENCES stored_files(id) ON DELETE SET NULL,
    CONSTRAINT fk_organization_resources_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
