-- Native/mobile API bearer sessions.
-- Tokens are stored only as SHA-256 hashes. Raw bearer tokens are returned once at login.
CREATE TABLE IF NOT EXISTS auth_mobile_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_mobile_tokens_hash (token_hash),
    KEY idx_auth_mobile_tokens_user (user_id),
    KEY idx_auth_mobile_tokens_expiry (expires_at),
    CONSTRAINT fk_auth_mobile_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
