<?php
/**
 * install/foreign_keys.php — connects related tables (same-named columns stay in sync).
 * Included by install.php (which defines INSTALL_APP). Do not open directly.
 */

if (!defined('INSTALL_APP')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function fkDefinitions(): array
{
    // [table, constraint, column, referenced, ON DELETE]
    return [
        ['movements',     'fk_movements_product',     'product_id',  'products(product_id)', 'RESTRICT'],
        ['sales',         'fk_sales_product',         'product_id',  'products(product_id)', 'RESTRICT'],
        ['sales',         'fk_sales_sku',             'product_sku', 'products(product_id)', 'RESTRICT'],
        ['notifications', 'fk_notifications_product', 'product_id',  'products(product_id)', 'SET NULL'],
    ];
}

function addForeignKeys(PDO $pdo): array
{
    $messages = [];
    foreach (fkDefinitions() as [$table, $name, $column, $ref, $onDelete]) {
        if (constraintExists($pdo, $table, $name)) continue;
        $pdo->exec(
            "ALTER TABLE `$table` ADD CONSTRAINT `$name`
             FOREIGN KEY (`$column`) REFERENCES $ref
             ON DELETE $onDelete ON UPDATE CASCADE"
        );
        $messages[] = "Added foreign key $name.";
    }
    return $messages;
}
