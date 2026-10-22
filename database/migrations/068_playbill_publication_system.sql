SET NAMES utf8mb4;

ALTER TABLE playbills
    ADD COLUMN editorial_status ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft' AFTER status,
    ADD COLUMN public_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER editorial_status,
    ADD COLUMN public_description VARCHAR(500) NULL AFTER cover_note,
    ADD COLUMN website_url VARCHAR(1000) NULL AFTER public_description,
    ADD COLUMN upcoming_url VARCHAR(1000) NULL AFTER website_url,
    ADD COLUMN donate_url VARCHAR(1000) NULL AFTER upcoming_url,
    ADD COLUMN social_image_stored_file_id BIGINT UNSIGNED NULL AFTER artwork_stored_file_id,
    ADD COLUMN unpublished_at DATETIME NULL AFTER published_at,
    ADD CONSTRAINT fk_playbill_social_image FOREIGN KEY (social_image_stored_file_id) REFERENCES stored_files(id) ON DELETE SET NULL;

UPDATE playbills
SET editorial_status = CASE status WHEN 'current' THEN 'published' WHEN 'archived' THEN 'archived' ELSE 'draft' END,
    public_enabled = CASE WHEN status IN ('current','archived') THEN 1 ELSE 0 END;

ALTER TABLE student_profiles
    ADD COLUMN photographer_credit VARCHAR(190) NULL AFTER headshot_stored_file_id;

CREATE TABLE IF NOT EXISTS playbill_cast_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playbill_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    role_title VARCHAR(190) NULL,
    biography TEXT NULL,
    headshot_stored_file_id BIGINT UNSIGNED NULL,
    photographer_credit VARCHAR(190) NULL,
    display_order INT NOT NULL DEFAULT 0,
    visible TINYINT(1) NOT NULL DEFAULT 0,
    approval_status ENUM('draft','pending','approved','withdrawn') NOT NULL DEFAULT 'draft',
    approved_by_user_id BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    updated_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_playbill_cast_profile (playbill_id,user_id),
    KEY idx_playbill_cast_public (playbill_id,visible,approval_status,display_order),
    CONSTRAINT fk_playbill_cast_playbill FOREIGN KEY (playbill_id) REFERENCES playbills(id) ON DELETE CASCADE,
    CONSTRAINT fk_playbill_cast_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_playbill_cast_headshot FOREIGN KEY (headshot_stored_file_id) REFERENCES stored_files(id) ON DELETE SET NULL,
    CONSTRAINT fk_playbill_cast_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_playbill_cast_editor FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sponsors
    ADD COLUMN tier ENUM('premium','regular') NOT NULL DEFAULT 'regular' AFTER name,
    ADD COLUMN public_description VARCHAR(1000) NULL AFTER blurb,
    ADD COLUMN advertisement_stored_file_id BIGINT UNSIGNED NULL AFTER logo_stored_file_id,
    ADD COLUMN cta_label VARCHAR(80) NULL AFTER advertisement_stored_file_id,
    ADD COLUMN internal_contact_name VARCHAR(190) NULL AFTER cta_label,
    ADD COLUMN internal_contact_email VARCHAR(255) NULL AFTER internal_contact_name,
    ADD COLUMN internal_contact_phone VARCHAR(80) NULL AFTER internal_contact_email,
    ADD COLUMN internal_notes TEXT NULL AFTER internal_contact_phone,
    ADD COLUMN sponsorship_starts_on DATE NULL AFTER internal_notes,
    ADD COLUMN sponsorship_ends_on DATE NULL AFTER sponsorship_starts_on,
    ADD CONSTRAINT fk_sponsor_advertisement FOREIGN KEY (advertisement_stored_file_id) REFERENCES stored_files(id) ON DELETE SET NULL;

ALTER TABLE playbill_sponsors
    ADD COLUMN placement_format ENUM('card','half_page','full_page') NOT NULL DEFAULT 'card' AFTER placement_label,
    ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order;

CREATE TABLE IF NOT EXISTS playbill_editions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playbill_id BIGINT UNSIGNED NOT NULL,
    edition_number INT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    published_by_user_id BIGINT UNSIGNED NOT NULL,
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_playbill_edition (playbill_id,edition_number),
    KEY idx_playbill_edition_latest (playbill_id,published_at),
    CONSTRAINT fk_playbill_edition_playbill FOREIGN KEY (playbill_id) REFERENCES playbills(id) ON DELETE CASCADE,
    CONSTRAINT fk_playbill_edition_publisher FOREIGN KEY (published_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO playbill_cast_profiles
    (playbill_id,user_id,display_name,role_title,biography,headshot_stored_file_id,photographer_credit,display_order,visible,approval_status,updated_by_user_id)
SELECT pb.id,pm.user_id,COALESCE(NULLIF(sp.preferred_name,''),CONCAT(u.first_name,' ',u.last_name)),pm.participation_role,
       sp.short_bio,sp.headshot_stored_file_id,sp.photographer_credit,
       (
           SELECT COUNT(*)
           FROM production_memberships pm2
           JOIN users u2 ON u2.id=pm2.user_id AND u2.active=1 AND u2.account_status<>'disabled'
           WHERE pm2.production_id=pb.production_id AND pm2.status='active' AND pm2.audience_type='student'
             AND (
                 pm2.participation_role < pm.participation_role
                 OR (pm2.participation_role = pm.participation_role AND u2.last_name < u.last_name)
                 OR (pm2.participation_role = pm.participation_role AND u2.last_name = u.last_name AND u2.first_name < u.first_name)
                 OR (pm2.participation_role = pm.participation_role AND u2.last_name = u.last_name AND u2.first_name = u.first_name AND pm2.user_id <= pm.user_id)
             )
       ) * 10,
       0,'draft',COALESCE(pb.created_by_user_id,pm.user_id)
FROM playbills pb
JOIN production_memberships pm ON pm.production_id=pb.production_id AND pm.status='active' AND pm.audience_type='student'
JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled'
LEFT JOIN student_profiles sp ON sp.user_id=pm.user_id
ON DUPLICATE KEY UPDATE user_id=VALUES(user_id);
