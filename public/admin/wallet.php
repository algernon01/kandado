<?php
// admin/wallet.php
// --------------------------------------------------
// Admin Wallets + Transactions (Modal view + Mobile-first)
// • "View" opens modal with AJAX-loaded detail
// • Ellipsis pagination for Users & Transactions
// • No horizontal scroll on mobile
// • Debit & Refund removed (Top-up + Adjustment only)
// • FIX: Actions column centered
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
              $sign = ($row['type']==='debit') ? '-' : '+'; ?>
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
  <link rel="stylesheet" href="../../assets/css/wallet.css">
</head>
<body>

<?php include '../../includes/admin_header.php'; ?>

  <main id="content">
    <div class="page-wrap">
      <div class="page-head">
        <div class="title-stack">
          <h1><i class="fa-solid fa-wallet"></i> Wallets</h1>
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
                <th class="t-right">Actions</th> <!-- centered -->
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
                  <td class="actions-cell" data-th="Actions" style="text-align:center;">
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

  <script src="../../assets/js/wallet.js"></script>

  <!-- Flash (kept inline because it needs server-side data) -->
  <script>
  <?php if ($f = take_flash()): ?>
    Swal.fire({
      icon: <?= json_encode($f['type']==='success' ? 'success' : 'error') ?>,
      title: <?= json_encode($f['type']==='success' ? 'Success' : 'Error') ?>,
      html: <?= json_encode($f['msg']) ?>,
      confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--brand-1').trim() || '#3353bb'
    });
  <?php endif; ?>
  </script>
</body>
</html>
