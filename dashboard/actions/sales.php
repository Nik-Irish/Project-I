<?php
/**
 * dashboard/actions/sales.php - Sale POST actions.
 * Extracted from dashboard.php; runs inside the POST branch of handlers.php.
 * Do not open directly - included by dashboard.php → dashboard/handlers.php.
 *
 * Helper functions used: getProduct(), getSale(), recordSale(), logMovement()
 *
 * @var PDO    $pdo            Database connection (config/db.php)
 * @var string $action         POST action name (dashboard/handlers.php)
 * @var string $view           Active view slug (dashboard/bootstrap.php)
 * @var string $errorMessage   Feedback rendered by views/messages.php
 * @var string $successMessage Feedback rendered by views/messages.php
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

if ($action === 'sale') {
    $result = recordSaleFromPost($pdo);
    if ($result['ok']) {
        $successMessage = $result['message'];
        $view = 'sales';
    } else {
        $errorMessage = $result['message'];
        $view = 'sale_add';
    }
}

if ($action === 'delete_sale') {
    $sid = (int)($_POST['id'] ?? 0);
    $sale = getSale($pdo, $sid);

    if (!$sale) {
        $errorMessage = 'Sale not found.';
        $view = 'sales';
    } else {
        $code = (string)($sale['product_id'] ?? '');
        $prod = null;
        if ($code !== '') {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id=?");
            $stmt->execute([$code]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($prod) {
            $pdo->prepare("UPDATE products SET quantity=quantity+? WHERE product_id=?")
                ->execute([(int)$sale['quantity'], $code]);

            $restoredQty = (int)$prod['quantity'] + (int)$sale['quantity'];
            logMovement($pdo, $code, 'in', (int)$sale['quantity'], $restoredQty, 'Sale cancelled / stock restored');
        }

        $pdo->prepare("DELETE FROM sales WHERE id=?")->execute([$sid]);
        $successMessage = 'Sale deleted and stock restored.';
        $view = 'sales';
    }
}