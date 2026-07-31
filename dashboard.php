<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: login.php'); exit; }
require_once __DIR__ . '/pdf_invoice.php';

 $dbHost = 'localhost'; $dbPort = 3306; $dbUser = 'root'; $dbPass = ''; $dbName = 'ims';
try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("USE `$dbName`");
} catch (PDOException $e) {
    die('<h2>Database connection failed</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Run <a href="install.php">install.php</a> first.</p>');
}

define('LOW_STOCK_THRESHOLD', 10);
define('PRODUCT_COUNT_ALERT', 10);
define('TAX_RATE', 0.13);

function shortText(string $text, int $max = 48): string {
    return strlen($text) <= $max ? $text : substr($text, 0, $max - 1) . '…';
}
function logMovement(PDO $pdo, int $pid, string $type, int $amt, int $bal, string $note = ''): void {
    $pdo->prepare('INSERT INTO movements (product_id,type,amount,balance_after,note) VALUES (?,?,?,?,?)')->execute([$pid,$type,$amt,$bal,$note]);
}
function addNotification(PDO $pdo, string $type, string $title, string $msg, ?int $pid = null): void {
    $pdo->prepare('INSERT INTO notifications (type,title,message,product_id,is_read) VALUES (?,?,?,?,0)')->execute([$type,$title,$msg,$pid]);
}
function checkStockNotification(PDO $pdo, array $product, int $old, int $new): void {
    $name = $product['name'] ?? 'Product'; $sku = $product['sku'] ?? ''; $id = (int)($product['id'] ?? 0);
    if ($new === 0 && $old > 0) { addNotification($pdo,'out_of_stock','Out of stock','"'.$name.'" (Product-ID: '.$sku.') is out of stock. Please restock.',$id); return; }
    if ($new > 0 && $new <= LOW_STOCK_THRESHOLD && $old > LOW_STOCK_THRESHOLD) { addNotification($pdo,'low_stock','Low stock alert','"'.$name.'" (Product-ID: '.$sku.') has only '.$new.' unit(s) left.',$id); }
}
function checkProductCountNotification(PDO $pdo, int $count): void {
    if ($count !== PRODUCT_COUNT_ALERT) return;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE type='product_count' AND message LIKE ?");
    $stmt->execute(['%'.PRODUCT_COUNT_ALERT.'%']);
    if ((int)$stmt->fetchColumn() > 0) return;
    addNotification($pdo,'product_count','Product catalog milestone','The system now has '.PRODUCT_COUNT_ALERT.' products registered.',null);
}

 $errorMessage = ''; $successMessage = '';
 $products      = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
 $sales         = $pdo->query('SELECT * FROM sales ORDER BY sale_date DESC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
 $movements     = $pdo->query('SELECT * FROM movements ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
 $notifications = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

 /* ===== CHANGE #3: fetch staff accounts, placed right after $notifications ===== */
 $staffUsers = $pdo->query("SELECT id,username,created_at FROM users WHERE role='staff' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
 /* ===== END CHANGE #3 ===== */

 $editProduct = null; $detailProduct = null;

 /* ===== CHANGE #1: 'staff' added to allowed views ===== */
 $allowedViews = ['list','add','edit','sales','sale_add','inventory','report','notifications','bill','staff'];
 /* ===== END CHANGE #1 ===== */
 $view = $_GET['view'] ?? 'list';
if (!in_array($view, $allowedViews, true)) $view = 'list';

if (isset($_GET['download']) && $_GET['download'] === 'pdf' && isset($_GET['sale_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?'); $stmt->execute([(int)$_GET['sale_id']]);
    $dl = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dl) { http_response_code(404); echo 'Bill not found.'; exit; }
    downloadInvoicePdf($dl);
}

 $billSale = null;
if ($view === 'bill' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?'); $stmt->execute([(int)$_GET['id']]);
    $billSale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$billSale) { $errorMessage = 'Bill not found.'; $view = 'sales'; }
}
if ($view === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([(int)$_GET['id']]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editProduct) { $errorMessage = 'Product not found.'; $view = 'list'; }
}
if ($view === 'inventory' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([(int)$_GET['id']]);
    $detailProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$detailProduct) { $errorMessage = 'Product not found.'; $view = 'list'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? ''); $sku = trim($_POST['sku'] ?? '');
        $cat = trim($_POST['category'] ?? ''); $price = trim($_POST['price'] ?? '');
        $qty = trim($_POST['quantity'] ?? ''); $desc = trim($_POST['description'] ?? '');
        if ($name === '' || $sku === '' || $price === '' || $qty === '') { $errorMessage = 'Name, Product-ID, price, and quantity are required.'; $view = 'add'; }
        elseif (!is_numeric($price) || (float)$price < 0) { $errorMessage = 'Price must be valid.'; $view = 'add'; }
        elseif (!preg_match('/^\d+$/', $qty)) { $errorMessage = 'Quantity must be a whole number.'; $view = 'add'; }
        else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku=?'); $stmt->execute([$sku]);
            if ($stmt->fetchColumn() > 0) { $errorMessage = 'This Product-ID already exists.'; $view = 'add'; }
            else {
                $q = (int)$qty; $c = $cat !== '' ? $cat : 'General';
                $pdo->prepare('INSERT INTO products (name,sku,category,price,quantity,description) VALUES (?,?,?,?,?,?)')->execute([$name,$sku,$c,round((float)$price,2),$q,$desc]);
                $newId = (int)$pdo->lastInsertId();
                if ($q > 0) logMovement($pdo,$newId,'in',$q,$q,'Initial stock');
                $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$newId]);
                $np = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($q > 0 && $q <= LOW_STOCK_THRESHOLD) checkStockNotification($pdo,$np,LOW_STOCK_THRESHOLD+1,$q);
                elseif ($q === 0) checkStockNotification($pdo,$np,1,0);
                checkProductCountNotification($pdo,(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
                $successMessage = 'Product added successfully.'; $view = 'list';
            }
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0); $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? ''); $cat = trim($_POST['category'] ?? '');
        $price = trim($_POST['price'] ?? ''); $qty = trim($_POST['quantity'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$id]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editProduct) { $errorMessage = 'Product not found.'; $view = 'list'; }
        elseif ($name === '' || $sku === '' || $price === '' || $qty === '') { $errorMessage = 'Name, Product-ID, price, and quantity are required.'; $view = 'edit'; }
        elseif (!is_numeric($price) || (float)$price < 0) { $errorMessage = 'Price must be valid.'; $view = 'edit'; }
        elseif (!preg_match('/^\d+$/', $qty)) { $errorMessage = 'Quantity must be a whole number.'; $view = 'edit'; }
        else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku=? AND id!=?'); $stmt->execute([$sku,$id]);
            if ($stmt->fetchColumn() > 0) { $errorMessage = 'This Product-ID already exists.'; $view = 'edit'; }
            else {
                $oldQty = (int)$editProduct['quantity']; $newQty = (int)$qty;
                $c = $cat !== '' ? $cat : 'General';
                $pdo->prepare('UPDATE products SET name=?,sku=?,category=?,price=?,quantity=?,description=? WHERE id=?')->execute([$name,$sku,$c,round((float)$price,2),$newQty,$desc,$id]);
                if ($newQty !== $oldQty) {
                    $diff = $newQty - $oldQty;
                    logMovement($pdo,$id,'adjust',abs($diff),$newQty,'Manual adjust ('.($diff>0?'+':'-').abs($diff).')');
                    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$id]);
                    checkStockNotification($pdo,$stmt->fetch(PDO::FETCH_ASSOC),$oldQty,$newQty);
                }
                $successMessage = 'Product updated.'; $view = 'list'; $editProduct = null;
            }
        }
    }

    if ($action === 'stock_in') {
        $id = (int)($_POST['id'] ?? 0); $amt = trim($_POST['amount'] ?? ''); $ret = $_POST['return_view'] ?? 'list';
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) { $errorMessage = 'Product not found.'; $view = 'list'; }
        elseif (!preg_match('/^\d+$/', $amt) || (int)$amt < 1) { $errorMessage = 'Enter valid amount (1+).'; $view = $ret==='inventory'?'inventory':'list'; if ($view==='inventory') $detailProduct=$product; }
        else {
            $a = (int)$amt; $old = (int)$product['quantity']; $new = $old + $a;
            $pdo->prepare('UPDATE products SET quantity=quantity+? WHERE id=?')->execute([$a,$id]);
            logMovement($pdo,$id,'in',$a,$new,'Stock input');
            $successMessage = 'Stock added: +'.$a.' to "'.$product['name'].'".';
            if ($ret === 'inventory') { $view='inventory'; $stmt=$pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$id]); $detailProduct=$stmt->fetch(PDO::FETCH_ASSOC); }
            else $view = 'list';
        }
    }

    if ($action === 'stock_out') {
        $id = (int)($_POST['id'] ?? 0); $amt = trim($_POST['amount'] ?? ''); $ret = $_POST['return_view'] ?? 'list';
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) { $errorMessage = 'Product not found.'; $view = 'list'; }
        elseif (!preg_match('/^\d+$/', $amt) || (int)$amt < 1) { $errorMessage = 'Enter valid amount (1+).'; $view = $ret==='inventory'?'inventory':'list'; if ($view==='inventory') $detailProduct=$product; }
        elseif ((int)$amt > (int)$product['quantity']) { $errorMessage = 'Not enough stock. Available: '.$product['quantity'].'.'; $view = $ret==='inventory'?'inventory':'list'; if ($view==='inventory') $detailProduct=$product; }
        else {
            $a = (int)$amt; $old = (int)$product['quantity']; $new = $old - $a;
            $pdo->prepare('UPDATE products SET quantity=quantity-? WHERE id=?')->execute([$a,$id]);
            logMovement($pdo,$id,'out',$a,$new,'Stock output');
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$id]);
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);
            checkStockNotification($pdo,$updated,$old,$new);
            $successMessage = 'Stock removed: -'.$a.' from "'.$product['name'].'".';
            if ($ret === 'inventory') { $view='inventory'; $detailProduct=$updated; } else $view='list';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT name FROM products WHERE id=?'); $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) $errorMessage = 'Product not found.';
        else { $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]); $successMessage = 'Product "'.$row['name'].'" deleted.'; }
        $view = 'list';
    }

    if ($action === 'sale') {
        $pid = (int)($_POST['product_id'] ?? 0); $qty = trim($_POST['quantity'] ?? '');
        $up = trim($_POST['unit_price'] ?? ''); $note = trim($_POST['note'] ?? '');
        $sd = trim($_POST['sale_date'] ?? date('Y-m-d'));
        $cn = trim($_POST['customer_name'] ?? ''); $cp = trim($_POST['customer_phone'] ?? '');
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$pid]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) { $errorMessage = 'Select a valid product.'; $view = 'sale_add'; }
        elseif (!preg_match('/^\d+$/', $qty) || (int)$qty < 1) { $errorMessage = 'Quantity must be at least 1.'; $view = 'sale_add'; }
        elseif ((int)$qty > (int)$p['quantity']) { $errorMessage = 'Not enough stock. Available: '.$p['quantity'].'.'; $view = 'sale_add'; }
        elseif ($up === '' || !is_numeric($up) || (float)$up < 0) { $errorMessage = 'Enter valid unit price.'; $view = 'sale_add'; }
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sd)) { $errorMessage = 'Enter valid date (YYYY-MM-DD).'; $view = 'sale_add'; }
        else {
            $qi = (int)$qty; $pf = round((float)$up,2);
            $subtotal = round($pf*$qi,2);
            $taxAmt = round($subtotal*TAX_RATE,2);
            $total = round($subtotal+$taxAmt,2);
            $oldQ = (int)$p['quantity']; $newQ = $oldQ - $qi;
            $custN = $cn !== '' ? $cn : 'Walk-in Customer';
            $status = $pdo->query("SHOW TABLE STATUS LIKE 'sales'")->fetch(PDO::FETCH_ASSOC);
            $nextId = (int)$status['Auto_increment']; $billNo = makeBillNo($nextId);
            $pdo->prepare('UPDATE products SET quantity=quantity-? WHERE id=?')->execute([$qi,$pid]);
            $pdo->prepare('INSERT INTO sales (bill_no,product_id,product_name,sku,category,quantity,unit_price,total,customer_name,customer_phone,note,sale_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$billNo,$pid,$p['name'],$p['sku'],$p['category']??'General',$qi,$pf,$total,$custN,$cp,$note,$sd]);
            $saleId = (int)$pdo->lastInsertId();
            if ($saleId !== $nextId) { $realBill = makeBillNo($saleId); $pdo->prepare('UPDATE sales SET bill_no=? WHERE id=?')->execute([$realBill,$saleId]); $billNo = $realBill; }
            logMovement($pdo,$pid,'sale',$qi,$newQ,'Sale '.$billNo.($note!==''?': '.$note:''));
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$pid]);
            checkStockNotification($pdo,$stmt->fetch(PDO::FETCH_ASSOC),$oldQ,$newQ);
            $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?'); $stmt->execute([$saleId]);
            $billSale = $stmt->fetch(PDO::FETCH_ASSOC);
            $billSale['_subtotal']=$subtotal;
            $billSale['_tax']=$taxAmt;
            $billSale['_total']=$total;
            $successMessage = 'Sale recorded. Bill '.$billNo.' generated.'; $view = 'bill';
        }
    }

    if ($action === 'delete_sale') {
        $sid = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE id=?'); $stmt->execute([$sid]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale) { $errorMessage = 'Sale not found.'; $view = 'sales'; }
        else {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([(int)$sale['product_id']]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($prod) { $pdo->prepare('UPDATE products SET quantity=quantity+? WHERE id=?')->execute([(int)$sale['quantity'],(int)$sale['product_id']]);
                logMovement($pdo,(int)$sale['product_id'],'in',(int)$sale['quantity'],(int)$prod['quantity']+(int)$sale['quantity'],'Sale cancelled / restored'); }
            $pdo->prepare('DELETE FROM sales WHERE id=?')->execute([$sid]);
            $successMessage = 'Sale deleted, stock restored.'; $view = 'sales';
        }
    }

    if ($action === 'mark_read') { $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=?')->execute([(int)($_POST['id']??0)]); $successMessage='Marked as read.'; $view='notifications'; }
    if ($action === 'mark_all_read') { $pdo->exec('UPDATE notifications SET is_read=1 WHERE is_read=0'); $successMessage='All marked as read.'; $view='notifications'; }
    if ($action === 'delete_notification') { $pdo->prepare('DELETE FROM notifications WHERE id=?')->execute([(int)($_POST['id']??0)]); $successMessage='Removed.'; $view='notifications'; }
    if ($action === 'clear_notifications') { $pdo->exec('DELETE FROM notifications'); $successMessage='All cleared.'; $view='notifications'; }

    /* ===== CHANGE #2: staff management POST handlers — placed right after clear_notifications, before the reload queries below ===== */
    if ($action === 'staff_update') {
        $id = (int)($_POST['id'] ?? 0);
        $newUser = trim($_POST['username'] ?? '');
        $newPass = trim($_POST['password'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='staff'"); $stmt->execute([$id]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$staff) { $errorMessage = 'Staff not found.'; $view = 'staff'; }
        elseif (!preg_match('/^[a-zA-Z0-9]{3,15}$/', $newUser)) { $errorMessage = 'Username: 3-15 chars, letters & numbers.'; $view = 'staff'; }
        else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=? AND id!=?'); $stmt->execute([$newUser,$id]);
            if ($stmt->fetchColumn() > 0) { $errorMessage = 'Username already taken.'; $view = 'staff'; }
            elseif ($newPass !== '' && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $newPass)) { $errorMessage = 'Password: 8+ chars, upper, lower, number, special char.'; $view = 'staff'; }
            else {
                if ($newPass !== '') {
                    $pdo->prepare('UPDATE users SET username=?,password_hash=? WHERE id=?')->execute([$newUser, password_hash($newPass, PASSWORD_DEFAULT), $id]);
                } else {
                    $pdo->prepare('UPDATE users SET username=? WHERE id=?')->execute([$newUser, $id]);
                }
                $successMessage = 'Staff account updated.'; $view = 'staff';
            }
        }
    }

    if ($action === 'staff_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id=? AND role='staff'"); $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $errorMessage = 'Staff not found.'; }
        else { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]); $successMessage = 'Staff "'.$row['username'].'" deleted.'; }
        $view = 'staff';
    }
    /* ===== END CHANGE #2 ===== */

    $products = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $sales = $pdo->query('SELECT * FROM sales ORDER BY sale_date DESC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $movements = $pdo->query('SELECT * FROM movements ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $notifications = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $staffUsers = $pdo->query("SELECT id,username,created_at FROM users WHERE role='staff' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if ($view === 'inventory' && $detailProduct) { $stmt=$pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([(int)$detailProduct['id']]); $detailProduct=$stmt->fetch(PDO::FETCH_ASSOC); if (!$detailProduct) { $view='list'; $errorMessage=$errorMessage?:'Product not found.'; } }
    if ($view === 'bill' && $billSale) { $stmt=$pdo->prepare('SELECT * FROM sales WHERE id=?'); $stmt->execute([(int)$billSale['id']]); $fresh=$stmt->fetch(PDO::FETCH_ASSOC); if($fresh){$fresh['_subtotal']=$billSale['_subtotal']??0;$fresh['_tax']=$billSale['_tax']??0;$fresh['_total']=$billSale['_total']??0;$billSale=$fresh;} }
}

 $search = trim($_GET['q'] ?? '');
if ($search !== '' && $view === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE CONCAT(name,' ',sku,' ',category,' ',COALESCE(description,'')) LIKE ? ORDER BY id");
    $stmt->execute(['%'.$search.'%']); $filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else $filtered = $products;

 $reportFrom = trim($_GET['from'] ?? ''); $reportTo = trim($_GET['to'] ?? '');
 $reportProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
 $reportCategory = trim($_GET['category'] ?? '');

if ($view === 'sales' || $view === 'report') {
    $sql = 'SELECT * FROM sales WHERE 1=1'; $params = [];
    if ($reportFrom !== '') { $sql .= ' AND sale_date>=?'; $params[] = $reportFrom; }
    if ($reportTo !== '') { $sql .= ' AND sale_date<=?'; $params[] = $reportTo; }
    if ($reportProductId > 0) { $sql .= ' AND product_id=?'; $params[] = $reportProductId; }
    if ($reportCategory !== '') { $sql .= ' AND category=?'; $params[] = $reportCategory; }
    $sql .= ' ORDER BY sale_date DESC, created_at DESC';
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $filteredSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else $filteredSales = $sales;

 $salesUnits = 0; $salesTotal = 0.0; $salesByProduct = []; $salesByDay = [];
foreach ($filteredSales as $s) {
    $salesUnits += (int)$s['quantity']; $salesTotal += (float)$s['total'];
    $pid = (int)$s['product_id'];
    if (!isset($salesByProduct[$pid])) $salesByProduct[$pid] = ['name'=>$s['product_name'],'sku'=>$s['sku'],'qty'=>0,'total'=>0.0];
    $salesByProduct[$pid]['qty'] += (int)$s['quantity']; $salesByProduct[$pid]['total'] += (float)$s['total'];
    $day = $s['sale_date'];
    if (!isset($salesByDay[$day])) $salesByDay[$day] = ['qty'=>0,'total'=>0.0];
    $salesByDay[$day]['qty'] += (int)$s['quantity']; $salesByDay[$day]['total'] += (float)$s['total'];
}
ksort($salesByDay);

 $categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category!='' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

 $partMovements = []; $partSales = [];
if ($view === 'inventory' && $detailProduct) {
    $pid = (int)$detailProduct['id'];
    $stmt = $pdo->prepare('SELECT * FROM movements WHERE product_id=? ORDER BY created_at DESC'); $stmt->execute([$pid]);
    $partMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE product_id=? ORDER BY sale_date DESC, created_at DESC'); $stmt->execute([$pid]);
    $partSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

 $statsRow = $pdo->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(quantity),0) AS total_stock, COALESCE(SUM(price*quantity),0) AS total_value, SUM(CASE WHEN quantity<='.LOW_STOCK_THRESHOLD.' THEN 1 ELSE 0 END) AS low_cnt FROM products')->fetch(PDO::FETCH_ASSOC);
 $totalProducts = (int)$statsRow['cnt']; $totalStock = (int)$statsRow['total_stock'];
 $totalValue = (float)$statsRow['total_value']; $lowStockCount = (int)$statsRow['low_cnt'];

 /* ===== CHANGE #5: page titles / subtitles — 'staff' entry added to both arrays ===== */
 $pageTitles = ['list'=>'Products','add'=>'Add Product','edit'=>'Modify Product','sales'=>'Sales Report','sale_add'=>'Record Sale','inventory'=>'Inventory Details','report'=>'Sales Summary','notifications'=>'Notifications','bill'=>'Bill','staff'=>'Manage Staff'];
 $pageTitle = $pageTitles[$view] ?? 'Dashboard';
 $pageSub = ['list'=>'Manage products and stock','add'=>'Add a new product','edit'=>'Update product details','sales'=>'View all sales','sale_add'=>'Record a sale','inventory'=>'Stock history for one product','report'=>'Sales totals','notifications'=>'System alerts','bill'=>'View bill','staff'=>'Edit or remove staff accounts'];
 /* ===== END CHANGE #5 ===== */

 $unreadNotifications = (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read=0')->fetchColumn();
 $sortedNotifications = $notifications;
 $bannerNotes = $pdo->query('SELECT * FROM notifications WHERE is_read=0 ORDER BY created_at DESC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Nirman</title>
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
    .bill-print{max-width:700px;margin:0 auto;background:#fff;color:#111;padding:2rem;border-radius:10px;font-family:'Segoe UI',sans-serif;}
    .bill-print h1{text-align:center;font-size:2rem;margin:0;color:#1e293b;}
    .bill-print .bill-title{text-align:center;font-size:1.3rem;font-weight:700;letter-spacing:2px;margin:.3rem 0 .1rem;color:#334155;}
    .bill-print .bill-company-info{text-align:center;font-size:.82rem;color:#64748b;margin-bottom:1rem;}
    .bill-print .bill-divider{border:none;border-top:2px solid #334155;margin:.7rem 0;}
    .bill-print .bill-meta-row{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:.5rem;}
    .bill-print table{width:100%;border-collapse:collapse;margin-top:.7rem;}
    .bill-print th{background:#1e293b;color:#fff;padding:.5rem .7rem;text-align:left;font-size:.85rem;}
    .bill-print td{padding:.45rem .7rem;border-bottom:1px solid #e2e8f0;font-size:.88rem;}
    .bill-print .bill-totals{margin-top:.5rem;text-align:right;}
    .bill-print .bill-totals table{width:auto;margin-left:auto;}
    .bill-print .bill-totals td{border:none;padding:.2rem .5rem;}
    .bill-print .grand-total td{font-size:1.1rem;font-weight:700;border-top:2px solid #334155;}
    .bill-print .bill-footer{text-align:center;margin-top:1.2rem;font-size:.78rem;color:#94a3b8;}
    @media print{.no-print{display:none!important;}.bill-print{box-shadow:none;}}
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand"><span class="brand-icon">📦</span><span>Nirman</span></div>
        <nav class="nav">
            <a href="dashboard.php?view=list" class="nav-link <?php echo $view==='list'?'active':''; ?>">📋 Products</a>
            <a href="dashboard.php?view=add" class="nav-link <?php echo $view==='add'?'active':''; ?>">➕ Add Product</a>
            <a href="dashboard.php?view=sale_add" class="nav-link <?php echo $view==='sale_add'?'active':''; ?>">🛒 Record Sale</a>
            <a href="dashboard.php?view=sales" class="nav-link <?php echo $view==='sales'?'active':''; ?>">📊 Sales Report</a>
            <a href="dashboard.php?view=report" class="nav-link <?php echo $view==='report'?'active':''; ?>">📈 Sales Summary</a>
            <!-- ===== CHANGE #4: Manage Staff sidebar link ===== -->
            <a href="dashboard.php?view=staff" class="nav-link <?php echo $view==='staff'?'active':''; ?>">👷 Manage Staff</a>
            <!-- ===== END CHANGE #4 ===== -->
            <a href="dashboard.php?view=notifications" class="nav-link <?php echo $view==='notifications'?'active':''; ?>">🔔 Notifications <?php if ($unreadNotifications>0): ?><span class="nav-badge"><?php echo $unreadNotifications; ?></span><?php endif; ?></a>
        </nav>
        <div class="sidebar-footer"><a href="login.php" class="nav-link logout">← Logout</a></div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p class="topbar-sub"><?php echo htmlspecialchars($pageSub[$view]??''); ?></p>
            </div>
            <a href="dashboard.php?view=notifications" class="notif-bell">🔔 <?php if ($unreadNotifications>0): ?><span class="bell-count"><?php echo $unreadNotifications; ?></span><?php endif; ?></a>
        </header>

        <?php if ($errorMessage!==''): ?><div class="msg msg-error"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>
        <?php if ($successMessage!==''): ?><div class="msg msg-success"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>

        <?php if (!empty($bannerNotes) && $view!=='notifications'): ?>
        <div class="notif-banner">
            <div class="notif-banner-title">Alerts (<?php echo $unreadNotifications; ?> unread) <a href="dashboard.php?view=notifications">View all</a></div>
            <ul class="notif-banner-list">
                <?php foreach ($bannerNotes as $bn): ?>
                <li class="type-<?php echo htmlspecialchars($bn['type']??'info'); ?>"><strong><?php echo htmlspecialchars($bn['title']??'Alert'); ?>:</strong> <?php echo htmlspecialchars($bn['message']??''); ?> <?php if (!empty($bn['product_id'])): ?><a href="dashboard.php?view=inventory&id=<?php echo (int)$bn['product_id']; ?>">View</a><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- ═══════ LIST ═══════ -->
        <?php if ($view==='list'): ?>
        <div class="stats">
            <div class="stat-card"><div class="stat-label">Total Products</div><div class="stat-value"><?php echo $totalProducts; ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Stock</div><div class="stat-value"><?php echo $totalStock; ?></div></div>
            <div class="stat-card"><div class="stat-label">Inventory Value</div><div class="stat-value">Rs.<?php echo number_format($totalValue,2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Low Stock (≤<?php echo LOW_STOCK_THRESHOLD; ?>)</div><div class="stat-value"><?php echo $lowStockCount; ?></div></div>
        </div>
        <div class="toolbar">
            <form class="search-form" method="GET" action="dashboard.php">
                <input type="hidden" name="view" value="list">
                <input type="search" name="q" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if ($search!==''): ?><a href="dashboard.php?view=list" class="btn btn-ghost">Clear</a><?php endif; ?>
            </form>
            <div class="toolbar-right">
                <a href="dashboard.php?view=sale_add" class="btn btn-secondary">Record Sale</a>
                <a href="dashboard.php?view=add" class="btn btn-primary">+ Add Product</a>
            </div>
        </div>
        <div class="table-wrap">
            <?php if (empty($filtered)): ?>
            <div class="empty-state"><p>No products found.</p><a href="dashboard.php?view=add" class="btn btn-primary">Add first product</a></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>ID</th><th>Name</th><th>Product-ID</th><th>Category</th><th>Price</th><th>Stock</th><th>In / Out</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($filtered as $p): ?>
                <tr>
                    <td><?php echo (int)$p['id']; ?></td>
                    <td><a class="link-name" href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                        <?php if (!empty($p['description'])): ?><div class="cell-desc"><?php echo htmlspecialchars(shortText($p['description'])); ?></div><?php endif; ?></td>
                    <td><code><?php echo htmlspecialchars($p['sku']); ?></code></td>
                    <td><span class="badge"><?php echo htmlspecialchars($p['category']); ?></span></td>
                    <td>Rs.<?php echo number_format((float)$p['price'],2); ?></td>
                    <td><span class="stock <?php echo (int)$p['quantity']===0?'stock-zero':((int)$p['quantity']<=LOW_STOCK_THRESHOLD?'stock-low':''); ?>"><?php echo (int)$p['quantity']; ?></span></td>
                    <td class="stock-actions">
                        <form method="POST" class="inline-form"><input type="hidden" name="action" value="stock_in"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><input type="number" name="amount" min="1" value="1" class="qty-input" required><button type="submit" class="btn btn-sm btn-in">In</button></form>
                        <form method="POST" class="inline-form"><input type="hidden" name="action" value="stock_out"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><input type="number" name="amount" min="1" value="1" class="qty-input" required><button type="submit" class="btn btn-sm btn-out">Out</button></form>
                    </td>
                    <td class="row-actions">
                        <a href="dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-info">Details</a>
                        <a href="dashboard.php?view=edit&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this product?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">Delete</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ═══════ ADD ═══════ -->
        <?php elseif ($view==='add'): ?>
        <div class="form-card">
            <h2>New Product</h2><p class="form-hint">Add a product to inventory.</p>
            <form method="POST" action="dashboard.php?view=add">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group"><label>Product Name <span class="req">*</span></label><input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name']??''); ?>"></div>
                    <div class="form-group"><label>Product-ID <span class="req">*</span></label><input type="text" name="sku" required value="<?php echo htmlspecialchars($_POST['sku']??''); ?>"></div>
                    <div class="form-group"><label>Category</label><input type="text" name="category" value="<?php echo htmlspecialchars($_POST['category']??''); ?>"></div>
                    <div class="form-group"><label>Price (Rs.) <span class="req">*</span></label><input type="number" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['price']??''); ?>"></div>
                    <div class="form-group"><label>Initial Quantity <span class="req">*</span></label><input type="number" name="quantity" min="0" step="1" required value="<?php echo htmlspecialchars($_POST['quantity']??'0'); ?>"></div>
                    <div class="form-group full"><label>Description</label><textarea name="description" rows="3" placeholder="Optional notes"><?php echo htmlspecialchars($_POST['description']??''); ?></textarea></div>
                </div>
                <div class="form-actions"><a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a><button type="submit" class="btn btn-primary">Save Product</button></div>
            </form>
        </div>

        <!-- ═══════ EDIT ═══════ -->
        <?php elseif ($view==='edit' && $editProduct): ?>
        <div class="form-card">
            <h2>Edit Product #<?php echo (int)$editProduct['id']; ?></h2>
            <form method="POST" action="dashboard.php?view=edit&id=<?php echo (int)$editProduct['id']; ?>">
                <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int)$editProduct['id']; ?>">
                <div class="form-grid">
                    <div class="form-group"><label>Product Name <span class="req">*</span></label><input type="text" name="name" required value="<?php echo htmlspecialchars($editProduct['name']); ?>"></div>
                    <div class="form-group"><label>Product-ID <span class="req">*</span></label><input type="text" name="sku" required value="<?php echo htmlspecialchars($editProduct['sku']); ?>"></div>
                    <div class="form-group"><label>Category</label><input type="text" name="category" value="<?php echo htmlspecialchars($editProduct['category']); ?>"></div>
                    <div class="form-group"><label>Price (Rs.) <span class="req">*</span></label><input type="number" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars((string)$editProduct['price']); ?>"></div>
                    <div class="form-group"><label>Quantity <span class="req">*</span></label><input type="number" name="quantity" min="0" step="1" required value="<?php echo htmlspecialchars((string)$editProduct['quantity']); ?>"></div>
                    <div class="form-group full"><label>Description</label><textarea name="description" rows="3"><?php echo htmlspecialchars($editProduct['description']??''); ?></textarea></div>
                </div>
                <div class="form-actions"><a href="dashboard.php?view=list" class="btn btn-ghost">Cancel</a><button type="submit" class="btn btn-primary">Update</button></div>
            </form>
        </div>

        <!-- ═══════ RECORD SALE ═══════ -->
        <?php elseif ($view==='sale_add'): ?>
        <div class="form-card">
            <h2>Record Sale</h2><p class="form-hint">Selling reduces stock and generates a bill. 13% VAT will be added.</p>
            <?php if (empty($products)): ?>
            <div class="empty-state"><p>Add products first.</p><a href="dashboard.php?view=add" class="btn btn-primary">Add Product</a></div>
            <?php else: ?>
            <form method="POST" action="dashboard.php?view=sale_add">
                <input type="hidden" name="action" value="sale">
                <div class="form-grid">
                    <div class="form-group"><label>Customer Name</label><input type="text" name="customer_name" value="<?php echo htmlspecialchars($_POST['customer_name']??''); ?>" placeholder="Walk-in Customer"></div>
                    <div class="form-group"><label>Customer Phone</label><input type="text" name="customer_phone" value="<?php echo htmlspecialchars($_POST['customer_phone']??''); ?>" placeholder="Optional"></div>
                    <div class="form-group full"><label>Product <span class="req">*</span></label>
                        <select id="product_id" name="product_id" required>
                            <option value="">— Select product —</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" data-price="<?php echo (float)$p['price']; ?>" data-stock="<?php echo (int)$p['quantity']; ?>"><?php echo htmlspecialchars($p['name'].' (Product-ID: '.$p['sku'].') — Stock: '.$p['quantity']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Quantity <span class="req">*</span></label><input type="number" id="quantity" name="quantity" min="1" step="1" required value="<?php echo htmlspecialchars($_POST['quantity']??'1'); ?>"></div>
                    <div class="form-group"><label>Unit Price (Rs.) <span class="req">*</span></label><input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['unit_price']??''); ?>" placeholder="Auto-filled"></div>
                    <div class="form-group"><label>Sale Date</label><input type="date" name="sale_date" value="<?php echo htmlspecialchars($_POST['sale_date']??date('Y-m-d')); ?>"></div>
                    <div class="form-group"><label>Note</label><input type="text" name="note" value="<?php echo htmlspecialchars($_POST['note']??''); ?>" placeholder="Optional"></div>
                </div>
                <div id="tax-preview" style="background:rgba(30,41,59,.6);border:1px solid #334155;border-radius:8px;padding:.8rem 1rem;margin:.5rem 0 1rem;font-size:.88rem;display:none;">
                    <span>Subtotal: <strong id="prev-sub">—</strong></span> &nbsp;|&nbsp;
                    <span>VAT 13%: <strong id="prev-tax">—</strong></span> &nbsp;|&nbsp;
                    <span>Total: <strong id="prev-total">—</strong></span>
                </div>
                <div class="form-actions"><a href="dashboard.php?view=sales" class="btn btn-ghost">Cancel</a><button type="submit" class="btn btn-primary">Record Sale</button></div>
            </form>
            <script>
            (function(){
                var sel=document.getElementById('product_id');
                var upEl=document.getElementById('unit_price');
                var qEl=document.getElementById('quantity');
                var prev=document.getElementById('tax-preview');
                function fmt(n){return'Rs.'+parseFloat(n).toFixed(2);}
                function updatePreview(){
                    var p=parseFloat(upEl.value)||0;var q=parseInt(qEl.value)||0;
                    if(p>0&&q>0){var sub=p*q;var tax=sub*0.13;var tot=sub+tax;
                        document.getElementById('prev-sub').textContent=fmt(sub);
                        document.getElementById('prev-tax').textContent=fmt(tax);
                        document.getElementById('prev-total').textContent=fmt(tot);
                        prev.style.display='block';
                    }else{prev.style.display='none';}
                }
                sel.addEventListener('change',function(){var o=this.options[this.selectedIndex];if(o.value){upEl.value=o.dataset.price||'';qEl.max=o.dataset.stock||'';}updatePreview();});
                upEl.addEventListener('input',updatePreview);
                qEl.addEventListener('input',updatePreview);
            })();
            </script>
            <?php endif; ?>
        </div>

        <!-- ═══════ SALES REPORT ═══════ -->
        <?php elseif ($view==='sales'): ?>
        <div class="toolbar">
            <form class="search-form" method="GET" action="dashboard.php">
                <input type="hidden" name="view" value="sales">
                <input type="date" name="from" value="<?php echo htmlspecialchars($reportFrom); ?>">
                <input type="date" name="to" value="<?php echo htmlspecialchars($reportTo); ?>">
                <select name="product_id"><option value="">All products</option><?php foreach ($products as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php echo $reportProductId==(int)$p['id']?'selected':''; ?>><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select>
                <select name="category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $reportCategory===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if ($reportFrom!==''||$reportTo!==''||$reportProductId>0||$reportCategory!==''): ?><a href="dashboard.php?view=sales" class="btn btn-ghost">Clear</a><?php endif; ?>
            </form>
        </div>
        <div class="table-wrap">
            <?php if (empty($filteredSales)): ?>
            <div class="empty-state"><p>No sales found.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Bill #</th><th>Product</th><th>Product-ID</th><th>Qty</th><th>Unit Price</th><th>Total (incl. VAT)</th><th>Customer</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($filteredSales as $s): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($s['bill_no']); ?></code></td>
                    <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['sku']); ?></td>
                    <td><?php echo (int)$s['quantity']; ?></td>
                    <td>Rs.<?php echo number_format((float)$s['unit_price'],2); ?></td>
                    <td>Rs.<?php echo number_format((float)$s['total'],2); ?></td>
                    <td><?php echo htmlspecialchars($s['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['sale_date']); ?></td>
                    <td class="row-actions">
                        <a href="dashboard.php?view=bill&id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-info">Bill</a>
                        <a href="dashboard.php?download=pdf&sale_id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-secondary">PDF</a>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this sale?');"><input type="hidden" name="action" value="delete_sale"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">Delete</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ═══════ SALES SUMMARY ═══════ -->
        <?php elseif ($view==='report'): ?>
        <div class="toolbar">
            <form class="search-form" method="GET" action="dashboard.php">
                <input type="hidden" name="view" value="report">
                <input type="date" name="from" value="<?php echo htmlspecialchars($reportFrom); ?>">
                <input type="date" name="to" value="<?php echo htmlspecialchars($reportTo); ?>">
                <select name="product_id"><option value="">All products</option><?php foreach ($products as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php echo $reportProductId==(int)$p['id']?'selected':''; ?>><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select>
                <select name="category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $reportCategory===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>
        </div>
        <div class="stats">
            <div class="stat-card"><div class="stat-label">Units Sold</div><div class="stat-value"><?php echo $salesUnits; ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Revenue (incl. VAT)</div><div class="stat-value">Rs.<?php echo number_format($salesTotal,2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Transactions</div><div class="stat-value"><?php echo count($filteredSales); ?></div></div>
        </div>
        <?php if (!empty($salesByProduct)): ?>
        <div class="table-wrap">
            <div class="section-head">Sales by Product</div>
            <table class="data-table"><thead><tr><th>Product</th><th>Product-ID</th><th>Units</th><th>Revenue</th></tr></thead>
            <tbody><?php foreach ($salesByProduct as $sp): ?><tr><td><?php echo htmlspecialchars($sp['name']); ?></td><td><?php echo htmlspecialchars($sp['sku']); ?></td><td><?php echo $sp['qty']; ?></td><td>Rs.<?php echo number_format($sp['total'],2); ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>
        <?php if (!empty($salesByDay)): ?>
        <div class="table-wrap">
            <div class="section-head">Sales by Day</div>
            <table class="data-table"><thead><tr><th>Date</th><th>Units</th><th>Revenue</th></tr></thead>
            <tbody><?php foreach ($salesByDay as $day => $sd): ?><tr><td><?php echo htmlspecialchars($day); ?></td><td><?php echo $sd['qty']; ?></td><td>Rs.<?php echo number_format($sd['total'],2); ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>

        <!-- ═══════ INVENTORY DETAIL ═══════ -->
        <?php elseif ($view==='inventory' && $detailProduct): ?>
        <div class="stats">
            <div class="stat-card"><div class="stat-label"><?php echo htmlspecialchars($detailProduct['name']); ?></div><div class="stat-value">Product-ID: <?php echo htmlspecialchars($detailProduct['sku']); ?></div></div>
            <div class="stat-card"><div class="stat-label">Current Stock</div><div class="stat-value <?php echo (int)$detailProduct['quantity']===0?'stock-zero':((int)$detailProduct['quantity']<=LOW_STOCK_THRESHOLD?'stock-low':''); ?>"><?php echo (int)$detailProduct['quantity']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Price</div><div class="stat-value">Rs.<?php echo number_format((float)$detailProduct['price'],2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Category</div><div class="stat-value"><?php echo htmlspecialchars($detailProduct['category']); ?></div></div>
        </div>
        <div class="toolbar">
            <form method="POST" action="dashboard.php?view=inventory&id=<?php echo (int)$detailProduct['id']; ?>" class="inline-form">
                <input type="hidden" name="action" value="stock_in"><input type="hidden" name="id" value="<?php echo (int)$detailProduct['id']; ?>"><input type="hidden" name="return_view" value="inventory">
                <input type="number" name="amount" min="1" value="1" class="qty-input" required><button type="submit" class="btn btn-in">Stock In</button>
            </form>
            <form method="POST" action="dashboard.php?view=inventory&id=<?php echo (int)$detailProduct['id']; ?>" class="inline-form">
                <input type="hidden" name="action" value="stock_out"><input type="hidden" name="id" value="<?php echo (int)$detailProduct['id']; ?>"><input type="hidden" name="return_view" value="inventory">
                <input type="number" name="amount" min="1" value="1" class="qty-input" required><button type="submit" class="btn btn-out">Stock Out</button>
            </form>
            <a href="dashboard.php?view=edit&id=<?php echo (int)$detailProduct['id']; ?>" class="btn btn-secondary">Edit Product</a>
        </div>
        <?php if (!empty($detailProduct['description'])): ?><div class="form-card"><p><?php echo htmlspecialchars($detailProduct['description']); ?></p></div><?php endif; ?>
        <?php if (!empty($partMovements)): ?>
        <div class="table-wrap"><div class="section-head">Stock Movements</div>
            <table class="data-table"><thead><tr><th>Type</th><th>Amount</th><th>Balance</th><th>Note</th><th>Date</th></tr></thead>
            <tbody><?php foreach ($partMovements as $m): ?><tr><td><span class="badge type-<?php echo htmlspecialchars($m['type']); ?>"><?php echo htmlspecialchars($m['type']); ?></span></td><td><?php echo (int)$m['amount']; ?></td><td><?php echo (int)$m['balance_after']; ?></td><td><?php echo htmlspecialchars($m['note']??''); ?></td><td><?php echo htmlspecialchars($m['created_at']); ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>
        <?php if (!empty($partSales)): ?>
        <div class="table-wrap"><div class="section-head">Sales History</div>
            <table class="data-table"><thead><tr><th>Bill #</th><th>Qty</th><th>Total (incl. VAT)</th><th>Customer</th><th>Date</th></tr></thead>
            <tbody><?php foreach ($partSales as $ps): ?><tr><td><code><?php echo htmlspecialchars($ps['bill_no']); ?></code></td><td><?php echo (int)$ps['quantity']; ?></td><td>Rs.<?php echo number_format((float)$ps['total'],2); ?></td><td><?php echo htmlspecialchars($ps['customer_name']); ?></td><td><?php echo htmlspecialchars($ps['sale_date']); ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>

        <!-- ═══════ BILL ═══════ -->
<?php elseif ($view==='bill' && $billSale):
    if(isset($billSale['_subtotal'])){
        $bSub=(float)$billSale['_subtotal'];
        $bTax=(float)$billSale['_tax'];
        $bTotal=(float)$billSale['_total'];
    } else {
        $bTotal=(float)$billSale['total'];
        $bSub=round($bTotal/1.13,2);
        $bTax=round($bTotal-$bSub,2);
    }
?>
<div class="bill-print">
    <h1>Nirman</h1>
    <div class="bill-title">Bill</div>
    <div class="bill-company-info">
        Phone: +977 9705217752 &nbsp;|&nbsp; Email: sales@nirmanirm.com
    </div>
    <hr class="bill-divider">
    <div style="display:flex;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <div style="font-weight:700;font-size:1.1rem;"><?php echo htmlspecialchars($billSale['customer_name']); ?></div>
            <?php if(!empty($billSale['customer_phone'])): ?>
            <div style="color:#64748b;"><?php echo htmlspecialchars($billSale['customer_phone']); ?></div>
            <?php endif; ?>
        </div>
        <div style="text-align:right;font-size:.9rem;">
            <div><strong>Bill No:</strong> <?php echo htmlspecialchars($billSale['bill_no']); ?></div>
            <div><strong>Date:</strong> <?php echo htmlspecialchars($billSale['sale_date']); ?></div>
        </div>
    </div>
    <table>
        <thead><tr><th>DESCRIPTION</th><th style="text-align:center;">QTY</th><th style="text-align:right;">RATE</th><th style="text-align:right;">AMOUNT</th></tr></thead>
        <tbody>
        <tr>
            <td><?php echo htmlspecialchars($billSale['product_name']); ?></td>
            <td style="text-align:center;"><?php echo (int)$billSale['quantity']; ?></td>
            <td style="text-align:right;">Rs.<?php echo number_format((float)$billSale['unit_price'],2); ?></td>
            <td style="text-align:right;">Rs.<?php echo number_format($bSub,2); ?></td>
        </tr>
        </tbody>
    </table>
    <div class="bill-totals">
        <table>
            <tr><td>SUBTOTAL</td><td>Rs.<?php echo number_format($bSub,2); ?></td></tr>
            <tr><td>TAX RATE (13%)</td><td></td></tr>
            <tr><td>SALES TAX</td><td>Rs.<?php echo number_format($bTax,2); ?></td></tr>
            <tr class="grand-total"><td>TOTAL</td><td>Rs.<?php echo number_format($bTotal,2); ?></td></tr>
        </table>
    </div>
    <hr class="bill-divider">
    <div class="bill-footer">THANK YOU FOR YOUR BUSINESS!</div>
    <div class="form-actions no-print" style="margin-top:1rem">
        <a href="dashboard.php?download=pdf&sale_id=<?php echo (int)$billSale['id']; ?>" class="btn btn-primary">Download PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
        <a href="dashboard.php?view=sales" class="btn btn-ghost">View All Sales</a>
        <a href="dashboard.php?view=list" class="btn btn-ghost">Back to Products</a>
    </div>
</div>

        <!-- ═══════ MANAGE STAFF ═══════ -->
        <!-- ===== CHANGE #6: entire staff management view block ===== -->
        <?php elseif ($view==='staff'): ?>
        <div class="table-wrap">
            <?php if (empty($staffUsers)): ?>
            <div class="empty-state"><p>No staff accounts yet. Staff can register at the login page.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>ID</th><th>Username</th><th>Created</th><th>Change Credentials</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($staffUsers as $st): ?>
                <tr>
                    <td><?php echo (int)$st['id']; ?></td>
                    <td><?php echo htmlspecialchars($st['username']); ?></td>
                    <td><?php echo htmlspecialchars($st['created_at']); ?></td>
                    <td>
                        <form method="POST" action="dashboard.php?view=staff" class="inline-form">
                            <input type="hidden" name="action" value="staff_update">
                            <input type="hidden" name="id" value="<?php echo (int)$st['id']; ?>">
                            <input type="text" name="username" value="<?php echo htmlspecialchars($st['username']); ?>" class="qty-input" style="width:110px" required>
                            <input type="password" name="password" placeholder="New password (optional)" style="width:170px">
                            <button type="submit" class="btn btn-sm btn-secondary">Save</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this staff account?');">
                            <input type="hidden" name="action" value="staff_delete">
                            <input type="hidden" name="id" value="<?php echo (int)$st['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:.75rem;font-size:.8rem;color:#64748b">Leave the password field blank to keep the current password. New passwords need 8+ chars with uppercase, lowercase, a number, and a special character.</p>
            <?php endif; ?>
        </div>
        <!-- ===== END CHANGE #6 ===== -->

        <!-- ═══════ NOTIFICATIONS ═══════ -->
        <?php elseif ($view==='notifications'): ?>
        <div class="toolbar">
            <form method="POST" action="dashboard.php?view=notifications"><button type="submit" name="action" value="mark_all_read" class="btn btn-secondary">Mark All Read</button></form>
            <form method="POST" action="dashboard.php?view=notifications" onsubmit="return confirm('Clear all?');"><button type="submit" name="action" value="clear_notifications" class="btn btn-danger">Clear All</button></form>
        </div>
        <div class="table-wrap">
            <?php if (empty($sortedNotifications)): ?>
            <div class="empty-state"><p>No notifications.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Type</th><th>Title</th><th>Message</th><th>Product</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($sortedNotifications as $n): ?>
                <tr style="<?php echo (int)$n['is_read']===0?'font-weight:600;':''; ?>">
                    <td><span class="badge type-<?php echo htmlspecialchars($n['type']??'info'); ?>"><?php echo htmlspecialchars($n['type']??'info'); ?></span></td>
                    <td><?php echo htmlspecialchars($n['title']??''); ?></td>
                    <td><?php echo htmlspecialchars($n['message']??''); ?></td>
                    <td><?php echo !empty($n['product_id'])?'<a href="dashboard.php?view=inventory&id='.(int)$n['product_id'].'">View</a>':'—'; ?></td>
                    <td><?php echo (int)$n['is_read']===0?'<span style="color:#f59e0b">Unread</span>':'Read'; ?></td>
                    <td><?php echo htmlspecialchars($n['created_at']??''); ?></td>
                    <td class="row-actions">
                        <?php if((int)$n['is_read']===0): ?><form method="POST" class="inline-form"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>"><button type="submit" class="btn btn-sm btn-secondary">Read</button></form><?php endif; ?>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete?');"><input type="hidden" name="action" value="delete_notification"><input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">Delete</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>