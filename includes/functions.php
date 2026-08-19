<?php
/**
 * includes/functions.php
 * Constants and helper functions used throughout the dashboard.
 */

define('LOW_STOCK_THRESHOLD', 10);
define('PRODUCT_COUNT_ALERT', 10);
define('TAX_RATE', 0.13);

// ── Truncate long strings ─────────────────────────────────────────────────────
function shortText(string $text, int $max = 48): string {
    return strlen($text) <= $max
        ? $text
        : substr($text, 0, $max - 1) . '…';
}

// NOTE: makeBillNo() lives in pdf_invoice.php — do NOT redeclare it here.

// ── Log a stock movement ──────────────────────────────────────────────────────
function logMovement(
    PDO $pdo,
    int $pid,
    string $type,
    int $amt,
    int $bal,
    string $note = ''
): void {
    $pdo->prepare(
        'INSERT INTO movements (product_id, type, amount, balance_after, note)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$pid, $type, $amt, $bal, $note]);
}

function recordSale(
    PDO $pdo,
    array $product,
    int $quantity,
    float $unitPrice,
    string $customerName,
    string $customerPhone,
    string $note,
    string $saleDate,
    ?int $staffId = null,
    ?string $staffName = null
): array {
    $subtotal = round($unitPrice * $quantity, 2);
    $tax = round($subtotal * TAX_RATE, 2);
    $total = round($subtotal + $tax, 2);
    $oldQuantity = (int)$product['quantity'];
    $newQuantity = $oldQuantity - $quantity;

    $pdo->prepare('UPDATE products SET quantity=? WHERE id=?')
        ->execute([$newQuantity, (int)$product['id']]);

    $pdo->prepare(
        "INSERT INTO sales (bill_no, product_id, product_name, sku, category, quantity, unit_price, total, customer_name, customer_phone, note, sale_date, staff_id, staff_name)
         VALUES ('', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        (int)$product['id'],
        $product['name'],
        $product['sku'],
        $product['category'] ?? 'General',
        $quantity,
        $unitPrice,
        $total,
        $customerName,
        $customerPhone,
        $note,
        $saleDate,
        $staffId,
        $staffName,
    ]);

    $saleId = (int)$pdo->lastInsertId();
    $billNo = makeBillNo($saleId);
    $pdo->prepare('UPDATE sales SET bill_no=? WHERE id=?')->execute([$billNo, $saleId]);

    logMovement($pdo, (int)$product['id'], 'sale', $quantity, $newQuantity, 'Sale ' . $billNo);
    checkStockNotification($pdo, array_merge($product, ['quantity' => $newQuantity]), $oldQuantity, $newQuantity);

    return [
        'id' => $saleId,
        'bill_no' => $billNo,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total,
    ];
}

// ── Insert a notification record ──────────────────────────────────────────────
function addNotification(
    PDO $pdo,
    string $type,
    string $title,
    string $msg,
    ?int $pid = null
): void {
    $pdo->prepare(
        'INSERT INTO notifications (type, title, message, product_id, is_read)
         VALUES (?, ?, ?, ?, 0)'
    )->execute([$type, $title, $msg, $pid]);
}

// ── Fire stock alerts when quantity changes ───────────────────────────────────
function checkStockNotification(
    PDO $pdo,
    array $product,
    int $old,
    int $new
): void {
    $name = $product['name'] ?? 'Product';
    $sku  = $product['sku']  ?? '';
    $id   = (int)($product['id'] ?? 0);

    if ($new === 0 && $old > 0) {
        addNotification(
            $pdo,
            'out_of_stock',
            'Out of stock',
            '"' . $name . '" (Product-ID: ' . $sku . ') is out of stock. Please restock.',
            $id
        );
        return;
    }

    if ($new > 0 && $new <= LOW_STOCK_THRESHOLD && $old > LOW_STOCK_THRESHOLD) {
        addNotification(
            $pdo,
            'low_stock',
            'Low stock alert',
            '"' . $name . '" (Product-ID: ' . $sku . ') has only ' . $new . ' unit(s) left.',
            $id
        );
    }
}

// ── Fire a milestone alert when product count hits target ─────────────────────
function checkProductCountNotification(PDO $pdo, int $count): void {
    if ($count !== PRODUCT_COUNT_ALERT) return;

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications
         WHERE type='product_count' AND message LIKE ?"
    );
    $stmt->execute(['%' . PRODUCT_COUNT_ALERT . '%']);
    if ((int)$stmt->fetchColumn() > 0) return;

    addNotification(
        $pdo,
        'product_count',
        'Product catalog milestone',
        'The system now has ' . PRODUCT_COUNT_ALERT . ' products registered.',
        null
    );
}