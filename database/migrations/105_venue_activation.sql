ALTER TABLE commerce_venues ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;
CREATE INDEX idx_commerce_venues_active ON commerce_venues (organization_id,is_active,name);
