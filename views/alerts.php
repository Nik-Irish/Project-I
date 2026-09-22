<?php
/**
 * views/alerts.php - System alerts (renamed from Notifications)
 *
 * Requires: $sortedNotifications, $unreadNotifications
 */
?>

<!-- Bulk actions -->
<div class="toolbar">
    <div class="alert-actions">
        <form method="POST" action="dashboard.php?view=notifications">
            <button type="submit" name="action" value="mark_all_read"
                    class="btn btn-secondary">
                Mark All as Read
            </button>
        </form>
        <form method="POST" action="dashboard.php?view=notifications"
              onsubmit="return confirm('Delete all alerts? This cannot be undone.');">
            <button type="submit" name="action" value="clear_notifications"
                    class="btn btn-danger">
                Delete All Alerts
            </button>
        </form>
    </div>
</div>

<!-- Alerts table -->
<div class="table-wrap">
    <?php if (empty($sortedNotifications)): ?>
        <div class="empty-state"><p>No alerts. Every product is above the low-stock limit.</p></div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Product</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sortedNotifications as $n): $unread = (int)$n['is_read'] === 0; ?>
                <tr class="<?php echo $unread ? 'alert-row-unread' : ''; ?>">

                    <td>
                        <span class="badge type-<?php echo htmlspecialchars($n['type'] ?? 'info'); ?>">
                            <?php echo htmlspecialchars($n['type'] ?? 'info'); ?>
                        </span>
                    </td>

                    <td><?php echo htmlspecialchars($n['title']   ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($n['message'] ?? ''); ?></td>

                    <td>
                        <?php if (!empty($n['product_id'])): ?>
                            <a href="dashboard.php?view=inventory&id=<?php echo urlencode($n['product_id']); ?>">
                                View Product
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="badge <?php echo $unread ? 'badge-unread' : 'badge-read'; ?>">
                            <?php echo $unread ? 'Unread' : 'Read'; ?>
                        </span>
                    </td>

                    <td class="alert-date">
                        <?php echo !empty($n['created_at'])
                            ? htmlspecialchars(date('Y-m-d H:i', strtotime($n['created_at'])))
                            : '-'; ?>
                    </td>

                    <td class="row-actions">
                        <?php if ($unread): ?>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="id"     value="<?php echo (int)$n['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">
                                    Mark Read
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" class="inline-form"
                              onsubmit="return confirm('Delete this alert?');">
                            <input type="hidden" name="action" value="delete_notification">
                            <input type="hidden" name="id"     value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>