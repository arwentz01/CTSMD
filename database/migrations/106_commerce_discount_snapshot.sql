-- Nullable for historical orders; no guessed backfill or financial-history rewrite.
ALTER TABLE commerce_orders ADD COLUMN tax_basis_points_snapshot INT UNSIGNED NULL;
