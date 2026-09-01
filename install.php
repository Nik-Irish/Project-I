<?php
/**
 * install.php — Run once to create the database, tables, foreign keys,
 * and the default admin. Redirects to login.php on success.
 *
 * Parts live in the install/ folder:
 *   helpers.php      — connection + schema-check helpers
 *   schema.php       — table definitions + createTables()
 *   migrations.php   — upgrades for older installs
 *   foreign_keys.php — connects related tables
 *   admin.php        — default admin account
 */

define('INSTALL_APP', true);

require __DIR__ . '/install/helpers.php';
require __DIR__ . '/install/schema.php';
require __DIR__ . '/install/migrations.php';
require __DIR__ . '/install/foreign_keys.php';
require __DIR__ . '/install/admin.php';

// --- configuration --------------------------------------------------------

$host = 'localhost';
$port = 3306;
$user = 'root';
$pass = '';
$dbname = 'ims';
$adminEmail = 'nikrishdulal01@gmail.com';

// --- run installation ------------------------------------------------------

$messages = [];
$ok = true;

try {
    $pdo = dbConnect($host, $port, $user, $pass);
    $messages[] = ensureDatabase($pdo, $dbname);
    $messages = array_merge(
        $messages,
        createTables($pdo),
        migrateSchema($pdo),
        addForeignKeys($pdo),
        ensureAdmin($pdo, $adminEmail)
    );
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
