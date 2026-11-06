-- CTSMD non-Commerce forms: informational section headings.
-- This migration extends the existing field type list without changing stored questions.
-- Review the live enum against information_schema before applying to a populated database.
SET NAMES utf8mb4;

ALTER TABLE form_fields
    MODIFY COLUMN field_type ENUM(
        'short_text',
        'long_text',
        'single_choice',
        'multiple_choice',
        'date',
        'acknowledgment',
        'signature',
        'resource_link',
        'section_heading'
    ) NOT NULL;
