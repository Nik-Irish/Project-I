<?php
                $dp = $detailProduct;
                $partSoldQty = 0;
                $partSoldRev = 0.0;
                foreach ($partSales as $ps) {
                    $partSoldQty += (int)$ps['quantity'];
                    $partSoldRev += (float)$ps['total'];
                }
                $inTotal = 0;
                $outTotal = 0;
                foreach ($partMovements as $m) {
                    if ($m['type'] === 'in') {
                        $inTotal += (int)$m['amount'];
                    } elseif ($m['type'] === 'out' || $m['type'] === 'sale') {
                        $outTotal += (int)$m['amount'];
                    }
                }
                ?>
                <div class="detail-header">
                    <div>
                        <h2 class="detail-title"><?php echo htmlspecialchars($dp['name']); ?></h2>
                        <p class="detail-meta">
                            <code><?php echo htmlspecialchars($dp['sku']); ?></code>
                            <span class="badge"><?php echo htmlspecialchars($dp['category']); ?></span>
                            · ID #<?php echo (int)$dp['id']; ?>
                        </p>
                        <?php if (!empty($dp['description'])): ?>
                            <p class="detail-desc"><?php echo htmlspecialchars($dp['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="detail-actions">
                        <a href="dashboard.php?view=edit&id=<?php echo (int)$dp['id']; ?>" class="btn btn-secondary">Modify</a>
                        <a href="dashboard.php?view=sale_add" class="btn btn-primary">Record Sale</a>
                        <a href="dashboard.php?view=list" class="btn btn-ghost">Back to List</a>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-label">Current Stock</div>
                        <div class="stat-value <?php echo (int)$dp['quantity'] <= LOW_STOCK_THRESHOLD ? 'text-warn' : ''; ?>">
                            <?php echo (int)$dp['quantity']; ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Unit Price</div>
                        <div class="stat-value">$<?php echo number_format((float)$dp['price'], 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Stock Value</div>
                        <div class="stat-value">$<?php echo number_format((float)$dp['price'] * (int)$dp['quantity'], 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Lifetime Sold</div>
                        <div class="stat-value"><?php echo $partSoldQty; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Revenue (this part)</div>
                        <div class="stat-value">$<?php echo number_format($partSoldRev, 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total In / Out</div>
                        <div class="stat-value small-stat">+<?php echo $inTotal; ?> / −<?php echo $outTotal; ?></div>
                    </div>
                </div>

                <!-- Quick stock adjust on detail page -->
                <div class="filter-card quick-stock">
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="stock_in">
                        <input type="hidden" name="id" value="<?php echo (int)$dp['id']; ?>">
                        <input type="hidden" name="return_view" value="inventory">
                        <label>Stock In</label>
                        <input type="number" name="amount" min="1" value="1" class="qty-input" required>
                        <button type="submit" class="btn btn-sm btn-in">Add In</button>
                    </form>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="stock_out">
                        <input type="hidden" name="id" value="<?php echo (int)$dp['id']; ?>">
                        <input type="hidden" name="return_view" value="inventory">
                        <label>Stock Out</label>
                        <input type="number" name="amount" min="1" value="1" class="qty-input" required>
                        <button type="submit" class="btn btn-sm btn-out">Take Out</button>
                    </form>
                </div>

                <div class="two-col">
                    <div class="table-wrap">
                        <div class="section-head">Movement History</div>
                        <?php if (empty($partMovements)): ?>
                            <div class="empty-state"><p>No movements recorded yet for this part.</p></div>
                        <?php else: ?>
                            <table class="product-table">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Balance After</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($partMovements as $m): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($m['created_at'] ?? ''); ?></td>
                                            <td>
                                                <?php
                                                $t = $m['type'] ?? '';
                                                $cls = 'type-badge type-' . $t;
                                                $label = [
                                                    'in' => 'IN',
                                                    'out' => 'OUT',
                                                    'sale' => 'SALE',
                                                    'adjust' => 'ADJUST',
                                                ][$t] ?? strtoupper($t);
                                                ?>
                                                <span class="<?php echo htmlspecialchars($cls); ?>"><?php echo htmlspecialchars($label); ?></span>
                                            </td>
                                            <td><?php echo (int)$m['amount']; ?></td>
                                            <td><?php echo (int)$m['balance_after']; ?></td>
                                            <td class="cell-desc"><?php echo htmlspecialchars($m['note'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="table-wrap">
                        <div class="section-head">Sales for this Part</div>
                        <?php if (empty($partSales)): ?>
                            <div class="empty-state"><p>No sales for this part yet.</p></div>
                        <?php else: ?>
                            <table class="product-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Qty</th>
                                        <th>Unit</th>
                                        <th>Total</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($partSales as $ps): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ps['sale_date'] ?? ''); ?></td>
                                            <td><?php echo (int)$ps['quantity']; ?></td>
                                            <td>$<?php echo number_format((float)$ps['unit_price'], 2); ?></td>
                                            <td>$<?php echo number_format((float)$ps['total'], 2); ?></td>
                                            <td class="cell-desc"><?php echo htmlspecialchars($ps['note'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="totals-row">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo $partSoldQty; ?></strong></td>
                                        <td></td>
                                        <td><strong>$<?php echo number_format($partSoldRev, 2); ?></strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-footer-meta">
                    Created: <?php echo htmlspecialchars($dp['created_at'] ?? '—'); ?>
                    · Updated: <?php echo htmlspecialchars($dp['updated_at'] ?? '—'); ?>
                </div>
