<?php
/**
 * Stock movements table access.
 */

function movements_log(int $productId, string $type, int $amount, int $balanceAfter, string $note = ''): void
{
    $stmt = db()->prepare(
        'INSERT INTO movements (product_id, type, amount, balance_after, note)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$productId, $type, $amount, $balanceAfter, $note]);
}

function movements_for_product(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM movements WHERE product_id = ? ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function movements_all(): array
{
    return db()->query('SELECT * FROM movements ORDER BY created_at DESC, id DESC')->fetchAll();
}
