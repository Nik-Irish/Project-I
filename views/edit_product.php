<?php
/**
 * views/edit_product.php — Edit an existing product
 *
 * Requires: $editProduct (associative array)
 */

if (!is_array($editProduct)) {
    return;
}
?>
<div class="form-card">
    <h2>Edit Product #<?php echo (int)$editProduct['id']; ?></h2>

    <form method="POST"
          action="dashboard.php?view=edit&id=<?php echo (int)$editProduct['id']; ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id"     value="<?php echo (int)$editProduct['id']; ?>">

        <div class="form-grid">
            <div class="form-group">
                <label>Product Name <span class="req">*</span></label>
                <input type="text" name="name" required readonly
                       value="<?php echo htmlspecialchars((string)($editProduct['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group">
                <label>Product-ID <span class="req">*</span></label>
                <input type="text" name="product_id" required readonly
                       value="<?php echo htmlspecialchars((string)($editProduct['product_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" readonly
                       value="<?php echo htmlspecialchars((string)($editProduct['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group">
                <label>Price (Rs.) <span class="req">*</span></label>
                <input type="number" name="price" step="0.01" min="0" required
                       value="<?php echo htmlspecialchars((string)$editProduct['price']); ?>">
            </div>

            <div class="form-group">
                <label>Quantity <span class="req">*</span></label>
                <input type="number" name="quantity" min="0" step="1" required
                       value="<?php echo htmlspecialchars((string)$editProduct['quantity']); ?>">
            </div>

            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" rows="3">
<?php echo htmlspecialchars($editProduct['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Product</button>
        </div>
    </form>
</div>