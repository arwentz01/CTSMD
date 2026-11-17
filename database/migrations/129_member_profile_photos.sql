SET NAMES utf8mb4;

-- One current, private profile image per CTSMD account. Original upload remains in
-- existing stored_files, while square display renditions live under STORAGE_PATH.
CREATE TABLE IF NOT EXISTS member_profile_photos (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  stored_file_id BIGINT UNSIGNED NOT NULL,
  avatar_large_key VARCHAR(500) NOT NULL,
  avatar_medium_key VARCHAR(500) NOT NULL,
  avatar_small_key VARCHAR(500) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_member_photo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_photo_file FOREIGN KEY (stored_file_id) REFERENCES stored_files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
