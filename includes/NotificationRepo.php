<?php
/**
 * System notifications table access.
 */

function notifications_all(): array
{
    return db()->query('SELECT * FROM notifications ORDER BY created_at DESC, id DESC')->fetchAll();
}

/**
 * Normalize row so views can use "read" like the old JSON version.
 */
function notifications_normalize(array $row): array
{
    $row['read'] = !empty($row['is_read']);
    return $row;
}

function notifications_all_normalized(): array
{
    return array_map('notifications_normalize', notifications_all());
}

function notifications_unread_count(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn();
}

function notifications_add(string $type, string $title, string $message, ?int $productId = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO notifications (type, title, message, product_id, is_read)
         VALUES (?, ?, ?, ?, 0)'
    );
    $stmt->execute([$type, $title, $message, $productId]);
}

function notifications_mark_read(int $id): void
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
    $stmt->execute([$id]);
}

function notifications_mark_all_read(): void
{
    db()->exec('UPDATE notifications SET is_read = 1');
}

function notifications_delete(int $id): void
{
    $stmt = db()->prepare('DELETE FROM notifications WHERE id = ?');
    $stmt->execute([$id]);
}

function notifications_clear(): void
{
    db()->exec('DELETE FROM notifications');
}

function notifications_has_product_count_milestone(): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM notifications
         WHERE type = 'product_count' AND message LIKE ?"
    );
    $stmt->execute(['%reached ' . PRODUCT_COUNT_ALERT . '%']);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * After stock changes: notify when qty is at/below threshold, or hits 0.
 */
function checkStockNotification(array $product, int $oldQty, int $newQty): void
{
    $name = $product['name'] ?? 'Product';
    $sku  = $product['sku'] ?? '';
    $id   = (int)($product['id'] ?? 0);
    $threshold = LOW_STOCK_THRESHOLD;

    if ($newQty === 0 && $oldQty > 0) {
        notifications_add(
            'out_of_stock',
            'Out of stock',
            '"' . $name . '" (SKU: ' . $sku . ') is out of stock. Please restock.',
            $id
        );
        return;
    }

    if ($newQty > 0 && $newQty <= $threshold && $oldQty > $threshold) {
        notifications_add(
            'low_stock',
            'Low stock alert',
            '"' . $name . '" (SKU: ' . $sku . ') has only ' . $newQty . ' unit(s) left (threshold: ' . $threshold . ').',
            $id
        );
    }
}

function checkProductCountNotification(int $count): void
{
    if ($count !== PRODUCT_COUNT_ALERT) {
        return;
    }
    if (notifications_has_product_count_milestone()) {
        return;
    }
    notifications_add(
        'product_count',
        'Product catalog milestone',
        'The system now has ' . PRODUCT_COUNT_ALERT . ' products registered.',
        null
    );
}
