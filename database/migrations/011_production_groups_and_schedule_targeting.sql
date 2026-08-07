SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS production_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    group_type VARCHAR(80) NOT NULL DEFAULT 'cast',
    description VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_production_group_name (production_id, name),
    KEY idx_production_groups_active (production_id, active, sort_order, name),
    CONSTRAINT fk_production_group_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
    CONSTRAINT fk_production_group_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_group_members (
    group_id BIGINT UNSIGNED NOT NULL,
    production_membership_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    added_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, production_membership_id),
    KEY idx_production_group_member_status (production_membership_id, status),
    CONSTRAINT fk_production_group_member_group FOREIGN KEY (group_id) REFERENCES production_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_production_group_member_membership FOREIGN KEY (production_membership_id) REFERENCES production_memberships(id) ON DELETE CASCADE,
    CONSTRAINT fk_production_group_member_added_by FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE schedule_items
    ADD COLUMN audience_mode ENUM('production','groups') NOT NULL DEFAULT 'production' AFTER visibility;

CREATE TABLE IF NOT EXISTS schedule_item_groups (
    schedule_item_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_item_id, group_id),
    KEY idx_schedule_item_group_group (group_id, schedule_item_id),
    CONSTRAINT fk_schedule_item_group_item FOREIGN KEY (schedule_item_id) REFERENCES schedule_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_item_group_group FOREIGN KEY (group_id) REFERENCES production_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
