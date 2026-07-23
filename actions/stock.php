<?php
/**
 * Stock in / stock out handlers.
 */

function handle_stock_actions(
    string $action,
    string &$errorMessage,
    string &$successMessage,
    string &$view,
    ?array &$detailProduct
): void {
    if ($action !== 'stock_in' && $action !== 'stock_out') {
        return;
    }

    $id     = (int)($_POST['id'] ?? 0);
    $amount = trim($_POST['amount'] ?? '');
    $return = $_POST['return_view'] ?? 'list';
    $product = products_find($id);

    $goInventory = static function () use ($return, &$view, &$detailProduct, $product): void {
        if ($return === 'inventory' && $product) {
            $view = 'inventory';
            $detailProduct = products_find((int)$product['id']);
        } else {
            $view = 'list';
        }
    };

    if (!$product) {
        $errorMessage = 'Product not found.';
        $view = 'list';
        return;
    }

    if (!preg_match('/^\d+$/', $amount) || (int)$amount < 1) {
        $errorMessage = $action === 'stock_in'
            ? 'Enter a valid amount to add (1 or more).'
            : 'Enter a valid amount to remove (1 or more).';
        $goInventory();
        return;
    }

    $amt = (int)$amount;
    $oldQty = (int)$product['quantity'];

    if ($action === 'stock_in') {
        $newQty = $oldQty + $amt;
        products_set_quantity($id, $newQty);
        movements_log($id, 'in', $amt, $newQty, 'Stock input');
        $successMessage = 'Stock added: +' . $amt . ' to "' . $product['name'] . '".';
        $goInventory();
        return;
    }

    // stock_out
    if ($amt > $oldQty) {
        $errorMessage = 'Not enough stock. Available: ' . $oldQty . '.';
        $goInventory();
        return;
    }

    $newQty = $oldQty - $amt;
    products_set_quantity($id, $newQty);
    movements_log($id, 'out', $amt, $newQty, 'Stock output');
    $updated = products_find($id);
    if ($updated) {
        checkStockNotification($updated, $oldQty, $newQty);
    }
    $successMessage = 'Stock removed: -' . $amt . ' from "' . $product['name'] . '".';
    $goInventory();
}
