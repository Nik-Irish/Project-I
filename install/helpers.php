<?php
/**
 * install/helpers.php — shared helpers for the installer.
 * Included by install.php (which defines INSTALL_APP). Do not open directly.
 */

if (!defined('INSTALL_APP')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function dbConnect(string $host, int $port, string $user, string $pass): PDO
{
    return new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function ensureDatabase(PDO $pdo, string $name): string
{
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$name`");
    return "Database `$name` ready.";
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    return (bool)$pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    return (bool)$pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'")->fetch();
}

function constraintExists(PDO $pdo, string $table, string $constraint): bool
{
    return (bool)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND CONSTRAINT_NAME = '$constraint'"
    )->fetchColumn();
}
