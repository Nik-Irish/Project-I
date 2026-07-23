<div class="stats">
                    <div class="stat-card">
                        <div class="stat-label">Transactions</div>
                        <div class="stat-value"><?php echo count($filteredSales); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Units Sold</div>
                        <div class="stat-value"><?php echo $salesUnits; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value">$<?php echo number_format($salesTotal, 2); ?></div>
                    </div>
                </div>

                <div class="filter-card">
                    <form method="GET" action="dashboard.php" class="filter-form">
                        <input type="hidden" name="view" value="sales">
                        <div class="form-group">
                            <label for="from">From</label>
                            <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($reportFrom); ?>">
                        </div>
                        <div class="form-group">
                            <label for="to">To</label>
                            <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($reportTo); ?>">
                        </div>
                        <div class="form-group">
                            <label for="product_id">Product</label>
                            <select id="product_id" name="product_id">
                                <option value="0">All products</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo (int)$p['id']; ?>" <?php echo $reportProductId === (int)$p['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name'] . ' (' . $p['sku'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">All categories</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $reportCategory === $c ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Apply Filter</button>
                            <a href="dashboard.php?view=sales" class="btn btn-ghost">Reset</a>
                            <a href="dashboard.php?view=sale_add" class="btn btn-secondary">+ New Sale</a>
                        </div>
                    </form>
                </div>

                <div class="table-wrap">
                    <?php if (empty($filteredSales)): ?>
                        <div class="empty-state">
                            <p>No sales match this filter.</p>
                            <a href="dashboard.php?view=sale_add" class="btn btn-primary">Record a sale</a>
                        </div>
                    <?php else: ?>
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th>Bill No</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>Bill / PDF</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredSales as $s): ?>
                                    <?php
                                    $sBillNo = $s['bill_no'] ?? makeBillNo((int)$s['id']);
                                    ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($sBillNo); ?></code></td>
                                        <td><?php echo htmlspecialchars($s['sale_date'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($s['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                        <td>
                                            <a class="link-name" href="dashboard.php?view=inventory&id=<?php echo (int)$s['product_id']; ?>">
                                                <?php echo htmlspecialchars($s['product_name']); ?>
                                            </a>
                                            <div class="cell-desc"><?php echo htmlspecialchars($s['sku'] ?? ''); ?></div>
                                        </td>
                                        <td><?php echo (int)$s['quantity']; ?></td>
                                        <td>$<?php echo number_format((float)$s['unit_price'], 2); ?></td>
                                        <td><strong>$<?php echo number_format((float)$s['total'], 2); ?></strong></td>
                                        <td class="row-actions">
                                            <a href="dashboard.php?view=bill&id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                            <a href="dashboard.php?download=pdf&sale_id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-primary">PDF</a>
                                        </td>
                                        <td>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Delete this sale and restore stock?');">
                                                <input type="hidden" name="action" value="delete_sale">
                                                <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="totals-row">
                                    <td colspan="4"><strong>Totals</strong></td>
                                    <td><strong><?php echo $salesUnits; ?></strong></td>
                                    <td></td>
                                    <td><strong>$<?php echo number_format($salesTotal, 2); ?></strong></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>
                </div>
