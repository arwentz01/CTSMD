SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS external_theatre_credits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    production_title VARCHAR(190) NOT NULL,
    role_title VARCHAR(190) NULL,
    organization_name VARCHAR(190) NOT NULL,
    season_label VARCHAR(100) NULL,
    credit_type ENUM('performance','crew','training') NOT NULL DEFAULT 'performance',
    notes VARCHAR(500) NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_external_theatre_credit_user (user_id,status,season_label),
    CONSTRAINT fk_external_credit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_external_credit_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
