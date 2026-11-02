SET NAMES utf8mb4;

-- Rehearsal videos are stored as private files on Bluehost, never inside the database.
-- Only production staff manage files; viewers use existing membership/guardian/group checks.
CREATE TABLE IF NOT EXISTS rehearsal_videos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  production_id BIGINT UNSIGNED NOT NULL,
  uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'choreography',
  description TEXT NULL,
  group_id BIGINT UNSIGNED NULL,
  original_filename VARCHAR(255) NOT NULL,
  storage_key VARCHAR(600) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  byte_size BIGINT UNSIGNED NOT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  archived_at DATETIME NULL,
  UNIQUE KEY uq_rehearsal_video_storage (storage_key),
  KEY idx_rehearsal_video_production (production_id,status,created_at),
  CONSTRAINT fk_rehearsal_video_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
  CONSTRAINT fk_rehearsal_video_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_rehearsal_video_group FOREIGN KEY (group_id) REFERENCES production_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
