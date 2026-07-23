<div class="filter-card">
                    <form method="GET" action="dashboard.php" class="filter-form">
                        <input type="hidden" name="view" value="report">
                        <div class="form-group">
                            <label for="from">From</label>
                            <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($reportFrom); ?>">
                        </div>
                        <div class="form-group">
                            <label for="to">To</label>
                            <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($reportTo); ?>">
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
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="dashboard.php?view=report" class="btn btn-ghost">Reset</a>
                        </div>
                    </form>
                </div>

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
                    <div class="stat-card">
                        <div class="stat-label">Products Sold</div>
                        <div class="stat-value"><?php echo count($salesByProduct); ?></div>
                    </div>
                </div>

                <div class="two-col">
                    <div class="table-wrap">
                        <div class="section-head">By Product / Part</div>
                        <?php if (empty($salesByProduct)): ?>
                            <div class="empty-state"><p>No sales in this period.</p></div>
                        <?php else: ?>
                            <table class="product-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Qty Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    uasort($salesByProduct, function ($a, $b) {
                                        return $b['total'] <=> $a['total'];
                                    });
                                    foreach ($salesByProduct as $pid => $row):
                                    ?>
                                        <tr>
                                            <td>
                                                <a class="link-name" href="dashboard.php?view=inventory&id=<?php echo (int)$pid; ?>">
                                                    <?php echo htmlspecialchars($row['name']); ?>
                                                </a>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($row['sku']); ?></code></td>
                                            <td><?php echo (int)$row['qty']; ?></td>
                                            <td>$<?php echo number_format($row['total'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="table-wrap">
                        <div class="section-head">By Day</div>
                        <?php if (empty($salesByDay)): ?>
                            <div class="empty-state"><p>No daily data.</p></div>
                        <?php else: ?>
                            <table class="product-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Qty</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salesByDay as $day => $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($day); ?></td>
                                            <td><?php echo (int)$row['qty']; ?></td>
                                            <td>$<?php echo number_format($row['total'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
