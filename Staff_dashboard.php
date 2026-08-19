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
    $saleDate = date('Y-m-d');

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
    } else {
        $quantity = (int)$qty;
        $unitPrice = round((float)$product['price'], 2);
        $sale = recordSale(
            $pdo,
            $product,
            $quantity,
            $unitPrice,
            $customerName,
            $customerPhone,
            $note,
            $saleDate,
            (int)$_SESSION['user_id'],
            $_SESSION['username']
        );

        $successMessage = 'Sale recorded: ' . $sale['bill_no'];
        $view = 'sales';
        $salesStmt->execute([$_SESSION['user_id']]);
        $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$pageTitles = [
    'list' => 'Dashboard',
    'sale_add' => 'Record Sale',
    'sales' => 'My Sales',
];
$pageSub = [
    'list' => 'Overview of available items',
    'sale_add' => 'Record a sale quickly',
    'sales' => 'Your recent sales',
];
$dashboardScript = 'Staff_dashboard.php';
$pageTitle = $pageTitles[$view] ?? 'Dashboard';

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
    'sale_add' => 'views/record_sale.php',
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
