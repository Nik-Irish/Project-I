<?php
/**
 * dashboard.php — Admin dashboard controller
 */
session_start();

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// ── Dependencies ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/pdf_invoice.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// ── Routing ───────────────────────────────────────────────────────────────────
$allowedViews = [
    'list', 'add', 'edit', 'sales', 'sale_add',
    'inventory', 'report', 'notifications', 'bill', 'staff',
];
$view = $_GET['view'] ?? 'list';
if (!in_array($view, $allowedViews, true)) $view = 'list';

// ── Initialise state ──────────────────────────────────────────────────────────
$errorMessage   = '';
$successMessage = '';
$editProduct    = null;
$detailProduct  = null;
$billSale       = null;
$editStaff      = null;

// ── Load base data ────────────────────────────────────────────────────────────
$products = $pdo->query('SELECT * FROM products ORDER BY id')
                ->fetchAll(PDO::FETCH_ASSOC);

$sales = $pdo->query('SELECT * FROM sales ORDER BY sale_date DESC, created_at DESC')
             ->fetchAll(PDO::FETCH_ASSOC);

$notifications = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC')
                     ->fetchAll(PDO::FETCH_ASSOC);

$staffUsers = $pdo->query(
    "SELECT id, username, created_at FROM users WHERE role='staff' ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

// ── PDF download (exits early) ────────────────────────────────────────────────
if (
    isset($_GET['download']) &&
    $_GET['download'] === 'pdf' &&
    isset($_GET['sale_id'])
) {
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?');
    $stmt->execute([(int)$_GET['sale_id']]);
    $dl = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dl) { http_response_code(404); echo 'Bill not found.'; exit; }
    downloadInvoicePdf($dl);
}

// ── Pre-load for GET views that need a specific record ────────────────────────
if ($view === 'bill' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?');
    $stmt->execute([(int)$_GET['id']]);
    $billSale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$billSale) { $errorMessage = 'Bill not found.'; $view = 'sales'; }
}

if ($view === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $stmt->execute([(int)$_GET['id']]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editProduct) { $errorMessage = 'Product not found.'; $view = 'list'; }
}

if ($view === 'inventory' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $stmt->execute([(int)$_GET['id']]);
    $detailProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$detailProduct) { $errorMessage = 'Product not found.'; $view = 'list'; }
}

// ── Pre-load staff record for inline editing ─────────────────────────────────
if ($view === 'staff' && isset($_GET['edit'])) {
    $stmt = $pdo->prepare(
        "SELECT id, username FROM users WHERE id=? AND role='staff'"
    );
    $stmt->execute([(int)$_GET['edit']]);
    $editStaff = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editStaff) { $errorMessage = 'Staff account not found.'; }
}

