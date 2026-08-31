<?php
/**
 * dashboard/actions/products.php — Product POST actions.
 * Extracted from dashboard.php; runs inside the POST branch of handlers.php.
 * Do not open directly — included by dashboard.php → dashboard/handlers.php.
 *
 * Helper functions used: getProduct(), logMovement(),
 *                        checkStockNotification(), checkProductCountNotification()
 *
 * @var PDO    $pdo            Database connection (config/db.php)
 * @var string $action         POST action name (dashboard/handlers.php)
 * @var string $view           Active view slug (dashboard/bootstrap.php)
 * @var string $errorMessage   Feedback rendered by views/messages.php
 * @var string $successMessage Feedback rendered by views/messages.php
 * @var array|null $detailProduct
 * @var array|null $editProduct
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    $productId = trim($_POST['product_id'] ?? '');
    $cat = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $qty = trim($_POST['quantity'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if ($name === '' || $productId === '' || $price === '' || $qty === '') {
        $errorMessage = 'Name, Product-ID, price, and quantity are required.';
        $view = 'add';
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $errorMessage = 'Price must be a valid positive number.';
        $view = 'add';
    } elseif (!preg_match('/^\d+$/', $qty)) {
        $errorMessage = 'Quantity must be a whole number.';
        $view = 'add';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE product_id=?");
        $stmt->execute([$productId]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errorMessage = 'This Product-ID already exists.';
            $view = 'add';
        } else {
            $q = (int)$qty;
            $c = $cat !== '' ? $cat : 'General';

            $pdo->prepare("INSERT INTO products (name, product_id, category, price, quantity, description) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$name, $productId, $c, round((float)$price, 2), $q, $desc]);

            $newId = (int)$pdo->lastInsertId();
            if ($q > 0) {
                logMovement($pdo, $productId, 'in', $q, $q, 'Initial stock');
            }

            $np = getProduct($pdo, $newId);
            if ($q > 0 && $q <= LOW_STOCK_THRESHOLD) {
                checkStockNotification($pdo, $np, LOW_STOCK_THRESHOLD + 1, $q);
            } elseif ($q === 0) {
                checkStockNotification($pdo, $np, 1, 0);
            }

            $count = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            checkProductCountNotification($pdo, $count);

            $successMessage = 'Product added successfully.';
            $view = 'list';
        }
    }
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $productId = trim($_POST['product_id'] ?? '');
    $cat = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $qty = trim($_POST['quantity'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    $editProduct = getProduct($pdo, $id);

    if (!$editProduct) {
        $errorMessage = 'Product not found.';
        $view = 'list';
    } elseif ($price === '' || $qty === '') {
        $errorMessage = 'Price and quantity are required.';
        $view = 'edit';
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $errorMessage = 'Price must be a valid positive number.';
        $view = 'edit';
    } elseif (!preg_match('/^\d+$/', $qty)) {
        $errorMessage = 'Quantity must be a whole number.';
        $view = 'edit';
    } else {
        $oldQty = (int)$editProduct['quantity'];
        $newQty = (int)$qty;

        /* Identity fields (name, product_id, category) are locked in Modify Product:
           ignore the posted values and always keep the stored ones. */
        $name = $editProduct['name'];
        $productId  = $editProduct['product_id'];
        $cat  = $editProduct['category'];
        $c = $cat !== '' ? $cat : 'General';

        $pdo->prepare("UPDATE products SET name=?, product_id=?, category=?, price=?, quantity=?, description=? WHERE id=?")
            ->execute([$name, $productId, $c, round((float)$price, 2), $newQty, $desc, $id]);

        if ($newQty !== $oldQty) {
            $diff = $newQty - $oldQty;
            $note = 'Manual adjust (' . ($diff > 0 ? '+' : '-') . abs($diff) . ')';
            logMovement($pdo, $editProduct['product_id'], 'adjust', abs($diff), $newQty, $note);
            checkStockNotification($pdo, getProduct($pdo, $id), $oldQty, $newQty);
        }

        $successMessage = 'Product updated successfully.';
        $view = 'list';
        $editProduct = null;
    }
}

if ($action === 'stock_in' || $action === 'stock_out') {
    $id = (int)($_POST['id'] ?? 0);
    $amt = trim($_POST['amount'] ?? '');
    $ret = $_POST['return_view'] ?? 'list';
    $product = getProduct($pdo, $id);

    if (!$product) {
        $errorMessage = 'Product not found.';
        $view = 'list';
    } elseif (!preg_match('/^\d+$/', $amt) || (int)$amt < 1) {
        $errorMessage = 'Enter a valid amount (minimum 1).';
        $view = $ret === 'inventory' ? 'inventory' : 'list';
        $detailProduct = $view === 'inventory' ? $product : null;
    } elseif ($action === 'stock_out' && (int)$amt > (int)$product['quantity']) {
        $errorMessage = 'Not enough stock. Available: ' . $product['quantity'] . '.';
        $view = $ret === 'inventory' ? 'inventory' : 'list';
        $detailProduct = $view === 'inventory' ? $product : null;
    } else {
        $a = (int)$amt;
        $old = (int)$product['quantity'];
        $new = $action === 'stock_in' ? $old + $a : $old - $a;
        $op = $action === 'stock_in' ? '+' : '-';

        $pdo->prepare("UPDATE products SET quantity=quantity{$op}? WHERE id=?")->execute([$a, $id]);
        logMovement($pdo, $product['product_id'], $action === 'stock_in' ? 'in' : 'out', $a, $new, $action === 'stock_in' ? 'Stock input' : 'Stock output');

        $updated = getProduct($pdo, $id);
        if ($action === 'stock_out') {
            checkStockNotification($pdo, $updated, $old, $new);
        }

        $successMessage = 'Stock ' . ($action === 'stock_in' ? 'added: +' : 'removed: -') . $a . ' ' . ($action === 'stock_in' ? 'to' : 'from') . ' "' . $product['name'] . '".';

        if ($ret === 'inventory') {
            $view = 'inventory';
            $detailProduct = $updated;
        } else {
            $view = 'list';
        }
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $row = getProduct($pdo, $id);
    if (!$row) {
        $errorMessage = 'Product not found.';
    } else {
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        $successMessage = 'Product "' . $row['name'] . '" deleted.';
    }
    $view = 'list';
}