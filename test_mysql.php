<?php
 $ports = [3306, 3307];
foreach ($ports as $port) {
    try {
        $pdo = new PDO("mysql:host=localhost;port=$port;charset=utf8mb4", "root", "");
        echo "<span style='color:green'>✅ MySQL is running on port $port</span><br>";
        echo "Version: " . $pdo->query("SELECT VERSION()")->fetchColumn() . "<br>";
    } catch (Throwable $e) {
        echo "<span style='color:red'>❌ Port $port: " . $e->getMessage() . "</span><br>";
    }
}
?>