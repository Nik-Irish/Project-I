<div class="form-card">
                    <h2>New Product</h2>
                    <p class="form-hint">Fill in the details to add a product / part to inventory.</p>
                    <form method="POST" action="dashboard.php?view=add">
                        <input type="hidden" name="action" value="add">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Product Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" required
                                    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                    placeholder="e.g. Brake Pad Set">
                            </div>
                            <div class="form-group">
                                <label for="sku">SKU / Part No. <span class="required">*</span></label>
                                <input type="text" id="sku" name="sku" required
                                    value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>"
                                    placeholder="e.g. BP-001">
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" id="category" name="category"
                                    value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>"
                                    placeholder="e.g. Brakes">
                            </div>
                            <div class="form-group">
                                <label for="price">Price ($) <span class="required">*</span></label>
                                <input type="number" id="price" name="price" step="0.01" min="0" required
                                    value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                    placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label for="quantity">Initial Quantity <span class="required">*</span></label>
                                <input type="number" id="quantity" name="quantity" min="0" step="1" required
                                    value="<?php echo htmlspecialchars($_POST['quantity'] ?? '0'); ?>">
                            </div>
                            <div class="form-group full">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3"
                                    placeholder="Optional notes"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Product</button>
                        </div>
                    </form>
                </div>
