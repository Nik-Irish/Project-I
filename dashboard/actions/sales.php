<?php
/**
 * dashboard/actions/sales.php — Sale POST actions.
 * Extracted from dashboard.php; runs inside the POST branch of handlers.php.
 * Do not open directly — included by dashboard.php → dashboard/handlers.php.
 *
 * Helper functions used: getProduct(), getSale(), recordSale(), logMovement()
 *
 * @var PDO    $pdo            Database connection (config/db.php)
 * @var string $action         POST action name (dashboard/handlers.php)
 * @var string $view           Active view slug (dashboard/bootstrap.php)
 * @var string $errorMessage   Feedback rendered by views/messages.php
 * @var string $successMessage Feedback rendered by views/messages.php
 * @var array|null $billSale   Sale shown on the bill page after recording
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

if ($action === 'sale') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = trim($_POST['quantity'] ?? '');
    $up = trim($_POST['unit_price'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $sd = date('Y-m-d');
    $cn = trim($_POST['customer_name'] ?? '');
    $cp = trim($_POST['customer_phone'] ?? '');
    $p = getProduct($pdo, $pid);

    if (!$p) {
        $errorMessage = 'Select a valid product.';
        $view = 'sale_add';
    } elseif (!preg_match('/^\d+$/', $qty) || (int)$qty < 1) {
        $errorMessage = 'Quantity must be at least 1.';
        $view = 'sale_add';
    } elseif ((int)$qty > (int)$p['quantity']) {
        $errorMessage = 'Not enough stock. Available: ' . $p['quantity'] . '.';
        $view = 'sale_add';
    } else {
        $qi = (int)$qty;
        $pf = round((float)$p['price'], 2);
        $custN = $cn !== '' ? $cn : 'Walk-in Customer';

        $sale = recordSale(
            $pdo,
            $p,
            $qi,
            $pf,
            $custN,
            $cp,
            $note,
            $sd,
            null,
            $_SESSION['username'] ?? 'admin'
        );

        $billSale = getSale($pdo, $sale['id']);
        $billSale['_subtotal'] = $sale['subtotal'];
        $billSale['_tax'] = $sale['tax'];
        $billSale['_total'] = $sale['total'];

        $successMessage = 'Sale recorded. Bill ' . $sale['bill_no'] . ' generated.';
        $view = 'bill';
    }
}

if ($action === 'delete_sale') {
    $sid = (int)($_POST['id'] ?? 0);
    $sale = getSale($pdo, $sid);

    if (!$sale) {
        $errorMessage = 'Sale not found.';
        $view = 'sales';
    } else {
        $prod = getProduct($pdo, (int)$sale['product_id']);
        if ($prod) {
            $pdo->prepare("UPDATE products SET quantity=quantity+? WHERE id=?")
                ->execute([(int)$sale['quantity'], (int)$sale['product_id']]);

            $restoredQty = (int)$prod['quantity'] + (int)$sale['quantity'];
            logMovement($pdo, (int)$sale['product_id'], 'in', (int)$sale['quantity'], $restoredQty, 'Sale cancelled / stock restored');
        }

        $pdo->prepare("DELETE FROM sales WHERE id=?")->execute([$sid]);
        $successMessage = 'Sale deleted and stock restored.';
        $view = 'sales';
    }
}