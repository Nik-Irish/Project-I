<?php
/**
 * dashboard/actions/notifications.php — Alert (notification) POST actions.
 * Extracted from dashboard.php; runs inside the POST branch of handlers.php.
 * Do not open directly — included by dashboard.php → dashboard/handlers.php.
 *
 * @var PDO    $pdo            Database connection (config/db.php)
 * @var string $action         POST action name (dashboard/handlers.php)
 * @var string $view           Active view slug (dashboard/bootstrap.php)
 * @var string $errorMessage   Feedback rendered by views/messages.php
 * @var string $successMessage Feedback rendered by views/messages.php
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
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