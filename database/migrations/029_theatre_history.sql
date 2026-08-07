SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS theatre_history_credits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    production_id BIGINT UNSIGNED NULL,
    source_membership_id BIGINT UNSIGNED NULL,
    credit_kind VARCHAR(40) NOT NULL DEFAULT 'performance',
    organization_name VARCHAR(190) NOT NULL DEFAULT 'Children''s Theatre of Southern Maryland',
    production_title VARCHAR(190) NOT NULL,
    season_label VARCHAR(100) NULL,
    role_title VARCHAR(190) NULL,
    participation_track VARCHAR(100) NULL,
    groups_snapshot TEXT NULL,
    verification_status ENUM('verified','voided') NOT NULL DEFAULT 'verified',
    verified_by_user_id BIGINT UNSIGNED NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    participation_started_at DATETIME NULL,
    participation_ended_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_theatre_credit_user_production_kind (user_id,production_id,credit_kind),
    KEY idx_theatre_credit_user_status (user_id,verification_status,verified_at),
    KEY idx_theatre_credit_production (production_id,user_id),
    CONSTRAINT fk_theatre_credit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_theatre_credit_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE SET NULL,
    CONSTRAINT fk_theatre_credit_membership FOREIGN KEY (source_membership_id) REFERENCES production_memberships(id) ON DELETE SET NULL,
    CONSTRAINT fk_theatre_credit_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill existing Student production memberships as CTSMD-verified credits.
-- Group names are snapshotted as a delimiter-separated string to remain portable
-- across MySQL/MariaDB versions used by local MAMP and shared hosting.
INSERT INTO theatre_history_credits (
    user_id,production_id,source_membership_id,credit_kind,production_title,season_label,
    role_title,participation_track,groups_snapshot,verification_status,verified_at,participation_started_at
)
SELECT
    pm.user_id,
    pm.production_id,
    pm.id,
    'performance',
    p.title,
    p.season,
    COALESCE(NULLIF(cr.role_title,''),NULLIF(pm.participation_role,'')),
    NULLIF(cr.participation_track,''),
    NULLIF(GROUP_CONCAT(DISTINCT pg.name ORDER BY pg.sort_order,pg.name SEPARATOR '||'),''),
    'verified',
    CURRENT_TIMESTAMP,
    pm.created_at
FROM production_memberships pm
JOIN productions p ON p.id=pm.production_id
LEFT JOIN production_casting_records cr ON cr.production_id=pm.production_id AND cr.user_id=pm.user_id
LEFT JOIN production_group_members pgm ON pgm.production_membership_id=pm.id AND pgm.status='active'
LEFT JOIN production_groups pg ON pg.id=pgm.group_id
WHERE pm.audience_type='student'
GROUP BY pm.user_id,pm.production_id,pm.id,p.title,p.season,cr.role_title,pm.participation_role,cr.participation_track,pm.created_at
ON DUPLICATE KEY UPDATE
    source_membership_id=VALUES(source_membership_id),
    role_title=VALUES(role_title),
    participation_track=VALUES(participation_track),
    groups_snapshot=COALESCE(VALUES(groups_snapshot),groups_snapshot),
    verification_status='verified',
    updated_at=CURRENT_TIMESTAMP;
