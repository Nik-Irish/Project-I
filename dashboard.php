<?php
session_start();

// --- MySQL database connection ---
require_once __DIR__ . '/pdf_invoice.php';

 $dbHost = 'localhost';
 $dbPort = 3306;          // ← Change to 3307 if your MySQL uses that port
 $dbUser = 'root';
 $dbPass = '';
 $dbName = 'ims';

try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("USE `$dbName`");
} catch (PDOException $e) {
    die('<h2>Database connection failed</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Run <a href="install.php">install.php</a> first and make sure MySQL is running.</p>');
}

define('LOW_STOCK_THRESHOLD', 10);
define('PRODUCT_COUNT_ALERT', 10);

// ---------- helpers ----------
function shortText(string $text, int $max = 48): string
{
    if (strlen($text) <= $max) { return $text; }
    return substr($text, 0, $max - 1) . '…';
}

function logMovement(PDO $pdo, int $productId, string $type, int $amount, int $balanceAfter, string $note = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO movements (product_id, type, amount, balance_after, note) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$productId, $type, $amount, $balanceAfter, $note]);
}

function addNotification(PDO $pdo, string $type, string $title, string $message, ?int $productId = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (type, title, message, product_id, is_read) VALUES (?, ?, ?, ?, 0)');
    $stmt->execute([$type, $title, $message, $productId]);
}

function checkStockNotification(PDO $pdo, array $product, int $oldQty, int $newQty): void
{
    $name = $product['name'] ?? 'Product';
    $sku  = $product['sku'] ?? '';
    $id   = (int)($product['id'] ?? 0);

    if ($newQty === 0 && $oldQty > 0) {
        addNotification($pdo, 'out_of_stock', 'Out of stock', '"' . $name . '" (SKU: ' . $sku . ') is out of stock. Please restock.', $id);
        return;
    }
    if ($newQty > 0 && $newQty <= LOW_STOCK_THRESHOLD && $oldQty > LOW_STOCK_THRESHOLD) {
        addNotification($pdo, 'low_stock', 'Low stock alert', '"' . $name . '" (SKU: ' . $sku . ') has only ' . $newQty . ' unit(s) left (threshold: ' . LOW_STOCK_THRESHOLD . ').', $id);
    }
}

function checkProductCountNotification(PDO $pdo, int $count): void
{
    if ($count !== PRODUCT_COUNT_ALERT) { return; }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE type = 'product_count' AND message LIKE ?");
    $stmt->execute(['%' . PRODUCT_COUNT_ALERT . '%']);
    if ((int)$stmt->fetchColumn() > 0) { return; }
    addNotification($pdo, 'product_count', 'Product catalog milestone', 'The system now has ' . PRODUCT_COUNT_ALERT . ' products registered.', null);
}

