<?php
/**
 * views/record_sale.php — Record a new sale
 *
 * Requires: $products (array)
 */
?>
<div class="form-card">
    <h2>Record Sale</h2>
    <p class="form-hint">
        Selling reduces stock and generates a bill automatically.
        13% VAT will be added to the subtotal.
    </p>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <p>No products in stock. Add a product first.</p>
            <a href="dashboard.php?view=add" class="btn btn-primary">Add Product</a>
        </div>
    <?php else: ?>
        <form method="POST" action="dashboard.php?view=sale_add">
            <input type="hidden" name="action" value="sale">

            <div class="form-grid">
                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name"
                           value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                           placeholder="Walk-in Customer">
                </div>

                <div class="form-group">
                    <label>Customer Phone</label>
                    <input type="text" name="customer_phone"
                           value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>"
                           placeholder="Optional">
                </div>

                <div class="form-group full">
                    <label>Product <span class="req">*</span></label>
                    <select id="product_id" name="product_id" required>
                        <option value="">— Select a product —</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"
                                    data-price="<?php echo (float)$p['price']; ?>"
                                    data-stock="<?php echo (int)$p['quantity']; ?>">
                                <?php echo htmlspecialchars(
                                    $p['name']
                                    . ' (Product-ID: ' . $p['sku'] . ')'
                                    . ' — Stock: ' . $p['quantity']
                                ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity <span class="req">*</span></label>
                    <input type="number" id="quantity" name="quantity"
                           min="1" step="1" required
                           value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>">
                </div>

                <div class="form-group">
                    <label>Unit Price (Rs.) <span class="req">*</span></label>
                    <input type="number" id="unit_price" name="unit_price"
                           step="0.01" min="0" required
                           value="<?php echo htmlspecialchars($_POST['unit_price'] ?? ''); ?>"
                           placeholder="Auto-filled on product select">
                </div>

                <div class="form-group">
                    <label>Sale Date</label>
                    <input type="date" name="sale_date"
                           value="<?php echo htmlspecialchars(
                               $_POST['sale_date'] ?? date('Y-m-d')
                           ); ?>">
                </div>

                <div class="form-group">
                    <label>Note</label>
                    <input type="text" name="note"
                           value="<?php echo htmlspecialchars($_POST['note'] ?? ''); ?>"
                           placeholder="Optional">
                </div>
            </div>

            <!-- Live VAT preview -->
            <div id="tax-preview"
                 style="background:rgba(30,41,59,.6);border:1px solid #334155;
                        border-radius:8px;padding:.8rem 1rem;
                        margin:.5rem 0 1rem;font-size:.88rem;display:none;">
                <span>Subtotal: <strong id="prev-sub">—</strong></span>
                &nbsp;|&nbsp;
                <span>VAT 13%: <strong id="prev-tax">—</strong></span>
                &nbsp;|&nbsp;
                <span>Total: <strong id="prev-total">—</strong></span>
            </div>

            <div class="form-actions">
                <a href="dashboard.php?view=sales" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Record Sale</button>
            </div>
        </form>

        <script>
        (function () {
            var sel   = document.getElementById('product_id');
            var upEl  = document.getElementById('unit_price');
            var qEl   = document.getElementById('quantity');
            var prev  = document.getElementById('tax-preview');

            function fmt(n) {
                return 'Rs.' + parseFloat(n).toFixed(2);
            }

            function updatePreview() {
                var p = parseFloat(upEl.value) || 0;
                var q = parseInt(qEl.value)    || 0;
                if (p > 0 && q > 0) {
                    var sub = p * q;
                    var tax = sub * 0.13;
                    var tot = sub + tax;
                    document.getElementById('prev-sub').textContent   = fmt(sub);
                    document.getElementById('prev-tax').textContent   = fmt(tax);
                    document.getElementById('prev-total').textContent = fmt(tot);
                    prev.style.display = 'block';
                } else {
                    prev.style.display = 'none';
                }
            }

            sel.addEventListener('change', function () {
                var o = this.options[this.selectedIndex];
                if (o.value) {
                    upEl.value = o.dataset.price || '';
                    qEl.max    = o.dataset.stock || '';
                }
                updatePreview();
            });

            upEl.addEventListener('input', updatePreview);
            qEl.addEventListener('input',  updatePreview);
        })();
        </script>
    <?php endif; ?>
</div>