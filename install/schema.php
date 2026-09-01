<?php
/**
 * install/schema.php — table definitions and creation.
 * Included by install.php (which defines INSTALL_APP). Do not open directly.
 */

if (!defined('INSTALL_APP')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function tableDefinitions(): array
{
    return [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(15) NOT NULL,
            email VARCHAR(150) NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_username (username),
            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'products' => "CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            product_id VARCHAR(50) NOT NULL COMMENT 'Product ID',
            category VARCHAR(80) NOT NULL DEFAULT 'General',
            price DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Rs.',
            quantity INT NOT NULL DEFAULT 0,
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_products_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'movements' => "CREATE TABLE IF NOT EXISTS movements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id VARCHAR(50) NOT NULL COMMENT 'Product ID code (products.product_id)',
            type ENUM('in','out','sale','adjust') NOT NULL,
            amount INT UNSIGNED NOT NULL,
            balance_after INT NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_movements_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'sales' => "CREATE TABLE IF NOT EXISTS sales (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            bill_no VARCHAR(30) NOT NULL,
            product_id VARCHAR(50) NULL COMMENT 'Product ID code (products.product_id)',
            product_name VARCHAR(150) NOT NULL,
            product_sku VARCHAR(50) NOT NULL COMMENT 'Product ID',
            category VARCHAR(80) NOT NULL DEFAULT 'General',
            quantity INT UNSIGNED NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL COMMENT 'Rs.',
            total DECIMAL(12,2) NOT NULL COMMENT 'Rs.',
            customer_name VARCHAR(120) NOT NULL DEFAULT 'Walk-in Customer',
            customer_phone VARCHAR(40) NULL,
            note VARCHAR(255) NULL,
            sale_date DATE NOT NULL,
            staff_id INT UNSIGNED NULL,
            staff_name VARCHAR(100) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sales_bill_no (bill_no),
            KEY idx_sales_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(40) NOT NULL DEFAULT 'info',
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            product_id VARCHAR(50) NULL COMMENT 'Product ID code (products.product_id)',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notifications_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // one-time OTP codes for password reset (email + login)
        'otps' => "CREATE TABLE IF NOT EXISTS otps (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(150) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_otps_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}

function createTables(PDO $pdo): array
{
    $messages = [];
    foreach (tableDefinitions() as $table => $ddl) {
        $pdo->exec($ddl);
        $messages[] = "Table $table ready.";
    }
    return $messages;
}
