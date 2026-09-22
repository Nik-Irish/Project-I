<?php
/**
 * dashboard/stats.php - Dashboard stats, alert-banner data, and page titles.
 * Extracted from dashboard.php. Do not open directly - included by dashboard.php.
 *
 * Context variables provided by dashboard.php:
 *
 * @var PDO    $pdo           Database connection (config/db.php)
 * @var string $view          Active view slug (dashboard/bootstrap.php)
 * @var array  $notifications All notifications (dashboard/bootstrap.php)
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// dashboard stats
$statsRow = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(quantity), 0) AS total_stock, COALESCE(SUM(price * quantity), 0) AS total_value, SUM(CASE WHEN quantity <= " . LOW_STOCK_THRESHOLD . " THEN 1 ELSE 0 END) AS low_cnt FROM products")->fetch(PDO::FETCH_ASSOC);

$totalProducts = (int)$statsRow['cnt'];
$totalStock = (int)$statsRow['total_stock'];
$totalValue = (float)$statsRow['total_value'];
$lowStockCount = (int)$statsRow['low_cnt'];

$unreadNotifications = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read=0")->fetchColumn();
$sortedNotifications = $notifications;
$bannerNotes = $pdo->query("SELECT * FROM notifications WHERE is_read=0 ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

// alerts (bell + banner) only on views where they are actionable
$showAlerts = in_array($view, ['list', 'inventory', 'notifications'], true);

$pageTitles = [
    'list' => 'Dashboard', 'add' => 'Add Product', 'edit' => 'Modify Product',
    'sales' => 'Sales Report', 'sale_add' => 'Record Sale', 'inventory' => 'Inventory Details',
    'report' => 'Sales Summary', 'notifications' => 'Alerts', 'staff' => 'Manage Staff',
];

$pageSub = [
    'list' => 'Stock counts, inventory value, and low-stock warnings on one screen.',
    'add' => 'Five fields. Your product is trackable the moment you save.',
    'edit' => 'Change price or quantity. Past sales and stock history stay untouched.',
    'sales' => 'Every sale with bill number and PDF invoice.',
    'sale_add' => 'Stock and revenue update the moment you save.',
    'inventory' => 'Full movement and sales history for this Product-ID.',
    'report' => 'Totals by product, by staff, and by day.',
    'notifications' => 'Low-stock warnings, newest first.',
    'staff' => 'Add staff, reset passwords, delete accounts.',
];

$pageTitle = $pageTitles[$view] ?? 'Dashboard';