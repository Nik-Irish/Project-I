<?php
/**
 * Pure PHP invoice PDF generator — no external libraries.
 * Builds a valid single-page PDF (Helvetica) for a sale/bill.
 */

define('BILL_COMPANY_NAME', 'Nirman');
define('BILL_COMPANY_PHONE', 'Phone: +977 9705217752');
define('BILL_COMPANY_EMAIL', 'Email: sales@nirmanirm.com');

function pdfEscape(string $text): string
{
    $map = [
        "\xE2\x82\xAC" => 'EUR',
        "\xC2\xA3"      => 'GBP',
        "\xE2\x80\x93"  => '-',
        "\xE2\x80\x94"  => '-',
        "\xE2\x80\x98"  => "'",
        "\xE2\x80\x99"  => "'",
        "\xE2\x80\x9C"  => '"',
        "\xE2\x80\x9D"  => '"',
        "\xE2\x80\xA6"  => '...',
    ];
    $text = strtr($text, $map);
    $out = '';
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $c = ord($text[$i]);
        if ($c >= 32 && $c <= 126) {
            $out .= $text[$i];
        } else {
            $out .= '?';
        }
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $out);
}

function pdfText(float $x, float $y, string $text, int $size = 11, string $font = 'F1'): string
{
    return sprintf(
        "BT /%s %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
        $font, $size, $x, $y, pdfEscape($text)
    );
}

function pdfLine(float $x1, float $y1, float $x2, float $y2, float $width = 0.5): string
{
    return sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n", $width, $x1, $y1, $x2, $y2);
}

if (!function_exists('makeBillNo')) {
    function makeBillNo(int $saleId): string
    {
        return 'INV-' . str_pad((string)$saleId, 5, '0', STR_PAD_LEFT);
    }
}

function buildInvoicePdf(array $sale): string
{
    $billNo   = $sale['bill_no'] ?? makeBillNo((int)($sale['id'] ?? 0));
    $date     = $sale['sale_date'] ?? date('Y-m-d');
    $customer = $sale['customer_name'] ?? 'Walk-in Customer';
    $phone    = $sale['customer_phone'] ?? '';
    $name     = $sale['product_name'] ?? '';
    $sku      = $sale['sku'] ?? '';
    $qty      = (int)($sale['quantity'] ?? 0);
    $unit     = (float)($sale['unit_price'] ?? 0);
    $total    = (float)($sale['total'] ?? ($qty * $unit));

    // Derive subtotal and 13% tax from the stored total
    $subtotal = round($total / 1.13, 2);
    $taxAmt   = round($total - $subtotal, 2);

    $unitStr     = number_format($unit, 2, '.', ',');
    $subtotalStr = number_format($subtotal, 2, '.', ',');
    $totalStr    = number_format($total, 2, '.', ',');

    // --- page content (A4 595 x 842) ---
    $c  = '';

    // Company name centered
    $c .= pdfText(255, 800, BILL_COMPANY_NAME, 22, 'F2');

    // "Bill" centered below company name
    $c .= pdfText(275, 780, 'Bill', 15, 'F2');

    // Contact info centered
    $c .= pdfText(175, 762, BILL_COMPANY_PHONE, 9);
    $c .= pdfText(180, 750, BILL_COMPANY_EMAIL, 9);

    $c .= pdfLine(50, 736, 545, 736, 1.0);

    // Customer name on left
    $c .= pdfText(50, 720, $customer, 11, 'F2');
    // Customer phone below name
    if ($phone !== '') {
        $c .= pdfText(50, 707, $phone, 10);
    }

    // Bill No and Date on right
    $c .= pdfText(400, 720, 'Bill No: ' . $billNo, 10, 'F2');
    $c .= pdfText(400, 707, 'Date: ' . $date, 10);

    $c .= pdfLine(50, 693, 545, 693, 0.8);

    // Table header
    $tableTop = 675;
    $c .= pdfLine(50, $tableTop + 8, 545, $tableTop + 8, 0.8);
    $c .= pdfText(55, $tableTop, 'DESCRIPTION', 10, 'F2');
    $c .= pdfText(360, $tableTop, 'QTY', 10, 'F2');
    $c .= pdfText(415, $tableTop, 'RATE', 10, 'F2');
    $c .= pdfText(480, $tableTop, 'AMOUNT', 10, 'F2');
    $c .= pdfLine(50, $tableTop - 8, 545, $tableTop - 8, 0.8);

    // Product row
    $rowY = $tableTop - 28;
    $dispName = $name;
    if (strlen($dispName) > 35) {
        $dispName = substr($dispName, 0, 34) . '.';
    }
    $c .= pdfText(55, $rowY, $dispName, 10);
    $c .= pdfText(368, $rowY, (string)$qty, 10);
    $c .= pdfText(415, $rowY, 'Rs.' . $unitStr, 10);
    $c .= pdfText(478, $rowY, 'Rs.' . $subtotalStr, 10);

    $c .= pdfLine(50, $rowY - 15, 545, $rowY - 15, 0.8);

    // Totals - right aligned
    $totY = $rowY - 38;
    $c .= pdfText(400, $totY, 'SUBTOTAL', 10);
    $c .= pdfText(478, $totY, 'Rs.' . $subtotalStr, 10);

    $c .= pdfText(400, $totY - 16, 'TAX RATE (13%)', 10);

    $c .= pdfText(400, $totY - 32, 'SALES TAX', 10);
    $c .= pdfText(478, $totY - 32, 'Rs.' . number_format($taxAmt, 2, '.', ','), 10);

    $c .= pdfLine(395, $totY - 44, 545, $totY - 44, 0.8);

    $c .= pdfText(400, $totY - 60, 'TOTAL', 12, 'F2');
    $c .= pdfText(478, $totY - 60, 'Rs.' . $totalStr, 12, 'F2');

    // Thank you at bottom
    $c .= pdfText(50, 100, 'THANK YOU FOR YOUR BUSINESS!', 11, 'F2');

    $stream = "<< /Length " . strlen($c) . " >>\nstream\n" . $c . "endstream";

    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>";
    $objects[4] = $stream;
    $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    for ($i = 1; $i <= 6; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 7\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= 6; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}

function downloadInvoicePdf(array $sale): void
{
    $billNo = $sale['bill_no'] ?? makeBillNo((int)($sale['id'] ?? 0));
    $safe   = preg_replace('/[^A-Za-z0-9_\-]/', '_', $billNo);
    $pdf    = buildInvoicePdf($sale);

    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { ob_end_clean(); }
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safe . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf;
    exit;
}