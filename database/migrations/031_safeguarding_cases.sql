SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS safeguarding_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_code VARCHAR(40) NULL UNIQUE,
    title VARCHAR(190) NOT NULL,
    category VARCHAR(100) NOT NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status ENUM('open','in_review','action_required','monitoring','resolved','closed') NOT NULL DEFAULT 'open',
    summary TEXT NOT NULL,
    production_id BIGINT UNSIGNED NULL,
    subject_user_id BIGINT UNSIGNED NULL,
    reported_by_user_id BIGINT UNSIGNED NOT NULL,
    assigned_to_user_id BIGINT UNSIGNED NULL,
    occurred_at DATETIME NULL,
    reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_safeguarding_cases_queue (status,priority,updated_at),
    KEY idx_safeguarding_cases_production (production_id,status),
    KEY idx_safeguarding_cases_subject (subject_user_id,status),
    KEY idx_safeguarding_cases_assignee (assigned_to_user_id,status),
    CONSTRAINT fk_safeguarding_case_production FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE SET NULL,
    CONSTRAINT fk_safeguarding_case_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_safeguarding_case_reporter FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_safeguarding_case_assignee FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS safeguarding_case_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('created','note','status_change','assignment','priority_change','resolution') NOT NULL DEFAULT 'note',
    note TEXT NOT NULL,
    status_from VARCHAR(40) NULL,
    status_to VARCHAR(40) NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_safeguarding_case_events_case (case_id,created_at,id),
    CONSTRAINT fk_safeguarding_case_event_case FOREIGN KEY (case_id) REFERENCES safeguarding_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_safeguarding_case_event_actor FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
