<?php
/**
 * Shared helper utilities (no database).
 */

define('LOW_STOCK_THRESHOLD', 10);
define('PRODUCT_COUNT_ALERT', 10);

function shortText(string $text, int $max = 48): string
{
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, $max - 1) . '…';
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function findById(array $rows, int $id): ?array
{
    foreach ($rows as $row) {
        if ((int)($row['id'] ?? 0) === $id) {
            return $row;
        }
    }
    return null;
}

function findIndex(array $rows, int $id): int|false
{
    foreach ($rows as $i => $r) {
        if ((int)($r['id'] ?? 0) === $id) {
            return $i;
        }
    }
    return false;
}

function makeBillNo(int $saleId): string
{
    return 'INV-' . str_pad((string)$saleId, 5, '0', STR_PAD_LEFT);
}

function pageMeta(string $view): array
{
    $titles = [
        'list'          => 'Products',
        'add'           => 'Add Product',
        'edit'          => 'Modify Product',
        'sales'         => 'Sales Report',
        'sale_add'      => 'Record Sale',
        'inventory'     => 'Inventory Details',
        'report'        => 'Sales Summary',
        'notifications' => 'System Notifications',
        'bill'          => 'Sales Bill / Invoice',
    ];
    $subs = [
        'list'          => 'Input, output stock, and modify product records',
        'add'           => 'Add a new product or part to inventory',
        'edit'          => 'Update product details',
        'sales'         => 'Filter and review all sales transactions',
        'sale_add'      => 'Record a sale, generate bill, and download PDF',
        'inventory'     => 'Detailed stock and movement history for one part',
        'report'        => 'Sales totals by product and by day',
        'notifications' => 'Alerts when stock reaches 10 or below, and system milestones',
        'bill'          => 'View invoice and download as PDF',
    ];

    return [
        'title' => $titles[$view] ?? 'Dashboard',
        'sub'   => $subs[$view] ?? '',
    ];
}
