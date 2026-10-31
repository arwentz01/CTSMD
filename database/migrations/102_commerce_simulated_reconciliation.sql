-- Local simulator evidence only. No provider credentials, callbacks or workers enabled.
CREATE TABLE IF NOT EXISTS commerce_simulated_results (
 organization_id BIGINT UNSIGNED NOT NULL,
 attempt_id BIGINT UNSIGNED NOT NULL,
 outcome ENUM('succeeded','failed','canceled') NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(attempt_id),
 FOREIGN KEY(attempt_id,organization_id) REFERENCES commerce_payment_attempts(id,organization_id),
 FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
