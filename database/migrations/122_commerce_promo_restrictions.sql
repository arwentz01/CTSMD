-- 122: optional promotion rules. Keep the original promo migration 121 immutable.
ALTER TABLE commerce_promo_codes
 ADD COLUMN minimum_subtotal_minor INT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN per_customer_limit INT UNSIGNED NOT NULL DEFAULT 0,
 ADD COLUMN max_discount_minor INT UNSIGNED NOT NULL DEFAULT 0,
 ADD CONSTRAINT chk_commerce_promo_customer_limit CHECK(per_customer_limit<=1000000);
CREATE INDEX idx_commerce_promo_claim_customer ON commerce_promo_claims(organization_id,promo_id,state,purchase_id);
