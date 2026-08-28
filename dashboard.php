<?php
/**
 * dashboard.php — Admin dashboard controller (refactored).
 *
 * This file is now only the auth gate + orchestrator. The logic lives in:
 *
 *   dashboard/helpers.php    → getProduct(), getSale(), getStaff(), loadLists()
 *   dashboard/bootstrap.php  → view whitelist, state vars, lists, GET routing
 *   dashboard/handlers.php   → POST action dispatch (dashboard/actions/*) + refresh
 *   dashboard/filters.php    → product search, sales filters, report aggregation
 *   dashboard/stats.php      → dashboard stats, alert-banner data, page titles
 *   views/messages.php       → error/success messages + unread alert banner
 *
 * All includes run at global scope, so shared variables ($pdo, $view,
 * $errorMessage, $products, ...) behave exactly as they did in the
 * original single-file version.
 */

session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/pdf_invoice.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// ── Prepare request data (order matters) ─────────────────────────────────────
// Sentinel consumed by the dashboard/ partials: without it they refuse to run,
// so they can never be executed by hitting their URL directly.
define('DASHBOARD_CONTROLLER', true);

require_once __DIR__ . '/dashboard/helpers.php';    // lookup + list helper functions
require_once __DIR__ . '/dashboard/bootstrap.php';  // $view whitelist, state vars, GET routing
require_once __DIR__ . '/dashboard/handlers.php';   // POST actions + post-action refresh
require_once __DIR__ . '/dashboard/filters.php';    // search, sales filters, aggregation
require_once __DIR__ . '/dashboard/stats.php';      // stats, banner notes, page titles

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/views/messages.php';       // error/success + alert banner

// ── Render the active view ───────────────────────────────────────────────────
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