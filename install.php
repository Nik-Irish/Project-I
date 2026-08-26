<?php
// Run once to create the database, tables, and default admin.
// Redirects to login.php on success.

$host = 'localhost';
$port = 3306;
$user = 'root';
$pass = '';
$dbname = 'ims';
$adminEmail = 'nikrishdulal01@gmail.com';

$messages = [];
$ok = true;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    $messages[] = "Database `$dbname` ready.";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table users ready.';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            sku VARCHAR(50) NOT NULL COMMENT 'Product ID',
            category VARCHAR(80) NOT NULL DEFAULT 'General',
            price DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Rs.',
            quantity INT NOT NULL DEFAULT 0,
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_products_sku (sku)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table products ready.';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS movements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id INT UNSIGNED NOT NULL,
            type ENUM('in','out','sale','adjust') NOT NULL,
            amount INT UNSIGNED NOT NULL,
            balance_after INT NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_movements_product (product_id),
            CONSTRAINT fk_movements_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table movements ready.';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sales (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            bill_no VARCHAR(30) NOT NULL,
            product_id INT UNSIGNED NULL,
            product_name VARCHAR(150) NOT NULL,
            sku VARCHAR(50) NOT NULL COMMENT 'Product ID',
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
            KEY idx_sales_product (product_id),
            CONSTRAINT fk_sales_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table sales ready.';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(40) NOT NULL DEFAULT 'info',
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            product_id INT UNSIGNED NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_notifications_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table notifications ready.';

    // one-time OTP codes for password reset (email + login)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS otps (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(150) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_otps_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table otps ready.';

    // add columns needed by older installs, if missing
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','staff') NOT NULL DEFAULT 'staff' AFTER password_hash");
        $messages[] = 'Added role column to users.';
    }

    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'email'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL AFTER username, ADD UNIQUE KEY uq_users_email (email)");
        $messages[] = 'Added email column to users.';
    }

    if (!$pdo->query("SHOW COLUMNS FROM sales LIKE 'staff_id'")->fetch()) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN staff_id INT UNSIGNED NULL AFTER sale_date, ADD COLUMN staff_name VARCHAR(100) NULL AFTER staff_id");
        $messages[] = 'Added staff_id and staff_name to sales.';
    }

    // create or reset the default admin account
    $adminHash = password_hash('Password123!', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username=?');
    $stmt->execute(['admin']);

    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE users SET password_hash=?, role='admin', email=? WHERE username='admin'")->execute([$adminHash, $adminEmail]);
        $messages[] = 'Admin password reset to Password123!';
    } else {
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES ('admin', ?, ?, 'admin')")->execute([$adminEmail, $adminHash]);
        $messages[] = 'Admin created: admin / Password123!';
    }
    $messages[] = "Admin email set to $adminEmail.";
} catch (Throwable $e) {
    $ok = false;
    $messages[] = 'ERROR: ' . $e->getMessage();
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
</head>
<body>
<div class="box">
    <h1>Installation Failed</h1>
    <ul>
        <?php foreach ($messages as $m): ?>
            <li><?php echo htmlspecialchars($m); ?></li>
        <?php endforeach; ?>
    </ul>
    <a class="retry" href="install.php">Try Again</a>
    <p class="hint">
        Make sure MySQL is running on port <?php echo $port; ?>.<br>
        Default admin credentials: <strong>admin</strong> / Password123!<br>
        Staff accounts can be created from the login page.
    </p>
</div>
</body>
</html>