-- Durable, replay-protected provider events for combined purchases.
CREATE TABLE IF NOT EXISTS commerce_shopping_webhooks (
 organization_id BIGINT UNSIGNED NOT NULL,
 event_id VARCHAR(255) NOT NULL,
 purchase_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(100) NOT NULL,
 object_id VARCHAR(255) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 status ENUM('queued','processed','failed') NOT NULL DEFAULT 'queued',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 available_at_utc DATETIME NOT NULL,
 received_at_utc DATETIME NOT NULL,
 PRIMARY KEY(organization_id,event_id),
 KEY idx_shopping_webhook_queue(organization_id,status,available_at_utc),
 FOREIGN KEY(purchase_id,organization_id) REFERENCES commerce_shopping_purchases(id,organization_id)
) ENGINE=InnoDB;
