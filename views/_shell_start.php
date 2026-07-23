<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
</head>

<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-icon">📦</span>
                <span>Product Manager</span>
            </div>
            <nav class="nav">
                <a href="dashboard.php?view=list" class="nav-link <?php echo $view === 'list' ? 'active' : ''; ?>">
                    <span>📋</span> All Products
                </a>
                <a href="dashboard.php?view=add" class="nav-link <?php echo $view === 'add' ? 'active' : ''; ?>">
                    <span>➕</span> Add Product
                </a>
                <a href="dashboard.php?view=sale_add" class="nav-link <?php echo $view === 'sale_add' ? 'active' : ''; ?>">
                    <span>🛒</span> Record Sale
                </a>
                <a href="dashboard.php?view=sales" class="nav-link <?php echo ($view === 'sales') ? 'active' : ''; ?>">
                    <span>📊</span> Sales Report
                </a>
                <a href="dashboard.php?view=report" class="nav-link <?php echo $view === 'report' ? 'active' : ''; ?>">
                    <span>📈</span> Sales Summary
                </a>
                <a href="dashboard.php?view=notifications" class="nav-link <?php echo $view === 'notifications' ? 'active' : ''; ?>">
                    <span>🔔</span> Notifications
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="nav-badge"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="login.php" class="nav-link logout">← Back to Login</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar topbar-flex">
                <div>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p class="topbar-sub"><?php echo htmlspecialchars($pageSub[$view] ?? ''); ?></p>
                </div>
                <a href="dashboard.php?view=notifications" class="notif-bell" title="System notifications">
                    🔔
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="bell-count"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
            </header>

            <?php if ($errorMessage !== ''): ?>
                <div class="message error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            <?php if ($successMessage !== ''): ?>
                <div class="message success-message"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php if (!empty($bannerNotes) && $view !== 'notifications'): ?>
                <div class="notif-banner">
                    <div class="notif-banner-title">
                        System alerts (<?php echo $unreadNotifications; ?> unread)
                        <a href="dashboard.php?view=notifications">View all</a>
                    </div>
                    <ul class="notif-banner-list">
                        <?php foreach ($bannerNotes as $bn): ?>
                            <li class="notif-banner-item type-<?php echo htmlspecialchars($bn['type'] ?? 'info'); ?>">
                                <strong><?php echo htmlspecialchars($bn['title'] ?? 'Alert'); ?>:</strong>
                                <?php echo htmlspecialchars($bn['message'] ?? ''); ?>
                                <?php if (!empty($bn['product_id'])): ?>
                                    <a href="dashboard.php?view=inventory&id=<?php echo (int)$bn['product_id']; ?>">Open part</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            