// ---------- load data ----------
 $errorMessage   = '';
 $successMessage = '';
 $products       = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
 $sales          = $pdo->query('SELECT * FROM sales ORDER BY sale_date DESC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
 $movements      = $pdo->query('SELECT * FROM movements ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
 $notifications  = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

 $editProduct    = null;
 $detailProduct  = null;

 $allowedViews = ['list', 'add', 'edit', 'sales', 'sale_add', 'inventory', 'report', 'notifications', 'bill'];
 $view = $_GET['view'] ?? 'list';
if (!in_array($view, $allowedViews, true)) { $view = 'list'; }

// --- PDF download ---
if (isset($_GET['download']) && $_GET['download'] === 'pdf' && isset($_GET['sale_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
    $stmt->execute([(int)$_GET['sale_id']]);
    $dlSale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dlSale) { http_response_code(404); echo 'Bill not found.'; exit; }
    downloadInvoicePdf($dlSale);
}

 $billSale = null;
if ($view === 'bill' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $billSale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$billSale) { $errorMessage = 'Bill not found.'; $view = 'sales'; }
}

if ($view === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editProduct) { $errorMessage = 'Product not found.'; $view = 'list'; }
}

if ($view === 'inventory' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $detailProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$detailProduct) { $errorMessage = 'Product / part not found.'; $view = 'list'; }
}

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD PRODUCT ──
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $sku  = trim($_POST['sku'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || $sku === '' || $price === '' || $quantity === '') {
            $errorMessage = 'Name, SKU, price, and quantity are required.';
            $view = 'add';
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $errorMessage = 'Price must be a valid non-negative number.';
            $view = 'add';
        } elseif (!preg_match('/^\d+$/', $quantity)) {
            $errorMessage = 'Quantity must be a whole number.';
            $view = 'add';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku = ?');
            $stmt->execute([$sku]);
            if ($stmt->fetchColumn() > 0) {
                $errorMessage = 'A product with this SKU already exists.';
                $view = 'add';
            } else {
                $qty = (int)$quantity;
                $cat = $category !== '' ? $category : 'General';
                $stmt = $pdo->prepare('INSERT INTO products (name, sku, category, price, quantity, description) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $sku, $cat, round((float)$price, 2), $qty, $description]);
                $newId = (int)$pdo->lastInsertId();

                if ($qty > 0) { logMovement($pdo, $newId, 'in', $qty, $qty, 'Initial stock'); }

                $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
                $stmt->execute([$newId]);
                $newProd = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($qty > 0 && $qty <= LOW_STOCK_THRESHOLD) {
                    checkStockNotification($pdo, $newProd, LOW_STOCK_THRESHOLD + 1, $qty);
                } elseif ($qty === 0) {
                    checkStockNotification($pdo, $newProd, 1, 0);
                }

                $countStmt = $pdo->query('SELECT COUNT(*) FROM products');
                checkProductCountNotification($pdo, (int)$countStmt->fetchColumn());

                $successMessage = 'Product added successfully.';
                $view = 'list';
            }
        }
    }

    // ── UPDATE PRODUCT ──
    if ($action === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $sku  = trim($_POST['sku'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editProduct) {
            $errorMessage = 'Product not found.';
            $view = 'list';
        } elseif ($name === '' || $sku === '' || $price === '' || $quantity === '') {
            $errorMessage = 'Name, SKU, price, and quantity are required.';
            $view = 'edit';
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $errorMessage = 'Price must be a valid non-negative number.';
            $view = 'edit';
        } elseif (!preg_match('/^\d+$/', $quantity)) {
            $errorMessage = 'Quantity must be a whole number.';
            $view = 'edit';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku = ? AND id != ?');
            $stmt->execute([$sku, $id]);
            if ($stmt->fetchColumn() > 0) {
                $errorMessage = 'A product with this SKU already exists.';
                $view = 'edit';
            } else {
                $oldQty = (int)$editProduct['quantity'];
                $newQty = (int)$quantity;
                $cat = $category !== '' ? $category : 'General';
                $stmt = $pdo->prepare('UPDATE products SET name=?, sku=?, category=?, price=?, quantity=?, description=? WHERE id=?');
                $stmt->execute([$name, $sku, $cat, round((float)$price, 2), $newQty, $description, $id]);

                if ($newQty !== $oldQty) {
                    $diff = $newQty - $oldQty;
                    logMovement($pdo, $id, 'adjust', abs($diff), $newQty, 'Manual quantity adjust (' . ($diff > 0 ? '+' : '-') . abs($diff) . ')');
                    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
                    $stmt->execute([$id]);
                    checkStockNotification($pdo, $stmt->fetch(PDO::FETCH_ASSOC), $oldQty, $newQty);
                }
                $successMessage = 'Product updated successfully.';
                $view = 'list';
                $editProduct = null;
            }
        }
    }

    // ── STOCK IN ──
    if ($action === 'stock_in') {
        $id     = (int)($_POST['id'] ?? 0);
        $amount = trim($_POST['amount'] ?? '');
        $return = $_POST['return_view'] ?? 'list';

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $errorMessage = 'Product not found.';
            $view = 'list';
        } elseif (!preg_match('/^\d+$/', $amount) || (int)$amount < 1) {
            $errorMessage = 'Enter a valid amount to add (1 or more).';
            $view = ($return === 'inventory') ? 'inventory' : 'list';
            if ($view === 'inventory') { $detailProduct = $product; }
        } else {
            $amt    = (int)$amount;
            $oldQty = (int)$product['quantity'];
            $newQty = $oldQty + $amt;

            $pdo->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?')->execute([$amt, $id]);
            logMovement($pdo, $id, 'in', $amt, $newQty, 'Stock input');

            $successMessage = 'Stock added: +' . $amt . ' to "' . $product['name'] . '".';
            if ($return === 'inventory') {
                $view = 'inventory';
                $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $detailProduct = $stmt->fetch(PDO::FETCH_ASSOC);
            } else { $view = 'list'; }
        }
    }

    // ── STOCK OUT ──
    if ($action === 'stock_out') {
        $id     = (int)($_POST['id'] ?? 0);
        $amount = trim($_POST['amount'] ?? '');
        $return = $_POST['return_view'] ?? 'list';

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $errorMessage = 'Product not found.';
            $view = 'list';
        } elseif (!preg_match('/^\d+$/', $amount) || (int)$amount < 1) {
            $errorMessage = 'Enter a valid amount to remove (1 or more).';
            $view = ($return === 'inventory') ? 'inventory' : 'list';
            if ($view === 'inventory') { $detailProduct = $product; }
        } elseif ((int)$amount > (int)$product['quantity']) {
            $errorMessage = 'Not enough stock. Available: ' . $product['quantity'] . '.';
            $view = ($return === 'inventory') ? 'inventory' : 'list';
            if ($view === 'inventory') { $detailProduct = $product; }
        } else {
            $amt    = (int)$amount;
            $oldQty = (int)$product['quantity'];
            $newQty = $oldQty - $amt;

            $pdo->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ?')->execute([$amt, $id]);
            logMovement($pdo, $id, 'out', $amt, $newQty, 'Stock output');

            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);
            checkStockNotification($pdo, $updated, $oldQty, $newQty);

            $successMessage = 'Stock removed: -' . $amt . ' from "' . $product['name'] . '".';
            if ($return === 'inventory') {
                $view = 'inventory';
                $detailProduct = $updated;
            } else { $view = 'list'; }
        }
    }

    // ── DELETE PRODUCT ──
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $errorMessage = 'Product not found.'; }
        else {
            $deletedName = $row['name'];
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            $successMessage = 'Product "' . $deletedName . '" deleted.';
        }
        $view = 'list';
    }

    // ── RECORD SALE ──
    if ($action === 'sale') {
        $productId     = (int)($_POST['product_id'] ?? 0);
        $qty           = trim($_POST['quantity'] ?? '');
        $unitPrice     = trim($_POST['unit_price'] ?? '');
        $note          = trim($_POST['note'] ?? '');
        $saleDate      = trim($_POST['sale_date'] ?? date('Y-m-d'));
        $customerName  = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            $errorMessage = 'Select a valid product.';
            $view = 'sale_add';
        } elseif (!preg_match('/^\d+$/', $qty) || (int)$qty < 1) {
            $errorMessage = 'Sale quantity must be at least 1.';
            $view = 'sale_add';
        } elseif ((int)$qty > (int)$p['quantity']) {
            $errorMessage = 'Not enough stock. Available: ' . $p['quantity'] . '.';
            $view = 'sale_add';
        } elseif ($unitPrice === '' || !is_numeric($unitPrice) || (float)$unitPrice < 0) {
            $errorMessage = 'Enter a valid unit price.';
            $view = 'sale_add';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) {
            $errorMessage = 'Enter a valid sale date (YYYY-MM-DD).';
            $view = 'sale_add';
        } else {
            $qtyInt   = (int)$qty;
            $priceF   = round((float)$unitPrice, 2);
            $total    = round($priceF * $qtyInt, 2);
            $oldQty   = (int)$p['quantity'];
            $newQty   = $oldQty - $qtyInt;
            $custName = $customerName !== '' ? $customerName : 'Walk-in Customer';

            // Predict next sale ID for bill number
            $status = $pdo->query("SHOW TABLE STATUS LIKE 'sales'")->fetch(PDO::FETCH_ASSOC);
            $nextSaleId = (int)$status['Auto_increment'];
            $billNo = makeBillNo($nextSaleId);

            // Reduce stock
            $pdo->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ?')->execute([$qtyInt, $productId]);

            // Insert sale
            $stmt = $pdo->prepare('INSERT INTO sales (bill_no, product_id, product_name, sku, category, quantity, unit_price, total, customer_name, customer_phone, note, sale_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$billNo, $productId, $p['name'], $p['sku'], $p['category'] ?? 'General', $qtyInt, $priceF, $total, $custName, $customerPhone, $note, $saleDate]);
            $saleId = (int)$pdo->lastInsertId();

            // Fix bill_no if predicted ID was wrong
            if ($saleId !== $nextSaleId) {
                $realBillNo = makeBillNo($saleId);
                $pdo->prepare('UPDATE sales SET bill_no = ? WHERE id = ?')->execute([$realBillNo, $saleId]);
                $billNo = $realBillNo;
            }

            logMovement($pdo, $productId, 'sale', $qtyInt, $newQty, 'Sale ' . $billNo . ($note !== '' ? ': ' . $note : ''));

            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            checkStockNotification($pdo, $stmt->fetch(PDO::FETCH_ASSOC), $oldQty, $newQty);

            $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
            $stmt->execute([$saleId]);
            $billSale = $stmt->fetch(PDO::FETCH_ASSOC);

            $successMessage = 'Sale recorded and bill ' . $billNo . ' generated.';
            $view = 'bill';
        }
    }

    // ── DELETE SALE (restores stock) ──
    if ($action === 'delete_sale') {
        $saleId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) { $errorMessage = 'Sale not found.'; $view = 'sales'; }
        else {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([(int)$sale['product_id']]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($prod) {
                $pdo->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?')->execute([(int)$sale['quantity'], (int)$sale['product_id']]);
                $newQty = (int)$prod['quantity'] + (int)$sale['quantity'];
                logMovement($pdo, (int)$sale['product_id'], 'in', (int)$sale['quantity'], $newQty, 'Sale cancelled / restored');
            }

            $pdo->prepare('DELETE FROM sales WHERE id = ?')->execute([$saleId]);
            $successMessage = 'Sale deleted and stock restored.';
            $view = 'sales';
        }
    }

    // ── NOTIFICATION ACTIONS ──
    if ($action === 'mark_read') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        $successMessage = 'Notification marked as read.';
        $view = 'notifications';
    }
    if ($action === 'mark_all_read') {
        $pdo->exec('UPDATE notifications SET is_read = 1 WHERE is_read = 0');
        $successMessage = 'All notifications marked as read.';
        $view = 'notifications';
    }
    if ($action === 'delete_notification') {
        $pdo->prepare('DELETE FROM notifications WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        $successMessage = 'Notification removed.';
        $view = 'notifications';
    }
    if ($action === 'clear_notifications') {
        $pdo->exec('DELETE FROM notifications');
        $successMessage = 'All notifications cleared.';
        $view = 'notifications';
    }

    // ── Reload all data after any mutation ──
    $products      = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $sales         = $pdo->query('SELECT * FROM sales ORDER BY sale_date DESC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $movements     = $pdo->query('SELECT * FROM movements ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $notifications = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

    if ($view === 'inventory' && $detailProduct) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([(int)$detailProduct['id']]);
        $detailProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$detailProduct) { $view = 'list'; $errorMessage = $errorMessage ?: 'Product not found.'; }
    }
    if ($view === 'bill' && $billSale) {
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?');
        $stmt->execute([(int)$billSale['id']]);
        $billSale = $stmt->fetch(PDO::FETCH_ASSOC) ?: $billSale;
    }
}

