-- One authoritative inventory row per admission entitlement; no separate capacity ledger.
ALTER TABLE commerce_performances ADD COLUMN seating_mode ENUM('reserved','general_admission') NOT NULL DEFAULT 'reserved';
