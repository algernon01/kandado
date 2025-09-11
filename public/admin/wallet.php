<?php
// admin/wallet.php
// --------------------------------------------------
// Admin Wallets + Transactions (Modal view + Mobile-first)
// • "View" opens modal with AJAX-loaded detail
// • Ellipsis pagination for Users & Transactions
// • No horizontal scroll on mobile
// • Debit & Refund removed (Top-up + Adjustment only)
// • FIX: Actions column right-aligned
// • FIX: Stat numbers render clean on mobile (no wrap/tabular)
// --------------------------------------------------
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../../config/db.php';

/* ------------ Helpers ------------ */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function peso($n){ return '₱' . number_format((float)$n, 2); }
function nowUtc(){ return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'); }
function genRef($pdo,$prefix='WLT'){
    do{
        $ref = $prefix.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
        $q = $pdo->prepare("SELECT 1 FROM wallet_transactions WHERE reference_no=?");
        $q->execute([$ref]);
        $exists = $q->fetchColumn();
    }while($exists);
    return $ref;
}
function flash($type,$msg){ $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function take_flash(){ $f=$_SESSION['flash']??null; if($f) unset($_SESSION['flash']); return $f; }

/* Ellipsis pager (returns HTML links) */
function pager_links($current,$pages,$qs,$base='wallet.php',$anchor=''){
    $current=max(1,(int)$current); $pages=max(1,(int)$pages);
    if ($pages<=1) return '';
    $window = 1; // neighbors on each side
    $show = [1,$pages,$current];
    for($i=$current-$window;$i<=$current+$window;$i++){ if($i>0 && $i<=$pages) $show[]=$i; }
    $show = array_values(array_unique($show)); sort($show);
    $out = '';

    // Prev
    if ($current>1){
        $qs['page']=$current-1; $out.='<a href="'.h($base.'?'.http_build_query($qs).$anchor).'" aria-label="Previous">‹</a>';
    } else {
        $out.='<span class="disabled">‹</span>';
    }

    // Pages with gaps
    $prev = 0;
    foreach($show as $p){
        if($prev && $p>$prev+1){ $out.='<span class="gap">…</span>'; }
        $qs['page']=$p;
        $out.='<a href="'.h($base.'?'.http_build_query($qs).$anchor).'" class="'.($p==$current?'active':'').'">'.$p.'</a>';
        $prev=$p;
    }

    // Next
    if ($current<$pages){
        $qs['page']=$current+1; $out.='<a href="'.h($base.'?'.http_build_query($qs).$anchor).'" aria-label="Next">›</a>';
    } else {
        $out.='<span class="disabled">›</span>';
    }
    return $out;
}

/* ------------ CSRF ------------ */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf_token'];

/* ------------ Rules (Debit & Refund removed) ------------ */
$ALLOWED_TYPES   = ['topup','adjustment'];
$ALLOWED_METHODS = ['GCash','Maya','Admin'];
function methodAllowedForType($type,$method){
    $map = [
        'topup'      => ['GCash','Maya','Admin'],
        'adjustment' => ['Admin'],
    ];
    return in_array($method, $map[$type] ?? [], true);
}

/* ------------ Create (no refund/debit) ------------ */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create_tx'){
    try{
        if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) throw new Exception('Invalid session token, refresh and try again.');

        $user_id   = (int)($_POST['user_id'] ?? 0);
        $type      = $_POST['type'] ?? '';
        $method    = $_POST['method'] ?? '';
        $amountStr = trim($_POST['amount'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        $direction = $_POST['direction'] ?? '';

        if ($user_id<=0) throw new Exception('Missing user.');
        if (!in_array($type,$ALLOWED_TYPES,true)) throw new Exception('Invalid type.');
        if (!in_array($method,$ALLOWED_METHODS,true) || !methodAllowedForType($type,$method)) throw new Exception('Invalid method.');

        $u = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id=? AND archived=0");
        $u->execute([$user_id]);
        $user = $u->fetch(PDO::FETCH_ASSOC);
        if (!$user) throw new Exception('User not found.');

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO user_wallets (user_id,balance) VALUES (?,0.00)
                       ON DUPLICATE KEY UPDATE user_id=user_id")->execute([$user_id]);

        $s = $pdo->prepare("SELECT balance FROM user_wallets WHERE user_id=? FOR UPDATE");
        $s->execute([$user_id]);
        $balance = (float)$s->fetchColumn();

        $reference_no = genRef($pdo);
        $delta = 0.00; $amount = 0.00;

        if ($amountStr==='' || !preg_match('/^\d+(\.\d{1,2})?$/',$amountStr)) throw new Exception('Enter a valid amount.');
        $amount = round((float)$amountStr,2);
        if ($amount<=0) throw new Exception('Amount must be greater than zero.');

        if     ($type==='topup')      $delta=+$amount;
        elseif ($type==='adjustment'){ if (!in_array($direction,['credit','debit'],true)) throw new Exception('Pick credit or debit for adjustment.'); $delta = ($direction==='credit') ? +$amount : -$amount; }

        if ($delta<0 && $balance+$delta < -0.00001) throw new Exception('Insufficient balance.');
        $newBalance = round($balance + $delta, 2);

        $pdo->prepare("UPDATE user_wallets SET balance=? WHERE user_id=?")->execute([$newBalance,$user_id]);

        $meta = [
            'performed_by_admin' => (int)($_SESSION['user_id'] ?? 0),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'at' => nowUtc(),
        ];
        if ($type==='adjustment') $meta['direction']=$direction;

        $pdo->prepare("INSERT INTO wallet_transactions (user_id,type,method,amount,reference_no,notes,meta)
                       VALUES (?,?,?,?,?,?,?)")
            ->execute([$user_id,$type,$method,$amount,$reference_no,$notes,json_encode($meta,JSON_UNESCAPED_SLASHES)]);

        $pdo->commit();
        flash('success', "Saved. New balance for <strong>".h($user['first_name'].' '.$user['last_name'])."</strong>: <strong>".peso($newBalance)."</strong>.");
        header("Location: wallet.php?user_id=".$user_id."#user-detail");
        exit();
    }catch(Exception $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getMessage());
        $back = (int)($_POST['user_id'] ?? 0) ? "?user_id=".(int)$_POST['user_id']."#user-detail" : '';
        header("Location: wallet.php".$back);
        exit();
    }
}

/* ------------ Page state ------------ */
$mode_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

/* ------------ Stats ------------ */
$total_balance = (float)$pdo->query("SELECT COALESCE(SUM(balance),0) FROM user_wallets")->fetchColumn();
$active_wallets= (int)$pdo->query("SELECT COUNT(*) FROM user_wallets")->fetchColumn();
$topups_30d    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type='topup' AND created_at>=NOW()-INTERVAL 30 DAY")->fetchColumn();
$debits_30d    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type='debit' AND created_at>=NOW()-INTERVAL 30 DAY")->fetchColumn(); // historic display only

/* ------------ Users list ------------ */
$q    = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'name_asc';
$page = max(1,(int)($_GET['page'] ?? 1));
$per  = min(50,max(5,(int)($_GET['per'] ?? 10)));

$sortSql = "u.last_name ASC, u.first_name ASC";
if ($sort==='name_desc')      $sortSql = "u.last_name DESC, u.first_name DESC";
if ($sort==='balance_desc')   $sortSql = "COALESCE(w.balance,0) DESC, u.last_name ASC";
if ($sort==='balance_asc')    $sortSql = "COALESCE(w.balance,0) ASC, u.last_name ASC";
if ($sort==='newest')         $sortSql = "u.created_at DESC";

$params = [];
$where  = "WHERE u.archived=0 AND u.role='user'";
if ($q!==''){
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $like = "%{$q}%";
    $params = [$like,$like,$like];
}
$stc = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
$stc->execute($params);
$list_total = (int)$stc->fetchColumn();
$pages = max(1,(int)ceil($list_total/$per));
$off   = ($page-1)*$per;

$sqlList = "
    SELECT u.id, u.first_name, u.last_name, u.email, u.profile_image, u.created_at, COALESCE(w.balance,0) AS balance
    FROM users u
    LEFT JOIN user_wallets w ON w.user_id = u.id
    $where
    ORDER BY $sortSql
    LIMIT $per OFFSET $off
";
$st = $pdo->prepare($sqlList);
$st->execute($params);
$list = $st->fetchAll(PDO::FETCH_ASSOC);

/* ------------ User detail + transactions (for modal / partial) ------------ */
$userDetail = null;
$tx = [];
$tx_total=0; $txPage=max(1,(int)($_GET['tx_page']??1)); $txPer=min(100,max(5,(int)($_GET['tx_per']??10)));
$filter_type = (!empty($_GET['type']) && in_array($_GET['type'],$ALLOWED_TYPES,true)) ? $_GET['type'] : ''; // only topup/adjustment
$filter_method = (!empty($_GET['method']) && in_array($_GET['method'],$ALLOWED_METHODS,true)) ? $_GET['method'] : ''; // GCash/Maya/Admin
$filter_from = trim($_GET['from'] ?? '');
$filter_to   = trim($_GET['to'] ?? '');

if ($mode_user_id>0){
    $ud = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.profile_image, COALESCE(w.balance,0) AS balance
        FROM users u LEFT JOIN user_wallets w ON w.user_id=u.id
        WHERE u.id=? AND u.archived=0
    ");
    $ud->execute([$mode_user_id]);
    $userDetail = $ud->fetch(PDO::FETCH_ASSOC);

    if ($userDetail){
        $w = "WHERE t.user_id=?";
        $p = [$mode_user_id];
        if ($filter_type){   $w.=" AND t.type=?";   $p[]=$filter_type; }
        if ($filter_method){ $w.=" AND t.method=?"; $p[]=$filter_method; }
        if ($filter_from){   $w.=" AND t.created_at>=?"; $p[]=$filter_from." 00:00:00"; }
        if ($filter_to){     $w.=" AND t.created_at<?";  $p[]=$filter_to." 23:59:59"; }

        $c = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions t $w");
        $c->execute($p);
        $tx_total = (int)$c->fetchColumn();

        $off = ($txPage-1)*$txPer;
        $qTx = $pdo->prepare("SELECT t.* FROM wallet_transactions t $w ORDER BY t.created_at DESC LIMIT $txPer OFFSET $off");
        $qTx->execute($p);
        $tx = $qTx->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* ------------ Partial: render user detail for modal (AJAX) ------------ */
function render_user_detail_fragment($ctx){
    $u = $ctx['userDetail'];
    $ALLOWED_TYPES   = $ctx['ALLOWED_TYPES'];
    $ALLOWED_METHODS = $ctx['ALLOWED_METHODS'];
    $tx              = $ctx['tx'];
    $tx_total        = $ctx['tx_total'];
    $txPage          = $ctx['txPage'];
    $txPer           = $ctx['txPer'];
    $filter_type     = $ctx['filter_type'];
    $filter_method   = $ctx['filter_method'];
    $filter_from     = $ctx['filter_from'];
    $filter_to       = $ctx['filter_to'];

    $fullName = h($u['first_name'].' '.$u['last_name']);
    $avatar   = !empty($u['profile_image']) ? $u['profile_image'] : 'default.jpg';

    // base QS for pager links
    $baseQS = [
        'partial'=>'detail',
        'user_id'=>(int)$u['id'],
        'type'=>$filter_type,
        'method'=>$filter_method,
        'from'=>$filter_from,
        'to'=>$filter_to,
        'tx_per'=>$txPer
    ];
    ?>
    <div id="uMeta" data-user-id="<?= (int)$u['id'] ?>" data-user-name="<?= $fullName ?>"></div>

    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;min-width:0;">
      <img class="avatar" src="<?= '/kandado/assets/uploads/'.h($avatar) ?>" loading="lazy" alt="">
      <div style="min-width:0;">
        <div style="font-weight:800;font-size:1.05rem;line-height:1.2"><?= $fullName ?></div>
        <div class="muted email" style="margin-top:2px;"><?= h($u['email']) ?></div>
        <div style="margin-top:.45rem;">
          <span class="chip green"><i class="fa-regular fa-circle-check"></i> Wallet Balance: <span class="num"><?= peso($u['balance']) ?></span></span>
        </div>
      </div>
    </div>

    <form method="get" action="wallet.php" class="filters" data-modal-filter autocomplete="off" style="margin-top:.25rem;">
      <input type="hidden" name="partial" value="detail">
      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
      <div class="group">
        <label for="mf-type">Type</label>
        <select class="select" id="mf-type" name="type">
          <option value="">All</option>
          <?php foreach($ALLOWED_TYPES as $t): ?>
            <option value="<?= h($t) ?>" <?= $filter_type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="group">
        <label for="mf-method">Method</label>
        <select class="select" id="mf-method" name="method">
          <option value="">All</option>
          <?php foreach($ALLOWED_METHODS as $m): ?>
            <option value="<?= h($m) ?>" <?= $filter_method===$m?'selected':'' ?>><?= $m ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="group">
        <label for="mf-from">From</label>
        <input class="input" id="mf-from" type="date" name="from" value="<?= h($filter_from) ?>">
      </div>
      <div class="group">
        <label for="mf-to">To</label>
        <input class="input" id="mf-to" type="date" name="to" value="<?= h($filter_to) ?>">
      </div>
      <div class="group">
        <label for="mf-per">Per</label>
        <select class="select" id="mf-per" name="tx_per">
          <?php foreach([10,20,50,100] as $opt): ?>
            <option value="<?= $opt ?>" <?= $txPer===$opt?'selected':'' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
      <a class="btn ghost m-link" href="wallet.php?<?= http_build_query(['partial'=>'detail','user_id'=>$u['id']]) ?>"><i class="fa-solid fa-rotate" style="text-decuration:none;"></i> Reset</a>
    </form>

    <div class="table-wrap">
      <table aria-label="Wallet transactions">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Method</th>
            <th class="t-num">Amount</th>
            <th>Reference</th>
            <th>Notes</th>
            <th class="t-right"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$tx): ?>
            <tr><td colspan="7"><div class="empty"><i class="fa-regular fa-note-sticky"></i> No transactions yet.</div></td></tr>
          <?php else: foreach($tx as $row): ?>
            <?php
              $chipClass = ($row['type']==='debit') ? 'red' : 'green'; // historic debits render red
              $sign = ($row['type']==='debit') ? '-' : '+';
            ?>
            <tr>
              <td data-th="Date"><?= h(date('Y-m-d H:i', strtotime($row['created_at']))) ?></td>
              <td data-th="Type"><span class="chip <?= $chipClass ?>"><?= ucfirst($row['type']) ?></span></td>
              <td data-th="Method"><?= h($row['method']) ?></td>
              <td class="t-num" data-th="Amount"><span class="num"><?= $sign.peso($row['amount']) ?></span></td>
              <td data-th="Reference"><code><?= h($row['reference_no']) ?></code></td>
              <td class="muted" data-th="Notes"><?= $row['notes'] ? h($row['notes']) : '—' ?></td>
              <td class="actions-cell" data-th="">
                <div class="row-actions"><!-- no actions in rows --></div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php
      $txPages=max(1,(int)ceil($tx_total/$txPer));
      if ($txPages>1):
        $qs = $baseQS;
        $html = '';
        $cur = $txPage; $pagesAll = $txPages;

        if ($cur>1){ $qs['tx_page']=$cur-1; $html.='<a class="m-link" href="wallet.php?'.h(http_build_query($qs)).'">‹</a>'; }
        else { $html.='<span class="disabled">‹</span>'; }

        $window=1; $show=[1,$pagesAll,$cur];
        for($i=$cur-$window;$i<=$cur+$window;$i++){ if($i>=1 && $i<=$pagesAll) $show[]=$i; }
        $show=array_values(array_unique($show)); sort($show);
        $prev=0;
        foreach($show as $p){
            if($prev && $p>$prev+1) $html.='<span class="gap">…</span>';
            $qs['tx_page']=$p;
            $html.='<a class="m-link '.($p==$cur?'active':'').'" href="wallet.php?'.h(http_build_query($qs)).'">'.$p.'</a>';
            $prev=$p;
        }
        if ($cur<$pagesAll){ $qs['tx_page']=$cur+1; $html.='<a class="m-link" href="wallet.php?'.h(http_build_query($qs)).'">›</a>'; }
        else { $html.='<span class="disabled">›</span>'; }
    ?>
      <div class="pager">
        <div class="pages">Page <?= $txPage ?> of <?= $txPages ?> • <?= number_format($tx_total) ?> transactions</div>
        <div class="nav"><?= $html ?></div>
      </div>
    <?php endif;
}

/* Emit partial (AJAX) and exit */
if (isset($_GET['partial']) && $_GET['partial']==='detail'){
    if (!$userDetail){
        http_response_code(404);
        echo '<div class="empty">User not found.</div>';
        exit;
    }
    render_user_detail_fragment([
        'userDetail'=>$userDetail,
        'ALLOWED_TYPES'=>$ALLOWED_TYPES,
        'ALLOWED_METHODS'=>$ALLOWED_METHODS,
        'tx'=>$tx,
        'tx_total'=>$tx_total,
        'txPage'=>$txPage,
        'txPer'=>$txPer,
        'filter_type'=>$filter_type,
        'filter_method'=>$filter_method,
        'filter_from'=>$filter_from,
        'filter_to'=>$filter_to,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Wallets · Admin</title>
  <style>
    /* ===== Reset-ish ===== */
    *,*::before,*::after{ box-sizing:border-box; }
    html, body { overflow-x: hidden; width:100%; }
    body { margin: 0; }
    img { max-width: 100%; height: auto; display:block; }
    code { white-space:normal; overflow-wrap:anywhere; }

    /* ===== Vars / Tokens ===== */
    :root{
      --primary-700:#334155;
      --muted:#6b7280;
      --border:#e5e7eb;
      --surface-2:#f9fafb;
      --text:#0f172a;
      --brand-1:#3b82f6;
      --brand-2:#6366f1;
      --accent:#a5b4fc;
      --shadow-2:0 1px 2px rgba(0,0,0,.05);
      --active-green-1:#166534;
      --active-green-border:#bbf7d0;
      --active-green-bg:#ecfdf5;
      --header-h:56px;
    }

    /* ===== Page layout ===== */
    .page-wrap{max-width:1200px;margin:0 auto;padding:1rem 1rem 0 1rem;}
    .page-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem;}
    .title-stack h1{font-size:1.4rem;line-height:1.2;margin-bottom:.2rem;color:var(--primary-700);}
    .title-stack p{color:var(--muted);font-size:.95rem;}
    .page-wrap a{ text-decoration:none !important; }

    .toolbar{display:flex;flex-wrap:wrap;gap:.5rem;min-width:0;}
    .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.58rem .9rem;border-radius:10px;border:1px solid var(--border);background:#fff;cursor:pointer;font-weight:700;}
    .btn i{font-size:.95rem;}
    .btn:hover{box-shadow:var(--shadow-2);}
    .btn.primary{background:linear-gradient(135deg,var(--brand-1),var(--brand-2));color:#fff;border-color:rgba(255,255,255,.25);}
    .btn.ghost{background:transparent;}
    .btn.small{padding:.42rem .7rem;border-radius:9px;font-weight:600;}

    .input,.select{height:40px;border-radius:10px;border:1px solid var(--border);padding:0 .75rem;background:#fff;min-width:0;}
    .input:focus,.select:focus{outline:2px solid var(--accent);outline-offset:2px;}

    /* Stats */
    .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin:1rem 0 1.2rem;}
    .stat{background:linear-gradient(180deg,#ffffff, var(--surface-2));border:1px solid var(--border);border-radius:16px;padding:1rem;box-shadow:var(--shadow-2);min-width:0;}
    .stat .k{font-size:.8rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.35rem;}
    .stat .v{font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:.45rem;}
    /* FIX: stat numbers no-wrap & tabular digits */
    .stat .v .num{white-space:nowrap;font-variant-numeric:tabular-nums;}

    /* smaller numbers on narrow phones */
    @media (max-width: 420px){
      .stat .v{font-size:1.05rem;}
    }

    /* Card + table */
    .card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:1rem;box-shadow:var(--shadow-2);min-width:0;}
    .card h2{font-size:1.05rem;margin-bottom:.75rem;color:var(--text);}
    .card .sub{font-size:.88rem;color:var(--muted);margin-bottom:.75rem;}

    .table-wrap{overflow-x:auto; overflow-y:hidden; border:1px solid var(--border);border-radius:12px;background:#fff;-webkit-overflow-scrolling:touch;}
    .table-wrap::-webkit-scrollbar{height:10px;}
    .table-wrap code{font-size:.85rem;}
    table{width:100%;border-collapse:separate;border-spacing:0;table-layout:auto;min-width:0;}
    thead th{position:sticky;top:0;background:#f8faff;border-bottom:1px solid var(--border);text-align:left;font-size:.85rem;color:var(--muted);padding:.7rem .8rem;z-index:3;}
    /* FIX: Actions header right side */
    thead th.t-right{ text-align:right; }
    tbody td{padding:.75rem .8rem;border-bottom:1px solid var(--border);}
    tbody tr:hover{background:#fbfdff;}
    .t-num{text-align:right;font-variant-numeric:tabular-nums;}
    .chip{display:inline-flex;align-items:center;gap:.4rem;padding:.22rem .55rem;border-radius:999px;font-size:.75rem;font-weight:700;border:1px solid var(--border);background:#f6f8ff;}
    .chip.green{background:var(--active-green-bg);border-color:var(--active-green-border);color:var(--active-green-1);}
    .chip.gray{background:#eef2ff;border-color:#dfe6ff;color:#374151;}
    .chip.red{background:#fee2e2;border-color:#fecaca;color:#991b1b;}
    .row-actions{display:flex;gap:.35rem;flex-wrap:wrap; justify-content:flex-end;} /* FIX: right align actions */
    .muted{color:var(--muted);overflow-wrap:break-word;}
    .email{font-size:.9rem;color:#475569;overflow-wrap:anywhere;}
    .avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid #e6e9f6;background:#f2f6ff;display:block;}

    .filters{display:flex;flex-wrap:wrap;gap:.5rem;margin:.4rem 0 .8rem;min-width:0;}
    .filters .group{display:flex;gap:.4rem;align-items:center;min-width:0;}
    .filters label{font-size:.78rem;color:var(--muted);}
    .filters input[type="date"]{height:40px;border-radius:10px;border:1px solid var(--border);padding:0 .5rem;background:#fff;min-width:0;}

    .empty{padding:1.2rem;border:1px dashed var(--border);border-radius:12px;background:#fff;color:var(--muted);}
    .pager{display:flex;justify-content:space-between;align-items:center;margin-top:.7rem;gap:.6rem;flex-wrap:wrap;}
    .pager .pages{font-size:.9rem;color:var(--muted);}
    .pager .nav{display:flex;gap:.25rem;align-items:center;flex-wrap:wrap;}
    .pager a,.pager span{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .7rem;border:1px solid var(--border);border-radius:10px;background:#fff;color:#0f172a;font-weight:600;text-decoration:none;}
    .pager a.active{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.35);}
    .pager .gap{border:none;background:transparent;padding:.2rem .25rem;color:var(--muted);}
    .pager .disabled{opacity:.45;pointer-events:none;}

    /* ===== Modals ===== */
    .modal{
      position:fixed; inset:0; display:none;
      align-items:flex-end; justify-content:center;
      background:rgba(15,23,42,.38);
      padding:12px;
      z-index:2000;
      overscroll-behavior:contain;
      -webkit-backdrop-filter:saturate(120%) blur(2px);
      backdrop-filter:saturate(120%) blur(2px);
    }
    .modal.show{ display:flex; animation:fadeIn .15s ease-out; }
    .modal .panel{
      width:100%;
      max-width:min(960px, 100%);
      background:#fff;
      border-radius:16px;
      box-shadow:0 10px 40px rgba(0,0,0,.12);
      transform:translateY(24px);
      opacity:0;
      transition:transform .18s ease, opacity .18s ease;
      display:flex; flex-direction:column;
      max-height:calc(100vh - var(--header-h) - 24px);
    }
    .modal.show .panel{ transform:translateY(0); opacity:1; }
    .modal header{
      position:sticky; top:0; z-index:5;
      display:flex; align-items:center; justify-content:space-between;
      gap:.75rem; padding:.75rem 1rem;
      border-bottom:1px solid var(--border); background:#fff;
      border-top-left-radius:16px; border-top-right-radius:16px;
    }
    .modal header.scrolled{ box-shadow:0 2px 8px rgba(15,23,42,.06); }
    .modal header h3{margin:0;font-size:1.05rem;}
    .modal .body{padding:1rem; overflow:auto; -webkit-overflow-scrolling:touch;}
    .modal .field{margin-bottom:.8rem;}
    .modal .field label{display:block;font-weight:700;margin-bottom:.35rem;color:#374151;}
    .modal .actions{display:flex;gap:.5rem;justify-content:flex-end;padding-top:.5rem;}
    .quick-amounts{overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch;}
    .quick-amounts .qa{display:inline-block;margin-top:.35rem;}
    .modal textarea{width:100%;min-height:72px;resize:vertical;}

    /* Bottom sheet for narrow screens */
    @media (max-width: 639.98px){
      .modal{ align-items:flex-end; padding-bottom: max(12px, env(safe-area-inset-bottom)); }
      .modal .panel{ max-height:calc(100vh - 12px); border-bottom-left-radius:0; border-bottom-right-radius:0; }
    }
    /* Center modal for >=640px */
    @media (min-width: 640px){
      .modal{ align-items:center; padding-top: calc(var(--header-h) + 16px); }
      .modal .panel{ transform:scale(.98); }
      .modal.show .panel{ transform:scale(1); }
    }
    @keyframes fadeIn{ from{opacity:.001} to{opacity:1} }
    @media (prefers-reduced-motion: reduce){
      .modal .panel{ transition:none; }
      .modal.show{ animation:none; }
    }

    /* ===== Mobile refinements ===== */
    @media (max-width: 1024px){ .stats{grid-template-columns:repeat(2,minmax(0,1fr));} }
    @media (max-width: 640px){
      .toolbar{ display:grid; grid-template-columns:1fr 1fr; width:100%; }
      .toolbar > *{ min-width:0; }
      .toolbar .btn{ grid-column:1/-1; }
    }
    /* Turn all tables into cards on small phones */
    @media (max-width: 640px){
      .stats{grid-template-columns:1fr 1fr;}
      .table-wrap{border:0;background:transparent;}
      thead{display:none;}
      tbody{display:block;}
      tbody tr{
        display:block;
        border:1px solid var(--border);
        border-radius:12px;
        background:#fff;
        box-shadow:var(--shadow-2);
        margin-bottom:.75rem;
        overflow:hidden;
      }
      tbody td{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:.75rem;
        padding:.65rem .8rem;
        border-bottom:1px solid var(--border);
      }
      tbody tr td:last-child{ border-bottom:0; }
      tbody td::before{
        content:attr(data-th);
        font-weight:700;
        color:var(--muted);
        min-width:5.5rem;
        max-width:45%;
        flex:0 0 auto;
      }
      .t-num{text-align:left;}
      .row-actions{gap:.5rem; justify-content:flex-end;} /* keep actions right on mobile cards too */
      .btn.small{padding:.5rem .8rem;}
      .avatar{width:36px;height:36px;}
      td.actions-cell::before{ display:none; }
      td.actions-cell{ padding:.5rem .65rem; }
      td.actions-cell .row-actions{
        display:flex; flex-wrap:nowrap; gap:.4rem;
        overflow-x:auto; -webkit-overflow-scrolling:touch;
        scrollbar-width:none; white-space:nowrap;
      }
      td.actions-cell .row-actions::-webkit-scrollbar{ display:none; }
      td.actions-cell .btn.small{ flex:0 0 auto; padding:.46rem .6rem; font-size:.9rem; }
    }
  </style>
</head>
<body>

<?php include '../../includes/admin_header.php'; ?>

  <main id="content">
    <div class="page-wrap">
      <div class="page-head">
        <div class="title-stack">
          <h1><i class="fa-solid fa-wallet"></i> Wallets</h1>
          <p>Manage user balances. Debit & Refund removed — use Top-up or Adjustment.</p>
        </div>
        <form method="get" action="wallet.php" class="toolbar">
          <input class="input" type="text" name="q" value="<?= h($q) ?>" placeholder="Search name or email" aria-label="Search">
          <select class="select" name="sort" aria-label="Sort">
            <option value="name_asc"     <?= $sort==='name_asc'?'selected':'' ?>>Name A→Z</option>
            <option value="name_desc"    <?= $sort==='name_desc'?'selected':'' ?>>Name Z→A</option>
            <option value="balance_desc" <?= $sort==='balance_desc'?'selected':'' ?>>Balance High→Low</option>
            <option value="balance_asc"  <?= $sort==='balance_asc'?'selected':'' ?>>Balance Low→High</option>
            <option value="newest"       <?= $sort==='newest'?'selected':'' ?>>Newest Users</option>
          </select>
          <select class="select" name="per" aria-label="Per page">
            <?php foreach([10,20,30,50] as $opt): ?>
              <option value="<?= $opt ?>" <?= $per===$opt?'selected':'' ?>><?= $opt ?>/page</option>
            <?php endforeach; ?>
          </select>
          <button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Apply</button>
        </form>
      </div>

      <section class="stats">
        <div class="stat">
          <div class="k">Total Balance</div>
          <div class="v"><i class="fa-solid fa-coins"></i> <span class="num"><?= peso($total_balance) ?></span></div>
        </div>
        <div class="stat">
          <div class="k">Top-ups (30d)</div>
          <div class="v"><i class="fa-solid fa-arrow-trend-up"></i> <span class="num"><?= peso($topups_30d) ?></span></div>
        </div>
        <div class="stat">
          <div class="k">Debits (30d)</div>
          <div class="v"><i class="fa-solid fa-arrow-trend-down"></i> <span class="num"><?= peso($debits_30d) ?></span></div>
        </div>
        <div class="stat">
          <div class="k">Active Wallets</div>
          <div class="v"><i class="fa-regular fa-id-badge"></i> <span class="num"><?= number_format($active_wallets) ?></span></div>
        </div>
      </section>

      <section class="grid">
        <div class="card">
          <h2><i class="fa-regular fa-rectangle-list"></i> Users & Balances</h2>
          <p class="sub">Tap <strong>View</strong> to open a user’s wallet & transactions in a popup.</p>
          <div class="table-wrap">
            <table aria-label="Users and balances">
              <thead>
              <tr>
                <th>User</th>
                <th>Email</th>
                <th class="t-num">Balance</th>
                <th class="t-right">Actions</th> <!-- FIX: right side -->
              </tr>
              </thead>
              <tbody>
              <?php if (!$list): ?>
                <tr><td colspan="4"><div class="empty"><i class="fa-regular fa-face-smile"></i> No users found.</div></td></tr>
              <?php else: foreach($list as $row): ?>
                <?php $avatar = !empty($row['profile_image']) ? $row['profile_image'] : 'default.jpg'; ?>
                <tr>
                  <td data-th="User">
                    <div style="display:flex;align-items:center;gap:.6rem;min-width:0;">
                      <img class="avatar" src="<?= '/kandado/assets/uploads/'.h($avatar) ?>" loading="lazy" alt="">
                      <div style="min-width:0;">
                        <div style="font-weight:700;"><?= h($row['first_name'].' '.$row['last_name']) ?></div>
                        <div class="muted" style="font-size:.8rem;">User #<?= (int)$row['id'] ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="email" data-th="Email"><?= h($row['email']) ?></td>
                  <td class="t-num" data-th="Balance"><span class="chip <?= ((float)$row['balance']>=0)?'green':'red' ?>"><span class="num"><?= peso($row['balance']) ?></span></span></td>
                  <td class="actions-cell" data-th="Actions" style="text-align:right;">
                    <div class="row-actions">
                      <a class="btn small js-view-user"
                         href="wallet.php?user_id=<?= (int)$row['id'] ?>#user-detail"
                         data-user-id="<?= (int)$row['id'] ?>"
                         data-user-name="<?= h($row['first_name'].' '.$row['last_name']) ?>">
                        <i class="fa-regular fa-eye"></i> View
                      </a>
                      <button class="btn small" data-open-modal
                              data-user-id="<?= (int)$row['id'] ?>"
                              data-user-name="<?= h($row['first_name'].' '.$row['last_name']) ?>"
                              data-type="topup"><i class="fa-solid fa-plus"></i> Top-up</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($pages>1): ?>
          <div class="pager">
            <div class="pages">Page <?= $page ?> of <?= $pages ?> • <?= number_format($list_total) ?> users</div>
            <div class="nav">
              <?= pager_links($page,$pages,$_GET,'wallet.php','') ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>

  <!-- User Wallet Modal -->
  <div class="modal" id="userModal" role="dialog" aria-modal="true" aria-labelledby="userTitle">
    <div class="panel">
      <header id="userHeader">
        <h3 id="userTitle"><i class="fa-regular fa-user"></i> Wallet</h3>
        <div style="display:flex;gap:.4rem;align-items:center;">
          <button class="btn small primary" id="userNewTxBtn" data-open-modal data-type="topup">
            <i class="fa-solid fa-plus"></i> New Transaction
          </button>
          <button class="btn small" data-close-user-modal><i class="fa-regular fa-circle-xmark"></i> Close</button>
        </div>
      </header>
      <div class="body" id="userModalBody">
        <div class="empty">Select <strong>View</strong> to load a user’s wallet…</div>
      </div>
    </div>
  </div>

  <!-- New Transaction Modal -->
  <div class="modal" id="txModal" role="dialog" aria-modal="true" aria-labelledby="txTitle">
    <div class="panel">
      <header>
        <h3 id="txTitle"><i class="fa-solid fa-money-bill-transfer"></i> New Transaction</h3>
        <button class="btn small" data-close-modal><i class="fa-regular fa-circle-xmark"></i> Close</button>
      </header>
      <form method="post" class="body" id="txForm" autocomplete="off">
        <input type="hidden" name="action" value="create_tx">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="user_id" id="tx_user_id" value="">
        <div class="field">
          <label>User</label>
          <input class="input" type="text" id="tx_user_name" value="" readonly>
        </div>
        <div class="field">
          <label for="tx_type">Type</label>
          <select class="select" name="type" id="tx_type" required>
            <option value="topup">Top-up</option>
            <option value="adjustment">Adjustment</option>
          </select>
        </div>
        <div class="field" id="adjustDirWrap" style="display:none;">
          <label for="tx_direction">Adjustment Direction</label>
          <select class="select" name="direction" id="tx_direction">
            <option value="credit">Credit (+)</option>
            <option value="debit">Debit (−)</option>
          </select>
        </div>
        <div class="field">
          <label for="tx_method">Method</label>
          <select class="select" name="method" id="tx_method" required>
            <option value="GCash">GCash</option>
            <option value="Maya">Maya</option>
            <option value="Admin">Admin</option>
          </select>
        </div>
        <div class="field" id="amountWrap">
          <label for="tx_amount" id="amountLabel">Amount</label>
          <input class="input" type="number" step="0.01" min="0.01" name="amount" id="tx_amount" required placeholder="0.00">
          <div class="quick-amounts" id="qaRow">
            <span class="qa" data-qa="50"  style="cursor:pointer;border:1px dashed var(--border);padding:.25rem .55rem;border-radius:999px;font-weight:700;font-size:.8rem;background:#f8faff;">+50</span>
            <span class="qa" data-qa="100" style="cursor:pointer;border:1px dashed var(--border);padding:.25rem .55rem;border-radius:999px;font-weight:700;font-size:.8rem;background:#f8faff;">+100</span>
            <span class="qa" data-qa="200" style="cursor:pointer;border:1px dashed var(--border);padding:.25rem .55rem;border-radius:999px;font-weight:700;font-size:.8rem;background:#f8faff;">+200</span>
            <span class="qa" data-qa="500" style="cursor:pointer;border:1px dashed var(--border);padding:.25rem .55rem;border-radius:999px;font-weight:700;font-size:.8rem;background:#f8faff;">+500</span>
          </div>
        </div>
        <div class="field">
          <label for="tx_notes">Notes (optional)</label>
          <textarea name="notes" id="tx_notes" placeholder="Short note for audit trail..."></textarea>
        </div>
        <div class="actions">
          <button type="button" class="btn" data-close-modal>Cancel</button>
          <button type="submit" class="btn primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  (function(){
    const root = document.documentElement;

    /* helpers */
    const lockScroll   = ()=>{ root.style.overflow = 'hidden'; };
    const unlockScroll = ()=>{ root.style.overflow = ''; };
    const show = el => { el.classList.add('show'); lockScroll(); };
    const hide = el => { el.classList.remove('show'); unlockScroll(); };

    /* New Tx modal */
    const txModal = document.getElementById('txModal');
    const txForm  = document.getElementById('txForm');
    const type    = document.getElementById('tx_type');
    const method  = document.getElementById('tx_method');
    const dirWrap = document.getElementById('adjustDirWrap');
    const amountLabel= document.getElementById('amountLabel');
    const txAmount = document.getElementById('tx_amount');

    function enableAmount(enabled){
      txAmount.readOnly = !enabled;
      txAmount.disabled = !enabled;
      document.getElementById('qaRow').style.display = enabled ? '' : 'none';
      amountLabel.textContent = enabled ? 'Amount' : 'Amount (auto)';
      if (!enabled) txAmount.value = txAmount.value || '0.00';
    }
    function updateControls(){
      const t = type.value;
      dirWrap.style.display = (t==='adjustment') ? '' : 'none';
      const allowed = {
        'topup': ['GCash','Maya','Admin'],
        'adjustment': ['Admin']
      }[t] || [];
      Array.from(method.options).forEach(opt=> opt.disabled = (allowed.indexOf(opt.value)===-1));
      if (allowed.indexOf(method.value)===-1) method.value = allowed[0] || 'Admin';
      enableAmount(true);
    }
    function openTxModalFrom(el){
      const userId   = el.getAttribute('data-user-id');
      const userName = el.getAttribute('data-user-name') || ('User #' + userId);
      const preType  = el.getAttribute('data-type') || 'topup';
      const preAmount= el.getAttribute('data-amount') || '';

      document.getElementById('tx_user_id').value = userId;
      document.getElementById('tx_user_name').value = userName;
      type.value = preType;
      txAmount.value = preAmount;

      if (preType === 'topup') method.value = 'GCash';
      else { method.value = 'Admin'; } // adjustment default

      updateControls();
      show(txModal);
    }

    document.addEventListener('click', function(e){
      const t = e.target.closest('[data-open-modal]');
      if (t){ e.preventDefault(); openTxModalFrom(t); }
    });
    document.querySelectorAll('#txModal [data-close-modal]').forEach(b=> b.addEventListener('click', ()=>{ hide(txModal); txForm.reset(); enableAmount(true); }));
    txModal.addEventListener('click', e=>{ if(e.target===txModal){ hide(txModal); txForm.reset(); enableAmount(true); }});
    window.addEventListener('keydown', e=>{ if(e.key==='Escape' && txModal.classList.contains('show')) hide(txModal); });
    type.addEventListener('change', updateControls);
    method.addEventListener('change', updateControls);
    document.querySelectorAll('.qa').forEach(el=>{
      el.addEventListener('click', ()=>{
        const v = parseFloat(txAmount.value || '0');
        txAmount.value = (v + parseFloat(el.getAttribute('data-qa'))).toFixed(2);
      });
    });

    /* User (View) modal */
    const userModal = document.getElementById('userModal');
    const userBody  = document.getElementById('userModalBody');
    const userTitle = document.getElementById('userTitle');
    const userHeader= document.getElementById('userHeader');
    const userNewTxBtn = document.getElementById('userNewTxBtn');

    function setUserHeaderFromFragment(){
      const meta = userBody.querySelector('#uMeta');
      if(!meta) return;
      const uid = meta.getAttribute('data-user-id');
      const uname = meta.getAttribute('data-user-name') || ('User #'+uid);
      userTitle.innerHTML = '<i class="fa-regular fa-user"></i> Wallet · ' + uname;
      userNewTxBtn.setAttribute('data-user-id', uid);
      userNewTxBtn.setAttribute('data-user-name', uname);
    }
    async function loadUserFragment(urlOrParams){
      let url = typeof urlOrParams==='string' ? urlOrParams : ('wallet.php?'+new URLSearchParams(urlOrParams).toString());
      const u = new URL(url, location.href);
      u.searchParams.set('partial','detail');
      url = u.toString();
      userBody.innerHTML = '<div class="empty">Loading…</div>';
      const res = await fetch(url, {credentials:'same-origin'});
      if (!res.ok){ userBody.innerHTML='<div class="empty">Unable to load. <a href="'+url+'">Open full page</a></div>'; return; }
      userBody.innerHTML = await res.text();
      setUserHeaderFromFragment();
      userBody.scrollTop = 0;
    }
    function openUserModal(userId){ show(userModal); loadUserFragment({partial:'detail', user_id:userId}); }

    document.addEventListener('click', function(e){
      const view = e.target.closest('.js-view-user');
      if (view){ e.preventDefault(); openUserModal(view.getAttribute('data-user-id')); }
    });

    userBody.addEventListener('submit', function(e){
      const f = e.target.closest('[data-modal-filter]');
      if (f){ e.preventDefault(); const params = new URLSearchParams(new FormData(f)); params.set('partial','detail'); loadUserFragment('wallet.php?'+params.toString()); }
    });
    userBody.addEventListener('click', function(e){
      const link = e.target.closest('a.m-link');
      if (link){ e.preventDefault(); loadUserFragment(link.getAttribute('href')); }
    });

    document.querySelector('[data-close-user-modal]').addEventListener('click', ()=> hide(userModal));
    userModal.addEventListener('click', e=>{ if(e.target===userModal) hide(userModal); });
    window.addEventListener('keydown', e=>{ if(e.key==='Escape' && userModal.classList.contains('show')) hide(userModal); });

    userBody.addEventListener('scroll', ()=>{ userHeader.classList.toggle('scrolled', userBody.scrollTop>4); });

    document.addEventListener('DOMContentLoaded', ()=>{
      const u = new URL(location.href);
      const uid = u.searchParams.get('user_id');
      if (uid) openUserModal(uid);
    });

    // Flash
    <?php if ($f = take_flash()): ?>
      Swal.fire({
        icon: <?= json_encode($f['type']==='success' ? 'success' : 'error') ?>,
        title: <?= json_encode($f['type']==='success' ? 'Success' : 'Error') ?>,
        html: <?= json_encode($f['msg']) ?>,
        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--brand-1').trim() || '#3353bb'
      });
    <?php endif; ?>
  })();
  </script>
</body>
</html>
