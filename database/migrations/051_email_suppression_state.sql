SET NAMES utf8mb4;

ALTER TABLE email_queue
    MODIFY COLUMN status ENUM('queued','sending','sent','failed','cancelled','suppressed') NOT NULL DEFAULT 'queued';

ALTER TABLE email_delivery_log
    MODIFY COLUMN outcome ENUM('sent','failed','suppressed') NOT NULL;
