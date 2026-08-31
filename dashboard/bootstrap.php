<?php
/**
 * dashboard/bootstrap.php — Request state & GET routing.
 * Extracted from dashboard.php. Do not open directly — included by dashboard.php.
 *
 * Helper functions used: downloadInvoicePdf(), getProduct(), getSale(),
 *                        getStaff(), loadLists()
 *
 * Context variables provided by dashboard.php:
 *
 * @var PDO $pdo Database connection (config/db.php)
 *
 * Variables this file sets for the rest of the request:
 *     $view, $errorMessage, $successMessage, $editProduct,
 *     $detailProduct, $editStaff,
 *     $products, $sales, $notifications, $staffUsers
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

if ($pdo->query("SHOW COLUMNS FROM products LIKE 'sku'")->fetch()) {
    $pdo->exec("ALTER TABLE products CHANGE COLUMN sku product_id VARCHAR(50) NOT NULL COMMENT 'Product ID'");
    $pdo->exec("ALTER TABLE products DROP INDEX uq_products_sku");
    $pdo->exec("CREATE UNIQUE INDEX uq_products_product_id ON products(product_id)");
}

$allowedViews = ['list', 'add', 'edit', 'sales', 'sale_add', 'inventory', 'report', 'notifications', 'staff'];
$view = $_GET['view'] ?? 'list';
if (!in_array($view, $allowedViews, true)) {
    $view = 'list';
}

$errorMessage = '';
$successMessage = '';
$editProduct = null;
$detailProduct = null;
$editStaff = null;

[$products, $sales, $notifications, $staffUsers] = loadLists($pdo);

// download bill as pdf
if (($_GET['download'] ?? '') === 'pdf' && isset($_GET['sale_id'])) {
    $dl = getSale($pdo, (int)$_GET['sale_id']);
    if (!$dl) {
        http_response_code(404);
        echo 'Bill not found.';
        exit;
    }
    downloadInvoicePdf($dl);
}

if ($view === 'edit' && isset($_GET['id'])) {
    $editProduct = getProduct($pdo, (int)$_GET['id']);
    if (!$editProduct) {
        $errorMessage = 'Product not found.';
        $view = 'list';
    }
}

if ($view === 'inventory' && isset($_GET['id'])) {
    // accept either the internal numeric id or the Product-ID code (e.g. B-33)
    $idParam = trim((string)$_GET['id']);
    $detailProduct = ctype_digit($idParam)
        ? getProduct($pdo, (int)$idParam)
        : getProductByCode($pdo, $idParam);
    if (!$detailProduct) {
        $errorMessage = 'Product not found.';
        $view = 'list';
    }
}

if ($view === 'staff' && isset($_GET['edit'])) {
    $editStaff = getStaff($pdo, (int)$_GET['edit']);
    if (!$editStaff) {
        $errorMessage = 'Staff account not found.';
    }
}