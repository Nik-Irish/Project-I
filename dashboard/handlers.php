<?php
/**
 * dashboard/handlers.php — POST action dispatch + post-action refresh.
 * Extracted from dashboard.php. Do not open directly — included by dashboard.php.
 *
 * Helper functions used: getProduct(), getSale(), getStaff(), loadLists()
 *
 * Context variables provided by dashboard.php:
 *
 * @var PDO         $pdo           Database connection (config/db.php)
 * @var string      $view          Active view slug (dashboard/bootstrap.php)
 * @var array|null  $detailProduct Product shown on the inventory detail page
 * @var array|null  $billSale      Sale shown on the bill page
 * @var array|null  $editStaff     Staff account being edited
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    require_once __DIR__ . '/actions/products.php';       // add, update, stock_in/out, delete
    require_once __DIR__ . '/actions/sales.php';          // sale, delete_sale
    require_once __DIR__ . '/actions/notifications.php';  // mark_read, mark_all_read, delete/clear
    require_once __DIR__ . '/actions/staff.php';          // staff_create/update/password_update/delete

    // reload lists after any action
    [$products, $sales, $notifications, $staffUsers] = loadLists($pdo);

    if ($view === 'inventory' && $detailProduct) {
        $detailProduct = getProduct($pdo, (int)$detailProduct['id']);
        if (!$detailProduct) {
            $view = 'list';
            $errorMessage = $errorMessage ?: 'Product not found.';
        }
    }

    if ($view === 'bill' && $billSale) {
        $fresh = getSale($pdo, (int)$billSale['id']);
        if ($fresh) {
            $fresh['_subtotal'] = $billSale['_subtotal'] ?? 0;
            $fresh['_tax'] = $billSale['_tax'] ?? 0;
            $fresh['_total'] = $billSale['_total'] ?? 0;
            $billSale = $fresh;
        }
    }

    if ($view === 'staff' && isset($_GET['edit']) && $editStaff !== null) {
        $editStaff = getStaff($pdo, (int)$_GET['edit']);
    }
}