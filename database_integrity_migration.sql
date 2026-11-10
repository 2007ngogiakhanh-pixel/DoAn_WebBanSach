-- Run in phpMyAdmin after selecting the bookstore database.
-- Safe to run more than once: existing constraints are left unchanged.
USE bookstore;

-- Keep seller books valid while allowing a seller account to be deleted.
SELECT COUNT(*) INTO @has_books_seller_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'books'
  AND COLUMN_NAME = 'seller_id'
  AND REFERENCED_TABLE_NAME = 'users'
  AND REFERENCED_COLUMN_NAME = 'id';

SET @migration_sql = IF(
    @has_books_seller_fk = 0,
    'ALTER TABLE books ADD CONSTRAINT fk_books_seller FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Preserve orders when a customer account is deleted.
SELECT COUNT(*) INTO @has_orders_customer_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'orders'
  AND COLUMN_NAME = 'customer_id'
  AND REFERENCED_TABLE_NAME = 'users'
  AND REFERENCED_COLUMN_NAME = 'id';

SET @migration_sql = IF(
    @has_orders_customer_fk = 0,
    'ALTER TABLE orders ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- The reviews migration already defines this constraint; add it only if absent.
SELECT COUNT(*) INTO @has_review_rating_check
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'book_reviews'
  AND CONSTRAINT_NAME = 'chk_book_reviews_rating'
  AND CONSTRAINT_TYPE = 'CHECK';

SET @migration_sql = IF(
    @has_review_rating_check = 0,
    'ALTER TABLE book_reviews ADD CONSTRAINT chk_book_reviews_rating CHECK (rating BETWEEN 1 AND 5)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
