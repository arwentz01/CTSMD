SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS stored_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    storage_driver VARCHAR(40) NOT NULL DEFAULT 'local',
    created_by_user_id BIGINT UNSIGNED NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_stored_files_status (status,updated_at),
    CONSTRAINT fk_stored_file_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stored_file_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stored_file_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(160) NOT NULL,
    extension VARCHAR(20) NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stored_file_version (stored_file_id,version_number),
    UNIQUE KEY uq_storage_key (storage_key),
    KEY idx_stored_file_versions_file (stored_file_id,created_at),
    CONSTRAINT fk_stored_file_version_file FOREIGN KEY (stored_file_id) REFERENCES stored_files(id) ON DELETE CASCADE,
    CONSTRAINT fk_stored_file_version_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_id BIGINT UNSIGNED NOT NULL,
    stored_file_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    audiences_json JSON NOT NULL,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_production_files_lookup (production_id,status,pinned,category,updated_at),
    CONSTRAINT fk_production_file_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
    CONSTRAINT fk_production_file_storage FOREIGN KEY (stored_file_id) REFERENCES stored_files(id) ON DELETE RESTRICT,
    CONSTRAINT fk_production_file_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
