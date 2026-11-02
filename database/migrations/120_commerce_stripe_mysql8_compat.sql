-- Migration 108 deliberately moves provider selection into application guards.
-- MySQL 8 enforces the simulator-only CHECK created by 101, while MySQL 5.7 ignores it.
-- Remove only that exact legacy check when it exists, preserving other constraints.
SET @ctsmd_legacy_provider_check = (
 SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS tc
 WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME='commerce_payment_attempts'
   AND tc.CONSTRAINT_TYPE='CHECK' AND tc.CONSTRAINT_NAME='commerce_payment_attempts_chk_1'
);
SET @ctsmd_provider_check_sql = IF(@ctsmd_legacy_provider_check=1,
 'ALTER TABLE commerce_payment_attempts DROP CHECK commerce_payment_attempts_chk_1',
 'DO 0');
PREPARE ctsmd_provider_check_stmt FROM @ctsmd_provider_check_sql;
EXECUTE ctsmd_provider_check_stmt;
DEALLOCATE PREPARE ctsmd_provider_check_stmt;
-- Reinstate the explicitly test-only allowlist on servers that enforce checks.
SET @ctsmd_test_provider_check = (
 SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS tc
 WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME='commerce_payment_attempts'
   AND tc.CONSTRAINT_TYPE='CHECK' AND tc.CONSTRAINT_NAME='commerce_payment_provider_allowed'
);
SET @ctsmd_test_provider_sql = IF(@ctsmd_test_provider_check=0,
 'ALTER TABLE commerce_payment_attempts ADD CONSTRAINT commerce_payment_provider_allowed CHECK (provider IN (''simulated'',''stripe_test''))',
 'DO 0');
PREPARE ctsmd_test_provider_stmt FROM @ctsmd_test_provider_sql;
EXECUTE ctsmd_test_provider_stmt;
DEALLOCATE PREPARE ctsmd_test_provider_stmt;
