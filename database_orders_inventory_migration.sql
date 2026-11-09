-- Run once in phpMyAdmin after selecting the existing bookstore database.
USE bookstore;

ALTER TABLE books
    ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1 AFTER approval_status;

ALTER TABLE orders
    ADD COLUMN order_code CHAR(10) NULL AFTER id;

UPDATE orders
SET order_code = LPAD(1000000000 + MOD(CAST(id AS UNSIGNED) * 73856093, 9000000000), 10, '0')
WHERE order_code IS NULL;

ALTER TABLE orders
    MODIFY COLUMN order_code CHAR(10) NOT NULL,
    ADD UNIQUE KEY uq_orders_order_code (order_code);
