<div class="form-card">
                    <h2>Modify Product #<?php echo (int)$editProduct['id']; ?></h2>
                    <p class="form-hint">Update product details below.</p>
                    <form method="POST" action="dashboard.php?view=edit&id=<?php echo (int)$editProduct['id']; ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo (int)$editProduct['id']; ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Product Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" required
                                    value="<?php echo htmlspecialchars($editProduct['name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="sku">SKU / Part No. <span class="required">*</span></label>
                                <input type="text" id="sku" name="sku" required
                                    value="<?php echo htmlspecialchars($editProduct['sku']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" id="category" name="category"
                                    value="<?php echo htmlspecialchars($editProduct['category']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="price">Price ($) <span class="required">*</span></label>
                                <input type="number" id="price" name="price" step="0.01" min="0" required
                                    value="<?php echo htmlspecialchars((string)$editProduct['price']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantity <span class="required">*</span></label>
                                <input type="number" id="quantity" name="quantity" min="0" step="1" required
                                    value="<?php echo htmlspecialchars((string)$editProduct['quantity']); ?>">
                            </div>
                            <div class="form-group full">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($editProduct['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a>
                            <a href="dashboard.php?view=inventory&id=<?php echo (int)$editProduct['id']; ?>" class="btn btn-secondary">View Details</a>
                            <button type="submit" class="btn btn-primary">Update Product</button>
                        </div>
                    </form>
                </div>
