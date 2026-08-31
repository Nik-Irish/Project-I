<?php
/**
 * views/sales_report.php — Sales transaction list with filters
 *
 * Requires: $filteredSales, $products, $categories,
 *           $reportFrom, $reportTo, $reportProductId, $reportCategory
 */
$filteredSales = $filteredSales ?? [];
$products = $products ?? [];
$categories = $categories ?? [];
$reportFrom = $reportFrom ?? '';
$reportTo = $reportTo ?? '';
$reportProductId = $reportProductId ?? '';
$reportCategory = $reportCategory ?? '';
?>

<div class="toolbar">
    <form class="search-form" method="GET" action="dashboard.php">
        <input type="hidden" name="view" value="sales">

        <select name="product_id">
            <option value="">All products</option>
            <?php foreach ($products as $p): ?>
                <option value="<?php echo htmlspecialchars($p['product_id']); ?>"
                    <?php echo (string)$reportProductId === (string)$p['product_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['name'] . ' (' . $p['product_id'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>"
                    <?php echo $reportCategory === $c ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-secondary">Filter</button>

        <?php if ($reportFrom !== '' || $reportTo !== '' || $reportProductId !== '' || $reportCategory !== ''): ?>
            <a href="dashboard.php?view=sales" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-wrap">
    <?php if (empty($filteredSales)): ?>
        <div class="empty-state"><p>No sales found for the selected filters.</p></div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Product</th>
                    <th>Product_ID</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                    <th>Customer</th>
                    <th>Recorded By</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filteredSales as $s): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($s['bill_no']); ?></code></td>
                    <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['product_sku']); ?></td>
                    <td><?php echo (int)$s['quantity']; ?></td>
                    <td>Rs.<?php echo number_format((float)$s['unit_price'], 2); ?></td>
                    <td>Rs.<?php echo number_format((float)$s['total'], 2); ?></td>
                    <td><?php echo htmlspecialchars($s['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['staff_name'] ?? 'Admin'); ?></td>
                    <td><?php echo htmlspecialchars($s['sale_date']); ?></td>
                    <td>
                        <a href="dashboard.php?download=pdf&sale_id=<?php echo (int)$s['id']; ?>"
                           class="btn btn-sm btn-secondary">PDF</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
