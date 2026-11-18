-- Run after database_checkout_cart_migration.sql to store cancellation reasons.
USE bookstore;

SELECT COUNT(*) INTO @has_cancellation_reason
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'orders'
  AND COLUMN_NAME = 'cancellation_reason';

SET @migration_sql = IF(
    @has_cancellation_reason = 0,
    'ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(100) NULL AFTER payment_method',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
