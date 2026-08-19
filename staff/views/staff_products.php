<?php
/**
 * views/staff_products.php
 * Simple product listing for staff.
 */
?>
<div class="toolbar">
    <div class="toolbar-left"><strong>Products</strong></div>
    <div class="toolbar-right">
        <a href="Staff_dashboard.php?view=sale_add" class="btn btn-primary">Record Sale</a>
    </div>
</div>

<div class="table-wrap">
    <?php if (empty($products)): ?>
        <div class="empty-state"><p>No products available.</p></div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo htmlspecialchars($product['sku']); ?></td>
                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                    <td>Rs.<?php echo number_format((float)$product['price'], 2); ?></td>
                    <td><?php echo (int)$product['quantity']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
