-- Run once in phpMyAdmin after selecting the existing bookstore database.
USE bookstore;

ALTER TABLE books
    ADD COLUMN pending_image VARCHAR(255) NULL AFTER image,
    ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved' AFTER seller_id;
