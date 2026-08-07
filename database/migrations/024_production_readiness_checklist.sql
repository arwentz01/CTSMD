SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS production_checklist_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    title VARCHAR(190) NOT NULL,
    notes VARCHAR(1000) NULL,
    due_at DATETIME NULL,
    status ENUM('open','in_progress','blocked','done') NOT NULL DEFAULT 'open',
    assigned_to_user_id BIGINT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_by_user_id BIGINT UNSIGNED NULL,
    completed_by_user_id BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_production_checklist_status (production_id,status,due_at,sort_order),
    KEY idx_production_checklist_assignee (assigned_to_user_id,status),
    CONSTRAINT fk_production_checklist_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
    CONSTRAINT fk_production_checklist_assignee FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_production_checklist_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_production_checklist_completer FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
