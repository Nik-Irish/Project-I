-- =============================================================
-- IMS Nepal - 02_foreign_keys.sql
-- Connects related tables. Same-named columns stay in sync.
-- Run AFTER 01_schema.sql. Safe to re-run manually only if the
-- constraint does not exist yet (install.php checks first).
-- =============================================================

ALTER TABLE movements
    ADD CONSTRAINT fk_movements_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE sales
    ADD CONSTRAINT fk_sales_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE sales
    ADD CONSTRAINT fk_sales_sku
    FOREIGN KEY (product_sku) REFERENCES products(product_id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE notifications
    ADD CONSTRAINT fk_notifications_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON DELETE SET NULL ON UPDATE CASCADE;
