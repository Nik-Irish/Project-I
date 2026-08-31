<?php
/**
 * config/connector.php — Database connection
 * Included by dashboard.php and any other file needing $pdo.
 */

$dbHost = 'localhost';
$dbPort = 3306;
$dbUser = 'root';
$dbPass = '';
$dbName = 'ims';

$pdo = new PDO(
    "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4",
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("USE `$dbName`");
