<?php
/**
 * views/staff_record_sale.php
 * Simple sale form for staff.
 */
?>
<div class="form-card">
    <form method="POST" action="staff_dashboard.php?view=sale_add">
        <input type="hidden" name="action" value="sale">

        <div class="form-group">
            <label>Product</label>
            <select name="product_id" id="product_id" required>
                <option value="">Select a product</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo (int)$product['id']; ?>" data-price="<?php echo htmlspecialchars($product['price']); ?>" data-stock="<?php echo (int)$product['quantity']; ?>">
                        <?php echo htmlspecialchars($product['name'] . ' (SKU: ' . $product['sku'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" id="quantity" min="1" value="1" required>
        </div>

        <div class="form-group">
            <label>Unit Price</label>
            <input type="number" name="unit_price" id="unit_price" step="0.01" min="0" required>
        </div>

        <div class="form-group">
            <label>Customer Name</label>
            <input type="text" name="customer_name" placeholder="Walk-in Customer">
        </div>

        <div class="form-group">
            <label>Sale Date</label>
            <input type="date" name="sale_date" value="<?php echo date('Y-m-d'); ?>">
        </div>

        <button type="submit" class="btn btn-primary">Save Sale</button>
    </form>
</div>

<script>
(function () {
    var product = document.getElementById('product_id');
    var price = document.getElementById('unit_price');
    product.addEventListener('change', function () {
        var selected = this.options[this.selectedIndex];
        if (selected.value) {
            price.value = selected.dataset.price || '';
        }
    });
})();
</script>
