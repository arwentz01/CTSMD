-- CTSMD Connect Build 001 schema draft
-- Target: MySQL 8+ / MariaDB 10.6+, InnoDB, utf8mb4

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    is_student BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('invited', 'active', 'deactivated') NOT NULL DEFAULT 'invited',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY users_email_unique (email),
    KEY users_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_memberships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('invited', 'active', 'deactivated') NOT NULL DEFAULT 'invited',
    joined_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY membership_org_user_unique (organization_id, user_id),
    CONSTRAINT memberships_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT memberships_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_system BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (code, name) VALUES
('owner', 'Owner'), ('administrator', 'Administrator'),
('safeguarding_administrator', 'Safeguarding Administrator'),
('production_staff', 'Production Staff'), ('instructor', 'Instructor'),
('volunteer', 'Volunteer'), ('guardian', 'Parent / Guardian'),
('student', 'Student'), ('general_member', 'General Member');

CREATE TABLE IF NOT EXISTS membership_roles (
    membership_id BIGINT UNSIGNED NOT NULL,
    role_id SMALLINT UNSIGNED NOT NULL,
    assigned_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (membership_id, role_id),
    CONSTRAINT membership_roles_membership_fk FOREIGN KEY (membership_id) REFERENCES organization_memberships(id) ON DELETE CASCADE,
    CONSTRAINT membership_roles_role_fk FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT membership_roles_assigner_fk FOREIGN KEY (assigned_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invitations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(254) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    invited_by_user_id BIGINT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY invitations_email_index (organization_id, email),
    CONSTRAINT invitations_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT invitations_inviter_fk FOREIGN KEY (invited_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guardian_student_relationships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    guardian_user_id BIGINT UNSIGNED NOT NULL,
    student_user_id BIGINT UNSIGNED NOT NULL,
    relationship_label VARCHAR(80) NULL,
    status ENUM('pending', 'approved', 'revoked') NOT NULL DEFAULT 'pending',
    approved_by_user_id BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY guardian_student_org_unique (organization_id, guardian_user_id, student_user_id),
    CONSTRAINT guardian_student_not_self CHECK (guardian_user_id <> student_user_id),
    CONSTRAINT guardian_student_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT guardian_student_guardian_fk FOREIGN KEY (guardian_user_id) REFERENCES users(id),
    CONSTRAINT guardian_student_student_fk FOREIGN KEY (student_user_id) REFERENCES users(id),
    CONSTRAINT guardian_student_approver_fk FOREIGN KEY (approved_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    type ENUM('production', 'class', 'committee', 'other') NOT NULL DEFAULT 'production',
    status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
    starts_on DATE NULL,
    ends_on DATE NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    UNIQUE KEY production_groups_org_slug_unique (organization_id, slug),
    CONSTRAINT production_groups_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT production_groups_creator_fk FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_memberships (
    group_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    member_label VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, membership_id),
    CONSTRAINT group_memberships_group_fk FOREIGN KEY (group_id) REFERENCES production_groups(id) ON DELETE CASCADE,
    CONSTRAINT group_memberships_membership_fk FOREIGN KEY (membership_id) REFERENCES organization_memberships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    type ENUM('announcement', 'discussion', 'group', 'parent', 'staff', 'resource') NOT NULL,
    visibility ENUM('organization', 'members_only') NOT NULL DEFAULT 'members_only',
    posting_policy ENUM('admins', 'selected_roles', 'members') NOT NULL DEFAULT 'members',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    UNIQUE KEY channels_org_slug_unique (organization_id, slug),
    CONSTRAINT channels_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT channels_group_fk FOREIGN KEY (group_id) REFERENCES production_groups(id),
    CONSTRAINT channels_creator_fk FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channel_memberships (
    channel_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NOT NULL,
    can_post BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (channel_id, membership_id),
    CONSTRAINT channel_memberships_channel_fk FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT channel_memberships_membership_fk FOREIGN KEY (membership_id) REFERENCES organization_memberships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channel_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL,
    author_user_id BIGINT UNSIGNED NOT NULL,
    parent_post_id BIGINT UNSIGNED NULL,
    body TEXT NOT NULL,
    is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
    pinned_by_user_id BIGINT UNSIGNED NULL,
    pinned_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY channel_posts_feed_index (channel_id, created_at),
    CONSTRAINT channel_posts_channel_fk FOREIGN KEY (channel_id) REFERENCES channels(id),
    CONSTRAINT channel_posts_author_fk FOREIGN KEY (author_user_id) REFERENCES users(id),
    CONSTRAINT channel_posts_parent_fk FOREIGN KEY (parent_post_id) REFERENCES channel_posts(id),
    CONSTRAINT channel_posts_pinner_fk FOREIGN KEY (pinned_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    type ENUM('direct', 'safeguarded') NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    CONSTRAINT conversations_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT conversations_creator_fk FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversation_participants (
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    participant_kind ENUM('adult', 'student', 'guardian') NOT NULL,
    is_required BOOLEAN NOT NULL DEFAULT FALSE,
    added_by_user_id BIGINT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME NULL,
    PRIMARY KEY (conversation_id, user_id),
    KEY conversation_participants_user_index (user_id, left_at),
    CONSTRAINT required_participant_is_guardian CHECK (is_required = FALSE OR participant_kind = 'guardian'),
    CONSTRAINT conversation_participants_conversation_fk FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    CONSTRAINT conversation_participants_user_fk FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT conversation_participants_adder_fk FOREIGN KEY (added_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at DATETIME NULL,
    deleted_at DATETIME NULL,
    deleted_by_user_id BIGINT UNSIGNED NULL,
    KEY messages_conversation_feed_index (conversation_id, created_at),
    CONSTRAINT messages_conversation_fk FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    CONSTRAINT messages_sender_fk FOREIGN KEY (sender_user_id) REFERENCES users(id),
    CONSTRAINT messages_deleter_fk FOREIGN KEY (deleted_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    reporter_user_id BIGINT UNSIGNED NOT NULL,
    subject_type ENUM('channel_post', 'message', 'user') NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(120) NOT NULL,
    details TEXT NULL,
    status ENUM('open', 'reviewing', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY content_reports_queue_index (organization_id, status, created_at),
    CONSTRAINT content_reports_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT content_reports_reporter_fk FOREIGN KEY (reporter_user_id) REFERENCES users(id),
    CONSTRAINT content_reports_reviewer_fk FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    subject_type VARCHAR(80) NULL,
    subject_id BIGINT UNSIGNED NULL,
    request_id CHAR(36) NULL,
    ip_address VARCHAR(45) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY audit_logs_lookup_index (organization_id, action, created_at),
    KEY audit_logs_subject_index (subject_type, subject_id),
    CONSTRAINT audit_logs_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT audit_logs_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    channel ENUM('in_app', 'email', 'push') NOT NULL DEFAULT 'in_app',
    payload JSON NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME NULL,
    read_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY notifications_delivery_index (status, available_at),
    KEY notifications_user_index (user_id, read_at, created_at),
    CONSTRAINT notifications_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT notifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

