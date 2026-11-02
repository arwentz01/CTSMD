CREATE TABLE IF NOT EXISTS production_checklist_assignments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 checklist_item_id BIGINT UNSIGNED NOT NULL,
 assigned_user_id BIGINT UNSIGNED NOT NULL,
 required TINYINT(1) NOT NULL DEFAULT 1,
 completed_at DATETIME NULL,
 completed_by_user_id BIGINT UNSIGNED NULL,
 assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_checklist_assignment (checklist_item_id,assigned_user_id),
 KEY idx_checklist_assignee (assigned_user_id,completed_at),
 CONSTRAINT fk_checklist_assignment_item FOREIGN KEY (checklist_item_id) REFERENCES production_checklist_items(id) ON DELETE CASCADE,
 CONSTRAINT fk_checklist_assignment_user FOREIGN KEY (assigned_user_id) REFERENCES users(id),
 CONSTRAINT fk_checklist_assignment_completer FOREIGN KEY (completed_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
