<?php
/**
 * views/alerts.php — System alerts (renamed from Notifications)
 *
 * Requires: $sortedNotifications, $unreadNotifications
 */
?>

<!-- Bulk actions toolbar -->
<div class="toolbar">
    <form method="POST" action="dashboard.php?view=notifications">
        <button type="submit" name="action" value="mark_all_read"
                class="btn btn-secondary">
            Mark All Read
        </button>
    </form>
    <form method="POST" action="dashboard.php?view=notifications"
          onsubmit="return confirm('Clear all alerts? This cannot be undone.');">
        <button type="submit" name="action" value="clear_notifications"
                class="btn btn-danger">
            Clear All
        </button>
    </form>
</div>

<!-- Alerts table -->
<div class="table-wrap">
    <?php if (empty($sortedNotifications)): ?>
        <div class="empty-state"><p>No alerts at this time.</p></div>
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
            <?php foreach ($sortedNotifications as $n): ?>
                <tr style="<?php echo (int)$n['is_read'] === 0 ? 'font-weight:600;' : ''; ?>">

                    <td>
                        <span class="badge type-<?php echo htmlspecialchars($n['type'] ?? 'info'); ?>">
                            <?php echo htmlspecialchars($n['type'] ?? 'info'); ?>
                        </span>
                    </td>

                    <td><?php echo htmlspecialchars($n['title']   ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($n['message'] ?? ''); ?></td>

                    <td>
                        <?php if (!empty($n['product_id'])): ?>
                            <a href="dashboard.php?view=inventory&id=<?php echo (int)$n['product_id']; ?>">
                                View
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ((int)$n['is_read'] === 0): ?>
                            <span style="color:#f59e0b;font-size:.82rem;">Unread</span>
                        <?php else: ?>
                            <span style="color:#64748b;font-size:.82rem;">Read</span>
                        <?php endif; ?>
                    </td>

                    <td style="white-space:nowrap;">
                        <?php echo htmlspecialchars($n['created_at'] ?? ''); ?>
                    </td>

                    <td class="row-actions">
                        <?php if ((int)$n['is_read'] === 0): ?>
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