// ---------- LIST filters ----------
 $search = trim($_GET['q'] ?? '');
if ($search !== '' && $view === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE CONCAT(name, ' ', sku, ' ', category, ' ', COALESCE(description,'')) LIKE ? ORDER BY id");
    $stmt->execute(['%' . $search . '%']);
    $filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $filtered = $products;
}

// ---------- SALES REPORT filters ----------
 $reportFrom      = trim($_GET['from'] ?? '');
 $reportTo        = trim($_GET['to'] ?? '');
 $reportProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
 $reportCategory  = trim($_GET['category'] ?? '');

if ($view === 'sales' || $view === 'report') {
    $sql = 'SELECT * FROM sales WHERE 1=1';
    $params = [];
    if ($reportFrom !== '')     { $sql .= ' AND sale_date >= ?'; $params[] = $reportFrom; }
    if ($reportTo !== '')       { $sql .= ' AND sale_date <= ?'; $params[] = $reportTo; }
    if ($reportProductId > 0)   { $sql .= ' AND product_id = ?'; $params[] = $reportProductId; }
    if ($reportCategory !== '') { $sql .= ' AND category = ?';   $params[] = $reportCategory; }
    $sql .= ' ORDER BY sale_date DESC, created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filteredSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $filteredSales = $sales;
}

