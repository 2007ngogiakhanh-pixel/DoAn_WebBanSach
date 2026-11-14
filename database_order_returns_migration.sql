-- Run after database_order_status_reviews_migration.sql.
-- Adds order workflow states and customer return requests.
USE bookstore;

ALTER TABLE orders
    MODIFY COLUMN status ENUM(
        'pending_confirmation',
        'confirmed',
        'preparing',
        'shipping',
        'delivered',
        'cancelled',
        'return_requested',
        'returned'
    ) NOT NULL DEFAULT 'pending_confirmation';

CREATE TABLE IF NOT EXISTS order_return_requests (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    reason TEXT NOT NULL,
    item_condition TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_order_return_requests_order (order_id),
    CONSTRAINT fk_order_return_requests_order
        FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_return_requests_customer
        FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_return_items (
    return_request_id INT NOT NULL,
    book_id INT NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    PRIMARY KEY (return_request_id, book_id),
    CONSTRAINT fk_order_return_items_request
        FOREIGN KEY (return_request_id) REFERENCES order_return_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_return_items_book
        FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
