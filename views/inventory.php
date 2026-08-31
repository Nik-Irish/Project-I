<?php
/**
 * views/inventory.php — Single product stock & history detail
 *
 * Requires: $detailProduct, $partMovements, $partSales, LOW_STOCK_THRESHOLD
 */
?>

<!-- Product summary cards -->
<div class="stats">
    <div class="stat-card">
        <div class="stat-label"><?php echo htmlspecialchars($detailProduct['name']); ?></div>
        <div class="stat-value" style="font-size:1rem;">
            Product-ID: <?php echo htmlspecialchars($detailProduct['product_id']); ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Current Stock</div>
        <div class="stat-value
            <?php echo (int)$detailProduct['quantity'] === 0
                ? 'stock-zero'
                : ((int)$detailProduct['quantity'] <= LOW_STOCK_THRESHOLD
                    ? 'stock-low' : ''); ?>">
            <?php echo (int)$detailProduct['quantity']; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Price</div>
        <div class="stat-value">
            Rs.<?php echo number_format((float)$detailProduct['price'], 2); ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Category</div>
        <div class="stat-value" style="font-size:1rem;">
            <?php echo htmlspecialchars($detailProduct['category']); ?>
        </div>
    </div>
</div>

<!-- Quick stock controls -->
<div class="toolbar">
    <a href="dashboard.php?view=edit&id=<?php echo (int)$detailProduct['id']; ?>"
       class="btn btn-secondary">Edit Product</a>
</div>

<!-- Description -->
<?php if (!empty($detailProduct['description'])): ?>
    <div class="form-card" style="margin-bottom:1rem;">
        <p style="color:#cbd5e1;font-size:.9rem;line-height:1.6;">
            <?php echo htmlspecialchars($detailProduct['description']); ?>
        </p>
    </div>
<?php endif; ?>

<!-- Stock movements -->
<?php if (!empty($partMovements)): ?>
    <div class="table-wrap">
        <div class="section-head">Stock Movements</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Note</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($partMovements as $m): ?>
                <tr>
                    <td>
                        <span class="badge type-<?php echo htmlspecialchars($m['type']); ?>">
                            <?php echo htmlspecialchars($m['type']); ?>
                        </span>
                    </td>
                    <td><?php echo (int)$m['amount']; ?></td>
                    <td><?php echo (int)$m['balance_after']; ?></td>
                    <td><?php echo htmlspecialchars($m['note'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($m['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Sales history for this product -->
<?php if (!empty($partSales)): ?>
    <div class="table-wrap">
        <div class="section-head">Sales History</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Qty</th>
                    <th>Total (incl. VAT)</th>
                    <th>Customer</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($partSales as $ps): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($ps['bill_no']); ?></code></td>
                    <td><?php echo (int)$ps['quantity']; ?></td>
                    <td>Rs.<?php echo number_format((float)$ps['total'], 2); ?></td>
                    <td><?php echo htmlspecialchars($ps['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($ps['sale_date']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>