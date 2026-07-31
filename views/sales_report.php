<?php
/**
 * views/sales_report.php — Sales transaction list with filters
 *
 * Requires: $filteredSales, $products, $categories,
 *           $reportFrom, $reportTo, $reportProductId, $reportCategory
 */
?>

<!-- Filter toolbar -->
<div class="toolbar">
    <form class="search-form" method="GET" action="dashboard.php">
        <input type="hidden" name="view" value="sales">

        <input type="date" name="from"
               value="<?php echo htmlspecialchars($reportFrom); ?>">

        <input type="date" name="to"
               value="<?php echo htmlspecialchars($reportTo); ?>">

        <select name="product_id">
            <option value="">All products</option>
            <?php foreach ($products as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"
                        <?php echo $reportProductId === (int)$p['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['name']); ?>
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

        <?php if ($reportFrom !== '' || $reportTo !== ''
               || $reportProductId > 0  || $reportCategory !== ''): ?>
            <a href="dashboard.php?view=sales" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Results table -->
<div class="table-wrap">
    <?php if (empty($filteredSales)): ?>
        <div class="empty-state"><p>No sales found for the selected filters.</p></div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Product</th>
                    <th>Product-ID</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total (incl. VAT)</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filteredSales as $s): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($s['bill_no']); ?></code></td>
                    <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['sku']); ?></td>
                    <td><?php echo (int)$s['quantity']; ?></td>
                    <td>Rs.<?php echo number_format((float)$s['unit_price'], 2); ?></td>
                    <td>Rs.<?php echo number_format((float)$s['total'],      2); ?></td>
                    <td><?php echo htmlspecialchars($s['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['sale_date']); ?></td>
                    <td class="row-actions">
                        <a href="dashboard.php?view=bill&id=<?php echo (int)$s['id']; ?>"
                           class="btn btn-sm btn-info">Bill</a>
                        <a href="dashboard.php?download=pdf&sale_id=<?php echo (int)$s['id']; ?>"
                           class="btn btn-sm btn-secondary">PDF</a>
                        <form method="POST" class="inline-form"
                              onsubmit="return confirm('Delete this sale and restore stock?');">
                            <input type="hidden" name="action" value="delete_sale">
                            <input type="hidden" name="id"     value="<?php echo (int)$s['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>