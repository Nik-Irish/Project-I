<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/pdf_invoice.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

function getProduct($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getSale($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getStaff($pdo, $id) {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id=? AND role='staff'");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function loadLists($pdo) {
    return [
        $pdo->query("SELECT * FROM products ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query("SELECT * FROM sales ORDER BY sale_date DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC),
        $pdo->query("SELECT id, username, created_at FROM users WHERE role='staff' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),
    ];
}

$allowedViews = ['list', 'add', 'edit', 'sales', 'sale_add', 'inventory', 'report', 'notifications', 'bill', 'staff'];
$view = $_GET['view'] ?? 'list';
if (!in_array($view, $allowedViews, true)) {
    $view = 'list';
}

$errorMessage = '';
$successMessage = '';
$editProduct = null;
$detailProduct = null;
$billSale = null;
$editStaff = null;

[$products, $sales, $notifications, $staffUsers] = loadLists($pdo);

// download bill as pdf
if (($_GET['download'] ?? '') === 'pdf' && isset($_GET['sale_id'])) {
    $dl = getSale($pdo, (int)$_GET['sale_id']);
    if (!$dl) {
        http_response_code(404);
        echo 'Bill not found.';
        exit;
    }
    downloadInvoicePdf($dl);
}

if ($view === 'bill' && isset($_GET['id'])) {
    $billSale = getSale($pdo, (int)$_GET['id']);
    if (!$billSale) {
        $errorMessage = 'Bill not found.';
        $view = 'sales';
    }
}

if ($view === 'edit' && isset($_GET['id'])) {
    $editProduct = getProduct($pdo, (int)$_GET['id']);
    if (!$editProduct) {
        $errorMessage = 'Product not found.';
        $view = 'list';
    }
}

if ($view === 'inventory' && isset($_GET['id'])) {
    $detailProduct = getProduct($pdo, (int)$_GET['id']);
    if (!$detailProduct) {
        $errorMessage = 'Product not found.';
        $view = 'list';
    }
}

if ($view === 'staff' && isset($_GET['edit'])) {
    $editStaff = getStaff($pdo, (int)$_GET['edit']);
    if (!$editStaff) {
        $errorMessage = 'Staff account not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $passwordRules = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $cat = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $qty = trim($_POST['quantity'] ?? '');
        $desc = trim($_POST['description'] ?? '');

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
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku=?");
            $stmt->execute([$sku]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errorMessage = 'This Product-ID already exists.';
                $view = 'add';
            } else {
                $q = (int)$qty;
                $c = $cat !== '' ? $cat : 'General';

                $pdo->prepare("INSERT INTO products (name, sku, category, price, quantity, description) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$name, $sku, $c, round((float)$price, 2), $q, $desc]);

                $newId = (int)$pdo->lastInsertId();
                if ($q > 0) {
                    logMovement($pdo, $newId, 'in', $q, $q, 'Initial stock');
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
        $sku = trim($_POST['sku'] ?? '');
        $cat = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $qty = trim($_POST['quantity'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        $editProduct = getProduct($pdo, $id);

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
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku=? AND id!=?");
            $stmt->execute([$sku, $id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errorMessage = 'This Product-ID already exists.';
                $view = 'edit';
            } else {
                $oldQty = (int)$editProduct['quantity'];
                $newQty = (int)$qty;
                $c = $cat !== '' ? $cat : 'General';

                $pdo->prepare("UPDATE products SET name=?, sku=?, category=?, price=?, quantity=?, description=? WHERE id=?")
                    ->execute([$name, $sku, $c, round((float)$price, 2), $newQty, $desc, $id]);

                if ($newQty !== $oldQty) {
                    $diff = $newQty - $oldQty;
                    $note = 'Manual adjust (' . ($diff > 0 ? '+' : '-') . abs($diff) . ')';
                    logMovement($pdo, $id, 'adjust', abs($diff), $newQty, $note);
                    checkStockNotification($pdo, getProduct($pdo, $id), $oldQty, $newQty);
                }

                $successMessage = 'Product updated successfully.';
                $view = 'list';
                $editProduct = null;
            }
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
            logMovement($pdo, $id, $action === 'stock_in' ? 'in' : 'out', $a, $new, $action === 'stock_in' ? 'Stock input' : 'Stock output');

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

    if ($action === 'mark_read') {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        $successMessage = 'Marked as read.';
        $view = 'notifications';
    }

    if ($action === 'mark_all_read') {
        $pdo->exec("UPDATE notifications SET is_read=1 WHERE is_read=0");
        $successMessage = 'All alerts marked as read.';
        $view = 'notifications';
    }

    if ($action === 'delete_notification') {
        $pdo->prepare("DELETE FROM notifications WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        $successMessage = 'Alert removed.';
        $view = 'notifications';
    }

    if ($action === 'clear_notifications') {
        $pdo->exec("DELETE FROM notifications");
        $successMessage = 'All alerts cleared.';
        $view = 'notifications';
    }

    if ($action === 'staff_create') {
        $newUser = trim($_POST['username'] ?? '');
        $newPass = trim($_POST['password'] ?? '');

        if ($newUser === '' || $newPass === '') {
            $errorMessage = 'Username and password are required.';
        } elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $newUser)) {
            $errorMessage = 'Username: 3-15 characters, letters and numbers only.';
        } elseif (!preg_match($passwordRules, $newPass)) {
            $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special character.';
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
            $stmt->execute([$newUser]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errorMessage = 'Username already taken.';
            } else {
                $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'staff')")
                    ->execute([$newUser, password_hash($newPass, PASSWORD_DEFAULT)]);
                $successMessage = 'Staff account created.';
            }
        }
        $view = 'staff';
    }

    if ($action === 'staff_update') {
        $id = (int)($_POST['id'] ?? 0);
        $newUser = trim($_POST['username'] ?? '');
        $newPass = trim($_POST['password'] ?? '');
        $staff = getStaff($pdo, $id);

        if (!$staff) {
            $errorMessage = 'Staff account not found.';
            $view = 'staff';
        } elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $newUser)) {
            $errorMessage = 'Username: 3-15 characters, letters and numbers only.';
            $view = 'staff';
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? AND id!=?");
            $stmt->execute([$newUser, $id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errorMessage = 'Username already taken.';
                $view = 'staff';
            } elseif ($newPass !== '' && !preg_match($passwordRules, $newPass)) {
                $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special character.';
                $view = 'staff';
            } else {
                if ($newPass !== '') {
                    $pdo->prepare("UPDATE users SET username=?, password_hash=? WHERE id=?")
                        ->execute([$newUser, password_hash($newPass, PASSWORD_DEFAULT), $id]);
                } else {
                    $pdo->prepare("UPDATE users SET username=? WHERE id=?")->execute([$newUser, $id]);
                }
                $successMessage = 'Staff account updated.';
                $view = 'staff';
                $editStaff = null;
            }
        }
    }

    if ($action === 'staff_password_update') {
        $id = (int)($_POST['id'] ?? 0);
        $newPass = trim($_POST['password'] ?? '');
        $staff = getStaff($pdo, $id);

        if (!$staff) {
            $errorMessage = 'Staff account not found.';
        } elseif (!preg_match($passwordRules, $newPass)) {
            $errorMessage = 'Password: 8+ chars, uppercase, lowercase, number, special character.';
        } else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
            $successMessage = 'Password updated for staff "' . $staff['username'] . '".';
        }
        $view = 'staff';
    }

    if ($action === 'staff_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $row = getStaff($pdo, $id);
        if (!$row) {
            $errorMessage = 'Staff account not found.';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            $successMessage = 'Staff "' . $row['username'] . '" deleted.';
        }
        $view = 'staff';
    }

    [$products, $sales, $notifications, $staffUsers] = loadLists($pdo);

    if ($view === 'inventory' && $detailProduct) {
        $detailProduct = getProduct($pdo, (int)$detailProduct['id']);
        if (!$detailProduct) {
            $view = 'list';
            $errorMessage = $errorMessage ?: 'Product not found.';
        }
    }

    if ($view === 'bill' && $billSale) {
        $fresh = getSale($pdo, (int)$billSale['id']);
        if ($fresh) {
            $fresh['_subtotal'] = $billSale['_subtotal'] ?? 0;
            $fresh['_tax'] = $billSale['_tax'] ?? 0;
            $fresh['_total'] = $billSale['_total'] ?? 0;
            $billSale = $fresh;
        }
    }

    if ($view === 'staff' && isset($_GET['edit']) && $editStaff !== null) {
        $editStaff = getStaff($pdo, (int)$_GET['edit']);
    }
}

// search filter for product list
$search = trim($_GET['q'] ?? '');
if ($search !== '' && $view === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE CONCAT(name, ' ', sku, ' ', category, ' ', COALESCE(description,'')) LIKE ? ORDER BY id");
    $stmt->execute(['%' . $search . '%']);
    $filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $filtered = $products;
}

// sales filters
$reportFrom = trim($_GET['from'] ?? '');
$reportTo = trim($_GET['to'] ?? '');
$reportProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$reportCategory = trim($_GET['category'] ?? '');

if ($view === 'sales' || $view === 'report') {
    $sql = "SELECT * FROM sales WHERE 1=1";
    $params = [];
    if ($reportFrom !== '') { $sql .= " AND sale_date>=?"; $params[] = $reportFrom; }
    if ($reportTo !== '') { $sql .= " AND sale_date<=?"; $params[] = $reportTo; }
    if ($reportProductId > 0) { $sql .= " AND product_id=?"; $params[] = $reportProductId; }
    if ($reportCategory !== '') { $sql .= " AND category=?"; $params[] = $reportCategory; }
    $sql .= " ORDER BY sale_date DESC, created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filteredSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $filteredSales = $sales;
}

// totals for report
$salesUnits = 0;
$salesTotal = 0.0;
$salesByProduct = [];
$salesByDay = [];
$salesByRecorderProduct = [];

foreach ($filteredSales as $s) {
    $salesUnits += (int)$s['quantity'];
    $salesTotal += (float)$s['total'];

    $pid = (int)$s['product_id'];
    if (!isset($salesByProduct[$pid])) {
        $salesByProduct[$pid] = ['name' => $s['product_name'], 'sku' => $s['sku'], 'qty' => 0, 'total' => 0.0];
    }
    $salesByProduct[$pid]['qty'] += (int)$s['quantity'];
    $salesByProduct[$pid]['total'] += (float)$s['total'];

    $recorder = trim((string)($s['staff_name'] ?? '')) ?: 'Admin';
    if (!isset($salesByRecorderProduct[$recorder][$pid])) {
        $salesByRecorderProduct[$recorder][$pid] = [
            'name' => $s['product_name'],
            'sku' => $s['sku'],
            'qty' => 0,
            'total' => 0.0,
        ];
    }
    $salesByRecorderProduct[$recorder][$pid]['qty'] += (int)$s['quantity'];
    $salesByRecorderProduct[$recorder][$pid]['total'] += (float)$s['total'];

    $day = $s['sale_date'];
    if (!isset($salesByDay[$day])) {
        $salesByDay[$day] = ['qty' => 0, 'total' => 0.0];
    }
    $salesByDay[$day]['qty'] += (int)$s['quantity'];
    $salesByDay[$day]['total'] += (float)$s['total'];
}
ksort($salesByDay);
ksort($salesByRecorderProduct);

$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// movement + sale history for inventory detail page
$partMovements = [];
$partSales = [];

if ($view === 'inventory' && $detailProduct) {
    $pid = (int)$detailProduct['id'];

    $stmt = $pdo->prepare("SELECT * FROM movements WHERE product_id=? ORDER BY created_at DESC");
    $stmt->execute([$pid]);
    $partMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE product_id=? ORDER BY sale_date DESC, created_at DESC");
    $stmt->execute([$pid]);
    $partSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// dashboard stats
$statsRow = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(quantity), 0) AS total_stock, COALESCE(SUM(price * quantity), 0) AS total_value, SUM(CASE WHEN quantity <= " . LOW_STOCK_THRESHOLD . " THEN 1 ELSE 0 END) AS low_cnt FROM products")->fetch(PDO::FETCH_ASSOC);

$totalProducts = (int)$statsRow['cnt'];
$totalStock = (int)$statsRow['total_stock'];
$totalValue = (float)$statsRow['total_value'];
$lowStockCount = (int)$statsRow['low_cnt'];

$unreadNotifications = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read=0")->fetchColumn();
$sortedNotifications = $notifications;
$bannerNotes = $pdo->query("SELECT * FROM notifications WHERE is_read=0 ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

$pageTitles = [
    'list' => 'Dashboard', 'add' => 'Add Product', 'edit' => 'Modify Product',
    'sales' => 'Sales Report', 'sale_add' => 'Record Sale', 'inventory' => 'Inventory Details',
    'report' => 'Sales Summary', 'notifications' => 'Alerts', 'bill' => 'Bill', 'staff' => 'Manage Staff',
];

$pageSub = [
    'list' => 'Overview of products and stock levels',
    'add' => 'Add a new product to the catalog',
    'edit' => 'Update product details',
    'sales' => 'View all recorded sales',
    'sale_add' => 'Record a new sale transaction',
    'inventory' => 'Stock and sales history for this product',
    'report' => 'Aggregated sales figures',
    'notifications' => 'System alerts and stock warnings',
    'bill' => 'Invoice details',
    'staff' => 'Edit or remove staff accounts',
];

$pageTitle = $pageTitles[$view] ?? 'Dashboard';

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
                        <a href="dashboard.php?view=inventory&id=<?php echo (int)$bn['product_id']; ?>">View</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
$viewMap = [
    'list' => 'views/products.php', 'add' => 'views/add_product.php', 'edit' => 'views/edit_product.php',
    'sale_add' => 'views/record_sale.php', 'sales' => 'views/sales_report.php', 'report' => 'views/sales_summary.php',
    'notifications' => 'views/alerts.php', 'staff' => 'staff/views/staff.php', 'bill' => 'views/bill.php', 'inventory' => 'views/inventory.php',
];

if (isset($viewMap[$view])) {
    $viewFile = __DIR__ . '/' . $viewMap[$view];
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        echo '<div class="msg msg-error">View file not found: ' . htmlspecialchars($viewMap[$view]) . '</div>';
    }
}

require_once __DIR__ . '/includes/footer.php';