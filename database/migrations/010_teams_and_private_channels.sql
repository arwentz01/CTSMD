SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_id BIGINT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    description VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_teams_production_active (production_id, active, name),
    CONSTRAINT fk_team_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE SET NULL,
    CONSTRAINT fk_team_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
    team_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    membership_role VARCHAR(120) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    added_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, user_id),
    KEY idx_team_member_user_status (user_id, status),
    CONSTRAINT fk_team_member_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_member_added_by FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE channels
    ADD COLUMN access_mode ENUM('audience','selected','team','hybrid') NOT NULL DEFAULT 'audience' AFTER post_audiences_json;

CREATE TABLE IF NOT EXISTS channel_members (
    channel_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    can_read TINYINT(1) NOT NULL DEFAULT 1,
    can_post TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    added_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (channel_id, user_id),
    KEY idx_channel_member_user_status (user_id, status),
    CONSTRAINT fk_channel_member_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_channel_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_channel_member_added_by FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channel_teams (
    channel_id BIGINT UNSIGNED NOT NULL,
    team_id BIGINT UNSIGNED NOT NULL,
    can_read TINYINT(1) NOT NULL DEFAULT 1,
    can_post TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (channel_id, team_id),
    CONSTRAINT fk_channel_team_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_channel_team_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
