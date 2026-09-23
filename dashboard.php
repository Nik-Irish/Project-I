<?php
require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/includes/invoice.php';
require_once __DIR__ . '/config/connector.php';
require_once __DIR__ . '/includes/functions.php';
define('DASHBOARD_CONTROLLER', true);

require_once __DIR__ . '/dashboard/helpers.php';
require_once __DIR__ . '/dashboard/bootstrap.php';
require_once __DIR__ . '/dashboard/handlers.php';
require_once __DIR__ . '/dashboard/filters.php';
require_once __DIR__ . '/dashboard/stats.php';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/views/messages.php';
$viewMap = [
    'list' => 'views/products.php', 'add' => 'views/add_product.php', 'edit' => 'views/edit_product.php',
    'sale_add' => 'views/record_sale.php', 'sales' => 'views/sales_report.php', 'report' => 'views/sales_summary.php',
    'notifications' => 'views/alerts.php', 'staff' => 'staff/views/staff.php', 'inventory' => 'views/inventory.php',
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