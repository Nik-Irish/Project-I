<?php
/**
 * includes/header.php
 * HTML <head>, sidebar, topbar opening tags.
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSub = $pageSub ?? [];
$view = $view ?? 'list';
$unreadNotifications = $unreadNotifications ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | IMS Nepal</title>
    <link rel="stylesheet" href="dashboard-style.css">
</head>
<body>
<div class="app">

    <!-- ═══════ SIDEBAR ═══════ -->
    <aside class="sidebar">
        <a href="dashboard.php?view=list" class="brand brand-link">IMS Nepal</a>

        <nav class="nav">
            <a href="dashboard.php?view=list"
               class="nav-link <?php echo $view === 'list'          ? 'active' : ''; ?>">
                Dashboard
            </a>
            <a href="dashboard.php?view=add"
               class="nav-link <?php echo $view === 'add'           ? 'active' : ''; ?>">
                Add Product
            </a>
            <a href="dashboard.php?view=sale_add"
               class="nav-link <?php echo $view === 'sale_add'      ? 'active' : ''; ?>">
                Record Sale
            </a>
            <a href="dashboard.php?view=sales"
               class="nav-link <?php echo $view === 'sales'         ? 'active' : ''; ?>">
                Sales Report
            </a>
            <a href="dashboard.php?view=report"
               class="nav-link <?php echo $view === 'report'        ? 'active' : ''; ?>">
                Sales Summary
            </a>
            <a href="dashboard.php?view=staff"
               class="nav-link <?php echo $view === 'staff'         ? 'active' : ''; ?>">
                Manage Staff
            </a>
            <a href="dashboard.php?view=notifications"
               class="nav-link <?php echo $view === 'notifications' ? 'active' : ''; ?>">
                Alerts
                <?php if ($unreadNotifications > 0): ?>
                    <span class="nav-badge"><?php echo $unreadNotifications; ?></span>
                <?php endif; ?>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="login.php?action=logout" class="nav-link logout">Logout</a>
        </div>
    </aside>

    <!-- ═══════ MAIN ═══════ -->
    <main class="main">
        <header class="topbar">
            <div>
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="topbar-sub">
                    <?php echo htmlspecialchars($pageSub[$view] ?? ''); ?>
                </p>
            </div>
            <a href="dashboard.php?view=notifications" class="notif-bell">
                Alerts
                <?php if ($unreadNotifications > 0): ?>
                    <span class="bell-count"><?php echo $unreadNotifications; ?></span>
                <?php endif; ?>
            </a>
        </header>
