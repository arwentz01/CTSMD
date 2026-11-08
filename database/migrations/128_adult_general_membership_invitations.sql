SET NAMES utf8mb4;

-- Distinguish invitation-led general membership from legacy account access links.
-- Existing invitations retain their original behavior until explicitly upgraded.
ALTER TABLE auth_invitations
    ADD COLUMN purpose VARCHAR(32) NOT NULL DEFAULT 'account_access' AFTER created_by_user_id;

CREATE INDEX idx_auth_invitations_purpose ON auth_invitations (purpose,accepted_at,expires_at);
