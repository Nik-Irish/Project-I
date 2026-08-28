<?php
/**
 * views/bill.php — Invoice / bill view
 *
 * Requires: $billSale  (may contain _subtotal, _tax, _total keys
 *                       when freshly generated; otherwise we back-calculate)
 * Optional: $dashboardScript — controller that hosts this view
 *           ('dashboard.php' by default; 'Staff_dashboard.php' for staff)
 */

if (!is_array($billSale)) {
    echo '<div class="msg msg-error">Bill details are unavailable.</div>';
    return;
}

$dashboardScript = $dashboardScript ?? 'dashboard.php';

if (isset($billSale['_subtotal'])) {
    $bSub   = (float)$billSale['_subtotal'];
    $bTax   = (float)$billSale['_tax'];
    $bTotal = (float)$billSale['_total'];
} else {
    $bTotal = (float)$billSale['total'];
    $bSub   = round($bTotal / 1.13, 2);
    $bTax   = round($bTotal - $bSub, 2);
}
?>

<!-- View-specific styles (small rules moved from dashboard-style.css) -->
<style>
.bill-print .bill-divider {
  border: none;
  border-top: 2px solid #334155;
  margin: 0.7rem 0;
}

.bill-print table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 0.7rem;
}


.bill-print td {
  padding: 0.45rem 0.7rem;
  border-bottom: 1px solid #e2e8f0;
  font-size: 0.88rem;
}

.bill-print .bill-totals {
  margin-top: 0.5rem;
  text-align: right;
}
.bill-print .bill-totals table {
  width: auto;
  margin-left: auto;
}
.bill-print .bill-totals td {
  border: none;
  padding: 0.2rem 0.5rem;
}

.bill-print .grand-total td {
  font-size: 1.1rem;
  font-weight: 700;
  border-top: 2px solid #334155;
}
</style>

<div class="bill-print">
    <h1>IMS Nepal</h1>
    <div class="bill-title">INVOICE</div>
    <div class="bill-company-info">
        Phone: +977 9705217752 &nbsp;|&nbsp; Email: sales@IMSFirm.com
    </div>
    <hr class="bill-divider">

    <!-- Customer & bill meta -->
    <div style="display:flex;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <div style="font-weight:700;font-size:1.1rem;">
                <?php echo htmlspecialchars($billSale['customer_name']); ?>
            </div>
            <?php if (!empty($billSale['customer_phone'])): ?>
                <div style="color:#64748b;font-size:.9rem;">
                    <?php echo htmlspecialchars($billSale['customer_phone']); ?>
                </div>
            <?php endif; ?>
        </div>
        <div style="text-align:right;font-size:.9rem;">
            <div><strong>Bill No:</strong> <?php echo htmlspecialchars($billSale['bill_no']); ?></div>
            <div><strong>Date:</strong>    <?php echo htmlspecialchars($billSale['sale_date']); ?></div>
        </div>
    </div>

    <!-- Line items -->
    <table>
        <thead>
            <tr>
                <th>DESCRIPTION</th>
                <th style="text-align:center;">QTY</th>
                <th style="text-align:right;">RATE</th>
                <th style="text-align:right;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo htmlspecialchars($billSale['product_name']); ?></td>
                <td style="text-align:center;"><?php echo (int)$billSale['quantity']; ?></td>
                <td style="text-align:right;">
                    Rs.<?php echo number_format((float)$billSale['unit_price'], 2); ?>
                </td>
                <td style="text-align:right;">Rs.<?php echo number_format($bSub, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="bill-totals">
        <table>
            <tr>
                <td>SUBTOTAL</td>
                <td>Rs.<?php echo number_format($bSub, 2); ?></td>
            </tr>
            <tr>
                <td>TAX RATE</td>
                <td>13%</td>
            </tr>
            <tr>
                <td>SALES TAX</td>
                <td>Rs.<?php echo number_format($bTax, 2); ?></td>
            </tr>
            <tr class="grand-total">
                <td>TOTAL</td>
                <td>Rs.<?php echo number_format($bTotal, 2); ?></td>
            </tr>
        </table>
    </div>

    <hr class="bill-divider">
    <div class="bill-footer">THANK YOU FOR YOUR BUSINESS!</div>

    <!-- Action buttons (hidden on print) -->
    <div class="form-actions no-print" style="margin-top:1rem;">
        <a href="<?php echo htmlspecialchars($dashboardScript); ?>?download=pdf&sale_id=<?php echo (int)$billSale['id']; ?>"
           class="btn btn-primary">Download PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
        <a href="<?php echo htmlspecialchars($dashboardScript); ?>?view=sales" class="btn btn-ghost">All Sales</a>
        <a href="<?php echo htmlspecialchars($dashboardScript); ?>?view=list"  class="btn btn-ghost">Products</a>
    </div>
</div>