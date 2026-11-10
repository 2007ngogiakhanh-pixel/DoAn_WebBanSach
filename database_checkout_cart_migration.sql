-- Run once in phpMyAdmin after selecting the bookstore database.
USE bookstore;

ALTER TABLE books
    -- Existing books intentionally start with zero inventory; set stock from Admin/Seller after migration.
    ADD COLUMN stock_quantity INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_available;

ALTER TABLE users
    ADD COLUMN shipping_name VARCHAR(150) NULL AFTER full_name,
    ADD COLUMN shipping_email VARCHAR(190) NULL AFTER email,
    ADD COLUMN phone VARCHAR(20) NULL,
    ADD COLUMN country VARCHAR(100) NULL,
    ADD COLUMN province VARCHAR(100) NULL,
    ADD COLUMN district VARCHAR(100) NULL,
    ADD COLUMN ward VARCHAR(100) NULL,
    ADD COLUMN shipping_address VARCHAR(255) NULL;

ALTER TABLE orders
    MODIFY COLUMN customer_name VARCHAR(150) NOT NULL,
    MODIFY COLUMN address VARCHAR(700) NOT NULL,
    ADD COLUMN recipient_email VARCHAR(190) NULL AFTER customer_name,
    ADD COLUMN country VARCHAR(100) NULL,
    ADD COLUMN province VARCHAR(100) NULL,
    ADD COLUMN district VARCHAR(100) NULL,
    ADD COLUMN ward VARCHAR(100) NULL,
    ADD COLUMN order_note TEXT NULL,
    ADD COLUMN payment_method ENUM('cod', 'bank_transfer') NOT NULL DEFAULT 'cod';

UPDATE books
SET is_available = 0
WHERE stock_quantity = 0;

CREATE TABLE user_cart_items (
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, book_id),
    KEY idx_user_cart_items_book_id (book_id),
    CONSTRAINT fk_user_cart_items_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_cart_items_book
        FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
