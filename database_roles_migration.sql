-- Run once in phpMyAdmin after selecting the existing bookstore database.
USE bookstore;

CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer', 'seller') NOT NULL,
    status ENUM('active', 'pending', 'rejected') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE books
    ADD COLUMN seller_id INT NULL,
    ADD INDEX idx_books_seller_id (seller_id);

ALTER TABLE orders
    ADD COLUMN customer_id INT NULL,
    ADD INDEX idx_orders_customer_id (customer_id);
