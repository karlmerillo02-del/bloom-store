ALTER TABLE orders
    ADD COLUMN customization_details TEXT NULL DEFAULT NULL,
    ADD COLUMN bouquet_message VARCHAR(500) NULL DEFAULT NULL,
    ADD COLUMN delivery_notes TEXT NULL DEFAULT NULL;
