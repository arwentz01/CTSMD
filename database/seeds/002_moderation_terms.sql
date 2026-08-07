SET NAMES utf8mb4;

-- Starter Community moderation vocabulary.
-- INSERT IGNORE is intentional: once an administrator changes a rule, rerunning this seed must not overwrite it.
-- This list is an initial safety baseline and is expected to be maintained through /admin/moderation/terms.

INSERT IGNORE INTO moderation_terms (term, category, action, match_mode, severity, aliases_json, notes, active)
VALUES
    ('fuck', 'profanity', 'review', 'normalized', 'medium', NULL, 'Starter profanity rule.', 1),
    ('shit', 'profanity', 'review', 'normalized', 'medium', NULL, 'Starter profanity rule.', 1),
    ('bitch', 'profanity', 'review', 'normalized', 'medium', NULL, 'Starter profanity rule.', 1),
    ('asshole', 'profanity', 'review', 'normalized', 'medium', NULL, 'Starter profanity rule.', 1),
    ('cunt', 'profanity', 'review', 'normalized', 'high', NULL, 'Starter profanity rule.', 1),
    ('fag', 'slur', 'block', 'normalized', 'critical', JSON_ARRAY('faggot'), 'Starter anti-LGBTQ slur rule.', 1),
    ('nigger', 'slur', 'block', 'normalized', 'critical', JSON_ARRAY('nigga'), 'Starter racial slur rule.', 1),
    ('chink', 'slur', 'block', 'normalized', 'critical', NULL, 'Starter racial slur rule.', 1),
    ('spic', 'slur', 'block', 'normalized', 'critical', NULL, 'Starter ethnic slur rule.', 1),
    ('kike', 'slur', 'block', 'normalized', 'critical', NULL, 'Starter antisemitic slur rule.', 1),
    ('retard', 'slur', 'review', 'normalized', 'high', NULL, 'Starter disability slur rule.', 1),
    ('dyke', 'slur', 'review', 'normalized', 'high', NULL, 'Context-sensitive anti-LGBTQ slur rule; review rather than automatic block.', 1);
