<div class="form-card">
                    <h2>Record Sale</h2>
                    <p class="form-hint">Selling reduces stock and generates a bill you can download as PDF.</p>
                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <p>Add products before recording sales.</p>
                            <a href="dashboard.php?view=add" class="btn btn-primary">Add Product</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="dashboard.php?view=sale_add">
                            <input type="hidden" name="action" value="sale">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="customer_name">Customer Name</label>
                                    <input type="text" id="customer_name" name="customer_name"
                                        value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                                        placeholder="Walk-in Customer">
                                </div>
                                <div class="form-group">
                                    <label for="customer_phone">Customer Phone</label>
                                    <input type="text" id="customer_phone" name="customer_phone"
                                        value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>"
                                        placeholder="Optional">
                                </div>
                                <div class="form-group full">
                                    <label for="product_id">Product / Part <span class="required">*</span></label>
                                    <select id="product_id" name="product_id" required>
                                        <option value="">— Select product —</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>"
                                                data-price="<?php echo htmlspecialchars((string)$p['price']); ?>"
                                                data-stock="<?php echo (int)$p['quantity']; ?>"
                                                <?php echo (isset($_POST['product_id']) && (int)$_POST['product_id'] === (int)$p['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['name'] . ' (' . $p['sku'] . ') — stock: ' . $p['quantity']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="quantity">Quantity Sold <span class="required">*</span></label>
                                    <input type="number" id="quantity" name="quantity" min="1" step="1" required
                                        value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="unit_price">Unit Price ($) <span class="required">*</span></label>
                                    <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required
                                        value="<?php echo htmlspecialchars($_POST['unit_price'] ?? ''); ?>"
                                        placeholder="Uses product price if empty">
                                </div>
                                <div class="form-group">
                                    <label for="sale_date">Sale Date <span class="required">*</span></label>
                                    <input type="date" id="sale_date" name="sale_date" required
                                        value="<?php echo htmlspecialchars($_POST['sale_date'] ?? date('Y-m-d')); ?>">
                                </div>
                                <div class="form-group full">
                                    <label for="note">Note</label>
                                    <input type="text" id="note" name="note"
                                        value="<?php echo htmlspecialchars($_POST['note'] ?? ''); ?>"
                                        placeholder="Optional note on the bill">
                                </div>
                            </div>
                            <div class="form-actions">
                                <a href="dashboard.php?view=sales" class="btn btn-ghost">View Sales Report</a>
                                <button type="submit" class="btn btn-primary">Save Sale &amp; Generate Bill</button>
                            </div>
                        </form>
                        <script>
                            // Pure JS — no libraries: fill unit price from selected product
                            (function () {
                                var sel = document.getElementById('product_id');
                                var price = document.getElementById('unit_price');
                                if (!sel || !price) return;
                                function fill() {
                                    var opt = sel.options[sel.selectedIndex];
                                    if (opt && opt.getAttribute('data-price') && price.value === '') {
                                        price.value = opt.getAttribute('data-price');
                                    }
                                }
                                sel.addEventListener('change', function () {
                                    var opt = sel.options[sel.selectedIndex];
                                    if (opt && opt.getAttribute('data-price')) {
                                        price.value = opt.getAttribute('data-price');
                                    }
                                });
                                fill();
                            })();
                        </script>
                    <?php endif; ?>
                </div>
