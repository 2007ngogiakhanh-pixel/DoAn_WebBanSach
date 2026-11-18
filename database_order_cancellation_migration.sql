-- Run after database_checkout_cart_migration.sql to store cancellation reasons.
USE bookstore;

SELECT COALESCE(MAX(CHARACTER_MAXIMUM_LENGTH), 0) INTO @cancellation_reason_length
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'orders'
  AND COLUMN_NAME = 'cancellation_reason';

SET @migration_sql = IF(
    @cancellation_reason_length = 0,
    'ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER payment_method',
    IF(
        @cancellation_reason_length < 255,
        'ALTER TABLE orders MODIFY COLUMN cancellation_reason VARCHAR(255) NULL',
        'SELECT 1'
    )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
