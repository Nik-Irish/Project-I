<?php
/**
 * Products table access.
 */

function products_all(): array
{
    return db()->query('SELECT * FROM products ORDER BY id ASC')->fetchAll();
}

function products_search(string $q): array
{
    $like = '%' . $q . '%';
    $stmt = db()->prepare(
        'SELECT * FROM products
         WHERE name LIKE ? OR sku LIKE ? OR category LIKE ? OR description LIKE ?
         ORDER BY id ASC'
    );
    $stmt->execute([$like, $like, $like, $like]);
    return $stmt->fetchAll();
}

function products_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function products_find_by_sku(string $sku, ?int $exceptId = null): ?array
{
    if ($exceptId) {
        $stmt = db()->prepare('SELECT * FROM products WHERE sku = ? AND id != ?');
        $stmt->execute([$sku, $exceptId]);
    } else {
        $stmt = db()->prepare('SELECT * FROM products WHERE sku = ?');
        $stmt->execute([$sku]);
    }
    $row = $stmt->fetch();
    return $row ?: null;
}

function products_count(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
}

function products_insert(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO products (name, sku, category, price, quantity, description)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['name'],
        $data['sku'],
        $data['category'],
        $data['price'],
        $data['quantity'],
        $data['description'] ?? '',
    ]);
    return (int)db()->lastInsertId();
}

function products_update(int $id, array $data): bool
{
    $stmt = db()->prepare(
        'UPDATE products
         SET name = ?, sku = ?, category = ?, price = ?, quantity = ?, description = ?
         WHERE id = ?'
    );
    return $stmt->execute([
        $data['name'],
        $data['sku'],
        $data['category'],
        $data['price'],
        $data['quantity'],
        $data['description'] ?? '',
        $id,
    ]);
}

function products_set_quantity(int $id, int $quantity): bool
{
    $stmt = db()->prepare('UPDATE products SET quantity = ? WHERE id = ?');
    return $stmt->execute([$quantity, $id]);
}

function products_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
    return $stmt->execute([$id]);
}

function products_categories(): array
{
    $rows = db()->query(
        'SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != "" ORDER BY category ASC'
    )->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: [];
}

function products_stats(): array
{
    $row = db()->query(
        'SELECT
            COUNT(*) AS total_products,
            COALESCE(SUM(quantity), 0) AS total_stock,
            COALESCE(SUM(price * quantity), 0) AS total_value,
            SUM(CASE WHEN quantity <= ' . (int)LOW_STOCK_THRESHOLD . ' THEN 1 ELSE 0 END) AS low_stock
         FROM products'
    )->fetch();

    return [
        'total_products' => (int)($row['total_products'] ?? 0),
        'total_stock'    => (int)($row['total_stock'] ?? 0),
        'total_value'    => (float)($row['total_value'] ?? 0),
        'low_stock'      => (int)($row['low_stock'] ?? 0),
    ];
}
