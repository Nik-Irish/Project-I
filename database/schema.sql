-- Product Manager / IMS schema for XAMPP MySQL
-- Database: ims

CREATE DATABASE IF NOT EXISTS `ims` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ims`;

-- Users (login / register / forgot password)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(15) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products / parts inventory
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'General',
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 0,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_category` (`category`),
  KEY `idx_products_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock movements (in / out / sale / adjust)
CREATE TABLE IF NOT EXISTS `movements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `type` ENUM('in','out','sale','adjust') NOT NULL,
  `amount` INT UNSIGNED NOT NULL,
  `balance_after` INT NOT NULL,
  `note` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_movements_product` (`product_id`),
  KEY `idx_movements_type` (`type`),
  CONSTRAINT `fk_movements_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales / bills
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_no` VARCHAR(30) NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'General',
  `quantity` INT UNSIGNED NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `total` DECIMAL(12,2) NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL DEFAULT 'Walk-in Customer',
  `customer_phone` VARCHAR(40) NULL,
  `note` VARCHAR(255) NULL,
  `sale_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_bill_no` (`bill_no`),
  KEY `idx_sales_product` (`product_id`),
  KEY `idx_sales_date` (`sale_date`),
  CONSTRAINT `fk_sales_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(40) NOT NULL DEFAULT 'info',
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_read` (`is_read`),
  KEY `idx_notifications_type` (`type`),
  CONSTRAINT `fk_notifications_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: username=admin password=Password123!
INSERT INTO `users` (`username`, `password_hash`)
SELECT 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin');
-- NOTE: the hash above is Laravel's "password" demo hash — install.php sets the real Password123! hash.
