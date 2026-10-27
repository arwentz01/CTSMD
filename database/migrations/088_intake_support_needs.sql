SET NAMES utf8mb4;

-- These answers are sensitive participant support information. Keep them on private intake submissions.
ALTER TABLE registration_submissions
  ADD COLUMN allergy_information TEXT NULL AFTER notes,
  ADD COLUMN accommodation_information TEXT NULL AFTER allergy_information;
