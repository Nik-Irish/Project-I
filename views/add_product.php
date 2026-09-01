<?php
/**
 * views/add_product.php — Add a new product
 *
 * Requires: $products (existing product list, for Product-ID suggestions)
 */
$existingProducts = [];
foreach ($products ?? [] as $p) {
    $existingProducts[] = [
        'code'     => (string)($p['product_id'] ?? ''),
        'name'     => (string)($p['name'] ?? ''),
        'category' => (string)($p['category'] ?? ''),
        'price'    => (float)($p['price'] ?? 0),
    ];
}
?>
<link rel="stylesheet" href="css/products.css">

<div class="form-card">
    <h2>New Product</h2>
    <p class="form-hint">Fill in the details below to add a product to inventory.</p>

    <form method="POST" action="dashboard.php?view=add">
        <input type="hidden" name="action" value="add">

        <div class="form-grid">
            <div class="form-group">
                <label>Product Name <span class="req">*</span></label>
                <input type="text" name="name" id="f_name" required
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Product-ID <span class="req">*</span></label>
                <div class="pid-wrap">
                    <input type="text" name="product_id" id="f_product_id" autocomplete="off"
                           value="<?php echo htmlspecialchars($_POST['product_id'] ?? ''); ?>">
                    <div class="pid-list" id="pidList"></div>
                </div>
            </div>

            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" id="f_category"
                       value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Price (Rs.) <span class="req">*</span></label>
                <input type="number" name="price" id="f_price" step="0.01" min="0" required
                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Initial Quantity <span class="req">*</span></label>
                <input type="number" name="quantity" min="0" step="1" required
                       value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>">
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

<script>
const existingProducts = <?php
    echo json_encode($existingProducts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>;

const pidInput = document.getElementById('f_product_id');
const pidList  = document.getElementById('pidList');

function findByCode(code) {
    const c = code.trim().toLowerCase();
    return existingProducts.find(p => p.code.toLowerCase() === c) || null;
}

function closePidList() {
    pidList.style.display = 'none';
}

function renderPidList() {
    const q = pidInput.value.trim().toLowerCase();
    const matches = existingProducts.filter(p =>
        !q || p.code.toLowerCase().includes(q) || p.name.toLowerCase().includes(q));

    pidList.innerHTML = '';
    if (!matches.length) {
        closePidList();
        return;
    }
    matches.forEach(p => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pid-item';

        const code = document.createElement('span');
        code.className = 'pid-code';
        code.textContent = p.code;
        btn.appendChild(code);

        const meta = document.createElement('span');
        meta.className = 'pid-meta';
        meta.textContent = p.name + ' · ' + (p.category || 'General') + ' · Rs. ' + Number(p.price).toFixed(2);
        btn.appendChild(meta);

        // mousedown fires before the input's blur, so the click always lands
        btn.addEventListener('mousedown', e => {
            e.preventDefault();
            pidInput.value = p.code;
            fillFromProduct(p);
            closePidList();
        });
        pidList.appendChild(btn);
    });
    pidList.style.display = 'block';
}

// typing an exact Product-ID auto-fills name, category, and price from the DB
function fillFromProduct(p) {
    document.getElementById('f_name').value = p.name;
    document.getElementById('f_category').value = p.category;
    document.getElementById('f_price').value = Number(p.price).toFixed(2);
}

pidInput.addEventListener('focus', renderPidList);
pidInput.addEventListener('input', () => {
    renderPidList();
    const p = findByCode(pidInput.value);
    if (p) {
        fillFromProduct(p);
    }
});
document.addEventListener('click', e => {
    if (!e.target.closest('.pid-wrap')) closePidList();
});
</script>