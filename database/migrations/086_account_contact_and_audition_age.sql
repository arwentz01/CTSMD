SET NAMES utf8mb4;

-- Contact and eligibility data belongs to the actual person, including managed Students.
ALTER TABLE users
  ADD COLUMN date_of_birth DATE NULL,
  ADD COLUMN contact_phone VARCHAR(40) NULL,
  ADD COLUMN address_line1 VARCHAR(190) NULL,
  ADD COLUMN address_line2 VARCHAR(190) NULL,
  ADD COLUMN address_city VARCHAR(100) NULL,
  ADD COLUMN address_state VARCHAR(60) NULL,
  ADD COLUMN address_postal_code VARCHAR(20) NULL;

-- Exact audition age requirements; cutoff defaults to audition start date.
ALTER TABLE registration_opportunities
  ADD COLUMN minimum_age TINYINT UNSIGNED NULL,
  ADD COLUMN maximum_age TINYINT UNSIGNED NULL,
  ADD COLUMN age_eligibility_date DATE NULL;
