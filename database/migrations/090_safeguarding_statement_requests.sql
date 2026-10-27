SET NAMES utf8mb4;

-- Staff-controlled, recipient-safe narrative; never reuse the private case summary.
ALTER TABLE safeguarding_cases
  ADD COLUMN shared_incident_description TEXT NULL AFTER summary;

-- One request / one immutable submitted statement. This is a child entity of a case,
-- not another generic forms system or a separate table per migration.
CREATE TABLE IF NOT EXISTS safeguarding_statement_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT UNSIGNED NOT NULL,
  recipient_name VARCHAR(190) NOT NULL,
  recipient_email VARCHAR(254) NOT NULL,
  recipient_user_id BIGINT UNSIGNED NULL,
  token_hash CHAR(64) NOT NULL,
  shared_title VARCHAR(190) NOT NULL,
  shared_description TEXT NOT NULL,
  shared_occurred_at DATETIME NULL,
  shared_production_title VARCHAR(190) NULL,
  status ENUM('pending','submitted','revoked','expired') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NOT NULL,
  invited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  invited_by_user_id BIGINT UNSIGNED NOT NULL,
  submitted_at DATETIME NULL,
  statement_body MEDIUMTEXT NULL,
  statement_witnesses TEXT NULL,
  statement_followup TEXT NULL,
  attested_name VARCHAR(190) NULL,
  accepted_attestation TINYINT(1) NOT NULL DEFAULT 0,
  revoked_at DATETIME NULL,
  revoked_by_user_id BIGINT UNSIGNED NULL,
  KEY idx_safeguarding_statement_case (case_id,status,invited_at),
  UNIQUE KEY uq_safeguarding_statement_token (token_hash),
  CONSTRAINT fk_safeguarding_statement_case FOREIGN KEY (case_id) REFERENCES safeguarding_cases(id) ON DELETE RESTRICT,
  CONSTRAINT fk_safeguarding_statement_inviter FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_safeguarding_statement_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_safeguarding_statement_revoker FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
