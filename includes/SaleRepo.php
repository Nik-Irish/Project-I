<?php
/**
 * Sales / bills table access.
 */

function sales_all(): array
{
    return db()->query('SELECT * FROM sales ORDER BY sale_date DESC, id DESC')->fetchAll();
}

function sales_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM sales WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sales_for_product(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM sales WHERE product_id = ? ORDER BY sale_date DESC, id DESC'
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function sales_filter(?string $from, ?string $to, int $productId = 0, string $category = ''): array
{
    $sql = 'SELECT * FROM sales WHERE 1=1';
    $params = [];

    if ($from !== null && $from !== '') {
        $sql .= ' AND sale_date >= ?';
        $params[] = $from;
    }
    if ($to !== null && $to !== '') {
        $sql .= ' AND sale_date <= ?';
        $params[] = $to;
    }
    if ($productId > 0) {
        $sql .= ' AND product_id = ?';
        $params[] = $productId;
    }
    if ($category !== '') {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }

    $sql .= ' ORDER BY sale_date DESC, id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Insert sale with temp bill_no, then set INV-##### from auto id.
 */
function sales_insert(array $data): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO sales
         (bill_no, product_id, product_name, sku, category, quantity, unit_price, total,
          customer_name, customer_phone, note, sale_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $tempBill = 'TMP-' . str_replace('.', '', uniqid('', true));
    $stmt->execute([
        $tempBill,
        $data['product_id'],
        $data['product_name'],
        $data['sku'],
        $data['category'],
        $data['quantity'],
        $data['unit_price'],
        $data['total'],
        $data['customer_name'],
        $data['customer_phone'] ?? '',
        $data['note'] ?? '',
        $data['sale_date'],
    ]);
    $id = (int)$pdo->lastInsertId();
    $billNo = makeBillNo($id);
    $upd = $pdo->prepare('UPDATE sales SET bill_no = ? WHERE id = ?');
    $upd->execute([$billNo, $id]);

    $sale = sales_find($id);
    return $sale ?: array_merge($data, ['id' => $id, 'bill_no' => $billNo]);
}

function sales_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM sales WHERE id = ?');
    return $stmt->execute([$id]);
}
