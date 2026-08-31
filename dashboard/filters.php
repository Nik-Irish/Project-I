<?php
/**
 * dashboard/filters.php — Product search, sales filters, report aggregation,
 * and per-product inventory history. Extracted from dashboard.php.
 * Do not open directly — included by dashboard.php.
 *
 * Variables this file sets for the rest of the request:
 *     $filtered, $search, $reportFrom, $reportTo, $reportProductId,
 *     $reportCategory, $filteredSales, $salesUnits, $salesTotal,
 *     $salesByProduct, $salesByDay, $salesByRecorderProduct, $categories,
 *     $partMovements, $partSales
 *
 * Context variables provided by dashboard.php:
 *
 * @var PDO         $pdo           Database connection (config/db.php)
 * @var string      $view          Active view slug (dashboard/bootstrap.php)
 * @var array       $products      All products (dashboard/bootstrap.php)
 * @var array       $sales         All sales (dashboard/bootstrap.php)
 * @var array|null  $detailProduct Product on the inventory detail page
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// search filter for product list
$search = trim($_GET['q'] ?? '');
if ($search !== '' && $view === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE CONCAT(name, ' ', product_id, ' ', category, ' ', COALESCE(description,'')) LIKE ? ORDER BY id");
    $stmt->execute(['%' . $search . '%']);
    $filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $filtered = $products;
}

// sales filters
$reportFrom = trim($_GET['from'] ?? '');
$reportTo = trim($_GET['to'] ?? '');
$reportProductId = trim($_GET['product_id'] ?? '');
$reportCategory = trim($_GET['category'] ?? '');

if ($view === 'sales' || $view === 'report') {
    $sql = "SELECT * FROM sales WHERE 1=1";
    $params = [];
    if ($reportFrom !== '') { $sql .= " AND sale_date>=?"; $params[] = $reportFrom; }
    if ($reportTo !== '') { $sql .= " AND sale_date<=?"; $params[] = $reportTo; }
    if ($reportProductId !== '') { $sql .= " AND product_id=?"; $params[] = $reportProductId; }
    if ($reportCategory !== '') { $sql .= " AND category=?"; $params[] = $reportCategory; }
    $sql .= " ORDER BY sale_date DESC, created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filteredSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $filteredSales = $sales;
}

// totals for report
$salesUnits = 0;
$salesTotal = 0.0;
$salesByProduct = [];
$salesByDay = [];
$salesByRecorderProduct = [];

foreach ($filteredSales as $s) {
    $salesUnits += (int)$s['quantity'];
    $salesTotal += (float)$s['total'];

    $pid = (int)$s['product_id'];
    if (!isset($salesByProduct[$pid])) {
        $salesByProduct[$pid] = ['name' => $s['product_name'], 'product_sku' => $s['product_sku'], 'qty' => 0, 'total' => 0.0];
    }
    $salesByProduct[$pid]['qty'] += (int)$s['quantity'];
    $salesByProduct[$pid]['total'] += (float)$s['total'];

    $recorder = trim((string)($s['staff_name'] ?? '')) ?: 'Admin';
    if (!isset($salesByRecorderProduct[$recorder][$pid])) {
        $salesByRecorderProduct[$recorder][$pid] = [
            'name' => $s['product_name'],
            'product_sku' => $s['product_sku'],
            'qty' => 0,
            'total' => 0.0,
        ];
    }
    $salesByRecorderProduct[$recorder][$pid]['qty'] += (int)$s['quantity'];
    $salesByRecorderProduct[$recorder][$pid]['total'] += (float)$s['total'];

    $day = $s['sale_date'];
    if (!isset($salesByDay[$day])) {
        $salesByDay[$day] = ['qty' => 0, 'total' => 0.0];
    }
    $salesByDay[$day]['qty'] += (int)$s['quantity'];
    $salesByDay[$day]['total'] += (float)$s['total'];
}
ksort($salesByDay);
ksort($salesByRecorderProduct);

$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// movement + sale history for inventory detail page
$partMovements = [];
$partSales = [];

if ($view === 'inventory' && $detailProduct) {
    $pcode = (string)($detailProduct['product_id'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM movements WHERE product_id=? ORDER BY created_at DESC");
    $stmt->execute([$pcode]);
    $partMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE product_id=? ORDER BY sale_date DESC, created_at DESC");
    $stmt->execute([$pcode]);
    $partSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}