// ════════════════════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add product ───────────────────────────────────────────────────────
    if ($action === 'add') {
        $name  = trim($_POST['name']        ?? '');
        $sku   = trim($_POST['sku']         ?? '');
        $cat   = trim($_POST['category']    ?? '');
        $price = trim($_POST['price']       ?? '');
        $qty   = trim($_POST['quantity']    ?? '');
        $desc  = trim($_POST['description'] ?? '');

        if ($name === '' || $sku === '' || $price === '' || $qty === '') {
            $errorMessage = 'Name, Product-ID, price, and quantity are required.';
            $view = 'add';
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $errorMessage = 'Price must be a valid positive number.';
            $view = 'add';
        } elseif (!preg_match('/^\d+$/', $qty)) {
            $errorMessage = 'Quantity must be a whole number.';
            $view = 'add';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku=?');
            $stmt->execute([$sku]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errorMessage = 'This Product-ID already exists.';
                $view = 'add';
            } else {
                $q = (int)$qty;
                $c = $cat !== '' ? $cat : 'General';
                $pdo->prepare(
                    'INSERT INTO products (name, sku, category, price, quantity, description)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$name, $sku, $c, round((float)$price, 2), $q, $desc]);

                $newId = (int)$pdo->lastInsertId();
                if ($q > 0) logMovement($pdo, $newId, 'in', $q, $q, 'Initial stock');

                $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
                $stmt->execute([$newId]);
                $np = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($q > 0 && $q <= LOW_STOCK_THRESHOLD) {
                    checkStockNotification($pdo, $np, LOW_STOCK_THRESHOLD + 1, $q);
                } elseif ($q === 0) {
                    checkStockNotification($pdo, $np, 1, 0);
                }

                checkProductCountNotification(
                    $pdo,
                    (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn()
                );

                $successMessage = 'Product added successfully.';
                $view = 'list';
            }
        }
    }

    // ── Update product ────────────────────────────────────────────────────
    if ($action === 'update') {
        $id    = (int)($_POST['id']         ?? 0);
        $name  = trim($_POST['name']        ?? '');
        $sku   = trim($_POST['sku']         ?? '');
        $cat   = trim($_POST['category']    ?? '');
        $price = trim($_POST['price']       ?? '');
        $qty   = trim($_POST['quantity']    ?? '');
        $desc  = trim($_POST['description'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
        $stmt->execute([$id]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editProduct) {
            $errorMessage = 'Product not found.';
            $view = 'list';
        } elseif ($name === '' || $sku === '' || $price === '' || $qty === '') {
            $errorMessage = 'Name, Product-ID, price, and quantity are required.';
            $view = 'edit';
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $errorMessage = 'Price must be a valid positive number.';
            $view = 'edit';
        } elseif (!preg_match('/^\d+$/', $qty)) {
            $errorMessage = 'Quantity must be a whole number.';
            $view = 'edit';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku=? AND id!=?');
            $stmt->execute([$sku, $id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errorMessage = 'This Product-ID already exists.';
                $view = 'edit';
            } else {
                $oldQty = (int)$editProduct['quantity'];
                $newQty = (int)$qty;
                $c      = $cat !== '' ? $cat : 'General';

                $pdo->prepare(
                    'UPDATE products
                     SET name=?, sku=?, category=?, price=?, quantity=?, description=?
                     WHERE id=?'
                )->execute([$name, $sku, $c, round((float)$price, 2), $newQty, $desc, $id]);

                if ($newQty !== $oldQty) {
                    $diff = $newQty - $oldQty;
                    logMovement(
                        $pdo, $id, 'adjust', abs($diff), $newQty,
                        'Manual adjust (' . ($diff > 0 ? '+' : '-') . abs($diff) . ')'
                    );
                    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
                    $stmt->execute([$id]);
                    checkStockNotification(
                        $pdo, $stmt->fetch(PDO::FETCH_ASSOC), $oldQty, $newQty
                    );
                }

                $successMessage = 'Product updated successfully.';
                $view        = 'list';
                $editProduct = null;
            }
        }
    }

    // ── Stock In ──────────────────────────────────────────────────────────
    if ($action === 'stock_in') {
        $id  = (int)($_POST['id']    ?? 0);
        $amt = trim($_POST['amount'] ?? '');
        $ret = $_POST['return_view'] ?? 'list';

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $errorMessage = 'Product not found.';
            $view = 'list';
        } elseif (!preg_match('/^\d+$/', $amt) || (int)$amt < 1) {
            $errorMessage = 'Enter a valid amount (minimum 1).';
            $view = ($ret === 'inventory') ? 'inventory' : 'list';
            if ($view === 'inventory') $detailProduct = $product;
        } else {
            $a   = (int)$amt;
            $old = (int)$product['quantity'];
            $new = $old + $a;
            $pdo->prepare('UPDATE products SET quantity=quantity+? WHERE id=?')
                ->execute([$a, $id]);
            logMovement($pdo, $id, 'in', $a, $new, 'Stock input');
            $successMessage = 'Stock added: +' . $a . ' to "' . $product['name'] . '".';

            if ($ret === 'inventory') {
                $view = 'inventory';
                $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
                $stmt->execute([$id]);
                $detailProduct = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $view = 'list';
            }
        }
    }

    // ── Stock Out ─────────────────────────────────────────────────────────
    if ($action === 'stock_out') {
        $id  = (int)($_POST['id']    ?? 0);
        $amt = trim($_POST['amount'] ?? '');
        $ret = $_POST['return_view'] ?? 'list';

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $errorMessage = 'Product not found.';
            $view = 'list';
        } elseif (!preg_match('/^\d+$/', $amt) || (int)$amt < 1) {
            $errorMessage = 'Enter a valid amount (minimum 1).';
            $view = ($ret === 'inventory') ? 'inventory' : 'list';
            if ($view === 'inventory') $detailProduct = $product;
        } elseif ((int)$amt > (int)$product['quantity']) {
            $errorMessage = 'Not enough stock. Available: ' . $product['quantity'] . '.';
            $view = ($ret === 'inventory') ? 'inventory' : 'list';
            if ($view === 'inventory') $detailProduct = $product;
        } else {
            $a   = (int)$amt;
            $old = (int)$product['quantity'];
            $new = $old - $a;
            $pdo->prepare('UPDATE products SET quantity=quantity-? WHERE id=?')
                ->execute([$a, $id]);
            logMovement($pdo, $id, 'out', $a, $new, 'Stock output');

            $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
            $stmt->execute([$id]);
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);
            checkStockNotification($pdo, $updated, $old, $new);

            $successMessage = 'Stock removed: -' . $a . ' from "' . $product['name'] . '".';

            if ($ret === 'inventory') {
                $view          = 'inventory';
                $detailProduct = $updated;
            } else {
                $view = 'list';
            }
        }
    }

    // ── Delete product ────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT name FROM products WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $errorMessage = 'Product not found.';
        } else {
            $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
            $successMessage = 'Product "' . $row['name'] . '" deleted.';
        }
        $view = 'list';
    }

    // ── Record sale ───────────────────────────────────────────────────────
    if ($action === 'sale') {
        $pid  = (int)($_POST['product_id']    ?? 0);
        $qty  = trim($_POST['quantity']       ?? '');
        $up   = trim($_POST['unit_price']     ?? '');
        $note = trim($_POST['note']           ?? '');
        $sd   = trim($_POST['sale_date']      ?? date('Y-m-d'));
        $cn   = trim($_POST['customer_name']  ?? '');
        $cp   = trim($_POST['customer_phone'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
        $stmt->execute([$pid]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            $errorMessage = 'Select a valid product.';
            $view = 'sale_add';
        } elseif (!preg_match('/^\d+$/', $qty) || (int)$qty < 1) {
            $errorMessage = 'Quantity must be at least 1.';
            $view = 'sale_add';
        } elseif ((int)$qty > (int)$p['quantity']) {
            $errorMessage = 'Not enough stock. Available: ' . $p['quantity'] . '.';
            $view = 'sale_add';
        } elseif ($up === '' || !is_numeric($up) || (float)$up < 0) {
            $errorMessage = 'Enter a valid unit price.';
            $view = 'sale_add';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sd)) {
            $errorMessage = 'Enter a valid date (YYYY-MM-DD).';
            $view = 'sale_add';
        } else {
            $qi       = (int)$qty;
            $pf       = round((float)$up, 2);
            $subtotal = round($pf * $qi, 2);
            $taxAmt   = round($subtotal * TAX_RATE, 2);
            $total    = round($subtotal + $taxAmt, 2);
            $oldQ     = (int)$p['quantity'];
            $newQ     = $oldQ - $qi;
            $custN    = $cn !== '' ? $cn : 'Walk-in Customer';

            $status = $pdo->query("SHOW TABLE STATUS LIKE 'sales'")->fetch(PDO::FETCH_ASSOC);
            $nextId = (int)$status['Auto_increment'];
            $billNo = makeBillNo($nextId);

            $pdo->prepare('UPDATE products SET quantity=quantity-? WHERE id=?')
                ->execute([$qi, $pid]);

            $pdo->prepare(
                'INSERT INTO sales
                 (bill_no, product_id, product_name, sku, category,
                  quantity, unit_price, total,
                  customer_name, customer_phone, note, sale_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $billNo, $pid, $p['name'], $p['sku'], $p['category'] ?? 'General',
                $qi, $pf, $total, $custN, $cp, $note, $sd,
            ]);

            $saleId = (int)$pdo->lastInsertId();

            if ($saleId !== $nextId) {
                $realBill = makeBillNo($saleId);
                $pdo->prepare('UPDATE sales SET bill_no=? WHERE id=?')
                    ->execute([$realBill, $saleId]);
                $billNo = $realBill;
            }

            logMovement(
                $pdo, $pid, 'sale', $qi, $newQ,
                'Sale ' . $billNo . ($note !== '' ? ': ' . $note : '')
            );

            $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
            $stmt->execute([$pid]);
            checkStockNotification($pdo, $stmt->fetch(PDO::FETCH_ASSOC), $oldQ, $newQ);

            $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?');
            $stmt->execute([$saleId]);
            $billSale              = $stmt->fetch(PDO::FETCH_ASSOC);
            $billSale['_subtotal'] = $subtotal;
            $billSale['_tax']      = $taxAmt;
            $billSale['_total']    = $total;

            $successMessage = 'Sale recorded. Bill ' . $billNo . ' generated.';
            $view = 'bill';
        }
    }

    // ── Delete sale ───────────────────────────────────────────────────────
    if ($action === 'delete_sale') {
        $sid = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?');
        $stmt->execute([$sid]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            $errorMessage = 'Sale not found.';
            $view = 'sales';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
            $stmt->execute([(int)$sale['product_id']]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($prod) {
                $pdo->prepare('UPDATE products SET quantity=quantity+? WHERE id=?')
                    ->execute([(int)$sale['quantity'], (int)$sale['product_id']]);
                logMovement(
                    $pdo,
                    (int)$sale['product_id'],
                    'in',
                    (int)$sale['quantity'],
                    (int)$prod['quantity'] + (int)$sale['quantity'],
                    'Sale cancelled / stock restored'
                );
            }

            $pdo->prepare('DELETE FROM sales WHERE id=?')->execute([$sid]);
            $successMessage = 'Sale deleted and stock restored.';
            $view = 'sales';
        }
    }

    // ── Alert / notification actions ──────────────────────────────────────
    if ($action === 'mark_read') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=?')
            ->execute([(int)($_POST['id'] ?? 0)]);
        $successMessage = 'Marked as read.';
        $view = 'notifications';
    }

    if ($action === 'mark_all_read') {
        $pdo->exec('UPDATE notifications SET is_read=1 WHERE is_read=0');
        $successMessage = 'All alerts marked as read.';
        $view = 'notifications';
    }

    if ($action === 'delete_notification') {
        $pdo->prepare('DELETE FROM notifications WHERE id=?')
            ->execute([(int)($_POST['id'] ?? 0)]);
        $successMessage = 'Alert removed.';
        $view = 'notifications';
    }

    if ($action === 'clear_notifications') {
        $pdo->exec('DELETE FROM notifications');
        $successMessage = 'All alerts cleared.';
        $view = 'notifications';
    }

    // ── Staff create (add) ────────────────────────────────────────────────
    if ($action === 'staff_create') {
        $newUser = trim($_POST['username'] ?? '');
        $newPass = trim($_POST['password'] ?? '');

        if ($newUser === '' || $newPass === '') {
            $errorMessage = 'Username and password are required.';
        } elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $newUser)) {
            $errorMessage = 'Username: 3–15 characters, letters and numbers only.';
        } elseif (!preg_match(
            '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            $newPass
        )) {
            $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special character.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?');
            $stmt->execute([$newUser]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errorMessage = 'Username already taken.';
            } else {
                $pdo->prepare(
                    "INSERT INTO users (username, password_hash, role)
                     VALUES (?, ?, 'staff')"
                )->execute([$newUser, password_hash($newPass, PASSWORD_DEFAULT)]);
                $successMessage = 'Staff account created.';
            }
        }
        $view = 'staff';
    }

    // ── Staff update ──────────────────────────────────────────────────────
    if ($action === 'staff_update') {
        $id      = (int)($_POST['id']      ?? 0);
        $newUser = trim($_POST['username'] ?? '');
        $newPass = trim($_POST['password'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='staff'");
        $stmt->execute([$id]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staff) {
            $errorMessage = 'Staff account not found.';
            $view = 'staff';
        } elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $newUser)) {
            $errorMessage = 'Username: 3–15 characters, letters and numbers only.';
            $view = 'staff';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=? AND id!=?');
            $stmt->execute([$newUser, $id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errorMessage = 'Username already taken.';
                $view = 'staff';
            } elseif ($newPass !== '' && !preg_match(
                '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
                $newPass
            )) {
                $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special character.';
                $view = 'staff';
            } else {
                if ($newPass !== '') {
                    $pdo->prepare('UPDATE users SET username=?, password_hash=? WHERE id=?')
                        ->execute([$newUser, password_hash($newPass, PASSWORD_DEFAULT), $id]);
                } else {
                    $pdo->prepare('UPDATE users SET username=? WHERE id=?')
                        ->execute([$newUser, $id]);
                }
                $successMessage = 'Staff account updated.';
                $view       = 'staff';
                $editStaff  = null; // exit edit mode
            }
        }
    }

    // ── Staff delete ──────────────────────────────────────────────────────
    if ($action === 'staff_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id=? AND role='staff'");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $errorMessage = 'Staff account not found.';
        } else {
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            $successMessage = 'Staff "' . $row['username'] . '" deleted.';
        }
        $view = 'staff';
    }

    // ── Reload all data after any POST ────────────────────────────────────
    $products = $pdo->query('SELECT * FROM products ORDER BY id')
                    ->fetchAll(PDO::FETCH_ASSOC);
    $sales = $pdo->query('SELECT * FROM sales ORDER BY sale_date DESC, created_at DESC')
                 ->fetchAll(PDO::FETCH_ASSOC);
    $notifications = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC')
                         ->fetchAll(PDO::FETCH_ASSOC);
    $staffUsers = $pdo->query(
        "SELECT id, username, created_at FROM users WHERE role='staff' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Refresh detail product after stock changes
    if ($view === 'inventory' && $detailProduct) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
        $stmt->execute([(int)$detailProduct['id']]);
        $detailProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$detailProduct) {
            $view         = 'list';
            $errorMessage = $errorMessage ?: 'Product not found.';
        }
    }

    // Refresh bill sale after record
    if ($view === 'bill' && $billSale) {
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?');
        $stmt->execute([(int)$billSale['id']]);
        $fresh = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fresh) {
            $fresh['_subtotal'] = $billSale['_subtotal'] ?? 0;
            $fresh['_tax']      = $billSale['_tax']      ?? 0;
            $fresh['_total']    = $billSale['_total']    ?? 0;
            $billSale = $fresh;
        }
    }

    // Refresh staff edit record (if still on edit)
    if ($view === 'staff' && isset($_GET['edit']) && $editStaff !== null) {
        $stmt = $pdo->prepare(
            "SELECT id, username FROM users WHERE id=? AND role='staff'"
        );
        $stmt->execute([(int)$_GET['edit']]);
        $editStaff = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// VIEW DATA PREPARATION
// ════════════════════════════════════════════════════════════════════════════

// ── Search / product filter ───────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
if ($search !== '' && $view === 'list') {
    $stmt = $pdo->prepare(
        "SELECT * FROM products
         WHERE CONCAT(name, ' ', sku, ' ', category, ' ', COALESCE(description,'')) LIKE ?
         ORDER BY id"
    );
    $stmt->execute(['%' . $search . '%']);
    $filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $filtered = $products;
}

// ── Sales filters ─────────────────────────────────────────────────────────────
$reportFrom      = trim($_GET['from']        ?? '');
$reportTo        = trim($_GET['to']          ?? '');
$reportProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$reportCategory  = trim($_GET['category']    ?? '');

if ($view === 'sales' || $view === 'report') {
    $sql    = 'SELECT * FROM sales WHERE 1=1';
    $params = [];
    if ($reportFrom      !== '') { $sql .= ' AND sale_date>=?'; $params[] = $reportFrom; }
    if ($reportTo        !== '') { $sql .= ' AND sale_date<=?'; $params[] = $reportTo; }
    if ($reportProductId  > 0)   { $sql .= ' AND product_id=?'; $params[] = $reportProductId; }
    if ($reportCategory  !== '') { $sql .= ' AND category=?';   $params[] = $reportCategory; }
    $sql .= ' ORDER BY sale_date DESC, created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filteredSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $filteredSales = $sales;
}

// ── Sales aggregates ──────────────────────────────────────────────────────────
$salesUnits     = 0;
$salesTotal     = 0.0;
$salesByProduct = [];
$salesByDay     = [];

foreach ($filteredSales as $s) {
    $salesUnits += (int)$s['quantity'];
    $salesTotal += (float)$s['total'];

    $pid = (int)$s['product_id'];
    if (!isset($salesByProduct[$pid])) {
        $salesByProduct[$pid] = [
            'name'  => $s['product_name'],
            'sku'   => $s['sku'],
            'qty'   => 0,
            'total' => 0.0,
        ];
    }
    $salesByProduct[$pid]['qty']   += (int)$s['quantity'];
    $salesByProduct[$pid]['total'] += (float)$s['total'];

    $day = $s['sale_date'];
    if (!isset($salesByDay[$day])) $salesByDay[$day] = ['qty' => 0, 'total' => 0.0];
    $salesByDay[$day]['qty']   += (int)$s['quantity'];
    $salesByDay[$day]['total'] += (float)$s['total'];
}
ksort($salesByDay);

// ── Categories dropdown ───────────────────────────────────────────────────────
$categories = $pdo->query(
    "SELECT DISTINCT category FROM products
     WHERE category IS NOT NULL AND category != '' ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

// ── Inventory detail data ─────────────────────────────────────────────────────
$partMovements = [];
$partSales     = [];

if ($view === 'inventory' && $detailProduct) {
    $pid = (int)$detailProduct['id'];

    $stmt = $pdo->prepare(
        'SELECT * FROM movements WHERE product_id=? ORDER BY created_at DESC'
    );
    $stmt->execute([$pid]);
    $partMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT * FROM sales WHERE product_id=? ORDER BY sale_date DESC, created_at DESC'
    );
    $stmt->execute([$pid]);
    $partSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Dashboard stats ───────────────────────────────────────────────────────────
$statsRow = $pdo->query(
    'SELECT
         COUNT(*)                                              AS cnt,
         COALESCE(SUM(quantity), 0)                           AS total_stock,
         COALESCE(SUM(price * quantity), 0)                   AS total_value,
         SUM(CASE WHEN quantity <= ' . LOW_STOCK_THRESHOLD . ' THEN 1 ELSE 0 END)
                                                              AS low_cnt
     FROM products'
)->fetch(PDO::FETCH_ASSOC);

$totalProducts = (int)$statsRow['cnt'];
$totalStock    = (int)$statsRow['total_stock'];
$totalValue    = (float)$statsRow['total_value'];
$lowStockCount = (int)$statsRow['low_cnt'];

// ── Notification counts ───────────────────────────────────────────────────────
$unreadNotifications = (int)$pdo->query(
    'SELECT COUNT(*) FROM notifications WHERE is_read=0'
)->fetchColumn();

$sortedNotifications = $notifications;

$bannerNotes = $pdo->query(
    'SELECT * FROM notifications WHERE is_read=0 ORDER BY created_at DESC LIMIT 3'
)->fetchAll(PDO::FETCH_ASSOC);

// ── Page meta ─────────────────────────────────────────────────────────────────
$pageTitles = [
    'list'          => 'Dashboard',
    'add'           => 'Add Product',
    'edit'          => 'Modify Product',
    'sales'         => 'Sales Report',
    'sale_add'      => 'Record Sale',
    'inventory'     => 'Inventory Details',
    'report'        => 'Sales Summary',
    'notifications' => 'Alerts',
    'bill'          => 'Bill',
    'staff'         => 'Manage Staff',
];

$pageSub = [
    'list'          => 'Overview of products and stock levels',
    'add'           => 'Add a new product to the catalog',
    'edit'          => 'Update product details',
    'sales'         => 'View all recorded sales',
    'sale_add'      => 'Record a new sale transaction',
    'inventory'     => 'Stock and sales history for this product',
    'report'        => 'Aggregated sales figures',
    'notifications' => 'System alerts and stock warnings',
    'bill'          => 'Invoice details',
    'staff'         => 'Edit or remove staff accounts',
];

$pageTitle = $pageTitles[$view] ?? 'Dashboard';

// ════════════════════════════════════════════════════════════════════════════
// RENDER
// ════════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($errorMessage !== ''): ?>
    <div class="msg msg-error"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<?php if ($successMessage !== ''): ?>
    <div class="msg msg-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>

<?php if (!empty($bannerNotes) && $view !== 'notifications'): ?>
    <div class="notif-banner">
        <div class="notif-banner-title">
            Alerts (<?php echo $unreadNotifications; ?> unread)
            <a href="dashboard.php?view=notifications">View all</a>
        </div>
        <ul class="notif-banner-list">
            <?php foreach ($bannerNotes as $bn): ?>
                <li class="type-<?php echo htmlspecialchars($bn['type'] ?? 'info'); ?>">
                    <strong><?php echo htmlspecialchars($bn['title'] ?? 'Alert'); ?>:</strong>
                    <?php echo htmlspecialchars($bn['message'] ?? ''); ?>
                    <?php if (!empty($bn['product_id'])): ?>
                        <a href="dashboard.php?view=inventory&id=<?php
                            echo (int)$bn['product_id']; ?>">View</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// ── Route to view file ────────────────────────────────────────────────────────
$viewMap = [
    'list'          => 'views/products.php',
    'add'           => 'views/add_product.php',
    'edit'          => 'views/edit_product.php',
    'sale_add'      => 'views/record_sale.php',
    'sales'         => 'views/sales_report.php',
    'report'        => 'views/sales_summary.php',
    'notifications' => 'views/alerts.php',
    'staff'         => 'staff/views/staff.php',
    'bill'          => 'views/bill.php',
    'inventory'     => 'views/inventory.php',
];

if (isset($viewMap[$view])) {
    $viewFile = __DIR__ . '/' . $viewMap[$view];
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        echo '<div class="msg msg-error">View file not found: '
            . htmlspecialchars($viewMap[$view]) . '</div>';
    }
}

require_once __DIR__ . '/includes/footer.php';