SET NAMES utf8mb4;

ALTER TABLE production_cast_publications
    DROP PRIMARY KEY,
    ADD COLUMN publication_type ENUM('callback','cast') NOT NULL DEFAULT 'cast' AFTER production_id,
    ADD PRIMARY KEY (production_id,publication_type);

UPDATE production_cast_publications
SET publication_type='cast';
