<?php
                $bs = $billSale;
                $billNo = $bs['bill_no'] ?? makeBillNo((int)$bs['id']);
                ?>
                <div class="toolbar no-print">
                    <div class="toolbar-right" style="margin-left:auto;">
                        <a href="dashboard.php?view=sale_add" class="btn btn-ghost">New Sale</a>
                        <a href="dashboard.php?view=sales" class="btn btn-secondary">All Sales</a>
                        <button type="button" class="btn btn-secondary" onclick="window.print();">Print</button>
                        <a href="dashboard.php?download=pdf&sale_id=<?php echo (int)$bs['id']; ?>" class="btn btn-primary">Download PDF</a>
                    </div>
                </div>

                <div class="invoice-paper" id="invoice">
                    <div class="invoice-top">
                        <div class="invoice-company">
                            <h2><?php echo htmlspecialchars(BILL_COMPANY_NAME); ?></h2>
                            <p><?php echo htmlspecialchars(BILL_COMPANY_LINE1); ?></p>
                            <p><?php echo htmlspecialchars(BILL_COMPANY_LINE2); ?></p>
                            <p><?php echo htmlspecialchars(BILL_COMPANY_LINE3); ?></p>
                        </div>
                        <div class="invoice-meta">
                            <h3>SALES INVOICE</h3>
                            <p><strong>Bill No:</strong> <?php echo htmlspecialchars($billNo); ?></p>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($bs['sale_date'] ?? ''); ?></p>
                            <p><strong>Issued:</strong> <?php echo htmlspecialchars($bs['created_at'] ?? ''); ?></p>
                        </div>
                    </div>

                    <div class="invoice-billto">
                        <h4>Bill To</h4>
                        <p class="invoice-customer"><?php echo htmlspecialchars($bs['customer_name'] ?? 'Walk-in Customer'); ?></p>
                        <?php if (!empty($bs['customer_phone'])): ?>
                            <p>Phone: <?php echo htmlspecialchars($bs['customer_phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($bs['note'])): ?>
                            <p class="cell-desc">Note: <?php echo htmlspecialchars($bs['note']); ?></p>
                        <?php endif; ?>
                    </div>

                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($bs['product_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($bs['sku'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($bs['category'] ?? ''); ?></td>
                                <td><?php echo (int)($bs['quantity'] ?? 0); ?></td>
                                <td>$<?php echo number_format((float)($bs['unit_price'] ?? 0), 2); ?></td>
                                <td>$<?php echo number_format((float)($bs['total'] ?? 0), 2); ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="invoice-total-label">Subtotal</td>
                                <td>$<?php echo number_format((float)($bs['total'] ?? 0), 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="invoice-total-label">Tax</td>
                                <td>$0.00</td>
                            </tr>
                            <tr class="invoice-grand">
                                <td colspan="5" class="invoice-total-label">TOTAL</td>
                                <td>$<?php echo number_format((float)($bs['total'] ?? 0), 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="invoice-footer">
                        <p>Thank you for your business!</p>
                        <p class="cell-desc">Computer-generated invoice · <?php echo htmlspecialchars($billNo); ?></p>
                    </div>
                </div>
