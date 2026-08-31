<?php
/**
 * views/products.php — Product list
 *
 * Requires: $filtered, $search, $totalProducts, $totalStock,
 *           $totalValue, $lowStockCount, LOW_STOCK_THRESHOLD
 */

// defensive defaults — $search/$filtered are normally set by dashboard/filters.php
$search = $search ?? '';
$filtered = $filtered ?? [];

?>

<!-- Stats row -->
<div class="stats">
    <div class="stat-card">
        <div class="stat-label">Total Products</div>
        <div class="stat-value"><?php echo $totalProducts; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Stock</div>
        <div class="stat-value"><?php echo $totalStock; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Inventory Value</div>
        <div class="stat-value">Rs.<?php echo number_format($totalValue, 2); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Low Stock (≤<?php echo LOW_STOCK_THRESHOLD; ?>)</div>
        <div class="stat-value"><?php echo $lowStockCount; ?></div>
    </div>
</div>

<!-- Toolbar -->
<!-- View-specific styles (products list) -->
<link rel="stylesheet" href="css/products.css">

<div class="toolbar">
    <form class="search-form" method="GET" action="dashboard.php">
        <input type="hidden" name="view" value="list">
        <input type="search" name="q"
               placeholder="Search products..."
               value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="dashboard.php?view=list" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>
    <div class="toolbar-right">
        <a href="dashboard.php?view=sale_add" class="btn btn-secondary">Record Sale</a>
        <a href="dashboard.php?view=add"      class="btn btn-primary">+ Add Product</a>
    </div>
</div>

<!-- Table -->
<div class="table-wrap">
    <?php if (empty($filtered)): ?>
        <div class="empty-state">
            <p>No products found.</p>
            <a href="dashboard.php?view=add" class="btn btn-primary">Add first product</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Product-ID</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filtered as $p): ?>
                <tr>
                    <td><?php echo (int)$p['id']; ?></td>

                    <td>
                        <a class="link-name"
                           href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </a>
                        <?php if (!empty($p['description'])): ?>
                            <div class="cell-desc">
                                <?php echo htmlspecialchars(shortText($p['description'])); ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td><code><?php echo htmlspecialchars($p['product_id']); ?></code></td>

                    <td>
                        <span class="badge"><?php echo htmlspecialchars($p['category']); ?></span>
                    </td>

                    <td>Rs.<?php echo number_format((float)$p['price'], 2); ?></td>
                    <td>
                        <span class="stock
                            <?php echo (int)$p['quantity'] === 0
                                ? 'stock-zero'
                                : ((int)$p['quantity'] <= LOW_STOCK_THRESHOLD
                                    ? 'stock-low' : ''); ?>">
                            <?php echo (int)$p['quantity']; ?>
                        </span>
                    </td>

                    <td>Rs.<?php echo number_format((float)$p['price'] * (int)$p['quantity'], 2); ?></td>


                    <!-- Row actions -->
                    <td class="row-actions">
                        <a href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>"
                           class="btn btn-sm btn-info">Details</a>
                        <a href="dashboard.php?view=edit&id=<?php echo (int)$p['id']; ?>"
                           class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" class="inline-form"
                              onsubmit="return confirm('Delete this product?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id"     value="<?php echo (int)$p['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>