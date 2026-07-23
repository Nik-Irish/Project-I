<div class="stats">
                    <div class="stat-card">
                        <div class="stat-label">Total Alerts</div>
                        <div class="stat-value"><?php echo count($notifications); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Unread</div>
                        <div class="stat-value <?php echo $unreadNotifications > 0 ? 'text-warn' : ''; ?>">
                            <?php echo $unreadNotifications; ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Low stock threshold</div>
                        <div class="stat-value"><?php echo LOW_STOCK_THRESHOLD; ?></div>
                    </div>
                </div>

                <div class="toolbar">
                    <p class="form-hint" style="margin:0;">
                        The system sends an alert when a product’s stock reaches <strong><?php echo LOW_STOCK_THRESHOLD; ?></strong> or below,
                        when it hits <strong>0</strong>, and when the catalog reaches <strong><?php echo PRODUCT_COUNT_ALERT; ?></strong> products.
                    </p>
                    <div class="toolbar-right">
                        <?php if ($unreadNotifications > 0): ?>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-secondary">Mark all read</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($notifications)): ?>
                            <form method="POST" class="inline-form" onsubmit="return confirm('Clear all notifications?');">
                                <input type="hidden" name="action" value="clear_notifications">
                                <button type="submit" class="btn btn-danger">Clear all</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-wrap">
                    <?php if (empty($sortedNotifications)): ?>
                        <div class="empty-state">
                            <p>No system notifications yet.</p>
                            <p class="cell-desc">Alerts appear when stock falls to <?php echo LOW_STOCK_THRESHOLD; ?> or below.</p>
                        </div>
                    <?php else: ?>
                        <ul class="notif-list">
                            <?php foreach ($sortedNotifications as $n): ?>
                                <li class="notif-item <?php echo empty($n['read']) ? 'notif-unread' : 'notif-read'; ?> notif-type-<?php echo htmlspecialchars($n['type'] ?? 'info'); ?>">
                                    <div class="notif-item-main">
                                        <div class="notif-item-top">
                                            <span class="type-badge type-<?php echo htmlspecialchars($n['type'] ?? 'info'); ?>">
                                                <?php
                                                $typeLabels = [
                                                    'low_stock'     => 'LOW STOCK',
                                                    'out_of_stock'  => 'OUT OF STOCK',
                                                    'product_count' => 'MILESTONE',
                                                    'info'          => 'INFO',
                                                ];
                                                echo htmlspecialchars($typeLabels[$n['type'] ?? 'info'] ?? strtoupper($n['type'] ?? 'INFO'));
                                                ?>
                                            </span>
                                            <span class="notif-time"><?php echo htmlspecialchars($n['created_at'] ?? ''); ?></span>
                                        </div>
                                        <h3 class="notif-item-title"><?php echo htmlspecialchars($n['title'] ?? ''); ?></h3>
                                        <p class="notif-item-msg"><?php echo htmlspecialchars($n['message'] ?? ''); ?></p>
                                        <?php if (!empty($n['product_id'])): ?>
                                            <a class="link-name" href="dashboard.php?view=inventory&id=<?php echo (int)$n['product_id']; ?>">
                                                View inventory details →
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notif-item-actions">
                                        <?php if (empty($n['read'])): ?>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary">Mark read</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge">Read</span>
                                        <?php endif; ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="delete_notification">
                                            <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