// Sales summary stats
 $salesUnits = 0; $salesTotal = 0.0;
 $salesByProduct = []; $salesByDay = [];
foreach ($filteredSales as $s) {
    $salesUnits += (int)$s['quantity'];
    $salesTotal += (float)$s['total'];
    $pid = (int)$s['product_id'];
    if (!isset($salesByProduct[$pid])) { $salesByProduct[$pid] = ['name' => $s['product_name'], 'sku' => $s['sku'], 'qty' => 0, 'total' => 0.0]; }
    $salesByProduct[$pid]['qty']   += (int)$s['quantity'];
    $salesByProduct[$pid]['total'] += (float)$s['total'];
    $day = $s['sale_date'];
    if (!isset($salesByDay[$day])) { $salesByDay[$day] = ['qty' => 0, 'total' => 0.0]; }
    $salesByDay[$day]['qty']   += (int)$s['quantity'];
    $salesByDay[$day]['total'] += (float)$s['total'];
}
ksort($salesByDay);

// Categories dropdown
 $categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Inventory detail: movements & sales for part
 $partMovements = []; $partSales = [];
if ($view === 'inventory' && $detailProduct) {
    $pid = (int)$detailProduct['id'];
    $stmt = $pdo->prepare('SELECT * FROM movements WHERE product_id = ? ORDER BY created_at DESC');
    $stmt->execute([$pid]);
    $partMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT * FROM sales WHERE product_id = ? ORDER BY sale_date DESC, created_at DESC');
    $stmt->execute([$pid]);
    $partSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Product list stats (single query)
 $statsRow = $pdo->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(quantity),0) AS total_stock, COALESCE(SUM(price*quantity),0) AS total_value, SUM(CASE WHEN quantity <= ' . LOW_STOCK_THRESHOLD . ' THEN 1 ELSE 0 END) AS low_cnt FROM products')->fetch(PDO::FETCH_ASSOC);
 $totalProducts = (int)$statsRow['cnt'];
 $totalStock    = (int)$statsRow['total_stock'];
 $totalValue    = (float)$statsRow['total_value'];
 $lowStockCount = (int)$statsRow['low_cnt'];

// Page titles
 $pageTitles = [
    'list' => 'Products', 'add' => 'Add Product', 'edit' => 'Modify Product',
    'sales' => 'Sales Report', 'sale_add' => 'Record Sale', 'inventory' => 'Inventory Details',
    'report' => 'Sales Summary', 'notifications' => 'System Notifications', 'bill' => 'Sales Bill / Invoice',
];
 $pageTitle = $pageTitles[$view] ?? 'Dashboard';
 $pageSub = [
    'list' => 'Input, output stock, and modify product records',
    'add' => 'Add a new product or part to inventory',
    'edit' => 'Update product details',
    'sales' => 'Filter and review all sales transactions',
    'sale_add' => 'Record a sale, generate bill, and download PDF',
    'inventory' => 'Detailed stock and movement history for one part',
    'report' => 'Sales totals by product and by day',
    'notifications' => 'Alerts when stock reaches 10 or below, and system milestones',
    'bill' => 'View invoice and download as PDF',
];

