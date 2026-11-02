SET NAMES utf8mb4;

CREATE TABLE production_late_pickup_settings (
  production_id BIGINT UNSIGNED PRIMARY KEY,
  occurrence_threshold INT UNSIGNED NOT NULL DEFAULT 3,
  grace_minutes INT UNSIGNED NOT NULL DEFAULT 10,
  notice_template TEXT NULL,
  updated_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_late_pickup_setting_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
  CONSTRAINT fk_late_pickup_setting_editor FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE late_pickup_occurrences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_item_id BIGINT UNSIGNED NOT NULL,
  production_id BIGINT UNSIGNED NOT NULL,
  student_user_id BIGINT UNSIGNED NOT NULL,
  actual_checkout_at DATETIME NOT NULL,
  expected_release_at DATETIME NOT NULL,
  late_minutes INT UNSIGNED NOT NULL,
  recorded_by_user_id BIGINT UNSIGNED NOT NULL,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  corrected_at DATETIME NULL,
  correction_reason VARCHAR(1000) NULL,
  voided_at DATETIME NULL,
  voided_by_user_id BIGINT UNSIGNED NULL,
  void_reason VARCHAR(1000) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_late_pickup_student_event (schedule_item_id,student_user_id),
  KEY idx_late_pickup_count (production_id,student_user_id,voided_at),
  CONSTRAINT fk_late_pickup_schedule FOREIGN KEY (schedule_item_id) REFERENCES schedule_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_recorder FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_voider FOREIGN KEY (voided_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE late_pickup_occurrence_revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  occurrence_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  action ENUM('created','corrected','voided','restored') NOT NULL,
  original_json JSON NULL,
  new_json JSON NULL,
  reason VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_late_pickup_revision (occurrence_id,created_at),
  CONSTRAINT fk_late_pickup_revision_occurrence FOREIGN KEY (occurrence_id) REFERENCES late_pickup_occurrences(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_revision_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE late_pickup_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  production_id BIGINT UNSIGNED NOT NULL,
  student_user_id BIGINT UNSIGNED NOT NULL,
  occurrence_count INT UNSIGNED NOT NULL DEFAULT 0,
  threshold_at_open INT UNSIGNED NOT NULL,
  status ENUM('open','dismissed','resolved','below_threshold') NOT NULL DEFAULT 'open',
  resolution_reason VARCHAR(1000) NULL,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_late_pickup_review_student (production_id,student_user_id),
  CONSTRAINT fk_late_pickup_review_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
  CONSTRAINT fk_late_pickup_review_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_review_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE late_pickup_notice_drafts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  review_id BIGINT UNSIGNED NOT NULL,
  production_id BIGINT UNSIGNED NOT NULL,
  student_user_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('draft','pending_approval','approved','queued','sent','failed','superseded') NOT NULL DEFAULT 'draft',
  revision_number INT UNSIGNED NOT NULL DEFAULT 1,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  approved_by_user_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  queued_by_user_id BIGINT UNSIGNED NULL,
  queued_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_late_pickup_notice_review (review_id,status),
  CONSTRAINT fk_late_pickup_notice_review FOREIGN KEY (review_id) REFERENCES late_pickup_reviews(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_notice_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_notice_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_notice_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_notice_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_notice_queuer FOREIGN KEY (queued_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE late_pickup_notice_revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notice_id BIGINT UNSIGNED NOT NULL,
  revision_number INT UNSIGNED NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_late_pickup_notice_revision (notice_id,revision_number),
  CONSTRAINT fk_late_pickup_notice_revision_notice FOREIGN KEY (notice_id) REFERENCES late_pickup_notice_drafts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_notice_revision_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE late_pickup_notice_recipients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notice_id BIGINT UNSIGNED NOT NULL,
  guardian_user_id BIGINT UNSIGNED NOT NULL,
  recipient_name VARCHAR(190) NOT NULL,
  recipient_email VARCHAR(255) NOT NULL,
  email_queue_id BIGINT UNSIGNED NULL,
  queue_status VARCHAR(40) NOT NULL DEFAULT 'queued',
  content_snapshot_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_late_pickup_notice_guardian (notice_id,guardian_user_id),
  CONSTRAINT fk_late_pickup_recipient_notice FOREIGN KEY (notice_id) REFERENCES late_pickup_notice_drafts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_recipient_guardian FOREIGN KEY (guardian_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_late_pickup_recipient_queue FOREIGN KEY (email_queue_id) REFERENCES email_queue(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
