<?php
/**
 * One-time installer: creates database `ims`, tables, and default admin user.
 * Open in browser once after starting XAMPP MySQL: http://localhost/Project%20I/install.php
 * (or wherever this folder is served from)
 */

$host = 'localhost';
$port = 3306; // XAMPP default MySQL port
$user = 'root';
$pass = '';
$dbname = 'ims';

$messages = [];
$ok = true;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    $messages[] = "Database `$dbname` ready.";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(15) NOT NULL,
          `password_hash` VARCHAR(255) NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_users_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table `users` ready.';

    $pdo->exec("
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
          UNIQUE KEY `uq_products_sku` (`sku`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table `products` ready.';

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
          CONSTRAINT `fk_movements_product`
            FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table `movements` ready.';

    $pdo->exec("
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
          CONSTRAINT `fk_sales_product`
            FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table `sales` ready.';

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
          CONSTRAINT `fk_notifications_product`
            FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table `notifications` ready.';

    // Default admin: admin / Password123!
    $hash = password_hash('Password123!', PASSWORD_DEFAULT);
    $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $check->execute(['admin']);
    if (!$check->fetch()) {
        $ins = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $ins->execute(['admin', $hash]);
        $messages[] = 'Default user created: admin / Password123!';
    } else {
        // Reset admin password so login always works after reinstall
        $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
        $upd->execute([$hash, 'admin']);
        $messages[] = 'Admin password reset to: Password123!';
    }
} catch (Throwable $e) {
    $ok = false;
    $messages[] = 'ERROR: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install IMS Database</title>
    <style>
        body { font-family: Segoe UI, sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
        .box { max-width: 560px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 1.75rem; }
        h1 { margin: 0 0 1rem; font-size: 1.4rem; }
        li { margin: 0.4rem 0; color: #94a3b8; }
        .ok { color: #86efac; }
        .err { color: #fca5a5; }
        a { color: #38bdf8; }
    </style>
</head>
<body>
    <div class="box">
        <h1 class="<?php echo $ok ? 'ok' : 'err'; ?>">
            <?php echo $ok ? 'Installation complete' : 'Installation failed'; ?>
        </h1>
        <ul>
            <?php foreach ($messages as $m): ?>
                <li><?php echo htmlspecialchars($m); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($ok): ?>
            <p style="margin-top:1.25rem;">
                <a href="login.php">Go to Login</a> ·
                <a href="dashboard.php">Go to Dashboard</a>
            </p>
            <p style="font-size:.85rem;color:#64748b;margin-top:1rem;">
                You can delete or protect <code>install.php</code> after setup.
            </p>
        <?php else: ?>
            <p style="margin-top:1rem;color:#fca5a5;">
                Start MySQL in XAMPP Control Panel, then refresh this page.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
