SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN password_hash VARCHAR(255) NULL AFTER email,
    ADD COLUMN account_status ENUM('invited','active','disabled') NOT NULL DEFAULT 'invited' AFTER password_hash,
    ADD COLUMN last_login_at DATETIME NULL AFTER account_status,
    ADD COLUMN password_changed_at DATETIME NULL AFTER last_login_at;

CREATE TABLE IF NOT EXISTS auth_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    system_role TINYINT(1) NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id,permission_id),
    CONSTRAINT fk_auth_role_permission_role FOREIGN KEY (role_id) REFERENCES auth_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_auth_role_permission_permission FOREIGN KEY (permission_id) REFERENCES auth_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assigned_by_user_id BIGINT UNSIGNED NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,role_id),
    KEY idx_auth_user_roles_role (role_id,user_id),
    CONSTRAINT fk_auth_user_role_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_auth_user_role_role FOREIGN KEY (role_id) REFERENCES auth_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_auth_user_role_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_invitations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auth_invitation_user (user_id,accepted_at,expires_at),
    CONSTRAINT fk_auth_invitation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_auth_invitation_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auth_reset_user (user_id,used_at,expires_at),
    CONSTRAINT fk_auth_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO auth_roles (code,name,description,system_role) VALUES
('member','Member','Standard adult/member access.',1),
('student','Student','Student account with safeguarding restrictions.',1),
('volunteer','Volunteer','Volunteer opportunities, readiness, training and service history.',1),
('production_staff','Production Staff','Production schedule, roster, attendance and production operations.',1),
('moderator','Moderator','Community moderation operations.',1),
('safeguarding','Safeguarding','Restricted safeguarding review permissions.',1),
('administrator','Administrator','Organization-wide administration and access management.',1);

INSERT IGNORE INTO auth_permissions (code,name,description) VALUES
('people.manage','Manage people','Manage people, family relationships and account access.'),
('production.manage','Manage productions','Manage productions, schedules, groups, attendance and production operations.'),
('community.manage','Manage Community','Manage Community channels and posting rules.'),
('community.moderate','Moderate Community','Manage moderation rules and review held posts.'),
('volunteer.manage','Manage volunteers','Manage volunteer shifts, approvals, training and hours.'),
('forms.manage','Manage forms','Create, assign, build and review forms.'),
('resources.manage','Manage resources','Manage production and organization resources.'),
('playbill.manage','Manage Playbills','Manage digital Playbills.'),
('safeguarding.manage','Manage safeguarding','Access restricted safeguarding operations.'),
('accounts.manage','Manage accounts','Invite, activate, disable and assign roles to accounts.'),
('audit.view','View audit','Review organization audit history.');

INSERT IGNORE INTO auth_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM auth_roles r JOIN auth_permissions p
WHERE r.code='production_staff' AND p.code IN ('production.manage','resources.manage','playbill.manage','forms.manage');
INSERT IGNORE INTO auth_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM auth_roles r JOIN auth_permissions p
WHERE r.code='moderator' AND p.code IN ('community.manage','community.moderate');
INSERT IGNORE INTO auth_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM auth_roles r JOIN auth_permissions p
WHERE r.code='safeguarding' AND p.code='safeguarding.manage';
INSERT IGNORE INTO auth_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM auth_roles r JOIN auth_permissions p WHERE r.code='administrator';

-- One-time compatibility bootstrap from prototype labels. Runtime access does not use these strings.
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='student' WHERE LOWER(u.display_role) LIKE '%student%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='volunteer' WHERE LOWER(u.display_role) LIKE '%volunteer%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='member' WHERE LOWER(u.display_role) NOT LIKE '%student%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='production_staff' WHERE LOWER(u.display_role) LIKE '%director%' OR LOWER(u.display_role) LIKE '%manager%' OR LOWER(u.display_role) LIKE '%staff%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='moderator' WHERE LOWER(u.display_role) LIKE '%director%' OR LOWER(u.display_role) LIKE '%manager%' OR LOWER(u.display_role) LIKE '%staff%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='safeguarding' WHERE LOWER(u.display_role) LIKE '%director%' OR LOWER(u.display_role) LIKE '%manager%' OR LOWER(u.display_role) LIKE '%staff%';
INSERT IGNORE INTO auth_user_roles (user_id,role_id)
SELECT u.id,r.id FROM users u JOIN auth_roles r ON r.code='administrator' WHERE LOWER(u.display_role) LIKE '%manager%' OR LOWER(u.display_role) LIKE '%admin%';

UPDATE users SET account_status='active' WHERE is_demo_current_user=1;
