<?php
/**
 * views/sales_summary.php — Aggregated sales totals
 *
 * Requires: $filteredSales, $salesUnits, $salesTotal,
 *           $salesByProduct, $salesByDay,
 *           $salesByRecorderProduct,
 *           $products, $categories,
 *           $reportFrom, $reportTo, $reportProductId, $reportCategory
 */
$filteredSales = $filteredSales ?? [];
$salesUnits = $salesUnits ?? 0;
$salesTotal = $salesTotal ?? 0;
$salesByProduct = $salesByProduct ?? [];
$salesByDay = $salesByDay ?? [];
$salesByRecorderProduct = $salesByRecorderProduct ?? [];
$products = $products ?? [];
$categories = $categories ?? [];
$reportFrom = $reportFrom ?? '';
$reportTo = $reportTo ?? '';
$reportProductId = $reportProductId ?? 0;
$reportCategory = $reportCategory ?? '';
?>

<!-- Filter toolbar -->
<div class="toolbar">
    <form class="search-form" method="GET" action="dashboard.php">
        <input type="hidden" name="view" value="report">

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
    </form>
</div>

<!-- Summary stat cards -->
<div class="stats">
    <div class="stat-card">
        <div class="stat-label">Units Sold</div>
        <div class="stat-value"><?php echo $salesUnits; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Revenue (incl. VAT)</div>
        <div class="stat-value">Rs.<?php echo number_format($salesTotal, 2); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Transactions</div>
        <div class="stat-value"><?php echo count($filteredSales); ?></div>
    </div>
</div>

<!-- By product -->
<?php if (!empty($salesByProduct)): ?>
    <div class="table-wrap">
        <div class="section-head">Sales by Product</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Product-ID</th>
                    <th>Units Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($salesByProduct as $sp): ?>
                <tr>
                    <td><?php echo htmlspecialchars($sp['name']); ?></td>
                    <td><?php echo htmlspecialchars($sp['product_sku']); ?></td>
                    <td><?php echo $sp['qty']; ?></td>
                    <td>Rs.<?php echo number_format($sp['total'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- By staff/admin and product -->
<?php if (!empty($salesByRecorderProduct)): ?>
    <div class="table-wrap">
        <div class="section-head">Sales by Staff/Admin and Product</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Recorded By</th>
                    <th>Product</th>
                    <th>Product-ID</th>
                    <th>Units Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($salesByRecorderProduct as $recorder => $recorderSales): ?>
                <?php foreach ($recorderSales as $recorderSale): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($recorder); ?></td>
                        <td><?php echo htmlspecialchars($recorderSale['name']); ?></td>
                        <td><?php echo htmlspecialchars($recorderSale['product_sku']); ?></td>
                        <td><?php echo $recorderSale['qty']; ?></td>
                        <td>Rs.<?php echo number_format($recorderSale['total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- By day -->
<?php if (!empty($salesByDay)): ?>
    <div class="table-wrap">
        <div class="section-head">Sales by Day</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Units Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($salesByDay as $day => $sd): ?>
                <tr>
                    <td><?php echo htmlspecialchars($day); ?></td>
                    <td><?php echo $sd['qty']; ?></td>
                    <td>Rs.<?php echo number_format($sd['total'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>