// Notification counts
 $unreadNotifications = (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn();
 $sortedNotifications = $notifications;
 $bannerNotes = $pdo->query('SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand"><span class="brand-icon">📦</span><span>Product Manager</span></div>
        <nav class="nav">
            <a href="dashboard.php?view=list" class="nav-link <?php echo $view==='list'?'active':''; ?>"><span>📋</span> All Products</a>
            <a href="dashboard.php?view=add" class="nav-link <?php echo $view==='add'?'active':''; ?>"><span>➕</span> Add Product</a>
            <a href="dashboard.php?view=sale_add" class="nav-link <?php echo $view==='sale_add'?'active':''; ?>"><span>🛒</span> Record Sale</a>
            <a href="dashboard.php?view=sales" class="nav-link <?php echo $view==='sales'?'active':''; ?>"><span>📊</span> Sales Report</a>
            <a href="dashboard.php?view=report" class="nav-link <?php echo $view==='report'?'active':''; ?>"><span>📈</span> Sales Summary</a>
            <a href="dashboard.php?view=notifications" class="nav-link <?php echo $view==='notifications'?'active':''; ?>">
                <span>🔔</span> Notifications
                <?php if ($unreadNotifications > 0): ?><span class="nav-badge"><?php echo $unreadNotifications; ?></span><?php endif; ?>
            </a>
        </nav>
        <div class="sidebar-footer"><a href="login.php" class="nav-link logout">← Back to Login</a></div>
    </aside>

    <main class="main">
        <header class="topbar topbar-flex">
            <div>
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="topbar-sub"><?php echo htmlspecialchars($pageSub[$view] ?? ''); ?></p>
            </div>
            <a href="dashboard.php?view=notifications" class="notif-bell" title="System notifications">
                🔔 <?php if ($unreadNotifications > 0): ?><span class="bell-count"><?php echo $unreadNotifications; ?></span><?php endif; ?>
            </a>
        </header>

        <?php if ($errorMessage !== ''): ?><div class="message error-message"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>
        <?php if ($successMessage !== ''): ?><div class="message success-message"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>

        <?php if (!empty($bannerNotes) && $view !== 'notifications'): ?>
        <div class="notif-banner">
            <div class="notif-banner-title">System alerts (<?php echo $unreadNotifications; ?> unread) <a href="dashboard.php?view=notifications">View all</a></div>
            <ul class="notif-banner-list">
                <?php foreach ($bannerNotes as $bn): ?>
                <li class="notif-banner-item type-<?php echo htmlspecialchars($bn['type'] ?? 'info'); ?>">
                    <strong><?php echo htmlspecialchars($bn['title'] ?? 'Alert'); ?>:</strong>
                    <?php echo htmlspecialchars($bn['message'] ?? ''); ?>
                    <?php if (!empty($bn['product_id'])): ?><a href="dashboard.php?view=inventory&id=<?php echo (int)$bn['product_id']; ?>">Open part</a><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php // ===================== LIST ===================== ?>
        <?php if ($view === 'list'): ?>
        <div class="stats">
            <div class="stat-card"><div class="stat-label">Total Products</div><div class="stat-value"><?php echo $totalProducts; ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Stock Units</div><div class="stat-value"><?php echo $totalStock; ?></div></div>
            <div class="stat-card"><div class="stat-label">Inventory Value</div><div class="stat-value">$<?php echo number_format($totalValue, 2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Low Stock (≤<?php echo LOW_STOCK_THRESHOLD; ?>)</div><div class="stat-value"><?php echo $lowStockCount; ?></div></div>
        </div>
        <div class="toolbar">
            <form class="search-form" method="GET" action="dashboard.php">
                <input type="hidden" name="view" value="list">
                <input type="search" name="q" placeholder="Search name, SKU, category..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if ($search !== ''): ?><a href="dashboard.php?view=list" class="btn btn-ghost">Clear</a><?php endif; ?>
            </form>
            <div class="toolbar-right">
                <a href="dashboard.php?view=sale_add" class="btn btn-secondary">Record Sale</a>
                <a href="dashboard.php?view=add" class="btn btn-primary">+ Add Product</a>
            </div>
        </div>
        <div class="table-wrap">
            <?php if (empty($filtered)): ?>
            <div class="empty-state"><p>No products found.</p><a href="dashboard.php?view=add" class="btn btn-primary">Add your first product</a></div>
            <?php else: ?>
            <table class="product-table">
                <thead><tr><th>ID</th><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Input / Output</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($filtered as $p): ?>
                <tr>
                    <td><?php echo (int)$p['id']; ?></td>
                    <td><strong><a class="link-name" href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a></strong>
                        <?php if (!empty($p['description'])): ?><div class="cell-desc"><?php echo htmlspecialchars(shortText($p['description'])); ?></div><?php endif; ?>
                    </td>
                    <td><code><?php echo htmlspecialchars($p['sku']); ?></code></td>
                    <td><span class="badge"><?php echo htmlspecialchars($p['category']); ?></span></td>
                    <td>$<?php echo number_format((float)$p['price'], 2); ?></td>
                    <td><span class="stock <?php echo (int)$p['quantity']===0?'stock-zero':((int)$p['quantity']<=LOW_STOCK_THRESHOLD?'stock-low':''); ?>"><?php echo (int)$p['quantity']; ?></span></td>
                    <td class="stock-actions">
                        <form method="POST" class="inline-form" title="Add stock"><input type="hidden" name="action" value="stock_in"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><input type="number" name="amount" min="1" value="1" class="qty-input" required><button type="submit" class="btn btn-sm btn-in">In</button></form>
                        <form method="POST" class="inline-form" title="Remove stock"><input type="hidden" name="action" value="stock_out"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><input type="number" name="amount" min="1" value="1" class="qty-input" required><button type="submit" class="btn btn-sm btn-out">Out</button></form>
                    </td>
                    <td class="row-actions">
                        <a href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-info">Details</a>
                        <a href="dashboard.php?view=edit&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-secondary">Modify</a>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this product?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">Delete</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php // ===================== ADD ===================== ?>
        <?php elseif ($view === 'add'): ?>
        <div class="form-card">
            <h2>New Product</h2><p class="form-hint">Fill in the details to add a product / part to inventory.</p>
            <form method="POST" action="dashboard.php?view=add">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group"><label for="name">Product Name <span class="required">*</span></label><input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="e.g. Brake Pad Set"></div>
                    <div class="form-group"><label for="sku">SKU / Part No. <span class="required">*</span></label><input type="text" id="sku" name="sku" required value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" placeholder="e.g. BP-001"></div>
                    <div class="form-group"><label for="category">Category</label><input type="text" id="category" name="category" value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>" placeholder="e.g. Brakes"></div>
                    <div class="form-group"><label for="price">Price ($) <span class="required">*</span></label><input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" placeholder="0.00"></div>
                    <div class="form-group"><label for="quantity">Initial Quantity <span class="required">*</span></label><input type="number" id="quantity" name="quantity" min="0" step="1" required value="<?php echo htmlspecialchars($_POST['quantity'] ?? '0'); ?>"></div>
                    <div class="form-group full"><label for="description">Description</label><textarea id="description" name="description" rows="3" placeholder="Optional notes"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea></div>
                </div>
                <div class="form-actions"><a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a><button type="submit" class="btn btn-primary">Save Product</button></div>
            </form>
        </div>

        <?php // ===================== EDIT ===================== ?>
        <?php elseif ($view === 'edit' && $editProduct): ?>
        <div class="form-card">
            <h2>Modify Product #<?php echo (int)$editProduct['id']; ?></h2><p class="form-hint">Update product details below.</p>
            <form method="POST" action="dashboard.php?view=edit&id=<?php echo (int)$editProduct['id']; ?>">
                <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int)$editProduct['id']; ?>">
                <div class="form-grid">
                    <div class="form-group"><label for="name">Product Name <span class="required">*</span></label><input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($editProduct['name']); ?>"></div>
                    <div class="form-group"><label for="sku">SKU / Part No. <span class="required">*</span></label><input type="text" id="sku" name="sku" required value="<?php echo htmlspecialchars($editProduct['sku']); ?>"></div>
                    <div class="form-group"><label for="category">Category</label><input type="text" id="category" name="category" value="<?php echo htmlspecialchars($editProduct['category']); ?>"></div>
                    <div class="form-group"><label for="price">Price ($) <span class="required">*</span></label><input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars((string)$editProduct['price']); ?>"></div>
                    <div class="form-group"><label for="quantity">Quantity <span class="required">*</span></label><input type="number" id="quantity" name="quantity" min="0" step="1" required value="<?php echo htmlspecialchars((string)$editProduct['quantity']); ?>"></div>
                    <div class="form-group full"><label for="description">Description</label><textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($editProduct['description'] ?? ''); ?></textarea></div>
                </div>
                <div class="form-actions"><a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a><a href="dashboard.php?view=inventory&id=<?php echo (int)$editProduct['id']; ?>" class="btn btn-secondary">View Details</a><button type="submit" class="btn btn-primary">Update Product</button></div>
            </form>
        </div>

        <?php // ===================== RECORD SALE ===================== ?>
        <?php elseif ($view === 'sale_add'): ?>
        <div class="form-card">
            <h2>Record Sale</h2><p class="form-hint">Selling reduces stock and generates a bill you can download as PDF.</p>
            <?php if (empty($products)): ?>
            <div class="empty-state"><p>Add products before recording sales.</p><a href="dashboard.php?view=add" class="btn btn-primary">Add Product</a></div>
            <?php else: ?>
            <form method="POST" action="dashboard.php?view=sale_add">
                <input type="hidden" name="action" value="sale">
                <div class="form-grid">
                    <div class="form-group"><label for="customer_name">Customer Name</label><input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" placeholder="Walk-in Customer"></div>
                    <div class="form-group"><label for="customer_phone">Customer Phone</label><input type="text" id="customer_phone" name="customer_phone" value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>" placeholder="Optional"></div>
                    <div class="form-group full"><label for="product_id">Product / Part <span class="required">*</span></label>
                        <select id="product_id" name="product_id" required>
                            <option value="">— Select product —</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" data-price="<?php echo (float)$p['price']; ?>" data-stock="<?php echo (int)$p['quantity']; ?>"><?php echo htmlspecialchars($p['name'] . ' (' . $p['sku'] . ') — Stock: ' . $p['quantity']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="quantity">Quantity <span class="required">*</span></label><input type="number" id="quantity" name="quantity" min="1" step="1" required value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>"></div>
                    <div class="form-group"><label for="unit_price">Unit Price ($) <span class="required">*</span></label><input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['unit_price'] ?? ''); ?>" placeholder="Auto-filled from product"></div>
                    <div class="form-group"><label for="sale_date">Sale Date</label><input type="date" id="sale_date" name="sale_date" value="<?php echo htmlspecialchars($_POST['sale_date'] ?? date('Y-m-d')); ?>"></div>
                    <div class="form-group"><label for="note">Note</label><input type="text" id="note" name="note" value="<?php echo htmlspecialchars($_POST['note'] ?? ''); ?>" placeholder="Optional"></div>
                </div>
                <div class="form-actions"><a href="dashboard.php?view=sales" class="btn btn-ghost">Cancel</a><button type="submit" class="btn btn-primary">Record Sale</button></div>
            </form>
            <script>
                document.getElementById('product_id').addEventListener('change', function() {
                    var opt = this.options[this.selectedIndex];
                    if (opt.value) {
                        document.getElementById('unit_price').value = opt.dataset.price || '';
                        document.getElementById('quantity').max = opt.dataset.stock || '';
                    }
                });
            </script>
            <?php endif; ?>
        </div>

        <?php // ===================== SALES REPORT ===================== ?>
        <?php elseif ($view === 'sales'): ?>
        <div class="toolbar">
            <form class="search-form" method="GET" action="dashboard.php">
                <input type="hidden" name="view" value="sales">
                <input type="date" name="from" value="<?php echo htmlspecialchars($reportFrom); ?>" placeholder="From date">
                <input type="date" name="to" value="<?php echo htmlspecialchars($reportTo); ?>" placeholder="To date">
                <select name="product_id"><option value="">All products</option><?php foreach ($products as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php echo $reportProductId==(int)$p['id']?'selected':''; ?>><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select>
                <select name="category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $reportCategory===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if ($reportFrom !== '' || $reportTo !== '' || $reportProductId > 0 || $reportCategory !== ''): ?><a href="dashboard.php?view=sales" class="btn btn-ghost">Clear</a><?php endif; ?>
            </form>
        </div>
        <div class="table-wrap">
            <?php if (empty($filteredSales)): ?>
            <div class="empty-state"><p>No sales found for the selected filters.</p></div>
            <?php else: ?>
            <table class="product-table">
                <thead><tr><th>Bill #</th><th>Product</th><th>SKU</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Customer</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($filteredSales as $s): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($s['bill_no']); ?></code></td>
                    <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['sku']); ?></td>
                    <td><?php echo (int)$s['quantity']; ?></td>
                    <td>$<?php echo number_format((float)$s['unit_price'], 2); ?></td>
                    <td>$<?php echo number_format((float)$s['total'], 2); ?></td>
                    <td><?php echo htmlspecialchars($s['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['sale_date']); ?></td>
                    <td class="row-actions">
                        <a href="dashboard.php?view=bill&id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-info">Bill</a>
                        <a href="dashboard.php?download=pdf&sale_id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-secondary">PDF</a>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this sale? Stock will be restored.');"><input type="hidden" name="action" value="delete_sale"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">Delete</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php // ===================== REPORT (Summary) ===================== ?>
        <?php elseif ($view === 'report'): ?>
        <div class="toolbar">
            <form class="search-form" method="GET" action="dashboard.php">
                <input type="hidden" name="view" value="report">
                <input type="date" name="from" value="<?php echo htmlspecialchars($reportFrom); ?>">
                <input type="date" name="to" value="<?php echo htmlspecialchars($reportTo); ?>">
                <select name="product_id"><option value="">All products</option><?php foreach ($products as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php echo $reportProductId==(int)$p['id']?'selected':''; ?>><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select>
                <select name="category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $reportCategory===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>
        </div>
        <div class="stats">
            <div class="stat-card"><div class="stat-label">Total Units Sold</div><div class="stat-value"><?php echo $salesUnits; ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Revenue</div><div class="stat-value">$<?php echo number_format($salesTotal, 2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Transactions</div><div class="stat-value"><?php echo count($filteredSales); ?></div></div>
        </div>
        <?php if (!empty($salesByProduct)): ?>
        <div class="table-wrap">
            <h3>Sales by Product</h3>
            <table class="product-table">
                <thead><tr><th>Product</th><th>SKU</th><th>Units</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($salesByProduct as $sp): ?>
                <tr><td><?php echo htmlspecialchars($sp['name']); ?></td><td><?php echo htmlspecialchars($sp['sku']); ?></td><td><?php echo $sp['qty']; ?></td><td>$<?php echo number_format($sp['total'], 2); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php if (!empty($salesByDay)): ?>
        <div class="table-wrap">
            <h3>Sales by Day</h3>
            <table class="product-table">
                <thead><tr><th>Date</th><th>Units</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($salesByDay as $day => $sd): ?>
                <tr><td><?php echo htmlspecialchars($day); ?></td><td><?php echo $sd['qty']; ?></td><td>$<?php echo number_format($sd['total'], 2); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php // ===================== INVENTORY DETAIL ===================== ?>
        <?php elseif ($view === 'inventory' && $detailProduct): ?>
        <div class="stats">
            <div class="stat-card"><div class="stat-label"><?php echo htmlspecialchars($detailProduct['name']); ?></div><div class="stat-value">SKU: <?php echo htmlspecialchars($detailProduct['sku']); ?></div></div>
            <div class="stat-card"><div class="stat-label">Current Stock</div><div class="stat-value <?php echo (int)$detailProduct['quantity']===0?'stock-zero':((int)$detailProduct['quantity']<=LOW_STOCK_THRESHOLD?'stock-low':''); ?>"><?php echo (int)$detailProduct['quantity']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Price</div><div class="stat-value">$<?php echo number_format((float)$detailProduct['price'], 2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Category</div><div class="stat-value"><?php echo htmlspecialchars($detailProduct['category']); ?></div></div>
        </div>
        <div class="toolbar">
            <form method="POST" action="dashboard.php?view=inventory&id=<?php echo (int)$detailProduct['id']; ?>" class="inline-form">
                <input type="hidden" name="action" value="stock_in"><input type="hidden" name="id" value="<?php echo (int)$detailProduct['id']; ?>"><input type="hidden" name="return_view" value="inventory">
                <input type="number" name="amount" min="1" value="1" class="qty-input" required>
                <button type="submit" class="btn btn-primary">Stock In</button>
            </form>
            <form method="POST" action="dashboard.php?view=inventory&id=<?php echo (int)$detailProduct['id']; ?>" class="inline-form">
                <input type="hidden" name="action" value="stock_out"><input type="hidden" name="id" value="<?php echo (int)$detailProduct['id']; ?>"><input type="hidden" name="return_view" value="inventory">
                <input type="number" name="amount" min="1" value="1" class="qty-input" required>
                <button type="submit" class="btn btn-danger">Stock Out</button>
            </form>
            <a href="dashboard.php?view=edit&id=<?php echo (int)$detailProduct['id']; ?>" class="btn btn-secondary">Modify Product</a>
        </div>
        <?php if (!empty($detailProduct['description'])): ?><div class="form-card"><p><?php echo htmlspecialchars($detailProduct['description']); ?></p></div><?php endif; ?>
        <?php if (!empty($partMovements)): ?>
        <div class="table-wrap"><h3>Stock Movements</h3>
            <table class="product-table"><thead><tr><th>Type</th><th>Amount</th><th>Balance After</th><th>Note</th><th>Date</th></tr></thead>
            <tbody><?php foreach ($partMovements as $m): ?>
                <tr><td><span class="badge"><?php echo htmlspecialchars($m['type']); ?></span></td><td><?php echo (int)$m['amount']; ?></td><td><?php echo (int)$m['balance_after']; ?></td><td><?php echo htmlspecialchars($m['note'] ?? ''); ?></td><td><?php echo htmlspecialchars($m['created_at']); ?></td></tr>
            <?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>
        <?php if (!empty($partSales)): ?>
        <div class="table-wrap"><h3>Sales History</h3>
            <table class="product-table"><thead><tr><th>Bill #</th><th>Qty</th><th>Total</th><th>Customer</th><th>Date</th></tr></thead>
            <tbody><?php foreach ($partSales as $ps): ?>
                <tr><td><code><?php echo htmlspecialchars($ps['bill_no']); ?></code></td><td><?php echo (int)$ps['quantity']; ?></td><td>$<?php echo number_format((float)$ps['total'], 2); ?></td><td><?php echo htmlspecialchars($ps['customer_name']); ?></td><td><?php echo htmlspecialchars($ps['sale_date']); ?></td></tr>
            <?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>

        <?php // ===================== NOTIFICATIONS ===================== ?>
        <?php elseif ($view === 'notifications'): ?>
        <div class="toolbar">
            <form method="POST" action="dashboard.php?view=notifications"><button type="submit" name="action" value="mark_all_read" class="btn btn-secondary">Mark All Read</button></form>
            <form method="POST" action="dashboard.php?view=notifications" onsubmit="return confirm('Clear all notifications?');"><button type="submit" name="action" value="clear_notifications" class="btn btn-danger">Clear All</button></form>
        </div>
        <div class="table-wrap">
            <?php if (empty($sortedNotifications)): ?>
            <div class="empty-state"><p>No notifications.</p></div>
            <?php else: ?>
            <table class="product-table">
                <thead><tr><th>Type</th><th>Title</th><th>Message</th><th>Product</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($sortedNotifications as $n): ?>
                <tr class="<?php echo (int)$n['is_read']===0?'unread':''; ?>">
                    <td><span class="badge type-<?php echo htmlspecialchars($n['type']); ?>"><?php echo htmlspecialchars($n['type']); ?></span></td>
                    <td><?php echo htmlspecialchars($n['title']); ?></td>
                    <td><?php echo htmlspecialchars($n['message']); ?></td>
                    <td><?php if (!empty($n['product_id'])): ?><a href="dashboard.php?view=inventory&id=<?php echo (int)$n['product_id']; ?>">#<?php echo (int)$n['product_id']; ?></a><?php else: ?>—<?php endif; ?></td>
                    <td><?php echo (int)$n['is_read']===0?'🔴 Unread':'✅ Read'; ?></td>
                    <td><?php echo htmlspecialchars($n['created_at']); ?></td>
                    <td class="row-actions">
                        <?php if ((int)$n['is_read']===0): ?>
                        <form method="POST" class="inline-form"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>"><button type="submit" class="btn btn-sm btn-secondary">Read</button></form>
                        <?php endif; ?>
                        <form method="POST" class="inline-form"><input type="hidden" name="action" value="delete_notification"><input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">Delete</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php // ===================== BILL ===================== ?>
        <?php elseif ($view === 'bill' && $billSale): ?>
        <div class="form-card">
            <h2>Invoice: <?php echo htmlspecialchars($billSale['bill_no']); ?></h2>
            <div class="bill-header">
                <div><strong>Customer:</strong> <?php echo htmlspecialchars($billSale['customer_name']); ?></div>
                <?php if (!empty($billSale['customer_phone'])): ?><div><strong>Phone:</strong> <?php echo htmlspecialchars($billSale['customer_phone']); ?></div><?php endif; ?>
                <div><strong>Date:</strong> <?php echo htmlspecialchars($billSale['sale_date']); ?></div>
            </div>
            <table class="product-table">
                <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
                <tbody>
                <tr><td><?php echo htmlspecialchars($billSale['product_name']); ?></td><td><?php echo htmlspecialchars($billSale['sku']); ?></td><td><?php echo (int)$billSale['quantity']; ?></td><td>$<?php echo number_format((float)$billSale['unit_price'], 2); ?></td><td>$<?php echo number_format((float)$billSale['total'], 2); ?></td></tr>
                </tbody>
                <tfoot><tr><td colspan="4" style="text-align:right;"><strong>Grand Total</strong></td><td><strong>$<?php echo number_format((float)$billSale['total'], 2); ?></strong></td></tr></tfoot>
            </table>
            <?php if (!empty($billSale['note'])): ?><p><strong>Note:</strong> <?php echo htmlspecialchars($billSale['note']); ?></p><?php endif; ?>
            <div class="form-actions">
                <a href="dashboard.php?download=pdf&sale_id=<?php echo (int)$billSale['id']; ?>" class="btn btn-primary">Download PDF</a>
                <a href="dashboard.php?view=sales" class="btn btn-ghost">Back to Sales</a>
            </div>
        </div>

        <?php endif; ?>
    </main>
</div>
</body>
</html>