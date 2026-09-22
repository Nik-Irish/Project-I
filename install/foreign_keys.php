<?php
/**
 * install/foreign_keys.php - connects related tables (same-named columns
 * stay in sync). The statements live in sql/02_foreign_keys.sql; this file
 * only checks and executes them. Included by install.php (which defines
 * INSTALL_APP). Do not open directly.
 */

if (!defined('INSTALL_APP')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function addForeignKeys(PDO $pdo): array
{
    $messages = [];
    foreach (sqlStatements('02_foreign_keys.sql') as $statement) {
        if (!preg_match('/ALTER TABLE\s+(\w+)\s+ADD CONSTRAINT\s+(\w+)/', $statement, $m)) {
            continue;
        }
        [$table, $name] = [$m[1], $m[2]];
        if (constraintExists($pdo, $table, $name)) {
            continue;
        }
        $pdo->exec($statement);
        $messages[] = "Added foreign key $name.";
    }
    return $messages;
}

