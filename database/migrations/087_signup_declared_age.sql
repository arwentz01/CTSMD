SET NAMES utf8mb4;

-- Exact guest ages are declarations, not verified DOBs. Existing age-group rows remain historical.
ALTER TABLE registration_submissions
  ADD COLUMN participant_declared_age TINYINT UNSIGNED NULL AFTER participant_age_group,
  ADD COLUMN age_evidence ENUM('legacy_group','guest_declared','account_dob') NOT NULL DEFAULT 'legacy_group' AFTER participant_declared_age,
  ADD KEY idx_registration_age_evidence (opportunity_id,age_evidence);
