<?php
/**
 * pdf_invoice.php — Simple PDF bill generator for IMS Nepal
 */

if (!function_exists('makeBillNo')) {
    function makeBillNo(int $id): string {
        return 'BILL-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
    }
}

function downloadInvoicePdf(array $sale): void {
    $total    = (float)$sale['total'];
    $subtotal = round($total / 1.13, 2);
    $tax      = round($total - $subtotal, 2);

    $billNo      = htmlspecialchars($sale['bill_no']       ?? '');
    $saleDate    = htmlspecialchars($sale['sale_date']      ?? '');
    $custName    = htmlspecialchars($sale['customer_name']  ?? 'Walk-in Customer');
    $custPhone   = htmlspecialchars($sale['customer_phone'] ?? '');
    $productName = htmlspecialchars($sale['product_name']   ?? '');
    $sku         = htmlspecialchars($sale['sku']            ?? '');
    $qty         = (int)($sale['quantity']  ?? 0);
    $unitPrice   = number_format((float)($sale['unit_price'] ?? 0), 2);
    $note        = htmlspecialchars($sale['note'] ?? '');

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $billNo; ?></title>
    <style>
        body { font-family: monospace; max-width: 400px; margin: 2rem auto; padding: 1rem; color: #000; }
        h2 { text-align: center; margin: 0; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: .5rem 0; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        td, th { padding: .25rem 0; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .small { font-size: .75rem; color: #555; }
        .actions { text-align: center; margin-top: 1.5rem; }
        .actions button, .actions a { margin: 0 .3rem; padding: .5rem 1rem; cursor: pointer; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>

<h2>Nirman</h2>
<p class="center small">Ph: +977 9705217752 | sales@nirmanirm.com</p>

<div class="line"></div>

<p>
    Bill: <?php echo $billNo; ?><br>
    Date: <?php echo $saleDate; ?><br>
    Customer: <?php echo $custName; ?>
    <?php if ($custPhone): ?>(<?php echo $custPhone; ?>)<?php endif; ?>
</p>

<div class="line"></div>

<table>
    <tr class="bold">
        <th>Item</th>
        <th class="right">Qty</th>
        <th class="right">Rate</th>
        <th class="right">Amt</th>
    </tr>
    <tr>
        <td><?php echo $productName; ?><br><span class="small"><?php echo $sku; ?></span></td>
        <td class="right"><?php echo $qty; ?></td>
        <td class="right"><?php echo $unitPrice; ?></td>
        <td class="right"><?php echo number_format($subtotal, 2); ?></td>
    </tr>
</table>

<div class="line"></div>

<table>
    <tr><td>Subtotal</td><td class="right">Rs. <?php echo number_format($subtotal, 2); ?></td></tr>
    <tr><td>VAT (13%)</td><td class="right">Rs. <?php echo number_format($tax, 2); ?></td></tr>
    <tr class="bold"><td>TOTAL</td><td class="right">Rs. <?php echo number_format($total, 2); ?></td></tr>
</table>

<div class="line"></div>

<?php if ($note): ?><p class="small">Note: <?php echo $note; ?></p><?php endif; ?>

<p class="center small">Thank you for your business!</p>

<div class="actions">
    <button onclick="window.print()">Print / Save PDF</button>
    <a href="javascript:history.back()">Back</a>
</div>

<script>window.onload = () => window.print();</script>
</body>
</html>
    <?php
    exit;
}