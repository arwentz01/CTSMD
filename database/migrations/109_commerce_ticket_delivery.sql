-- Commerce reuses the existing email queue/SMTP transport, with an isolated worker and capture default.
ALTER TABLE email_queue
 MODIFY category ENUM('account_security','schedule','forms','volunteer','community','digest','system','commerce') NOT NULL DEFAULT 'system',
 MODIFY status ENUM('queued','sending','sent','failed','cancelled','suppressed','captured','commerce_queued','commerce_sending') NOT NULL DEFAULT 'queued',
 MODIFY recipient_email VARCHAR(254) NOT NULL;
ALTER TABLE email_delivery_log MODIFY outcome ENUM('sent','failed','suppressed','captured','commerce_queued','commerce_sending') NOT NULL;
ALTER TABLE commerce_order_links ADD COLUMN expires_at_utc DATETIME NULL;
CREATE TABLE IF NOT EXISTS commerce_deliveries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 email_queue_id BIGINT UNSIGNED NOT NULL,
 delivery_key VARCHAR(100) NOT NULL,
 public_reference CHAR(32) NOT NULL,
 retrieval_path VARCHAR(1024) NOT NULL,
 token_hash CHAR(64) NOT NULL,
 transport ENUM('capture','smtp') NOT NULL DEFAULT 'capture',
 available_at_utc DATETIME NOT NULL,
 lease_token CHAR(64) NULL,
 lease_until_utc DATETIME NULL,
 last_error_code VARCHAR(80) NULL,
 captured_at_utc DATETIME NULL,
 bounced_at_utc DATETIME NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 created_at_utc DATETIME NOT NULL,
 UNIQUE KEY(organization_id,order_id,delivery_key), UNIQUE KEY(email_queue_id), UNIQUE KEY(public_reference),
 FOREIGN KEY(order_id,organization_id) REFERENCES commerce_orders(id,organization_id),
 FOREIGN KEY(email_queue_id) REFERENCES email_queue(id),
 FOREIGN KEY(token_hash) REFERENCES commerce_order_links(token_hash),
 FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS commerce_delivery_events (
 organization_id BIGINT UNSIGNED NOT NULL,
 event_id VARCHAR(100) NOT NULL,
 delivery_id BIGINT UNSIGNED NOT NULL,
 event_type ENUM('delivered','bounced','complained') NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 received_at_utc DATETIME NOT NULL,
 PRIMARY KEY(organization_id,event_id),
 FOREIGN KEY(organization_id) REFERENCES commerce_organizations(id),
 FOREIGN KEY(delivery_id) REFERENCES commerce_deliveries(id)
) ENGINE=InnoDB;
