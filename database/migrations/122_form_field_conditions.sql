-- Non-Commerce form builder: conditional visibility for existing question records.
-- Unconditional questions have NULL condition_json. No user responses are modified.
SET NAMES utf8mb4;
ALTER TABLE form_fields
    ADD COLUMN condition_json JSON NULL AFTER options_json;
