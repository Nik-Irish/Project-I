<?php
/**
 * config/db.php — Database connection
 * Included by dashboard.php and any other file needing $pdo.
 */

$dbHost = 'localhost';
$dbPort = 3306;
$dbUser = 'root';
$dbPass = '';
$dbName = 'ims';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("USE `$dbName`");
} catch (PDOException $e) {
    die(
        '<div style="text-align:center;padding:3rem;color:#fca5a5;'
        . 'font-family:sans-serif;background:#0f172a;min-height:100vh">'
        . '<h2>Database connection failed</h2>'
        . '<p style="margin:.75rem 0;color:#94a3b8">'
        . htmlspecialchars($e->getMessage())
        . '</p>'
        . '<a href="install.php" style="color:#38bdf8">Run install.php first</a>'
        . '</div>'
    );
}