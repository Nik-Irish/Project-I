<?php
/**
 * Sale record / delete handlers (+ bill generation).
 */

function handle_sale_actions(
    string $action,
    string &$errorMessage,
    string &$successMessage,
    string &$view,
    ?array &$billSale
): void {
    if ($action === 'sale') {
        $productId     = (int)($_POST['product_id'] ?? 0);
        $qty           = trim($_POST['quantity'] ?? '');
        $unitPrice     = trim($_POST['unit_price'] ?? '');
        $note          = trim($_POST['note'] ?? '');
        $saleDate      = trim($_POST['sale_date'] ?? date('Y-m-d'));
        $customerName  = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');

        $product = products_find($productId);
        if (!$product) {
            $errorMessage = 'Select a valid product.';
            $view = 'sale_add';
            return;
        }
        if (!preg_match('/^\d+$/', $qty) || (int)$qty < 1) {
            $errorMessage = 'Sale quantity must be at least 1.';
            $view = 'sale_add';
            return;
        }
        if ((int)$qty > (int)$product['quantity']) {
            $errorMessage = 'Not enough stock. Available: ' . $product['quantity'] . '.';
            $view = 'sale_add';
            return;
        }
        if ($unitPrice === '' || !is_numeric($unitPrice) || (float)$unitPrice < 0) {
            $errorMessage = 'Enter a valid unit price.';
            $view = 'sale_add';
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) {
            $errorMessage = 'Enter a valid sale date (YYYY-MM-DD).';
            $view = 'sale_add';
            return;
        }

        $qtyInt = (int)$qty;
        $priceF = round((float)$unitPrice, 2);
        $total  = round($priceF * $qtyInt, 2);
        $oldQty = (int)$product['quantity'];
        $newQty = $oldQty - $qtyInt;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            products_set_quantity($productId, $newQty);

            $newSale = sales_insert([
                'product_id'     => $productId,
                'product_name'   => $product['name'],
                'sku'            => $product['sku'],
                'category'       => $product['category'],
                'quantity'       => $qtyInt,
                'unit_price'     => $priceF,
                'total'          => $total,
                'customer_name'  => $customerName !== '' ? $customerName : 'Walk-in Customer',
                'customer_phone' => $customerPhone,
                'note'           => $note,
                'sale_date'      => $saleDate,
            ]);

            $billNo = $newSale['bill_no'] ?? makeBillNo((int)$newSale['id']);
            movements_log($productId, 'sale', $qtyInt, $newQty, 'Sale ' . $billNo . ($note !== '' ? ': ' . $note : ''));

            $pdo->commit();

            $updated = products_find($productId);
            if ($updated) {
                checkStockNotification($updated, $oldQty, $newQty);
            }

            $billSale = $newSale;
            $successMessage = 'Sale recorded and bill ' . $billNo . ' generated. You can download the PDF below.';
            $view = 'bill';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errorMessage = 'Could not save sale: ' . $e->getMessage();
            $view = 'sale_add';
        }
        return;
    }

    if ($action === 'delete_sale') {
        $saleId = (int)($_POST['id'] ?? 0);
        $sale = sales_find($saleId);
        if (!$sale) {
            $errorMessage = 'Sale not found.';
            $view = 'sales';
            return;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if (!empty($sale['product_id'])) {
                $product = products_find((int)$sale['product_id']);
                if ($product) {
                    $newQty = (int)$product['quantity'] + (int)$sale['quantity'];
                    products_set_quantity((int)$sale['product_id'], $newQty);
                    movements_log(
                        (int)$sale['product_id'],
                        'in',
                        (int)$sale['quantity'],
                        $newQty,
                        'Sale cancelled / restored'
                    );
                }
            }
            sales_delete($saleId);
            $pdo->commit();
            $successMessage = 'Sale deleted and stock restored (if product still exists).';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errorMessage = 'Could not delete sale: ' . $e->getMessage();
        }
        $view = 'sales';
    }
}
