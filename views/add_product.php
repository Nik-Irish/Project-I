<?php
/**
 * views/add_product.php — Add a new product
 */
?>
<a href="dashboard.php"></a> 
<div class="form-card">
    <h2>New Product</h2>
    <p class="form-hint">Fill in the details below to add a product to inventory.</p>

    <form method="POST" action="dashboard.php?view=add">
        <input type="hidden" name="action" value="add">

        <div class="form-grid">
            <div class="form-group">
                <label>Product Name <span class="req">*</span></label>
                <input type="text" name="name" required
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Product-ID <span class="req">*</span></label>
                <input type="text" name="sku" required
                       value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category"
                       value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Price (Rs.) <span class="req">*</span></label>
                <input type="number" name="price" step="0.01" min="0" required
                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Initial Quantity <span class="req">*</span></label>
                <input type="number" name="quantity" min="0" step="1" required
                       value="<?php echo htmlspecialchars($_POST['quantity'] ?? '0'); ?>">
            </div>

            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" rows="3"
                          placeholder="Optional notes about this product">
<?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Product</button>
        </div>
    </form>
</div>