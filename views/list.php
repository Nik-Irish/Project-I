<div class="stats">
                    <div class="stat-card">
                        <div class="stat-label">Total Products</div>
                        <div class="stat-value"><?php echo $totalProducts; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Stock Units</div>
                        <div class="stat-value"><?php echo $totalStock; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Inventory Value</div>
                        <div class="stat-value">$<?php echo number_format($totalValue, 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Low Stock (≤<?php echo LOW_STOCK_THRESHOLD; ?>)</div>
                        <div class="stat-value"><?php echo $lowStockCount; ?></div>
                    </div>
                </div>

                <div class="toolbar">
                    <form class="search-form" method="GET" action="dashboard.php">
                        <input type="hidden" name="view" value="list">
                        <input type="search" name="q" placeholder="Search name, SKU, category..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-secondary">Search</button>
                        <?php if ($search !== ''): ?>
                            <a href="dashboard.php?view=list" class="btn btn-ghost">Clear</a>
                        <?php endif; ?>
                    </form>
                    <div class="toolbar-right">
                        <a href="dashboard.php?view=sale_add" class="btn btn-secondary">Record Sale</a>
                        <a href="dashboard.php?view=add" class="btn btn-primary">+ Add Product</a>
                    </div>
                </div>

                <div class="table-wrap">
                    <?php if (empty($filtered)): ?>
                        <div class="empty-state">
                            <p>No products found.</p>
                            <a href="dashboard.php?view=add" class="btn btn-primary">Add your first product</a>
                        </div>
                    <?php else: ?>
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Input / Output</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filtered as $p): ?>
                                    <tr>
                                        <td><?php echo (int)$p['id']; ?></td>
                                        <td>
                                            <strong>
                                                <a class="link-name" href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>">
                                                    <?php echo htmlspecialchars($p['name']); ?>
                                                </a>
                                            </strong>
                                            <?php if (!empty($p['description'])): ?>
                                                <div class="cell-desc"><?php echo htmlspecialchars(shortText($p['description'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($p['sku']); ?></code></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($p['category']); ?></span></td>
                                        <td>$<?php echo number_format((float)$p['price'], 2); ?></td>
                                        <td>
                                            <span class="stock <?php echo (int)$p['quantity'] === 0 ? 'stock-zero' : ((int)$p['quantity'] <= LOW_STOCK_THRESHOLD ? 'stock-low' : ''); ?>">
                                                <?php echo (int)$p['quantity']; ?>
                                            </span>
                                        </td>
                                        <td class="stock-actions">
                                            <form method="POST" class="inline-form" title="Add stock (Input)">
                                                <input type="hidden" name="action" value="stock_in">
                                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                <input type="number" name="amount" min="1" value="1" class="qty-input" required>
                                                <button type="submit" class="btn btn-sm btn-in">In</button>
                                            </form>
                                            <form method="POST" class="inline-form" title="Remove stock (Output)">
                                                <input type="hidden" name="action" value="stock_out">
                                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                <input type="number" name="amount" min="1" value="1" class="qty-input" required>
                                                <button type="submit" class="btn btn-sm btn-out">Out</button>
                                            </form>
                                        </td>
                                        <td class="row-actions">
                                            <a href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-info">Details</a>
                                            <a href="dashboard.php?view=edit&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-secondary">Modify</a>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Delete this product?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
