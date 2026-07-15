<?php
$host = "localhost";
$user = "root";
$password = ""; // XAMPP default is blank
$dbname = "ims";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully to XAMPP database!";
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
