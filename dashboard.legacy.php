<?php
session_start();

// --- File storage (no database, no libraries) ---
require_once __DIR__ . '/pdf_invoice.php';

$productsFile      = __DIR__ . '/products.json';
$salesFile         = __DIR__ . '/sales.json';
$movementsFile     = __DIR__ . '/movements.json';
$notificationsFile = __DIR__ . '/notifications.json';

// Stock at or below this level triggers a system notification
define('LOW_STOCK_THRESHOLD', 10);
// Total product count that triggers a catalog milestone notification
define('PRODUCT_COUNT_ALERT', 10);

// ---------- helpers ----------
function loadJson(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveJson(string $file, array $rows): bool
{
    return file_put_contents(
        $file,
        json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function nextId(array $rows): int
{
    $max = 0;
    foreach ($rows as $r) {
        $max = max($max, (int)($r['id'] ?? 0));
    }
    return $max + 1;
}

function findIndex(array $rows, int $id): int|false
{
    foreach ($rows as $i => $r) {
        if ((int)$r['id'] === $id) {
            return $i;
        }
    }
    return false;
}

function shortText(string $text, int $max = 48): string
{
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, $max - 1) . '…';
}

function logMovement(string $file, int $productId, string $type, int $amount, int $balanceAfter, string $note = ''): void
{
    $rows = loadJson($file);
    $rows[] = [
        'id'            => nextId($rows),
        'product_id'    => $productId,
        'type'          => $type, // in | out | sale | adjust
        'amount'        => $amount,
        'balance_after' => $balanceAfter,
        'note'          => $note,
        'created_at'    => date('Y-m-d H:i:s'),
    ];
    saveJson($file, $rows);
}

/**
 * Push a system notification (stored in notifications.json).
 * type: low_stock | out_of_stock | product_count | info
 */
function addNotification(string $file, string $type, string $title, string $message, ?int $productId = null): void
{
    $rows = loadJson($file);
    $rows[] = [
        'id'         => nextId($rows),
        'type'       => $type,
        'title'      => $title,
        'message'    => $message,
        'product_id' => $productId,
        'read'       => false,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    saveJson($file, $rows);
}

/**
 * After stock changes: notify system when qty is at/below 10, or hits 0.
 * Only fires when stock crosses into the threshold (or hits zero), not on every page load.
 */
function checkStockNotification(string $file, array $product, int $oldQty, int $newQty): void
{
    $name = $product['name'] ?? 'Product';
    $sku  = $product['sku'] ?? '';
    $id   = (int)($product['id'] ?? 0);
    $threshold = LOW_STOCK_THRESHOLD;

    // Out of stock
    if ($newQty === 0 && $oldQty > 0) {
        addNotification(
            $file,
            'out_of_stock',
            'Out of stock',
            '"' . $name . '" (SKU: ' . $sku . ') is out of stock. Please restock.',
            $id
        );
        return;
    }

    // Crossed into low stock (was above 10, now 1–10)
    if ($newQty > 0 && $newQty <= $threshold && $oldQty > $threshold) {
        addNotification(
            $file,
            'low_stock',
            'Low stock alert',
            '"' . $name . '" (SKU: ' . $sku . ') has only ' . $newQty . ' unit(s) left (threshold: ' . $threshold . ').',
            $id
        );
    }
}

/**
 * When total products reaches 10, send a one-time milestone notification.
 */
function checkProductCountNotification(string $file, int $count): void
{
    if ($count !== PRODUCT_COUNT_ALERT) {
        return;
    }
    // Avoid duplicate milestone for the same count in a short window
    $existing = loadJson($file);
    foreach ($existing as $n) {
        if (($n['type'] ?? '') === 'product_count' && strpos($n['message'] ?? '', 'reached ' . PRODUCT_COUNT_ALERT) !== false) {
            // already notified for this milestone
            return;
        }
    }
    addNotification(
        $file,
        'product_count',
        'Product catalog milestone',
        'The system now has ' . PRODUCT_COUNT_ALERT . ' products registered.',
        null
    );
}

// ---------- load data ----------
$errorMessage   = '';
$successMessage = '';
$products       = loadJson($productsFile);
$sales          = loadJson($salesFile);
$movements      = loadJson($movementsFile);
$notifications  = loadJson($notificationsFile);
$editProduct    = null;
$detailProduct  = null;

$allowedViews = ['list', 'add', 'edit', 'sales', 'sale_add', 'inventory', 'report', 'notifications', 'bill'];
$view = $_GET['view'] ?? 'list';
if (!in_array($view, $allowedViews, true)) {
    $view = 'list';
}

// --- PDF download (pure PHP, no libraries) ---
if (isset($_GET['download']) && $_GET['download'] === 'pdf' && isset($_GET['sale_id'])) {
    $dlId  = (int)$_GET['sale_id'];
    $dlIdx = findIndex($sales, $dlId);
    if ($dlIdx === false) {
        http_response_code(404);
        echo 'Bill not found.';
        exit;
    }
    downloadInvoicePdf($sales[$dlIdx]);
}

$billSale = null;
if ($view === 'bill' && isset($_GET['id'])) {
    $bIdx = findIndex($sales, (int)$_GET['id']);
    if ($bIdx === false) {
        $errorMessage = 'Bill not found.';
        $view = 'sales';
    } else {
        $billSale = $sales[$bIdx];
    }
}

// Edit product load
if ($view === 'edit' && isset($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $idx = findIndex($products, $editId);
    if ($idx === false) {
        $errorMessage = 'Product not found.';
        $view = 'list';
    } else {
        $editProduct = $products[$idx];
    }
}

// Inventory detail load
if ($view === 'inventory' && isset($_GET['id'])) {
    $detailId = (int)$_GET['id'];
    $idx = findIndex($products, $detailId);
    if ($idx === false) {
        $errorMessage = 'Product / part not found.';
        $view = 'list';
    } else {
        $detailProduct = $products[$idx];
    }
}

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD PRODUCT
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
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $errorMessage = 'Price must be a valid non-negative number.';
            $view = 'add';
        } elseif (!preg_match('/^\d+$/', $quantity)) {
            $errorMessage = 'Quantity must be a whole number.';
            $view = 'add';
        } else {
            $skuExists = false;
            foreach ($products as $p) {
                if (strcasecmp($p['sku'], $sku) === 0) {
                    $skuExists = true;
                    break;
                }
            }
            if ($skuExists) {
                $errorMessage = 'A product with this SKU already exists.';
                $view = 'add';
            } else {
                $newId = nextId($products);
                $qty   = (int)$quantity;
                $products[] = [
                    'id'          => $newId,
                    'name'        => $name,
                    'sku'         => $sku,
                    'category'    => $category !== '' ? $category : 'General',
                    'price'       => round((float)$price, 2),
                    'quantity'    => $qty,
                    'description' => $description,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ];
                saveJson($productsFile, $products);
                if ($qty > 0) {
                    logMovement($movementsFile, $newId, 'in', $qty, $qty, 'Initial stock');
                    $movements = loadJson($movementsFile);
                }
                // Low stock if added with qty already at/below 10
                if ($qty > 0 && $qty <= LOW_STOCK_THRESHOLD) {
                    checkStockNotification(
                        $notificationsFile,
                        $products[findIndex($products, $newId)],
                        LOW_STOCK_THRESHOLD + 1,
                        $qty
                    );
                } elseif ($qty === 0) {
                    checkStockNotification(
                        $notificationsFile,
                        $products[findIndex($products, $newId)],
                        1,
                        0
                    );
                }
                checkProductCountNotification($notificationsFile, count($products));
                $notifications = loadJson($notificationsFile);
                $successMessage = 'Product added successfully.';
                $view = 'list';
            }
        }
    }

    // UPDATE PRODUCT
    if ($action === 'update') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $sku         = trim($_POST['sku'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        $quantity    = trim($_POST['quantity'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $idx = findIndex($products, $id);
        if ($idx === false) {
            $errorMessage = 'Product not found.';
            $view = 'list';
        } elseif ($name === '' || $sku === '' || $price === '' || $quantity === '') {
            $errorMessage = 'Name, SKU, price, and quantity are required.';
            $view = 'edit';
            $editProduct = $products[$idx];
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $errorMessage = 'Price must be a valid non-negative number.';
            $view = 'edit';
            $editProduct = $products[$idx];
        } elseif (!preg_match('/^\d+$/', $quantity)) {
            $errorMessage = 'Quantity must be a whole number.';
            $view = 'edit';
            $editProduct = $products[$idx];
        } else {
            foreach ($products as $i => $p) {
                if ($i !== $idx && strcasecmp($p['sku'], $sku) === 0) {
                    $errorMessage = 'A product with this SKU already exists.';
                    $view = 'edit';
                    $editProduct = $products[$idx];
                    break;
                }
            }
            if ($errorMessage === '') {
                $oldQty = (int)$products[$idx]['quantity'];
                $newQty = (int)$quantity;
                $products[$idx]['name']        = $name;
                $products[$idx]['sku']         = $sku;
                $products[$idx]['category']    = $category !== '' ? $category : 'General';
                $products[$idx]['price']       = round((float)$price, 2);
                $products[$idx]['quantity']    = $newQty;
                $products[$idx]['description'] = $description;
                $products[$idx]['updated_at']  = date('Y-m-d H:i:s');
                saveJson($productsFile, $products);

                if ($newQty !== $oldQty) {
                    $diff = $newQty - $oldQty;
                    logMovement(
                        $movementsFile,
                        $id,
                        'adjust',
                        abs($diff),
                        $newQty,
                        'Manual quantity adjust (' . ($diff > 0 ? '+' : '-') . abs($diff) . ')'
                    );
                    $movements = loadJson($movementsFile);
                    checkStockNotification($notificationsFile, $products[$idx], $oldQty, $newQty);
                    $notifications = loadJson($notificationsFile);
                }
                $successMessage = 'Product updated successfully.';
                $view = 'list';
                $editProduct = null;
            }
        }
    }

    // STOCK IN
    if ($action === 'stock_in') {
        $id     = (int)($_POST['id'] ?? 0);
        $amount = trim($_POST['amount'] ?? '');
        $return = $_POST['return_view'] ?? 'list';
        $idx    = findIndex($products, $id);
        if ($idx === false) {
            $errorMessage = 'Product not found.';
            $view = 'list';
        } elseif (!preg_match('/^\d+$/', $amount) || (int)$amount < 1) {
            $errorMessage = 'Enter a valid amount to add (1 or more).';
            $view = ($return === 'inventory') ? 'inventory' : 'list';
            if ($view === 'inventory') {
                $detailProduct = $products[$idx] ?? null;
            }
        } else {
            $amt = (int)$amount;
            $oldQty = (int)$products[$idx]['quantity'];
            $products[$idx]['quantity']   += $amt;
            $products[$idx]['updated_at']  = date('Y-m-d H:i:s');
            saveJson($productsFile, $products);
            logMovement($movementsFile, $id, 'in', $amt, (int)$products[$idx]['quantity'], 'Stock input');
            $movements = loadJson($movementsFile);
            // Stock in does not create low-stock alerts (stock is increasing)
            $successMessage = 'Stock added: +' . $amt . ' to "' . $products[$idx]['name'] . '".';
            if ($return === 'inventory') {
                $view = 'inventory';
                $detailProduct = $products[$idx];
            } else {
                $view = 'list';
            }
        }
    }

    // STOCK OUT
    if ($action === 'stock_out') {
        $id     = (int)($_POST['id'] ?? 0);
        $amount = trim($_POST['amount'] ?? '');
        $return = $_POST['return_view'] ?? 'list';
        $idx    = findIndex($products, $id);
        if ($idx === false) {
            $errorMessage = 'Product not found.';
            $view = 'list';
        } elseif (!preg_match('/^\d+$/', $amount) || (int)$amount < 1) {
            $errorMessage = 'Enter a valid amount to remove (1 or more).';
            $view = ($return === 'inventory') ? 'inventory' : 'list';
            if ($view === 'inventory') {
                $detailProduct = $products[$idx] ?? null;
            }
        } elseif ((int)$amount > (int)$products[$idx]['quantity']) {
            $errorMessage = 'Not enough stock. Available: ' . $products[$idx]['quantity'] . '.';
            $view = ($return === 'inventory') ? 'inventory' : 'list';
            if ($view === 'inventory') {
                $detailProduct = $products[$idx];
            }
        } else {
            $amt = (int)$amount;
            $oldQty = (int)$products[$idx]['quantity'];
            $products[$idx]['quantity']   -= $amt;
            $newQty = (int)$products[$idx]['quantity'];
            $products[$idx]['updated_at']  = date('Y-m-d H:i:s');
            saveJson($productsFile, $products);
            logMovement($movementsFile, $id, 'out', $amt, $newQty, 'Stock output');
            $movements = loadJson($movementsFile);
            checkStockNotification($notificationsFile, $products[$idx], $oldQty, $newQty);
            $notifications = loadJson($notificationsFile);
            $successMessage = 'Stock removed: -' . $amt . ' from "' . $products[$idx]['name'] . '".';
            if ($return === 'inventory') {
                $view = 'inventory';
                $detailProduct = $products[$idx];
            } else {
                $view = 'list';
            }
        }
    }

    // DELETE PRODUCT
    if ($action === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $idx = findIndex($products, $id);
        if ($idx === false) {
            $errorMessage = 'Product not found.';
        } else {
            $deletedName = $products[$idx]['name'];
            array_splice($products, $idx, 1);
            saveJson($productsFile, $products);
            $successMessage = 'Product "' . $deletedName . '" deleted.';
        }
        $view = 'list';
    }

    // RECORD SALE (+ generate bill)
    if ($action === 'sale') {
        $productId     = (int)($_POST['product_id'] ?? 0);
        $qty           = trim($_POST['quantity'] ?? '');
        $unitPrice     = trim($_POST['unit_price'] ?? '');
        $note          = trim($_POST['note'] ?? '');
        $saleDate      = trim($_POST['sale_date'] ?? date('Y-m-d'));
        $customerName  = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');

        $idx = findIndex($products, $productId);
        if ($idx === false) {
            $errorMessage = 'Select a valid product.';
            $view = 'sale_add';
        } elseif (!preg_match('/^\d+$/', $qty) || (int)$qty < 1) {
            $errorMessage = 'Sale quantity must be at least 1.';
            $view = 'sale_add';
        } elseif ((int)$qty > (int)$products[$idx]['quantity']) {
            $errorMessage = 'Not enough stock. Available: ' . $products[$idx]['quantity'] . '.';
            $view = 'sale_add';
        } elseif ($unitPrice === '' || !is_numeric($unitPrice) || (float)$unitPrice < 0) {
            $errorMessage = 'Enter a valid unit price.';
            $view = 'sale_add';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) {
            $errorMessage = 'Enter a valid sale date (YYYY-MM-DD).';
            $view = 'sale_add';
        } else {
            $qtyInt   = (int)$qty;
            $priceF   = round((float)$unitPrice, 2);
            $total    = round($priceF * $qtyInt, 2);
            $p        = $products[$idx];
            $oldQty   = (int)$products[$idx]['quantity'];
            $saleId   = nextId($sales);
            $billNo   = makeBillNo($saleId);

            $products[$idx]['quantity']   -= $qtyInt;
            $newQty = (int)$products[$idx]['quantity'];
            $products[$idx]['updated_at']  = date('Y-m-d H:i:s');
            saveJson($productsFile, $products);

            $newSale = [
                'id'             => $saleId,
                'bill_no'        => $billNo,
                'product_id'     => $productId,
                'product_name'   => $p['name'],
                'sku'            => $p['sku'],
                'category'       => $p['category'],
                'quantity'       => $qtyInt,
                'unit_price'     => $priceF,
                'total'          => $total,
                'customer_name'  => $customerName !== '' ? $customerName : 'Walk-in Customer',
                'customer_phone' => $customerPhone,
                'note'           => $note,
                'sale_date'      => $saleDate,
                'created_at'     => date('Y-m-d H:i:s'),
            ];
            $sales[] = $newSale;
            saveJson($salesFile, $sales);

            logMovement(
                $movementsFile,
                $productId,
                'sale',
                $qtyInt,
                $newQty,
                'Sale ' . $billNo . ($note !== '' ? ': ' . $note : '')
            );
            $movements = loadJson($movementsFile);

            checkStockNotification($notificationsFile, $products[$idx], $oldQty, $newQty);
            $notifications = loadJson($notificationsFile);

            $billSale = $newSale;
            $successMessage = 'Sale recorded and bill ' . $billNo . ' generated. You can download the PDF below.';
            $view = 'bill';
        }
    }

    // DELETE SALE (restores stock)
    if ($action === 'delete_sale') {
        $saleId = (int)($_POST['id'] ?? 0);
        $sIdx   = findIndex($sales, $saleId);
        if ($sIdx === false) {
            $errorMessage = 'Sale not found.';
            $view = 'sales';
        } else {
            $sale = $sales[$sIdx];
            $pIdx = findIndex($products, (int)$sale['product_id']);
            if ($pIdx !== false) {
                $products[$pIdx]['quantity']   += (int)$sale['quantity'];
                $products[$pIdx]['updated_at']  = date('Y-m-d H:i:s');
                saveJson($productsFile, $products);
                logMovement(
                    $movementsFile,
                    (int)$sale['product_id'],
                    'in',
                    (int)$sale['quantity'],
                    (int)$products[$pIdx]['quantity'],
                    'Sale cancelled / restored'
                );
                $movements = loadJson($movementsFile);
            }
            array_splice($sales, $sIdx, 1);
            saveJson($salesFile, $sales);
            $successMessage = 'Sale deleted and stock restored (if product still exists).';
            $view = 'sales';
        }
    }

    // MARK NOTIFICATION AS READ
    if ($action === 'mark_read') {
        $nid = (int)($_POST['id'] ?? 0);
        $nIdx = findIndex($notifications, $nid);
        if ($nIdx !== false) {
            $notifications[$nIdx]['read'] = true;
            saveJson($notificationsFile, $notifications);
            $successMessage = 'Notification marked as read.';
        }
        $view = 'notifications';
    }

    // MARK ALL NOTIFICATIONS AS READ
    if ($action === 'mark_all_read') {
        foreach ($notifications as $i => $n) {
            $notifications[$i]['read'] = true;
        }
        saveJson($notificationsFile, $notifications);
        $successMessage = 'All notifications marked as read.';
        $view = 'notifications';
    }

    // DELETE NOTIFICATION
    if ($action === 'delete_notification') {
        $nid = (int)($_POST['id'] ?? 0);
        $nIdx = findIndex($notifications, $nid);
        if ($nIdx !== false) {
            array_splice($notifications, $nIdx, 1);
            saveJson($notificationsFile, $notifications);
            $successMessage = 'Notification removed.';
        }
        $view = 'notifications';
    }

    // CLEAR ALL NOTIFICATIONS
    if ($action === 'clear_notifications') {
        $notifications = [];
        saveJson($notificationsFile, $notifications);
        $successMessage = 'All notifications cleared.';
        $view = 'notifications';
    }

    $products = loadJson($productsFile);
    $sales    = loadJson($salesFile);
    $movements = loadJson($movementsFile);
    $notifications = loadJson($notificationsFile);

    // Keep inventory detail product in sync after mutations
    if ($view === 'inventory' && $detailProduct) {
        $refreshIdx = findIndex($products, (int)$detailProduct['id']);
        $detailProduct = ($refreshIdx !== false) ? $products[$refreshIdx] : null;
        if ($detailProduct === null) {
            $view = 'list';
            $errorMessage = $errorMessage !== '' ? $errorMessage : 'Product not found.';
        }
    }

    // Keep bill sale in sync after recording a sale
    if ($view === 'bill' && $billSale) {
        $refreshBill = findIndex($sales, (int)$billSale['id']);
        $billSale = ($refreshBill !== false) ? $sales[$refreshBill] : $billSale;
    }
}

// ---------- LIST filters ----------
$search   = trim($_GET['q'] ?? '');
$filtered = $products;
if ($search !== '' && $view === 'list') {
    $filtered = array_filter($products, function ($p) use ($search) {
        $hay = strtolower($p['name'] . ' ' . $p['sku'] . ' ' . $p['category'] . ' ' . ($p['description'] ?? ''));
        return strpos($hay, strtolower($search)) !== false;
    });
}

// ---------- SALES REPORT filters ----------
$reportFrom      = trim($_GET['from'] ?? '');
$reportTo        = trim($_GET['to'] ?? '');
$reportProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$reportCategory  = trim($_GET['category'] ?? '');

if ($reportFrom === '' && ($view === 'sales' || $view === 'report')) {
    // default: show all (no date limit)
}
$filteredSales = $sales;
if ($view === 'sales' || $view === 'report') {
    $filteredSales = array_filter($sales, function ($s) use ($reportFrom, $reportTo, $reportProductId, $reportCategory) {
        if ($reportFrom !== '' && ($s['sale_date'] ?? '') < $reportFrom) {
            return false;
        }
        if ($reportTo !== '' && ($s['sale_date'] ?? '') > $reportTo) {
            return false;
        }
        if ($reportProductId > 0 && (int)$s['product_id'] !== $reportProductId) {
            return false;
        }
        if ($reportCategory !== '' && strcasecmp($s['category'] ?? '', $reportCategory) !== 0) {
            return false;
        }
        return true;
    });
    // newest first
    usort($filteredSales, function ($a, $b) {
        $da = ($a['sale_date'] ?? '') . ' ' . ($a['created_at'] ?? '');
        $db = ($b['sale_date'] ?? '') . ' ' . ($b['created_at'] ?? '');
        return strcmp($db, $da);
    });
}

// Sales summary stats
$salesUnits  = 0;
$salesTotal  = 0.0;
$salesByProduct = [];
$salesByDay     = [];
foreach ($filteredSales as $s) {
    $salesUnits += (int)$s['quantity'];
    $salesTotal += (float)$s['total'];
    $pid = (int)$s['product_id'];
    if (!isset($salesByProduct[$pid])) {
        $salesByProduct[$pid] = [
            'name'  => $s['product_name'],
            'sku'   => $s['sku'],
            'qty'   => 0,
            'total' => 0.0,
        ];
    }
    $salesByProduct[$pid]['qty']   += (int)$s['quantity'];
    $salesByProduct[$pid]['total'] += (float)$s['total'];

    $day = $s['sale_date'] ?? substr($s['created_at'] ?? '', 0, 10);
    if (!isset($salesByDay[$day])) {
        $salesByDay[$day] = ['qty' => 0, 'total' => 0.0];
    }
    $salesByDay[$day]['qty']   += (int)$s['quantity'];
    $salesByDay[$day]['total'] += (float)$s['total'];
}
ksort($salesByDay);

// Categories for filter dropdown
$categories = [];
foreach ($products as $p) {
    $c = $p['category'] ?? 'General';
    if ($c !== '' && !in_array($c, $categories, true)) {
        $categories[] = $c;
    }
}
sort($categories);

// Inventory detail: movements for part
$partMovements = [];
$partSales     = [];
if ($view === 'inventory' && $detailProduct) {
    $pid = (int)$detailProduct['id'];
    foreach ($movements as $m) {
        if ((int)$m['product_id'] === $pid) {
            $partMovements[] = $m;
        }
    }
    usort($partMovements, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    foreach ($sales as $s) {
        if ((int)$s['product_id'] === $pid) {
            $partSales[] = $s;
        }
    }
    usort($partSales, function ($a, $b) {
        return strcmp(($b['sale_date'] ?? '') . ($b['created_at'] ?? ''), ($a['sale_date'] ?? '') . ($a['created_at'] ?? ''));
    });
}

// Product list stats
$totalProducts = count($products);
$totalStock    = 0;
$totalValue    = 0.0;
$lowStockCount = 0;
foreach ($products as $p) {
    $totalStock += (int)$p['quantity'];
    $totalValue += (float)$p['price'] * (int)$p['quantity'];
    if ((int)$p['quantity'] <= LOW_STOCK_THRESHOLD) {
        $lowStockCount++;
    }
}

// Page titles
$pageTitles = [
    'list'          => 'Products',
    'add'           => 'Add Product',
    'edit'          => 'Modify Product',
    'sales'         => 'Sales Report',
    'sale_add'      => 'Record Sale',
    'inventory'     => 'Inventory Details',
    'report'        => 'Sales Summary',
    'notifications' => 'System Notifications',
    'bill'          => 'Sales Bill / Invoice',
];
$pageTitle = $pageTitles[$view] ?? 'Dashboard';
$pageSub = [
    'list'          => 'Input, output stock, and modify product records',
    'add'           => 'Add a new product or part to inventory',
    'edit'          => 'Update product details',
    'sales'         => 'Filter and review all sales transactions',
    'sale_add'      => 'Record a sale, generate bill, and download PDF',
    'inventory'     => 'Detailed stock and movement history for one part',
    'report'        => 'Sales totals by product and by day',
    'notifications' => 'Alerts when stock reaches 10 or below, and system milestones',
    'bill'          => 'View invoice and download as PDF',
];

// Notification counts (newest first for display)
$unreadNotifications = 0;
foreach ($notifications as $n) {
    if (empty($n['read'])) {
        $unreadNotifications++;
    }
}
$sortedNotifications = $notifications;
usort($sortedNotifications, function ($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});
// Latest unread for banner (up to 3)
$bannerNotes = [];
foreach ($sortedNotifications as $n) {
    if (empty($n['read'])) {
        $bannerNotes[] = $n;
        if (count($bannerNotes) >= 3) {
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
</head>

<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-icon">📦</span>
                <span>Product Manager</span>
            </div>
            <nav class="nav">
                <a href="dashboard.php?view=list" class="nav-link <?php echo $view === 'list' ? 'active' : ''; ?>">
                    <span>📋</span> All Products
                </a>
                <a href="dashboard.php?view=add" class="nav-link <?php echo $view === 'add' ? 'active' : ''; ?>">
                    <span>➕</span> Add Product
                </a>
                <a href="dashboard.php?view=sale_add" class="nav-link <?php echo $view === 'sale_add' ? 'active' : ''; ?>">
                    <span>🛒</span> Record Sale
                </a>
                <a href="dashboard.php?view=sales" class="nav-link <?php echo ($view === 'sales') ? 'active' : ''; ?>">
                    <span>📊</span> Sales Report
                </a>
                <a href="dashboard.php?view=report" class="nav-link <?php echo $view === 'report' ? 'active' : ''; ?>">
                    <span>📈</span> Sales Summary
                </a>
                <a href="dashboard.php?view=notifications" class="nav-link <?php echo $view === 'notifications' ? 'active' : ''; ?>">
                    <span>🔔</span> Notifications
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="nav-badge"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="login.php" class="nav-link logout">← Back to Login</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar topbar-flex">
                <div>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p class="topbar-sub"><?php echo htmlspecialchars($pageSub[$view] ?? ''); ?></p>
                </div>
                <a href="dashboard.php?view=notifications" class="notif-bell" title="System notifications">
                    🔔
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="bell-count"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
            </header>

            <?php if ($errorMessage !== ''): ?>
                <div class="message error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            <?php if ($successMessage !== ''): ?>
                <div class="message success-message"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php if (!empty($bannerNotes) && $view !== 'notifications'): ?>
                <div class="notif-banner">
                    <div class="notif-banner-title">
                        System alerts (<?php echo $unreadNotifications; ?> unread)
                        <a href="dashboard.php?view=notifications">View all</a>
                    </div>
                    <ul class="notif-banner-list">
                        <?php foreach ($bannerNotes as $bn): ?>
                            <li class="notif-banner-item type-<?php echo htmlspecialchars($bn['type'] ?? 'info'); ?>">
                                <strong><?php echo htmlspecialchars($bn['title'] ?? 'Alert'); ?>:</strong>
                                <?php echo htmlspecialchars($bn['message'] ?? ''); ?>
                                <?php if (!empty($bn['product_id'])): ?>
                                    <a href="dashboard.php?view=inventory&id=<?php echo (int)$bn['product_id']; ?>">Open part</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php // ===================== LIST ===================== ?>
            <?php if ($view === 'list'): ?>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-label">Total Products</div>
                        <div class="stat-value"><?php echo $totalProducts; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Stock Units</div>
                        <div class="stat-value"><?php echo $totalStock; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Inventory Value</div>
                        <div class="stat-value">$<?php echo number_format($totalValue, 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Low Stock (≤<?php echo LOW_STOCK_THRESHOLD; ?>)</div>
                        <div class="stat-value"><?php echo $lowStockCount; ?></div>
                    </div>
                </div>

                <div class="toolbar">
                    <form class="search-form" method="GET" action="dashboard.php">
                        <input type="hidden" name="view" value="list">
                        <input type="search" name="q" placeholder="Search name, SKU, category..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-secondary">Search</button>
                        <?php if ($search !== ''): ?>
                            <a href="dashboard.php?view=list" class="btn btn-ghost">Clear</a>
                        <?php endif; ?>
                    </form>
                    <div class="toolbar-right">
                        <a href="dashboard.php?view=sale_add" class="btn btn-secondary">Record Sale</a>
                        <a href="dashboard.php?view=add" class="btn btn-primary">+ Add Product</a>
                    </div>
                </div>

                <div class="table-wrap">
                    <?php if (empty($filtered)): ?>
                        <div class="empty-state">
                            <p>No products found.</p>
                            <a href="dashboard.php?view=add" class="btn btn-primary">Add your first product</a>
                        </div>
                    <?php else: ?>
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Input / Output</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filtered as $p): ?>
                                    <tr>
                                        <td><?php echo (int)$p['id']; ?></td>
                                        <td>
                                            <strong>
                                                <a class="link-name" href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>">
                                                    <?php echo htmlspecialchars($p['name']); ?>
                                                </a>
                                            </strong>
                                            <?php if (!empty($p['description'])): ?>
                                                <div class="cell-desc"><?php echo htmlspecialchars(shortText($p['description'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($p['sku']); ?></code></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($p['category']); ?></span></td>
                                        <td>$<?php echo number_format((float)$p['price'], 2); ?></td>
                                        <td>
                                            <span class="stock <?php echo (int)$p['quantity'] === 0 ? 'stock-zero' : ((int)$p['quantity'] <= LOW_STOCK_THRESHOLD ? 'stock-low' : ''); ?>">
                                                <?php echo (int)$p['quantity']; ?>
                                            </span>
                                        </td>
                                        <td class="stock-actions">
                                            <form method="POST" class="inline-form" title="Add stock (Input)">
                                                <input type="hidden" name="action" value="stock_in">
                                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                <input type="number" name="amount" min="1" value="1" class="qty-input" required>
                                                <button type="submit" class="btn btn-sm btn-in">In</button>
                                            </form>
                                            <form method="POST" class="inline-form" title="Remove stock (Output)">
                                                <input type="hidden" name="action" value="stock_out">
                                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                <input type="number" name="amount" min="1" value="1" class="qty-input" required>
                                                <button type="submit" class="btn btn-sm btn-out">Out</button>
                                            </form>
                                        </td>
                                        <td class="row-actions">
                                            <a href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-info">Details</a>
                                            <a href="dashboard.php?view=edit&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-secondary">Modify</a>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Delete this product?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            <?php // ===================== ADD ===================== ?>
            <?php elseif ($view === 'add'): ?>
                <div class="form-card">
                    <h2>New Product</h2>
                    <p class="form-hint">Fill in the details to add a product / part to inventory.</p>
                    <form method="POST" action="dashboard.php?view=add">
                        <input type="hidden" name="action" value="add">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Product Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" required
                                    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                    placeholder="e.g. Brake Pad Set">
                            </div>
                            <div class="form-group">
                                <label for="sku">SKU / Part No. <span class="required">*</span></label>
                                <input type="text" id="sku" name="sku" required
                                    value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>"
                                    placeholder="e.g. BP-001">
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" id="category" name="category"
                                    value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>"
                                    placeholder="e.g. Brakes">
                            </div>
                            <div class="form-group">
                                <label for="price">Price ($) <span class="required">*</span></label>
                                <input type="number" id="price" name="price" step="0.01" min="0" required
                                    value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                    placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label for="quantity">Initial Quantity <span class="required">*</span></label>
                                <input type="number" id="quantity" name="quantity" min="0" step="1" required
                                    value="<?php echo htmlspecialchars($_POST['quantity'] ?? '0'); ?>">
                            </div>
                            <div class="form-group full">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3"
                                    placeholder="Optional notes"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Product</button>
                        </div>
                    </form>
                </div>

            <?php // ===================== EDIT ===================== ?>
            <?php elseif ($view === 'edit' && $editProduct): ?>
                <div class="form-card">
                    <h2>Modify Product #<?php echo (int)$editProduct['id']; ?></h2>
                    <p class="form-hint">Update product details below.</p>
                    <form method="POST" action="dashboard.php?view=edit&id=<?php echo (int)$editProduct['id']; ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo (int)$editProduct['id']; ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Product Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" required
                                    value="<?php echo htmlspecialchars($editProduct['name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="sku">SKU / Part No. <span class="required">*</span></label>
                                <input type="text" id="sku" name="sku" required
                                    value="<?php echo htmlspecialchars($editProduct['sku']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" id="category" name="category"
                                    value="<?php echo htmlspecialchars($editProduct['category']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="price">Price ($) <span class="required">*</span></label>
                                <input type="number" id="price" name="price" step="0.01" min="0" required
                                    value="<?php echo htmlspecialchars((string)$editProduct['price']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantity <span class="required">*</span></label>
                                <input type="number" id="quantity" name="quantity" min="0" step="1" required
                                    value="<?php echo htmlspecialchars((string)$editProduct['quantity']); ?>">
                            </div>
                            <div class="form-group full">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($editProduct['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a>
                            <a href="dashboard.php?view=inventory&id=<?php echo (int)$editProduct['id']; ?>" class="btn btn-secondary">View Details</a>
                            <button type="submit" class="btn btn-primary">Update Product</button>
                        </div>
                    </form>
                </div>

            <?php // ===================== RECORD SALE ===================== ?>
            <?php elseif ($view === 'sale_add'): ?>
                <div class="form-card">
                    <h2>Record Sale</h2>
                    <p class="form-hint">Selling reduces stock and generates a bill you can download as PDF.</p>
                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <p>Add products before recording sales.</p>
                            <a href="dashboard.php?view=add" class="btn btn-primary">Add Product</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="dashboard.php?view=sale_add">
                            <input type="hidden" name="action" value="sale">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="customer_name">Customer Name</label>
                                    <input type="text" id="customer_name" name="customer_name"
                                        value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                                        placeholder="Walk-in Customer">
                                </div>
                                <div class="form-group">
                                    <label for="customer_phone">Customer Phone</label>
                                    <input type="text" id="customer_phone" name="customer_phone"
                                        value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>"
                                        placeholder="Optional">
                                </div>
                                <div class="form-group full">
                                    <label for="product_id">Product / Part <span class="required">*</span></label>
                                    <select id="product_id" name="product_id" required>
                                        <option value="">— Select product —</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>"
                                                data-price="<?php echo htmlspecialchars((string)$p['price']); ?>"
                                                data-stock="<?php echo (int)$p['quantity']; ?>"
                                                <?php echo (isset($_POST['product_id']) && (int)$_POST['product_id'] === (int)$p['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['name'] . ' (' . $p['sku'] . ') — stock: ' . $p['quantity']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="quantity">Quantity Sold <span class="required">*</span></label>
                                    <input type="number" id="quantity" name="quantity" min="1" step="1" required
                                        value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="unit_price">Unit Price ($) <span class="required">*</span></label>
                                    <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required
                                        value="<?php echo htmlspecialchars($_POST['unit_price'] ?? ''); ?>"
                                        placeholder="Uses product price if empty">
                                </div>
                                <div class="form-group">
                                    <label for="sale_date">Sale Date <span class="required">*</span></label>
                                    <input type="date" id="sale_date" name="sale_date" required
                                        value="<?php echo htmlspecialchars($_POST['sale_date'] ?? date('Y-m-d')); ?>">
                                </div>
                                <div class="form-group full">
                                    <label for="note">Note</label>
                                    <input type="text" id="note" name="note"
                                        value="<?php echo htmlspecialchars($_POST['note'] ?? ''); ?>"
                                        placeholder="Optional note on the bill">
                                </div>
                            </div>
                            <div class="form-actions">
                                <a href="dashboard.php?view=sales" class="btn btn-ghost">View Sales Report</a>
                                <button type="submit" class="btn btn-primary">Save Sale &amp; Generate Bill</button>
                            </div>
                        </form>
                        <script>
                            // Pure JS — no libraries: fill unit price from selected product
                            (function () {
                                var sel = document.getElementById('product_id');
                                var price = document.getElementById('unit_price');
                                if (!sel || !price) return;
                                function fill() {
                                    var opt = sel.options[sel.selectedIndex];
                                    if (opt && opt.getAttribute('data-price') && price.value === '') {
                                        price.value = opt.getAttribute('data-price');
                                    }
                                }
                                sel.addEventListener('change', function () {
                                    var opt = sel.options[sel.selectedIndex];
                                    if (opt && opt.getAttribute('data-price')) {
                                        price.value = opt.getAttribute('data-price');
                                    }
                                });
                                fill();
                            })();
                        </script>
                    <?php endif; ?>
                </div>

            <?php // ===================== SALES REPORT ===================== ?>
            <?php elseif ($view === 'sales'): ?>
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

            <?php // ===================== SALES SUMMARY ===================== ?>
            <?php elseif ($view === 'report'): ?>
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

            <?php // ===================== INVENTORY DETAILS (one part) ===================== ?>
            <?php elseif ($view === 'inventory' && $detailProduct): ?>
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

            <?php // ===================== BILL / INVOICE ===================== ?>
            <?php elseif ($view === 'bill' && $billSale): ?>
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

            <?php // ===================== SYSTEM NOTIFICATIONS ===================== ?>
            <?php elseif ($view === 'notifications'): ?>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-label">Total Alerts</div>
                        <div class="stat-value"><?php echo count($notifications); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Unread</div>
                        <div class="stat-value <?php echo $unreadNotifications > 0 ? 'text-warn' : ''; ?>">
                            <?php echo $unreadNotifications; ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Low stock threshold</div>
                        <div class="stat-value"><?php echo LOW_STOCK_THRESHOLD; ?></div>
                    </div>
                </div>

                <div class="toolbar">
                    <p class="form-hint" style="margin:0;">
                        The system sends an alert when a product’s stock reaches <strong><?php echo LOW_STOCK_THRESHOLD; ?></strong> or below,
                        when it hits <strong>0</strong>, and when the catalog reaches <strong><?php echo PRODUCT_COUNT_ALERT; ?></strong> products.
                    </p>
                    <div class="toolbar-right">
                        <?php if ($unreadNotifications > 0): ?>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-secondary">Mark all read</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($notifications)): ?>
                            <form method="POST" class="inline-form" onsubmit="return confirm('Clear all notifications?');">
                                <input type="hidden" name="action" value="clear_notifications">
                                <button type="submit" class="btn btn-danger">Clear all</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-wrap">
                    <?php if (empty($sortedNotifications)): ?>
                        <div class="empty-state">
                            <p>No system notifications yet.</p>
                            <p class="cell-desc">Alerts appear when stock falls to <?php echo LOW_STOCK_THRESHOLD; ?> or below.</p>
                        </div>
                    <?php else: ?>
                        <ul class="notif-list">
                            <?php foreach ($sortedNotifications as $n): ?>
                                <li class="notif-item <?php echo empty($n['read']) ? 'notif-unread' : 'notif-read'; ?> notif-type-<?php echo htmlspecialchars($n['type'] ?? 'info'); ?>">
                                    <div class="notif-item-main">
                                        <div class="notif-item-top">
                                            <span class="type-badge type-<?php echo htmlspecialchars($n['type'] ?? 'info'); ?>">
                                                <?php
                                                $typeLabels = [
                                                    'low_stock'     => 'LOW STOCK',
                                                    'out_of_stock'  => 'OUT OF STOCK',
                                                    'product_count' => 'MILESTONE',
                                                    'info'          => 'INFO',
                                                ];
                                                echo htmlspecialchars($typeLabels[$n['type'] ?? 'info'] ?? strtoupper($n['type'] ?? 'INFO'));
                                                ?>
                                            </span>
                                            <span class="notif-time"><?php echo htmlspecialchars($n['created_at'] ?? ''); ?></span>
                                        </div>
                                        <h3 class="notif-item-title"><?php echo htmlspecialchars($n['title'] ?? ''); ?></h3>
                                        <p class="notif-item-msg"><?php echo htmlspecialchars($n['message'] ?? ''); ?></p>
                                        <?php if (!empty($n['product_id'])): ?>
                                            <a class="link-name" href="dashboard.php?view=inventory&id=<?php echo (int)$n['product_id']; ?>">
                                                View inventory details →
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notif-item-actions">
                                        <?php if (empty($n['read'])): ?>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary">Mark read</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge">Read</span>
                                        <?php endif; ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="delete_notification">
                                            <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>

</html>
