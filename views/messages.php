<?php
/**
 * views/messages.php - Error/success messages + unread alert banner.
 * Extracted from dashboard.php; included after includes/header.php.
 * Do not open directly - included by dashboard.php.
 *
 * Context variables provided by dashboard.php:
 *
 * @var string     $errorMessage        Rendered as .msg-error
 * @var string     $successMessage      Rendered as .msg-success
 * @var array      $bannerNotes         Unread alerts (dashboard/stats.php)
 * @var int        $unreadNotifications Unread alert count (dashboard/stats.php)
 * @var string     $view                Active view slug
 */

if (!defined('DASHBOARD_CONTROLLER')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// defaults so staff pages (no alerts) can include this partial safely
$errorMessage = $errorMessage ?? '';
$successMessage = $successMessage ?? '';
$bannerNotes = $bannerNotes ?? [];
$unreadNotifications = $unreadNotifications ?? 0;
$view = $view ?? '';
?>
<?php if ($errorMessage !== ''): ?>
    <div class="msg msg-error"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<?php if ($successMessage !== ''): ?>
    <div class="msg msg-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>

<!-- View-specific styles (alert banner) -->
<link rel="stylesheet" href="css/banner.css">

<?php if (!empty($bannerNotes) && $view !== 'notifications'): ?>
    <div class="notif-banner">
        <div class="notif-banner-title">
            <?php echo $unreadNotifications; ?> <?php echo $unreadNotifications === 1 ? 'Alert Needs' : 'Alerts Need'; ?> Action
            <a href="dashboard.php?view=notifications">View All Alerts</a>
        </div>
        <ul class="notif-banner-list">
            <?php foreach ($bannerNotes as $bn): ?>
                <li class="type-<?php echo htmlspecialchars($bn['type'] ?? 'info'); ?>">
                    <strong><?php echo htmlspecialchars($bn['title'] ?? 'Alert'); ?>:</strong>
                    <?php echo htmlspecialchars($bn['message'] ?? ''); ?>
                    <?php if (!empty($bn['product_id'])): ?>
                        <a href="dashboard.php?view=inventory&id=<?php echo urlencode($bn['product_id']); ?>">View Product</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>