<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') { header('Location: login.php'); exit; }

require_once __DIR__ . '/pdf_invoice.php';

 $dbHost='localhost'; $dbPort=3306; $dbUser='root'; $dbPass=''; $dbName='ims';
try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("USE `$dbName`");
} catch(PDOException $e) { die('DB error: '.$e->getMessage()); }

define('LOW_STOCK_THRESHOLD',10);
define('PRODUCT_COUNT_ALERT',10);
define('TAX_RATE',0.13); // 13% VAT

function shortText(string $t,int $m=48):string{return strlen($t)<=$m?$t:substr($t,0,$m-1).'…';}
function logMovement(PDO $pdo,int $pid,string $type,int $amt,int $bal,string $n=''):void{$pdo->prepare('INSERT INTO movements(product_id,type,amount,balance_after,note)VALUES(?,?,?,?,?)')->execute([$pid,$type,$amt,$bal,$n]);}
function addNotification(PDO $pdo,string $type,string $title,string $msg,?int $pid=null):void{$pdo->prepare('INSERT INTO notifications(type,title,message,product_id,is_read)VALUES(?,?,?,?,0)')->execute([$type,$title,$msg,$pid]);}
function checkStock(PDO $pdo,array $p,int $old,int $new):void{
    $n=$p['name']??'Product';$s=$p['sku']??'';$id=(int)($p['id']??0);
    if($new===0&&$old>0){addNotification($pdo,'out_of_stock','Out of stock','"'.$n.'" (ID:'.$s.') out of stock.',$id);return;}
    if($new>0&&$new<=LOW_STOCK_THRESHOLD&&$old>LOW_STOCK_THRESHOLD){addNotification($pdo,'low_stock','Low stock','"'.$n.'" (ID:'.$s.') only '.$new.' left.',$id);}
}

 $err='';$suc='';
 $products=$pdo->query('SELECT*FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
 $sales=$pdo->query('SELECT*FROM sales ORDER BY sale_date DESC,created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
 $movements=$pdo->query('SELECT*FROM movements ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
 $notifications=$pdo->query('SELECT*FROM notifications ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

 $detailProduct=null;
 $allowedViews=['list','sale_add','sales','report','inventory','bill'];
 $view=$_GET['view']??'list';
if(!in_array($view,$allowedViews,true))$view='list';

if(isset($_GET['download'])&&$_GET['download']=='pdf'&&isset($_GET['sale_id'])){
    $st=$pdo->prepare('SELECT*FROM sales WHERE id=?');$st->execute([(int)$_GET['sale_id']]);
    $dl=$st->fetch(PDO::FETCH_ASSOC);if(!$dl){http_response_code(404);echo'Not found';exit;}
    downloadInvoicePdf($dl);
}

 $billSale=null;
if($view==='bill'&&isset($_GET['id'])){$st=$pdo->prepare('SELECT*FROM sales WHERE id=?');$st->execute([(int)$_GET['id']]);$billSale=$st->fetch(PDO::FETCH_ASSOC);if(!$billSale){$err='Bill not found.';$view='sales';}}
if($view==='inventory'&&isset($_GET['id'])){$st=$pdo->prepare('SELECT*FROM products WHERE id=?');$st->execute([(int)$_GET['id']]);$detailProduct=$st->fetch(PDO::FETCH_ASSOC);if(!$detailProduct){$err='Not found.';$view='list';}}

// ── POST: sale only (NO stock_in, stock_out, add/edit/delete) ──
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';

    if($action==='sale'){
        $pid=(int)($_POST['product_id']??0);$qty=trim($_POST['quantity']??'');
        $up=trim($_POST['unit_price']??'');$note=trim($_POST['note']??'');
        $sd=trim($_POST['sale_date']??date('Y-m-d'));
        $cn=trim($_POST['customer_name']??'');$cp=trim($_POST['customer_phone']??'');
        $st=$pdo->prepare('SELECT*FROM products WHERE id=?');$st->execute([$pid]);$p=$st->fetch(PDO::FETCH_ASSOC);
        if(!$p){$err='Select valid product.';$view='sale_add';}
        elseif(!preg_match('/^\d+$/',$qty)||(int)$qty<1){$err='Qty must be ≥1.';$view='sale_add';}
        elseif((int)$qty>(int)$p['quantity']){$err='Not enough stock.';$view='sale_add';}
        elseif($up===''||!is_numeric($up)||(float)$up<0){$err='Enter valid price.';$view='sale_add';}
        elseif(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$sd)){$err='Enter valid date.';$view='sale_add';}
        else{
            $qi=(int)$qty;$pf=round((float)$up,2);
            $subtotal=round($pf*$qi,2);
            $taxAmt=round($subtotal*TAX_RATE,2);
            $total=round($subtotal+$taxAmt,2);
            $oldQ=(int)$p['quantity'];$newQ=$oldQ-$qi;$custN=$cn!==''?$cn:'Walk-in Customer';
            $staffId=(int)($_SESSION['user_id']??0);
            $staffName=$_SESSION['username']??'Unknown';
            $status=$pdo->query("SHOW TABLE STATUS LIKE 'sales'")->fetch(PDO::FETCH_ASSOC);
            $nextId=(int)$status['Auto_increment'];$billNo=makeBillNo($nextId);
            $pdo->prepare('UPDATE products SET quantity=quantity-? WHERE id=?')->execute([$qi,$pid]);
            $pdo->prepare('INSERT INTO sales(bill_no,product_id,product_name,sku,category,quantity,unit_price,total,customer_name,customer_phone,note,sale_date,staff_id,staff_name)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$billNo,$pid,$p['name'],$p['sku'],$p['category']??'General',$qi,$pf,$total,$custN,$cp,$note,$sd,$staffId,$staffName]);
            $saleId=(int)$pdo->lastInsertId();
            if($saleId!==$nextId){$rb=makeBillNo($saleId);$pdo->prepare('UPDATE sales SET bill_no=? WHERE id=?')->execute([$rb,$saleId]);$billNo=$rb;}
            logMovement($pdo,$pid,'sale',$qi,$newQ,'Sale '.$billNo.($note!==''?': '.$note:''));
            $st=$pdo->prepare('SELECT*FROM products WHERE id=?');$st->execute([$pid]);
            checkStock($pdo,$st->fetch(PDO::FETCH_ASSOC),$oldQ,$newQ);
            $st=$pdo->prepare('SELECT*FROM sales WHERE id=?');$st->execute([$saleId]);
            $billSale=$st->fetch(PDO::FETCH_ASSOC);
            // Store tax info for bill display
            $billSale['_subtotal']=$subtotal;
            $billSale['_tax']=$taxAmt;
            $billSale['_total']=$total;
            $suc='Sale recorded. Bill '.$billNo.' generated.';$view='bill';
        }
    }

    // Reload
    $products=$pdo->query('SELECT*FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $sales=$pdo->query('SELECT*FROM sales ORDER BY sale_date DESC,created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $movements=$pdo->query('SELECT*FROM movements ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $notifications=$pdo->query('SELECT*FROM notifications ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    if($view==='bill'&&$billSale){$st=$pdo->prepare('SELECT*FROM sales WHERE id=?');$st->execute([(int)$billSale['id']]);$fresh=$st->fetch(PDO::FETCH_ASSOC);if($fresh){$fresh['_subtotal']=$billSale['_subtotal']??0;$fresh['_tax']=$billSale['_tax']??0;$fresh['_total']=$billSale['_total']??0;$billSale=$fresh;}}
}

// Filters
 $search=trim($_GET['q']??'');
if($search!==''&&$view==='list'){$st=$pdo->prepare("SELECT*FROM products WHERE CONCAT(name,' ',sku,' ',category,' ',COALESCE(description,''))LIKE? ORDER BY id");$st->execute(['%'.$search.'%']);$filtered=$st->fetchAll(PDO::FETCH_ASSOC);}else$filtered=$products;

 $rFrom=trim($_GET['from']??'');$rTo=trim($_GET['to']??'');$rPid=isset($_GET['product_id'])?(int)$_GET['product_id']:0;$rCat=trim($_GET['category']??'');
if($view==='sales'||$view==='report'){
    $sql='SELECT*FROM sales WHERE 1=1';$params=[];
    if($rFrom!==''){$sql.=' AND sale_date>=?';$params[]=$rFrom;}
    if($rTo!==''){$sql.=' AND sale_date<=?';$params[]=$rTo;}
    if($rPid>0){$sql.=' AND product_id=?';$params[]=$rPid;}
    if($rCat!==''){$sql.=' AND category=?';$params[]=$rCat;}
    $sql.=' ORDER BY sale_date DESC,created_at DESC';
    $st=$pdo->prepare($sql);$st->execute($params);$fSales=$st->fetchAll(PDO::FETCH_ASSOC);
}else$fSales=$sales;

 $sU=0;$sT=0.0;$sBP=[];$sBD=[];
foreach($fSales as $s){
    $sU+=(int)$s['quantity'];$sT+=(float)$s['total'];
    $pid=(int)$s['product_id'];
    if(!isset($sBP[$pid]))$sBP[$pid]=['name'=>$s['product_name'],'sku'=>$s['sku'],'qty'=>0,'total'=>0.0];
    $sBP[$pid]['qty']+=(int)$s['quantity'];$sBP[$pid]['total']+=(float)$s['total'];
    $day=$s['sale_date'];if(!isset($sBD[$day]))$sBD[$day]=['qty'=>0,'total'=>0.0];
    $sBD[$day]['qty']+=(int)$s['quantity'];$sBD[$day]['total']+=(float)$s['total'];
}
ksort($sBD);

 $cats=$pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category!='' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

 $pMov=[];$pSales=[];
if($view==='inventory'&&$detailProduct){
    $pid=(int)$detailProduct['id'];
    $st=$pdo->prepare('SELECT*FROM movements WHERE product_id=? ORDER BY created_at DESC');$st->execute([$pid]);$pMov=$st->fetchAll(PDO::FETCH_ASSOC);
    $st=$pdo->prepare('SELECT*FROM sales WHERE product_id=? ORDER BY sale_date DESC,created_at DESC');$st->execute([$pid]);$pSales=$st->fetchAll(PDO::FETCH_ASSOC);
}

 $sr=$pdo->query('SELECT COUNT(*)AS cnt,COALESCE(SUM(quantity),0)AS ts,COALESCE(SUM(price*quantity),0)AS tv,SUM(CASE WHEN quantity<='.LOW_STOCK_THRESHOLD.' THEN 1 ELSE 0 END)AS lc FROM products')->fetch(PDO::FETCH_ASSOC);
 $tP=(int)$sr['cnt'];$tS=(int)$sr['ts'];$tV=(float)$sr['tv'];$lC=(int)$sr['lc'];

 $unread=(int)$pdo->query('SELECT COUNT(*)FROM notifications WHERE is_read=0')->fetchColumn();
 $banner=$pdo->query('SELECT*FROM notifications WHERE is_read=0 ORDER BY created_at DESC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);

 $pt=['list'=>'Products','sale_add'=>'Record Sale','sales'=>'Sales Report','report'=>'Sales Summary','inventory'=>'Inventory','bill'=>'Bill'];
 $pT=$pt[$view]??'Dashboard';
 $ps=['list'=>'View products and stock','sale_add'=>'Record a sale','sales'=>'View all sales','report'=>'Sales totals','inventory'=>'Stock history','bill'=>'View bill'];

$preselectPid=(int)($_GET['sell_pid']??0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($pT); ?> | Staff — Nirman</title>
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
        <div class="brand"><span class="brand-icon">📦</span><span>Nirman — Staff</span></div>
        <nav class="nav">
            <a href="staff_dashboard.php?view=list" class="nav-link <?php echo $view==='list'?'active':''; ?>">📋 Products</a>
            <a href="staff_dashboard.php?view=sale_add" class="nav-link <?php echo $view==='sale_add'?'active':''; ?>">🛒 Record Sale</a>
            <a href="staff_dashboard.php?view=sales" class="nav-link <?php echo $view==='sales'?'active':''; ?>">📊 Sales Report</a>
            <a href="staff_dashboard.php?view=report" class="nav-link <?php echo $view==='report'?'active':''; ?>">📈 Sales Summary</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-link logout">← Logout</a>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div><h1><?php echo htmlspecialchars($pT); ?></h1><p class="topbar-sub"><?php echo htmlspecialchars($ps[$view]??''); ?></p></div>
            <div style="font-size:.8rem;color:#fbbf24;background:rgba(251,191,36,.15);padding:.4rem .7rem;border-radius:6px;border:1px solid rgba(251,191,36,.35)">👷 Staff: <?php echo htmlspecialchars($_SESSION['username']); ?></div>
        </header>

        <?php if($err!==''): ?><div class="msg msg-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
        <?php if($suc!==''): ?><div class="msg msg-success"><?php echo htmlspecialchars($suc); ?></div><?php endif; ?>

        <?php if(!empty($banner)&&$view!=='notifications'): ?>
        <div class="notif-banner">
            <div class="notif-banner-title">Alerts (<?php echo $unread; ?> unread)</div>
            <ul class="notif-banner-list">
                <?php foreach($banner as $bn): ?>
                <li class="type-<?php echo htmlspecialchars($bn['type']??'info'); ?>"><strong><?php echo htmlspecialchars($bn['title']??'Alert'); ?>:</strong> <?php echo htmlspecialchars($bn['message']??''); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- ═══ LIST ═══ -->
        <?php if($view==='list'): ?>
        <div class="stats">
            <div class="stat-card"><div class="stat-label">Total Products</div><div class="stat-value"><?php echo $tP; ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Stock</div><div class="stat-value"><?php echo $tS; ?></div></div>
            <div class="stat-card"><div class="stat-label">Inventory Value</div><div class="stat-value">Rs.<?php echo number_format($tV,2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Low Stock (≤<?php echo LOW_STOCK_THRESHOLD; ?>)</div><div class="stat-value"><?php echo $lC; ?></div></div>
        </div>
        <div class="toolbar">
            <form class="search-form" method="GET" action="staff_dashboard.php">
                <input type="hidden" name="view" value="list">
                <input type="search" name="q" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if($search!==''): ?><a href="staff_dashboard.php?view=list" class="btn btn-ghost">Clear</a><?php endif; ?>
            </form>
            <div class="toolbar-right">
                <a href="staff_dashboard.php?view=sale_add" class="btn btn-primary">🛒 Record Sale</a>
            </div>
        </div>
        <div class="table-wrap">
            <?php if(empty($filtered)): ?>
            <div class="empty-state"><p>No products found.</p><?php if($search===''): ?><p style="margin-top:.5rem;font-size:.85rem;color:#64748b">No products yet. Ask admin to add products.</p><?php endif; ?></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>ID</th><th>Name</th><th>Product-ID</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach($filtered as $p): ?>
                <tr>
                    <td><?php echo (int)$p['id']; ?></td>
                    <td><a class="link-name" href="staff_dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                        <?php if(!empty($p['description'])): ?><div class="cell-desc"><?php echo htmlspecialchars(shortText($p['description'])); ?></div><?php endif; ?></td>
                    <td><code><?php echo htmlspecialchars($p['sku']); ?></code></td>
                    <td><span class="badge"><?php echo htmlspecialchars($p['category']); ?></span></td>
                    <td>Rs.<?php echo number_format((float)$p['price'],2); ?></td>
                    <td><span class="stock <?php echo (int)$p['quantity']===0?'stock-zero':((int)$p['quantity']<=LOW_STOCK_THRESHOLD?'stock-low':''); ?>"><?php echo (int)$p['quantity']; ?></span></td>
                    <td style="white-space:nowrap">
                        <a href="staff_dashboard.php?view=inventory&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-info">Details</a>
                        <?php if((int)$p['quantity']>0): ?>
                        <a href="staff_dashboard.php?view=sale_add&sell_pid=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary">🛒 Sell</a>
                        <?php else: ?>
                        <span class="btn btn-sm btn-ghost" style="opacity:.4;cursor:not-allowed">Out of Stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ═══ RECORD SALE ═══ -->
        <?php elseif($view==='sale_add'): ?>
        <div class="form-card">
            <h2>Record Sale</h2><p class="form-hint">Selling reduces stock and generates a bill. 13% VAT will be added.</p>
            <?php if(empty($products)): ?>
            <div class="empty-state"><p>No products available. Ask admin to add products first.</p></div>
            <?php else: ?>
            <form method="POST" action="staff_dashboard.php?view=sale_add">
                <input type="hidden" name="action" value="sale">
                <div class="form-grid">
                    <div class="form-group"><label>Customer Name</label><input type="text" name="customer_name" value="<?php echo htmlspecialchars($_POST['customer_name']??''); ?>" placeholder="Walk-in Customer"></div>
                    <div class="form-group"><label>Customer Phone</label><input type="text" name="customer_phone" value="<?php echo htmlspecialchars($_POST['customer_phone']??''); ?>" placeholder="Optional"></div>
                    <div class="form-group full"><label>Product <span class="req">*</span></label>
                        <select id="product_id" name="product_id" required>
                            <option value="">— Select product —</option>
                            <?php foreach($products as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"
                                data-price="<?php echo (float)$p['price']; ?>"
                                data-stock="<?php echo (int)$p['quantity']; ?>"
                                <?php $selPid=(int)($_POST['product_id']??$preselectPid);echo $selPid===(int)$p['id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($p['name'].' (Product-ID:'.$p['sku'].') Stock:'.$p['quantity']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Quantity <span class="req">*</span></label><input type="number" id="qty" name="quantity" min="1" required value="<?php echo htmlspecialchars($_POST['quantity']??'1'); ?>"></div>
                    <div class="form-group"><label>Unit Price (Rs.) <span class="req">*</span></label><input type="number" id="up" name="unit_price" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['unit_price']??''); ?>" placeholder="Auto-filled"></div>
                    <div class="form-group"><label>Sale Date</label><input type="date" name="sale_date" value="<?php echo htmlspecialchars($_POST['sale_date']??date('Y-m-d')); ?>"></div>
                    <div class="form-group"><label>Note</label><input type="text" name="note" value="<?php echo htmlspecialchars($_POST['note']??''); ?>" placeholder="Optional"></div>
                </div>
                <!-- Live tax preview -->
                <div id="tax-preview" style="background:rgba(30,41,59,.6);border:1px solid #334155;border-radius:8px;padding:.8rem 1rem;margin:.5rem 0 1rem;font-size:.88rem;display:none;">
                    <span>Subtotal: <strong id="prev-sub">—</strong></span> &nbsp;|&nbsp;
                    <span>VAT 13%: <strong id="prev-tax">—</strong></span> &nbsp;|&nbsp;
                    <span>Total: <strong id="prev-total">—</strong></span>
                </div>
                <div class="form-actions"><a href="staff_dashboard.php?view=list" class="btn btn-ghost">← Back</a><button type="submit" class="btn btn-primary">Record Sale & Generate Bill</button></div>
            </form>
            <script>
            (function(){
                var sel=document.getElementById('product_id');
                var upEl=document.getElementById('up');
                var qEl=document.getElementById('qty');
                var prev=document.getElementById('tax-preview');
                function fmt(n){return'Rs.'+parseFloat(n).toFixed(2);}
                function updatePreview(){
                    var p=parseFloat(upEl.value)||0;
                    var q=parseInt(qEl.value)||0;
                    if(p>0&&q>0){
                        var sub=p*q;var tax=sub*0.13;var tot=sub+tax;
                        document.getElementById('prev-sub').textContent=fmt(sub);
                        document.getElementById('prev-tax').textContent=fmt(tax);
                        document.getElementById('prev-total').textContent=fmt(tot);
                        prev.style.display='block';
                    }else{prev.style.display='none';}
                }
                function applySelected(){
                    var o=sel.options[sel.selectedIndex];
                    if(o&&o.value){
                        if(!upEl.value||upEl.dataset.auto==='1'){upEl.value=o.dataset.price||'';upEl.dataset.auto='1';}
                        qEl.max=o.dataset.stock||'';
                    }
                    updatePreview();
                }
                sel.addEventListener('change',function(){upEl.dataset.auto='1';applySelected();});
                upEl.addEventListener('input',function(){upEl.dataset.auto='0';updatePreview();});
                qEl.addEventListener('input',updatePreview);
                if(sel.value){applySelected();}
            })();
            </script>
            <?php endif; ?>
        </div>

        <!-- ═══ SALES REPORT ═══ -->
        <?php elseif($view==='sales'): ?>
        <div class="toolbar">
            <form class="search-form" method="GET" action="staff_dashboard.php">
                <input type="hidden" name="view" value="sales">
                <input type="date" name="from" value="<?php echo htmlspecialchars($rFrom); ?>">
                <input type="date" name="to" value="<?php echo htmlspecialchars($rTo); ?>">
                <select name="product_id"><option value="">All</option><?php foreach($products as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php echo $rPid==(int)$p['id']?'selected':''; ?>><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select>
                <select name="category"><option value="">All</option><?php foreach($cats as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $rCat===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if($rFrom!==''||$rTo!==''||$rPid>0||$rCat!==''): ?><a href="staff_dashboard.php?view=sales" class="btn btn-ghost">Clear</a><?php endif; ?>
            </form>
            <div class="toolbar-right"><a href="staff_dashboard.php?view=sale_add" class="btn btn-primary">🛒 Record Sale</a></div>
        </div>
        <div class="table-wrap">
            <?php if(empty($fSales)): ?><div class="empty-state"><p>No sales found.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Bill #</th><th>Product</th><th>Product-ID</th><th>Qty</th><th>Unit Price</th><th>Total (incl. VAT)</th><th>Customer</th><th>Staff</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach($fSales as $s): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($s['bill_no']); ?></code></td>
                    <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['sku']); ?></td>
                    <td><?php echo (int)$s['quantity']; ?></td>
                    <td>Rs.<?php echo number_format((float)$s['unit_price'],2); ?></td>
                    <td>Rs.<?php echo number_format((float)$s['total'],2); ?></td>
                    <td><?php echo htmlspecialchars($s['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['staff_name']??'—'); ?></td>
                    <td><?php echo htmlspecialchars($s['sale_date']); ?></td>
                    <td>
                        <a href="staff_dashboard.php?view=bill&id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-info">Bill</a>
                        <a href="staff_dashboard.php?download=pdf&sale_id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-secondary">PDF</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ═══ SALES SUMMARY ═══ -->
        <?php elseif($view==='report'): ?>
        <div class="toolbar">
            <form class="search-form" method="GET" action="staff_dashboard.php">
                <input type="hidden" name="view" value="report">
                <input type="date" name="from" value="<?php echo htmlspecialchars($rFrom); ?>">
                <input type="date" name="to" value="<?php echo htmlspecialchars($rTo); ?>">
                <select name="product_id"><option value="">All</option><?php foreach($products as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php echo $rPid==(int)$p['id']?'selected':''; ?>><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select>
                <select name="category"><option value="">All</option><?php foreach($cats as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $rCat===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>
        </div>
        <div class="stats">
            <div class="stat-card"><div class="stat-label">Units Sold</div><div class="stat-value"><?php echo $sU; ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Revenue (incl. VAT)</div><div class="stat-value">Rs.<?php echo number_format($sT,2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Transactions</div><div class="stat-value"><?php echo count($fSales); ?></div></div>
        </div>
        <?php if(!empty($sBP)): ?>
        <div class="table-wrap">
            <div class="section-head">Sales by Product</div>
            <table class="data-table"><thead><tr><th>Product</th><th>Product-ID</th><th>Units</th><th>Revenue</th></tr></thead>
            <tbody><?php foreach($sBP as $sp): ?><tr><td><?php echo htmlspecialchars($sp['name']); ?></td><td><?php echo htmlspecialchars($sp['sku']); ?></td><td><?php echo $sp['qty']; ?></td><td>Rs.<?php echo number_format($sp['total'],2); ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>
        <?php if(!empty($sBD)): ?>
        <div class="table-wrap">
            <div class="section-head">Sales by Day</div>
            <table class="data-table"><thead><tr><th>Date</th><th>Units</th><th>Revenue</th></tr></thead>
            <tbody><?php foreach($sBD as $day=>$sd): ?><tr><td><?php echo htmlspecialchars($day); ?></td><td><?php echo $sd['qty']; ?></td><td>Rs.<?php echo number_format($sd['total'],2); ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>

        <!-- ═══ INVENTORY DETAIL ═══ -->
        <?php elseif($view==='inventory'&&$detailProduct): ?>
        <div class="stats">
            <div class="stat-card"><div class="stat-label"><?php echo htmlspecialchars($detailProduct['name']); ?></div><div class="stat-value">Product-ID: <?php echo htmlspecialchars($detailProduct['sku']); ?></div></div>
            <div class="stat-card"><div class="stat-label">Current Stock</div><div class="stat-value <?php echo (int)$detailProduct['quantity']===0?'stock-zero':((int)$detailProduct['quantity']<=LOW_STOCK_THRESHOLD?'stock-low':''); ?>"><?php echo (int)$detailProduct['quantity']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Price</div><div class="stat-value">Rs.<?php echo number_format((float)$detailProduct['price'],2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Category</div><div class="stat-value"><?php echo htmlspecialchars($detailProduct['category']); ?></div></div>
        </div>
        <div class="toolbar">
            <?php if((int)$detailProduct['quantity']>0): ?>
            <a href="staff_dashboard.php?view=sale_add&sell_pid=<?php echo (int)$detailProduct['id']; ?>" class="btn btn-primary">🛒 Sell This Product</a>
            <?php endif; ?>
            <a href="staff_dashboard.php?view=list" class="btn btn-ghost">← Back to Products</a>
        </div>
        <?php if(!empty($detailProduct['description'])): ?><div class="form-card"><p><?php echo htmlspecialchars($detailProduct['description']); ?></p></div><?php endif; ?>
        <?php if(!empty($pMov)): ?>
        <div class="table-wrap"><div class="section-head">Stock Movements</div>
            <table class="data-table"><thead><tr><th>Type</th><th>Amount</th><th>Balance</th><th>Note</th><th>Date</th></tr></thead>
            <tbody><?php foreach($pMov as $m): ?><tr><td><span class="badge type-<?php echo htmlspecialchars($m['type']); ?>"><?php echo htmlspecialchars($m['type']); ?></span></td><td><?php echo (int)$m['amount']; ?></td><td><?php echo (int)$m['balance_after']; ?></td><td><?php echo htmlspecialchars($m['note']??''); ?></td><td><?php echo htmlspecialchars($m['created_at']); ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>
        <?php if(!empty($pSales)): ?>
        <div class="table-wrap"><div class="section-head">Sales History</div>
            <table class="data-table"><thead><tr><th>Bill #</th><th>Qty</th><th>Total (incl. VAT)</th><th>Customer</th><th>Staff</th><th>Date</th></tr></thead>
            <tbody><?php foreach($pSales as $ps): ?><tr><td><code><?php echo htmlspecialchars($ps['bill_no']); ?></code></td><td><?php echo (int)$ps['quantity']; ?></td><td>Rs.<?php echo number_format((float)$ps['total'],2); ?></td><td><?php echo htmlspecialchars($ps['customer_name']); ?></td><td><?php echo htmlspecialchars($ps['staff_name']??'—'); ?></td><td><?php echo htmlspecialchars($ps['sale_date']); ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>

        <!-- ═══ BILL ═══ -->
<?php elseif($view==='bill'&&$billSale):
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
            <div><strong>Staff:</strong> <?php echo htmlspecialchars($billSale['staff_name']??'—'); ?></div>
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
        <a href="staff_dashboard.php?download=pdf&sale_id=<?php echo (int)$billSale['id']; ?>" class="btn btn-primary">Download PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
        <a href="staff_dashboard.php?view=sales" class="btn btn-ghost">View All Sales</a>
        <a href="staff_dashboard.php?view=list" class="btn btn-ghost">Back to Products</a>
    </div>
</div>

        <?php endif; ?>
    </main>
</div>
</body>
</html>