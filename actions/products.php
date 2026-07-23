<?php
/**
 * Product add / update / delete handlers.
 * Expects: $action, $errorMessage, $successMessage, $view, $editProduct (by ref via return array)
 */

function handle_product_actions(string $action, string &$errorMessage, string &$successMessage, string &$view, ?array &$editProduct): void
{
    if ($action === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $sku         = trim($_POST['sku'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        $quantity    = trim($_POST['quantity'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || $sku === '' || $price === '' || $quantity === '') {
            $errorMessage = 'Name, SKU, price, and quantity are required.';
            $view = 'add';
            return;
        }
        if (!is_numeric($price) || (float)$price < 0) {
            $errorMessage = 'Price must be a valid non-negative number.';
            $view = 'add';
            return;
        }
        if (!preg_match('/^\d+$/', $quantity)) {
            $errorMessage = 'Quantity must be a whole number.';
            $view = 'add';
            return;
        }
        if (products_find_by_sku($sku)) {
            $errorMessage = 'A product with this SKU already exists.';
            $view = 'add';
            return;
        }

        $qty = (int)$quantity;
        $newId = products_insert([
            'name'        => $name,
            'sku'         => $sku,
            'category'    => $category !== '' ? $category : 'General',
            'price'       => round((float)$price, 2),
            'quantity'    => $qty,
            'description' => $description,
        ]);

        if ($qty > 0) {
            movements_log($newId, 'in', $qty, $qty, 'Initial stock');
        }

        $product = products_find($newId);
        if ($product) {
            if ($qty > 0 && $qty <= LOW_STOCK_THRESHOLD) {
                checkStockNotification($product, LOW_STOCK_THRESHOLD + 1, $qty);
            } elseif ($qty === 0) {
                checkStockNotification($product, 1, 0);
            }
        }
        checkProductCountNotification(products_count());

        $successMessage = 'Product added successfully.';
        $view = 'list';
        return;
    }

    if ($action === 'update') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $sku         = trim($_POST['sku'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        $quantity    = trim($_POST['quantity'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $product = products_find($id);
        if (!$product) {
            $errorMessage = 'Product not found.';
            $view = 'list';
            return;
        }

        if ($name === '' || $sku === '' || $price === '' || $quantity === '') {
            $errorMessage = 'Name, SKU, price, and quantity are required.';
            $view = 'edit';
            $editProduct = $product;
            return;
        }
        if (!is_numeric($price) || (float)$price < 0) {
            $errorMessage = 'Price must be a valid non-negative number.';
            $view = 'edit';
            $editProduct = $product;
            return;
        }
        if (!preg_match('/^\d+$/', $quantity)) {
            $errorMessage = 'Quantity must be a whole number.';
            $view = 'edit';
            $editProduct = $product;
            return;
        }
        if (products_find_by_sku($sku, $id)) {
            $errorMessage = 'A product with this SKU already exists.';
            $view = 'edit';
            $editProduct = $product;
            return;
        }

        $oldQty = (int)$product['quantity'];
        $newQty = (int)$quantity;

        products_update($id, [
            'name'        => $name,
            'sku'         => $sku,
            'category'    => $category !== '' ? $category : 'General',
            'price'       => round((float)$price, 2),
            'quantity'    => $newQty,
            'description' => $description,
        ]);

        if ($newQty !== $oldQty) {
            $diff = $newQty - $oldQty;
            movements_log(
                $id,
                'adjust',
                abs($diff),
                $newQty,
                'Manual quantity adjust (' . ($diff > 0 ? '+' : '-') . abs($diff) . ')'
            );
            $updated = products_find($id);
            if ($updated) {
                checkStockNotification($updated, $oldQty, $newQty);
            }
        }

        $successMessage = 'Product updated successfully.';
        $view = 'list';
        $editProduct = null;
        return;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $product = products_find($id);
        if (!$product) {
            $errorMessage = 'Product not found.';
        } else {
            $deletedName = $product['name'];
            products_delete($id);
            $successMessage = 'Product "' . $deletedName . '" deleted.';
        }
        $view = 'list';
    }
}
