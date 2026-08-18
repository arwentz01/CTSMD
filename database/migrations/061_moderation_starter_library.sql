SET NAMES utf8mb4;

-- CTSMD Connect starter moderation library.
-- Idempotent by design: existing staff-created/customized terms are never overwritten.
-- Ordinary profanity is generally held for staff review; direct threats and unambiguous slurs are blocked.

INSERT IGNORE INTO moderation_terms
    (term, category, action, match_mode, severity, aliases_json, notes, active, created_by_user_id, updated_by_user_id)
VALUES
    ('fuck', 'profanity', 'review', 'normalized', 'medium', JSON_ARRAY('fucking','fucker','motherfucker','motherfucking'), 'Starter rule: common profanity. Hold for context review rather than auto-block.', 1, NULL, NULL),
    ('shit', 'profanity', 'review', 'normalized', 'medium', JSON_ARRAY('bullshit','shithead'), 'Starter rule: common profanity.', 1, NULL, NULL),
    ('bitch', 'profanity', 'review', 'normalized', 'medium', JSON_ARRAY('bitches','bitchy'), 'Starter rule: profanity that may also be targeted harassment.', 1, NULL, NULL),
    ('asshole', 'profanity', 'review', 'normalized', 'medium', NULL, 'Starter rule: common profanity.', 1, NULL, NULL),
    ('bastard', 'profanity', 'review', 'normalized', 'low', NULL, 'Starter rule: lower-severity profanity.', 1, NULL, NULL),
    ('cunt', 'profanity', 'review', 'normalized', 'high', NULL, 'Starter rule: high-severity profanity.', 1, NULL, NULL),
    ('dick', 'sexual_language', 'review', 'normalized', 'medium', JSON_ARRAY('dickhead'), 'Starter rule: sexual/profane language; context may matter.', 1, NULL, NULL),
    ('pussy', 'sexual_language', 'review', 'normalized', 'medium', NULL, 'Starter rule: sexual/profane language; context may matter.', 1, NULL, NULL),
    ('slut', 'harassment', 'review', 'normalized', 'high', NULL, 'Starter rule: commonly used as targeted sexual harassment.', 1, NULL, NULL),
    ('whore', 'harassment', 'review', 'normalized', 'high', NULL, 'Starter rule: commonly used as targeted sexual harassment.', 1, NULL, NULL),
    ('porn', 'sexual_content', 'review', 'normalized', 'high', JSON_ARRAY('pornography','porno'), 'Starter rule: sexual-content reference in a youth community.', 1, NULL, NULL),
    ('blowjob', 'sexual_content', 'review', 'normalized', 'high', JSON_ARRAY('blow job'), 'Starter rule: explicit sexual content.', 1, NULL, NULL),
    ('handjob', 'sexual_content', 'review', 'normalized', 'high', JSON_ARRAY('hand job'), 'Starter rule: explicit sexual content.', 1, NULL, NULL),
    ('sext', 'sexual_content', 'review', 'normalized', 'high', JSON_ARRAY('sexting'), 'Starter rule: sexual solicitation/content.', 1, NULL, NULL),
    ('send nudes', 'sexual_solicitation', 'block', 'normalized', 'critical', JSON_ARRAY('send nude','send me nudes','send me a nude'), 'Starter rule: direct sexual-image solicitation.', 1, NULL, NULL),
    ('dick pic', 'sexual_solicitation', 'block', 'normalized', 'critical', JSON_ARRAY('dickpic','send a dick pic','send dick pic'), 'Starter rule: explicit sexual-image solicitation.', 1, NULL, NULL),
    ('kill yourself', 'threat_or_self_harm_harassment', 'block', 'normalized', 'critical', JSON_ARRAY('kys','go kill yourself'), 'Starter rule: targeted self-harm encouragement.', 1, NULL, NULL),
    ('i will kill you', 'threat', 'block', 'normalized', 'critical', JSON_ARRAY('ill kill you','i am going to kill you','im going to kill you'), 'Starter rule: direct threat of violence.', 1, NULL, NULL),
    ('shoot you', 'threat', 'block', 'normalized', 'critical', JSON_ARRAY('shoot up the theatre','shoot up the theater'), 'Starter rule: direct firearm threat language.', 1, NULL, NULL),
    ('bomb the theatre', 'threat', 'block', 'normalized', 'critical', JSON_ARRAY('bomb the theater','bomb threat'), 'Starter rule: direct explosive-threat language.', 1, NULL, NULL),
    ('rape you', 'threat', 'block', 'normalized', 'critical', JSON_ARRAY('i will rape you','ill rape you'), 'Starter rule: direct sexual-violence threat.', 1, NULL, NULL),
    ('faggot', 'slur', 'block', 'normalized', 'critical', JSON_ARRAY('fag'), 'Starter rule: unambiguous anti-LGBTQ slur.', 1, NULL, NULL),
    ('nigger', 'slur', 'block', 'normalized', 'critical', JSON_ARRAY('nigga'), 'Starter rule: unambiguous anti-Black slur.', 1, NULL, NULL),
    ('kike', 'slur', 'block', 'normalized', 'critical', NULL, 'Starter rule: unambiguous antisemitic slur.', 1, NULL, NULL),
    ('chink', 'slur', 'block', 'normalized', 'critical', NULL, 'Starter rule: unambiguous anti-Asian slur.', 1, NULL, NULL),
    ('spic', 'slur', 'block', 'normalized', 'critical', NULL, 'Starter rule: unambiguous anti-Latino slur.', 1, NULL, NULL),
    ('tranny', 'slur', 'block', 'normalized', 'critical', NULL, 'Starter rule: unambiguous anti-trans slur.', 1, NULL, NULL),
    ('retard', 'slur', 'review', 'normalized', 'high', JSON_ARRAY('retarded'), 'Starter rule: ableist slur; held for context review.', 1, NULL, NULL),
    ('you are worthless', 'harassment', 'review', 'normalized', 'high', JSON_ARRAY('youre worthless'), 'Starter rule: targeted degrading harassment.', 1, NULL, NULL),
    ('nobody likes you', 'harassment', 'review', 'normalized', 'medium', NULL, 'Starter rule: targeted bullying language.', 1, NULL, NULL),
    ('go die', 'harassment', 'block', 'normalized', 'critical', JSON_ARRAY('just die'), 'Starter rule: targeted death/self-harm encouragement.', 1, NULL, NULL);
