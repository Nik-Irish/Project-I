<?php
/**
 * Pure PHP invoice PDF generator — no external libraries.
 * Builds a valid single-page PDF (Helvetica) for a sale/bill.
 */

// Shop details shown on every bill (edit as needed)
define('BILL_COMPANY_NAME', 'Product Manager Store');
define('BILL_COMPANY_LINE1', 'Inventory & Sales System');
define('BILL_COMPANY_LINE2', 'Phone: +1-000-000-0000');
define('BILL_COMPANY_LINE3', 'Email: sales@example.com');

/**
 * Escape text for PDF string literals (ASCII-safe).
 */
function pdfEscape(string $text): string
{
    // Replace non-ASCII with simple approximations so Helvetica works
    $map = [
        '€' => 'EUR', '£' => 'GBP', '–' => '-', '—' => '-',
        '‘' => "'", '’' => "'", '“' => '"', '”' => '"',
        '…' => '...',
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

/**
 * Build PDF content stream commands for one text line.
 * Coordinates: origin bottom-left, points (1/72 inch). Page is A4 595 x 842.
 */
function pdfText(float $x, float $y, string $text, int $size = 11, string $font = 'F1'): string
{
    return sprintf(
        "BT /%s %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
        $font,
        $size,
        $x,
        $y,
        pdfEscape($text)
    );
}

/**
 * Horizontal line
 */
function pdfLine(float $x1, float $y1, float $x2, float $y2, float $width = 0.5): string
{
    return sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n", $width, $x1, $y1, $x2, $y2);
}

/**
 * Generate bill number from sale id.
 */
function makeBillNo(int $saleId): string
{
    return 'INV-' . str_pad((string)$saleId, 5, '0', STR_PAD_LEFT);
}

/**
 * Build raw PDF binary for one sale/bill.
 *
 * @param array $sale Sale record from sales.json
 * @return string PDF file contents
 */
function buildInvoicePdf(array $sale): string
{
    $billNo   = $sale['bill_no'] ?? makeBillNo((int)($sale['id'] ?? 0));
    $date     = $sale['sale_date'] ?? date('Y-m-d');
    $created  = $sale['created_at'] ?? '';
    $customer = $sale['customer_name'] ?? 'Walk-in Customer';
    $phone    = $sale['customer_phone'] ?? '';
    $note     = $sale['note'] ?? '';
    $name     = $sale['product_name'] ?? '';
    $sku      = $sale['sku'] ?? '';
    $category = $sale['category'] ?? '';
    $qty      = (int)($sale['quantity'] ?? 0);
    $unit     = (float)($sale['unit_price'] ?? 0);
    $total    = (float)($sale['total'] ?? ($qty * $unit));

    $unitStr  = number_format($unit, 2, '.', ',');
    $totalStr = number_format($total, 2, '.', ',');

    // --- page content (A4) ---
    $c  = '';
    $c .= pdfText(50, 800, BILL_COMPANY_NAME, 18, 'F2');
    $c .= pdfText(50, 782, BILL_COMPANY_LINE1, 10);
    $c .= pdfText(50, 768, BILL_COMPANY_LINE2, 9);
    $c .= pdfText(50, 756, BILL_COMPANY_LINE3, 9);

    $c .= pdfText(360, 800, 'SALES INVOICE', 16, 'F2');
    $c .= pdfText(360, 780, 'Bill No: ' . $billNo, 11, 'F2');
    $c .= pdfText(360, 764, 'Date: ' . $date, 10);
    if ($created !== '') {
        $c .= pdfText(360, 750, 'Issued: ' . $created, 9);
    }

    $c .= pdfLine(50, 735, 545, 735, 1.2);

    $c .= pdfText(50, 715, 'Bill To:', 11, 'F2');
    $c .= pdfText(50, 698, $customer, 11);
    if ($phone !== '') {
        $c .= pdfText(50, 682, 'Phone: ' . $phone, 10);
    }
    if ($note !== '') {
        $c .= pdfText(50, 666, 'Note: ' . $note, 9);
    }

    // Table header
    $tableTop = 630;
    $c .= pdfLine(50, $tableTop + 15, 545, $tableTop + 15, 0.8);
    $c .= pdfText(55, $tableTop, 'Item', 10, 'F2');
    $c .= pdfText(250, $tableTop, 'SKU', 10, 'F2');
    $c .= pdfText(330, $tableTop, 'Qty', 10, 'F2');
    $c .= pdfText(380, $tableTop, 'Unit Price', 10, 'F2');
    $c .= pdfText(470, $tableTop, 'Amount', 10, 'F2');
    $c .= pdfLine(50, $tableTop - 8, 545, $tableTop - 8, 0.8);

    // Product row
    $rowY = $tableTop - 28;
    // Truncate long names for PDF line width
    $dispName = $name;
    if (strlen($dispName) > 32) {
        $dispName = substr($dispName, 0, 31) . '.';
    }
    $c .= pdfText(55, $rowY, $dispName, 10);
    $c .= pdfText(250, $rowY, $sku, 10);
    $c .= pdfText(335, $rowY, (string)$qty, 10);
    $c .= pdfText(385, $rowY, '$' . $unitStr, 10);
    $c .= pdfText(475, $rowY, '$' . $totalStr, 10);

    if ($category !== '') {
        $c .= pdfText(55, $rowY - 14, 'Category: ' . $category, 8);
    }

    $c .= pdfLine(50, $rowY - 30, 545, $rowY - 30, 0.8);

    // Totals
    $totY = $rowY - 55;
    $c .= pdfText(360, $totY, 'Subtotal:', 11);
    $c .= pdfText(470, $totY, '$' . $totalStr, 11);
    $c .= pdfText(360, $totY - 18, 'Tax:', 11);
    $c .= pdfText(470, $totY - 18, '$0.00', 11);
    $c .= pdfLine(350, $totY - 28, 545, $totY - 28, 0.6);
    $c .= pdfText(360, $totY - 45, 'TOTAL:', 13, 'F2');
    $c .= pdfText(470, $totY - 45, '$' . $totalStr, 13, 'F2');

    $c .= pdfLine(50, 120, 545, 120, 0.5);
    $c .= pdfText(50, 100, 'Thank you for your business!', 10, 'F2');
    $c .= pdfText(50, 85, 'This is a computer-generated invoice. No signature required.', 8);
    $c .= pdfText(50, 70, 'Generated by Product Manager System', 8);

    // Wrap content stream
    $stream = "<< /Length " . strlen($c) . " >>\nstream\n" . $c . "endstream";

    // PDF objects
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

/**
 * Send PDF to browser as a download and exit.
 */
function downloadInvoicePdf(array $sale): void
{
    $billNo = $sale['bill_no'] ?? makeBillNo((int)($sale['id'] ?? 0));
    $safe   = preg_replace('/[^A-Za-z0-9_\-]/', '_', $billNo);
    $pdf    = buildInvoicePdf($sale);

    // Clear any previous output
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safe . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf;
    exit;
}
