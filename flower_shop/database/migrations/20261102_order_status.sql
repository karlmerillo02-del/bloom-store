UPDATE orders
SET status = 'pending'
WHERE status IS NULL OR status = '';

ALTER TABLE orders
    MODIFY COLUMN status ENUM('pending', 'paid', 'shipped', 'delivered', 'cancelled')
    NOT NULL DEFAULT 'pending';
