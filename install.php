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
            product_id VARCHAR(50) NOT NULL COMMENT 'Product ID',
            category VARCHAR(80) NOT NULL DEFAULT 'General',
            price DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Rs.',
            quantity INT NOT NULL DEFAULT 0,
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_products_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table products ready.';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS movements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id VARCHAR(50) NOT NULL COMMENT 'Product ID code (products.product_id)',
            type ENUM('in','out','sale','adjust') NOT NULL,
            amount INT UNSIGNED NOT NULL,
            balance_after INT NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_movements_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table movements ready.';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sales (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = 'Table sales ready.';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(40) NOT NULL DEFAULT 'info',
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            product_id VARCHAR(50) NULL COMMENT 'Product ID code (products.product_id)',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notifications_product (product_id)
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

    if ($pdo->query("SHOW COLUMNS FROM products LIKE 'sku'")->fetch()) {
        $pdo->exec("ALTER TABLE products DROP COLUMN sku");
        $messages[] = 'Removed sku column from products.';
    }
    // add product_id column needed by older installs, if missing
    if (!$pdo->query("SHOW COLUMNS FROM products LIKE 'product_id'")->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN product_id VARCHAR(50) NOT NULL AFTER name");
        // give existing rows a unique placeholder Product-ID so the UNIQUE key can be added
        $pdo->exec("UPDATE products SET product_id = CONCAT('P', id) WHERE product_id = ''");
        $messages[] = 'Added product_id column to products.';
    }

    if (!$pdo->query("SHOW INDEX FROM products WHERE Key_name = 'uq_products_product_id'")->fetch()) {
        $pdo->exec("ALTER TABLE products ADD UNIQUE KEY uq_products_product_id (product_id)");
        $messages[] = 'Added unique key on products.product_id.';
    }

    // add product_sku column to sales needed by older installs, if missing
    if (!$pdo->query("SHOW COLUMNS FROM sales LIKE 'product_sku'")->fetch()) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN product_sku VARCHAR(50) NOT NULL COMMENT 'Product ID' AFTER product_name");
        // backfill existing rows from the product's current Product-ID where possible
        $pdo->exec("UPDATE sales s LEFT JOIN products p ON s.product_id = p.id SET s.product_sku = COALESCE(p.product_id, '') WHERE s.product_sku = ''");
        $messages[] = 'Added product_sku column to sales.';
    }

    // convert numeric product references to the Product-ID code on older installs
    foreach (['movements', 'sales', 'notifications'] as $tbl) {
        $colType = $pdo->query(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl' AND COLUMN_NAME = 'product_id'"
        )->fetchColumn();
        if (!$colType || $colType === 'varchar' || $colType === 'char') continue;

        $fkName = $pdo->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl'
               AND COLUMN_NAME = 'product_id' AND REFERENCED_TABLE_NAME = 'products'"
        )->fetchColumn();
        if ($fkName) {
            $pdo->exec("ALTER TABLE `$tbl` DROP FOREIGN KEY `$fkName`");
        }
        $pdo->exec("UPDATE `$tbl` t JOIN products p ON t.product_id = p.id SET t.product_id = p.product_id");
        $nullability = ($tbl === 'movements') ? 'NOT NULL' : 'NULL';
        $pdo->exec("ALTER TABLE `$tbl` MODIFY product_id VARCHAR(50) $nullability");
        $messages[] = "Converted $tbl.product_id to store Product-ID codes.";
    }

    // connect related tables with foreign keys (same-named columns stay in sync)
    $fks = [
        ['movements', 'fk_movements_product',
            'ALTER TABLE movements ADD CONSTRAINT fk_movements_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT ON UPDATE CASCADE'],
        ['sales', 'fk_sales_product',
            'ALTER TABLE sales ADD CONSTRAINT fk_sales_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT ON UPDATE CASCADE'],
        ['sales', 'fk_sales_sku',
            'ALTER TABLE sales ADD CONSTRAINT fk_sales_sku FOREIGN KEY (product_sku) REFERENCES products(product_id) ON DELETE RESTRICT ON UPDATE CASCADE'],
        ['notifications', 'fk_notifications_product',
            'ALTER TABLE notifications ADD CONSTRAINT fk_notifications_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL ON UPDATE CASCADE'],
        ['sales', 'fk_sales_staff',
            'ALTER TABLE sales ADD CONSTRAINT fk_sales_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'],
    ];
    foreach ($fks as [$tbl, $fkName, $fkSql]) {
        $exists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl' AND CONSTRAINT_NAME = '$fkName'"
        )->fetchColumn();
        if (!$exists) {
            $pdo->exec($fkSql);
            $messages[] = "Added foreign key $fkName.";
        }
    }

    // create or reset the default admin account

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