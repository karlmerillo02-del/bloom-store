ALTER TABLE products
    ADD COLUMN is_ready_bouquet TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN sale_price DECIMAL(10,2) NULL DEFAULT NULL,
    ADD COLUMN sale_starts_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN sale_ends_at DATETIME NULL DEFAULT NULL;

ALTER TABLE users
    ADD COLUMN bio VARCHAR(500) NULL DEFAULT NULL;

CREATE TABLE app_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO app_settings (setting_key, setting_value) VALUES
    ('store_name', 'Bloom Flower Shop'),
    ('store_tagline', 'Fresh flowers, thoughtfully chosen'),
    ('contact_email', ''),
    ('contact_phone', ''),
    ('store_location', 'Ilocos')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

UPDATE products
SET category = CASE
    WHEN LOWER(name) LIKE '%rose%' THEN 'Valentine''s Day'
    WHEN LOWER(name) LIKE '%chrysanthemum%' THEN 'Sympathy & Funeral'
    WHEN LOWER(name) LIKE '%lily%' THEN 'Sympathy & Funeral'
    WHEN LOWER(name) LIKE '%sunflower%' THEN 'Birthday'
    WHEN LOWER(name) LIKE '%tulip%' THEN 'Anniversary'
    WHEN LOWER(name) LIKE '%daffodil%' THEN 'Get Well Soon'
    WHEN LOWER(name) LIKE '%orchid%' THEN 'Anniversary'
    WHEN LOWER(name) LIKE '%daisy%' THEN 'Birthday'
    ELSE 'Just Because'
END;
