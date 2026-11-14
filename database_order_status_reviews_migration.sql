-- Run once in phpMyAdmin after selecting the existing bookstore database.
USE bookstore;

ALTER TABLE orders
    ADD COLUMN status ENUM(
        'pending_confirmation',
        'confirmed',
        'preparing',
        'shipping',
        'delivered',
        'cancelled',
        'return_requested',
        'returned'
    )
        NOT NULL DEFAULT 'pending_confirmation' AFTER total_price;

CREATE TABLE IF NOT EXISTS book_reviews (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    customer_id INT NULL,
    reviewer_name VARCHAR(150) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_book_reviews_book_customer (book_id, customer_id),
    KEY idx_book_reviews_book_created (book_id, created_at),
    CONSTRAINT chk_book_reviews_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_book_reviews_book FOREIGN KEY (book_id)
        REFERENCES books (id) ON DELETE CASCADE,
    CONSTRAINT fk_book_reviews_customer FOREIGN KEY (customer_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
