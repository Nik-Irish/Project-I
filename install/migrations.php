<?php
/**
 * install/migrations.php — schema upgrades for older installs.
 * Included by install.php (which defines INSTALL_APP). Do not open directly.
 */

if (!defined('INSTALL_APP')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function migrateSchema(PDO $pdo): array
{
    $messages = [];

    // add columns needed by older installs, if missing
    if (!columnExists($pdo, 'users', 'role')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','staff') NOT NULL DEFAULT 'staff' AFTER password_hash");
        $messages[] = 'Added role column to users.';
    }
    if (!columnExists($pdo, 'users', 'email')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL AFTER username, ADD UNIQUE KEY uq_users_email (email)");
        $messages[] = 'Added email column to users.';
    }
    if (!columnExists($pdo, 'sales', 'staff_name')) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN staff_name VARCHAR(100) NULL AFTER sale_date");
        $messages[] = 'Added staff_name to sales.';
    }
    if (columnExists($pdo, 'products', 'sku')) {
        $pdo->exec("ALTER TABLE products DROP COLUMN sku");
        $messages[] = 'Removed sku column from products.';
    }
    // give existing rows a unique placeholder Product-ID so the UNIQUE key can be added
    if (!columnExists($pdo, 'products', 'product_id')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN product_id VARCHAR(50) NOT NULL AFTER name");
        $pdo->exec("UPDATE products SET product_id = CONCAT('P', id) WHERE product_id = ''");
        $messages[] = 'Added product_id column to products.';
    }
    if (!indexExists($pdo, 'products', 'uq_products_product_id')) {
        $pdo->exec("ALTER TABLE products ADD UNIQUE KEY uq_products_product_id (product_id)");
        $messages[] = 'Added unique key on products.product_id.';
    }
    // backfill existing rows from the product's current Product-ID where possible
    if (!columnExists($pdo, 'sales', 'product_sku')) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN product_sku VARCHAR(50) NOT NULL COMMENT 'Product ID' AFTER product_name");
        $pdo->exec("UPDATE sales s LEFT JOIN products p ON s.product_id = p.id SET s.product_sku = COALESCE(p.product_id, '') WHERE s.product_sku = ''");
        $messages[] = 'Added product_sku column to sales.';
    }

    // older installs: convert numeric product references to Product-ID codes
    foreach (['movements', 'sales', 'notifications'] as $table) {
        $colType = $pdo->query(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = 'product_id'"
        )->fetchColumn();
        if (!$colType || $colType === 'varchar' || $colType === 'char') continue;

        $oldFk = $pdo->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'
               AND COLUMN_NAME = 'product_id' AND REFERENCED_TABLE_NAME = 'products'"
        )->fetchColumn();
        if ($oldFk) {
            $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$oldFk`");
        }
        $pdo->exec("UPDATE `$table` t JOIN products p ON t.product_id = p.id SET t.product_id = p.product_id");
        $nullability = ($table === 'movements') ? 'NOT NULL' : 'NULL';
        $pdo->exec("ALTER TABLE `$table` MODIFY product_id VARCHAR(50) $nullability");
        $messages[] = "Converted $table.product_id to store Product-ID codes.";
    }

    return $messages;
}
