<?php
/**
 * install/schema.php - table creation. The DDL lives in sql/01_schema.sql;
 * this file only executes it. Included by install.php (which defines
 * INSTALL_APP). Do not open directly.
 */

if (!defined('INSTALL_APP')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function createTables(PDO $pdo): array
{
    $messages = [];
    foreach (sqlStatements('01_schema.sql') as $statement) {
        $pdo->exec($statement);
        if (preg_match('/CREATE TABLE IF NOT EXISTS\s+(\w+)/', $statement, $m)) {
            $messages[] = "Table {$m[1]} ready.";
        }
    }
    return $messages;
}

