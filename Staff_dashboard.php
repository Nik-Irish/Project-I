<?php
require_once __DIR__ . '/includes/auth.php';
requireRole('staff');

require_once __DIR__ . '/pdf_invoice.php';
require_once __DIR__ . '/config/connector.php';
require_once __DIR__ . '/includes/functions.php';

define('DASHBOARD_CONTROLLER', true);
require_once __DIR__ . '/dashboard/helpers.php';

$view = $_GET['view'] ?? 'list';
$allowedViews = ['list', 'sale_add', 'sales'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'list';
}

$errorMessage = '';
$successMessage = '';

$products = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$salesStmt = $pdo->prepare('SELECT * FROM sales WHERE staff_name = ? ORDER BY sale_date DESC, created_at DESC');
$salesStmt->execute([$_SESSION['username']]);
$sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

// download bill as pdf - staff may only download their own sales
if (($_GET['download'] ?? '') === 'pdf' && isset($_GET['sale_id'])) {
    $dl = getSale($pdo, (int)$_GET['sale_id']);
    if (!$dl || ($dl['staff_name'] ?? '') !== $_SESSION['username']) {
        http_response_code(404);
        echo 'Bill not found.';
        exit;
    }
    downloadInvoicePdf($dl);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sale') {
    $result = recordSaleFromPost($pdo);
    if ($result['ok']) {
        $successMessage = $result['message'];
        $view = 'sales';
    } else {
        $errorMessage = $result['message'];
        $view = 'sale_add';
    }
    $salesStmt->execute([$_SESSION['username']]);
    $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitles = [
    'list' => 'Dashboard',
    'sale_add' => 'Record Sale',
    'sales' => 'My Sales',
];
$pageSub = [
    'list' => 'Check stock before you sell.',
    'sale_add' => 'Stock and totals update the moment you save.',
    'sales' => 'Every sale you recorded, with PDF receipts.',
];
$dashboardScript = 'Staff_dashboard.php';
$pageTitle = $pageTitles[$view] ?? 'Dashboard';

require_once __DIR__ . '/staff/includes/staff_header.php';
?>

<?php require __DIR__ . '/views/messages.php'; ?>

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
