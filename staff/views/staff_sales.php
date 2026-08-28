<?php
/**
 * views/staff_sales.php
 * Simple sales history for staff.
 */
?>
<div class="toolbar">
    <div class="toolbar-left"><strong>My Sales</strong></div>
</div>

<div class="table-wrap">
    <?php if (empty($sales)): ?>
        <div class="empty-state"><p>No sales recorded yet.</p></div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $sale): ?>
                <tr>
                    <td><?php echo htmlspecialchars($sale['bill_no']); ?></td>
                    <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                    <td><?php echo (int)$sale['quantity']; ?></td>
                    <td>Rs.<?php echo number_format((float)$sale['total'], 2); ?></td>
                    <td><?php echo htmlspecialchars($sale['sale_date']); ?></td>
                    <td>
                        <a href="Staff_dashboard.php?download=pdf&sale_id=<?php echo (int)$sale['id']; ?>"
                           class="btn btn-sm btn-secondary">PDF</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
