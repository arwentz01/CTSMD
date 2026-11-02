SET NAMES utf8mb4;
-- Additive: reuse existing functions, retain populated cast/creative/crew history.
ALTER TABLE production_participant_functions
 ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
 MODIFY function_type ENUM('cast','crew','creative','operations') NOT NULL,
 ADD COLUMN function_code VARCHAR(60) NOT NULL DEFAULT 'custom',
 ADD COLUMN display_order INT NOT NULL DEFAULT 0,
 ADD COLUMN staff_notes VARCHAR(2000) NULL,
 ADD COLUMN playbill_eligible TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN revision INT UNSIGNED NOT NULL DEFAULT 1;
-- Existing publication consent and historical credits are not inferred/backfilled.
