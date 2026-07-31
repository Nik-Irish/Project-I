<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/pdf_Invoice.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$view = $_GET['view'] ?? 'list';
$allowedViews = ['list', 'sale_add', 'sales'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'list';
}

$errorMessage = '';
$successMessage = '';

$products = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$salesStmt = $pdo->prepare('SELECT * FROM sales WHERE staff_id = ? ORDER BY sale_date DESC, created_at DESC');
$salesStmt->execute([$_SESSION['user_id']]);
$sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sale') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = trim($_POST['quantity'] ?? '');
    $up = trim($_POST['unit_price'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? 'Walk-in Customer');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $saleDate = trim($_POST['sale_date'] ?? date('Y-m-d'));

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$pid]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $errorMessage = 'Select a valid product.';
        $view = 'sale_add';
    } elseif (!preg_match('/^\d+$/', $qty) || (int)$qty < 1) {
        $errorMessage = 'Quantity must be a whole number.';
        $view = 'sale_add';
    } elseif ((int)$qty > (int)$product['quantity']) {
        $errorMessage = 'Not enough stock available.';
        $view = 'sale_add';
    } elseif ($up === '' || !is_numeric($up) || (float)$up < 0) {
        $errorMessage = 'Enter a valid unit price.';
        $view = 'sale_add';
    } else {
        $quantity = (int)$qty;
        $unitPrice = round((float)$up, 2);
        $subtotal = round($unitPrice * $quantity, 2);
        $tax = round($subtotal * TAX_RATE, 2);
        $total = round($subtotal + $tax, 2);
        $newQty = (int)$product['quantity'] - $quantity;

        $pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')
            ->execute([$newQty, $pid]);

        $next = $pdo->query("SHOW TABLE STATUS LIKE 'sales'")->fetch(PDO::FETCH_ASSOC);
        $billNo = makeBillNo((int)$next['Auto_increment']);

        $insert = $pdo->prepare(
            'INSERT INTO sales (bill_no, product_id, product_name, sku, category, quantity, unit_price, total, customer_name, customer_phone, note, sale_date, staff_id, staff_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $insert->execute([
            $billNo,
            $pid,
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
            $_SESSION['user_id'],
            $_SESSION['username'],
        ]);

        logMovement($pdo, $pid, 'sale', $quantity, $newQty, 'Sale ' . $billNo);
        $successMessage = 'Sale recorded: ' . $billNo;
        $view = 'sales';
        $salesStmt->execute([$_SESSION['user_id']]);
        $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$pageTitles = [
    'list' => 'Products',
    'sale_add' => 'Record Sale',
    'sales' => 'My Sales',
];
$pageSub = [
    'list' => 'Browse available items',
    'sale_add' => 'Record a sale quickly',
    'sales' => 'Your recent sales',
];
$pageTitle = $pageTitles[$view] ?? 'Products';

require_once __DIR__ . '/staff/includes/staff_header.php';
?>

<?php if ($errorMessage !== ''): ?>
    <div class="msg msg-error"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<?php if ($successMessage !== ''): ?>
    <div class="msg msg-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>

<?php
$viewMap = [
    'list' => 'staff/views/staff_products.php',
    'sale_add' => 'staff/views/staff_record_sale.php',
    'sales' => 'staff/views/staff_sales.php',
];
if (isset($viewMap[$view])) {
    require_once __DIR__ . '/' . $viewMap[$view];
}
?>

</main>
</div>
</body>
</html>
