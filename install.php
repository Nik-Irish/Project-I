<?php

/**
 * IMS Nepal — Installer
 * Creates database, tables, admin + staff default users.
 * Success → instant redirect to login.php
 */

$host = 'localhost';
$port = 3306;
$user = 'root';
$pass = '';
$dbname = 'ims';
$messages = [];
$ok = true;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    $messages[] = "✅ Database `$dbname` ready.";

    // ── USERS (with role) ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(15) NOT NULL,
          `password_hash` VARCHAR(255) NOT NULL,
          `role` ENUM('admin','staff') NOT NULL DEFAULT 'staff',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_users_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = '✅ Table `users` ready (with admin/staff roles).';
    // ── Add role column if missing (from older installations) ──
    $colCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `role` ENUM('admin','staff') NOT NULL DEFAULT 'staff' AFTER `password_hash`");
        $messages[] = '✅ Added `role` column to existing `users` table.';
    } else {
        $messages[] = '✅ `role` column already exists.';
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `products` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(150) NOT NULL,
          `sku` VARCHAR(50) NOT NULL COMMENT 'Product ID',
          `category` VARCHAR(80) NOT NULL DEFAULT 'General',
          `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Rs.',
          `quantity` INT NOT NULL DEFAULT 0,
          `description` TEXT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_products_sku` (`sku`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = '✅ Table `products` ready.';

    $pdo->exec("
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
          CONSTRAINT `fk_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = '✅ Table `movements` ready.';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sales` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `bill_no` VARCHAR(30) NOT NULL,
          `product_id` INT UNSIGNED NULL,
          `product_name` VARCHAR(150) NOT NULL,
          `sku` VARCHAR(50) NOT NULL COMMENT 'Product ID',
          `category` VARCHAR(80) NOT NULL DEFAULT 'General',
          `quantity` INT UNSIGNED NOT NULL,
          `unit_price` DECIMAL(12,2) NOT NULL COMMENT 'Rs.',
          `total` DECIMAL(12,2) NOT NULL COMMENT 'Rs.',
          `customer_name` VARCHAR(120) NOT NULL DEFAULT 'Walk-in Customer',
          `customer_phone` VARCHAR(40) NULL,
          `note` VARCHAR(255) NULL,
          `sale_date` DATE NOT NULL,
          `staff_id` INT UNSIGNED NULL,
          `staff_name` VARCHAR(100) NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_sales_bill_no` (`bill_no`),
          KEY `idx_sales_product` (`product_id`),
          CONSTRAINT `fk_sales_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // ── Add staff columns if missing (from older installations) ──
    $colCheck = $pdo->query("SHOW COLUMNS FROM `sales` LIKE 'staff_id'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE `sales` ADD COLUMN `staff_id` INT UNSIGNED NULL AFTER `sale_date`, ADD COLUMN `staff_name` VARCHAR(100) NULL AFTER `staff_id`");
        $messages[] = '✅ Added `staff_id` and `staff_name` columns to `sales` table.';
    } else {
        $messages[] = '✅ `staff_id` and `staff_name` columns already exist in `sales`.';
    }
    $messages[] = '✅ Table `sales` ready.';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notifications` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `type` VARCHAR(40) NOT NULL DEFAULT 'info',
          `title` VARCHAR(150) NOT NULL,
          `message` TEXT NOT NULL,
          `product_id` INT UNSIGNED NULL,
          `is_read` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          CONSTRAINT `fk_notifications_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = '✅ Table `notifications` ready.';

    // ── Default ADMIN ──
    $adminHash = password_hash('Password123!', PASSWORD_DEFAULT);
    $check = $pdo->prepare('SELECT id,role FROM users WHERE username=?');
    $check->execute(['admin']);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        $pdo->prepare('INSERT INTO users (username,password_hash,role) VALUES (?,?,?)')->execute(['admin', $adminHash, 'admin']);
        $messages[] = '✅ Admin: admin / Password123!';
    } else {
        $pdo->prepare('UPDATE users SET password_hash=?,role=? WHERE username=?')->execute([$adminHash, 'admin', 'admin']);
        $messages[] = '✅ Admin password reset.';
    }

} catch (Throwable $e) {
    $ok = false;
    $messages[] = '❌ ERROR: ' . $e->getMessage();
}

if ($ok) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>IMS Nepal — Installation Failed</title>
    <style>
        body {
            font-family: "Segoe UI", system-ui, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 2rem
        }

        .box {
            max-width: 560px;
            margin: 0 auto;
            background: rgba(30, 41, 59, .95);
            border: 1px solid rgba(148, 163, 184, .15);
            border-radius: 12px;
            padding: 1.75rem
        }

        h1 {
            margin: 0 0 1rem;
            font-size: 1.3rem;
            color: #fca5a5
        }

        li {
            margin: .4rem 0;
            color: #94a3b8;
            font-size: .85rem
        }

        a {
            color: #38bdf8
        }

        .hint {
            color: #64748b;
            font-size: .8rem;
            margin-top: 1rem;
            line-height: 1.4
        }
    </style>
</head>

<body>
    <div class="box">
        <h1>❌ Installation Failed</h1>
        <ul><?php foreach ($messages as $m): ?><li><?php echo htmlspecialchars($m); ?></li><?php endforeach; ?></ul>
        <p style="margin-top:1rem;color:#fca5a5">Make sure MySQL is running, then <a href="install.php">try again</a>.</p>
        <p class="hint">Run <a href="test_mysql.php">test_mysql.php</a> to check port.<br>Admin: <strong>admin</strong>/Password123! · Staff: register at login page</p>
    </div>
</body>

</html>