<?php
/**
 * includes/staff_header.php
 * Header for the staff dashboard.
 */
$pageTitle = $pageTitle ?? 'Staff Dashboard';
$pageSub = $pageSub ?? [];
$view = $view ?? 'list';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Nirman Staff</title>
    <link rel="stylesheet" href="dashboard-style.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <a href="Staff_dashboard.php?view=list" class="brand brand-link">Nirman</a>
        <nav class="nav">
            <a href="Staff_dashboard.php?view=list"
               class="nav-link <?php echo $view === 'list' ? 'active' : ''; ?>">
                Dashboard
            </a>
            <a href="Staff_dashboard.php?view=sale_add"
               class="nav-link <?php echo $view === 'sale_add' ? 'active' : ''; ?>">
                Record Sale
            </a>
            <a href="Staff_dashboard.php?view=sales"
               class="nav-link <?php echo $view === 'sales' ? 'active' : ''; ?>">
                My Sales
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="login.php?action=logout" class="nav-link logout">Logout</a>
        </div>
    </aside>
    <main class="main">
        <header class="topbar">
            <div>
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="topbar-sub">
                    <?php echo htmlspecialchars($pageSub[$view] ?? ''); ?>
                </p>
            </div>
        </header>
