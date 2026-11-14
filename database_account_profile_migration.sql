-- Run after database_checkout_cart_migration.sql to add account profile fields.
USE bookstore;

SELECT COUNT(*) INTO @has_gender
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'gender';
SET @migration_sql = IF(
    @has_gender = 0,
    "ALTER TABLE users ADD COLUMN gender ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL AFTER email",
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SELECT COUNT(*) INTO @has_bank_name
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'bank_name';
SET @migration_sql = IF(
    @has_bank_name = 0,
    'ALTER TABLE users ADD COLUMN bank_name VARCHAR(100) NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SELECT COUNT(*) INTO @has_bank_account_name
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'bank_account_name';
SET @migration_sql = IF(
    @has_bank_account_name = 0,
    'ALTER TABLE users ADD COLUMN bank_account_name VARCHAR(150) NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SELECT COUNT(*) INTO @has_bank_account_number
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'bank_account_number';
SET @migration_sql = IF(
    @has_bank_account_number = 0,
    'ALTER TABLE users ADD COLUMN bank_account_number VARCHAR(50) NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
