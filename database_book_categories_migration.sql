-- Run once in phpMyAdmin after selecting the existing bookstore database.
USE bookstore;

CREATE TABLE IF NOT EXISTS book_categories (
    book_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (book_id, category_id),
    KEY idx_book_categories_category_id (category_id),
    CONSTRAINT fk_book_categories_book
        FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE,
    CONSTRAINT fk_book_categories_category
        FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO book_categories (book_id, category_id)
SELECT id, category_id FROM books;

CREATE TABLE IF NOT EXISTS book_pending_categories (
    book_id INT NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (book_id, category_name),
    CONSTRAINT fk_book_pending_categories_book
        FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
