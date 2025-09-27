<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../login.php'); exit();
}


if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = $_SESSION['csrf_token'];

include '../../includes/admin_header.php';


$host = 'localhost';
$dbname = 'kandado';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) { die('Connection failed: ' . $conn->connect_error); }


function int_array(array $arr): array { $o=[]; foreach ($arr as $v){$v=(int)$v; if($v>0)$o[]=$v;} return array_values(array_unique($o)); }
function not_self(array $ids, $selfId): array { if(!$selfId) return $ids; return array_values(array_filter($ids, fn($x)=>(int)$x!==(int)$selfId)); }
function in_placeholders(int $n): string { return implode(',', array_fill(0,$n,'?')); }
function safe_dir(string $d): string { return strtolower($d)==='desc'?'DESC':'ASC'; }
function order_by_for(string $key, string $dir): string {
  $dir = safe_dir($dir);
  switch ($key) {
    case 'name':    return "u.first_name $dir, u.last_name $dir";
    case 'email':   return "u.email $dir";
    case 'role':    return "u.role $dir, u.id ASC";
    case 'created': return "u.created_at $dir";
    case 'status':  return "archived $dir, u.id ASC";
    case 'locker':  return "l.locker_number $dir, u.id ASC";
    case 'id':
    default:        return "u.id $dir";
  }
}
function status_label(bool $isArchived): string { return $isArchived ? 'Archived' : 'Active'; }

$sessionUserId = $_SESSION['user_id'] ?? null;
$actionDone = null; $actionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? null; $postedCsrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $postedCsrf)) {
    $actionError = 'Security check failed. Please refresh and try again.';
  } else {
   
    $normalize = static function($a){
      $a = strtolower(trim((string)$a));
      if ($a==='hold') return 'archive';
      if ($a==='unhold' || $a==='undo') return 'unarchive';
      return $a;
    };
    $action = $normalize($action);

  
    $ids = [];
    if (!empty($_POST['user_ids']) && is_array($_POST['user_ids'])) { $ids = int_array($_POST['user_ids']); }
    elseif (!empty($_POST['user_id'])) { $ids = int_array([$_POST['user_id']]); }
    $ids = not_self($ids, $sessionUserId);

    if ($action === 'undo_last_archive') {
      $last = $_SESSION['last_archived_ids'] ?? [];
      $ids  = int_array(is_array($last)?$last:[]);
      if (!empty($ids)) {
        $ph = in_placeholders(count($ids));
        $sql = "UPDATE users SET archived = 0 WHERE id IN ($ph)";
        $stmt=$conn->prepare($sql); $types=str_repeat('i', count($ids)); $stmt->bind_param($types, ...$ids); $stmt->execute();
        unset($_SESSION['last_archived_ids']);
        $actionDone = 'last archive undone';
      } else {
        $actionError = 'Nothing to undo.';
      }
    } elseif ($action && !empty($ids)) {
      $ph = in_placeholders(count($ids));
      if ($action === 'delete') {
        $sql = "DELETE FROM users WHERE id IN ($ph)";
        $stmt=$conn->prepare($sql); $types=str_repeat('i', count($ids)); $stmt->bind_param($types, ...$ids); $stmt->execute();
        $actionDone = 'deleted';
      } elseif ($action === 'archive' || $action === 'unarchive') {
        $arch = ($action==='archive')?1:0;
        $sql = "UPDATE users SET archived = ? WHERE id IN ($ph)";
        $stmt=$conn->prepare($sql); $types='i'.str_repeat('i',count($ids)); $params=array_merge([$arch],$ids); $stmt->bind_param($types, ...$params); $stmt->execute();
        if ($arch===1) { $_SESSION['last_archived_ids'] = $ids; }
        $actionDone = $arch ? 'archived' : 'restored';
      }
    } else if ($action) {
      $actionError = 'No valid users selected.';
    }
  }
}


$limit   = isset($_GET['per']) ? max(5, min(100, (int)$_GET['per'])) : 10;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $limit;

$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$f_role  = isset($_GET['role']) ? (string)$_GET['role'] : '';
$f_stat  = isset($_GET['status']) ? (string)$_GET['status'] : '';
if ($f_stat === 'hold') $f_stat = 'archived'; 
$f_lock  = isset($_GET['has_locker']) ? (string)$_GET['has_locker'] : '';

$sortKey = isset($_GET['sort']) ? (string)$_GET['sort'] : 'id';
$sortDir = isset($_GET['dir']) ? (string)$_GET['dir'] : 'asc';
$orderBy = order_by_for($sortKey, $sortDir);

$whereParts=[]; $params=[]; $types='';
if ($q !== '') {
  if (ctype_digit($q)) {
    $whereParts[]='(u.id = ? OR CAST(u.id AS CHAR) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $idExact=(int)$q; $like="%$q%"; $params=array_merge($params,[$idExact,$like,$like,$like,$like]); $types.='issss';
  } else {
    $whereParts[]='(CAST(u.id AS CHAR) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $like="%$q%"; $params=array_merge($params,[$like,$like,$like,$like]); $types.='ssss';
  }
}
if ($f_role==='admin'||$f_role==='user'){ $whereParts[]='u.role = ?'; $params[]=$f_role; $types.='s'; }
if ($f_stat==='active'){ $whereParts[]='(IFNULL(u.archived,0) = 0)'; } elseif ($f_stat==='archived'){ $whereParts[]='(IFNULL(u.archived,0) = 1)'; }
if ($f_lock==='yes'){ $whereParts[]="(l.status = 'occupied' AND l.user_id = u.id)"; } elseif ($f_lock==='no'){ $whereParts[]='(l.user_id IS NULL)'; }
$whereSql = empty($whereParts) ? '1' : implode(' AND ', $whereParts);


$countSql = "SELECT COUNT(DISTINCT u.id) AS total
             FROM users u
             LEFT JOIN locker_qr l ON l.user_id=u.id AND l.status='occupied'
             WHERE $whereSql";
$countStmt = $conn->prepare($countSql);
if ($countStmt){
  if(!empty($params)) $countStmt->bind_param($types, ...$params);
  $countStmt->execute(); $totalUsers=(int)($countStmt->get_result()->fetch_assoc()['total']??0); $countStmt->close();
} else { $totalUsers=0; }
$totalPages = max(1, (int)ceil($totalUsers / $limit));


$listSql = "SELECT
              u.id, u.first_name, u.last_name, u.email, u.role, u.created_at,
              u.profile_image, IFNULL(u.archived,0) AS archived,
              l.locker_number
            FROM users u
            LEFT JOIN locker_qr l ON l.user_id=u.id AND l.status='occupied'
            WHERE $whereSql
            GROUP BY u.id
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
if ($listStmt){
  if(!empty($params)){ $typesList=$types.'ii'; $paramsList=array_merge($params,[$limit,$offset]); $listStmt->bind_param($typesList, ...$paramsList); }
  else { $listStmt->bind_param('ii',$limit,$offset); }
  $listStmt->execute(); $usersRes=$listStmt->get_result();
} else {
  $usersRes = new class{ public $num_rows=0; public function fetch_assoc(){return null;} };
}

function keep(array $extra=[]): string {
  $base=['q'=>$_GET['q']??'','role'=>$_GET['role']??'','status'=>$_GET['status']??'','has_locker'=>$_GET['has_locker']??'','per'=>$_GET['per']??''];
  $q=array_merge($base,$extra); return http_build_query(array_filter($q,fn($v)=>$v!==''&&$v!==null));
}


$onArchivedView = ($f_stat==='archived');
$hasUndo = !empty($_SESSION['last_archived_ids']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<title>Manage Users · Admin</title>

<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" href="../../assets/icon/icon_tab.png" sizes="any">

<link rel="stylesheet" href="../../assets/css/manage_users.css">
</head>
<body
  data-action-done="<?= htmlspecialchars($actionDone ?? '', ENT_QUOTES, 'UTF-8') ?>"
  data-action-error="<?= htmlspecialchars($actionError ?? '', ENT_QUOTES, 'UTF-8') ?>"
>

<div id="manage-users">
  <main id="content" class="mu-main">
    <div class="container-xl">

      <!-- Top toolbar -->
      <div class="toolbar mb-3">
        <div class="left">
    <h1 class="h1 mb-1" style ="font-weight: 800;">
      <i class="fas fa-users me-2" style="color:var(--brand)" aria-hidden="true"></i>
      Manage Users
    </h1>
        </div>

        <div class="right">
          <!-- Segmented Toggle (Active | Archived) -->
          <div class="segmented" role="tablist" aria-label="Status view">
            <?php
              $activeHref    = '?'.keep(['status'=>'active','page'=>1]);
              $archiveHref   = '?'.keep(['status'=>'archived','page'=>1]);
            ?>
            <a role="tab" href="<?= $activeHref ?>" class="<?= $onArchivedView?'':'active' ?>"><i class="fa-regular fa-circle-check"></i> Active</a>
            <a role="tab" href="<?= $archiveHref ?>" class="<?= $onArchivedView?'active':'' ?>"><i class="fa-solid fa-box-archive"></i> Archived</a>
          </div>

          <!-- Columns dropdown -->
          <div class="dropdown">
            <button class="btn btn-ghost" id="colsToggle" aria-expanded="false" aria-haspopup="true"><i class="fa-solid fa-table-columns me-1"></i> Columns</button>
            <div class="menu" id="colsMenu" role="menu" aria-label="Toggle columns">
              <div class="small mb-1">Toggle visibility</div>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="email" type="checkbox" checked> <span>Email</span></label>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="role" type="checkbox" checked> <span>Role</span></label>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="created" type="checkbox" checked> <span>Created</span></label>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="status" type="checkbox" checked> <span>Status</span></label>
              <label class="form-check"><input class="form-check-input col-toggle" data-col="locker" type="checkbox" checked> <span>Locker</span></label>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <details class="filters card mb-3" open>
        <summary><i class="fa-solid fa-sliders me-1"></i> Filters</summary>
        <form method="get" id="filtersForm" class="card-body">
          <div class="filters-grid">
            <div>
              <label class="form-label">Search</label>
              <div class="input-icon">
                <span class="addon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                <input type="text" id="searchInput" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Search by ID, name, or email…" autocomplete="off" aria-label="Search">
              </div>
            </div>
            <div>
              <label class="form-label">Role</label>
              <select class="form-select" name="role" aria-label="Filter by role">
                <option value="" <?= $f_role===''?'selected':'' ?>>All</option>
                <option value="admin" <?= $f_role==='admin'?'selected':'' ?>>Admin</option>
                <option value="user" <?= $f_role==='user'?'selected':'' ?>>User</option>
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select class="form-select" name="status" aria-label="Filter by status">
                <option value="" <?= $f_stat===''?'selected':'' ?>>All</option>
                <option value="active" <?= $f_stat==='active'?'selected':'' ?>>Active</option>
                <option value="archived" <?= $f_stat==='archived'?'selected':'' ?>>Archived</option>
              </select>
            </div>
            <div>
              <label class="form-label">Has Locker</label>
              <select class="form-select" name="has_locker" aria-label="Filter by locker">
                <option value="" <?= $f_lock===''?'selected':'' ?>>Any</option>
                <option value="yes" <?= $f_lock==='yes'?'selected':'' ?>>Yes</option>
                <option value="no" <?= $f_lock==='no'?'selected':'' ?>>No</option>
              </select>
            </div>
            <div>
              <label class="form-label">Per Page</label>
              <select class="form-select" name="per" aria-label="Per page">
                <?php foreach ([10,20,50,100] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $limit===$opt?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="d-flex gap-2 mt-1 wrap">
            <button class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Apply</button>
            <a class="btn btn-ghost" href="manage_users.php"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
          </div>
        </form>
      </details>

      
      <form id="undoLastForm" method="post" class="d-inline">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="action" value="undo_last_archive">
      </form>

      
      <form id="bulkForm" method="post" class="card" aria-describedby="bulkActionsHelp">
        <input type="hidden" name="action" id="bulkAction">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="card-header d-flex align-center justify-between wrap">
          <div class="d-flex align-center gap-2 wrap compact">
            <?php if ($onArchivedView): ?>
              <?php if ($hasUndo): ?>
                <button type="submit" form="undoLastForm" class="btn btn-pill btn-ghost" title="Undo Last Archive">
                  <i class="fa-solid fa-rotate-left"></i> <span class="txt">Undo Last Archive</span>
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-pill btn-ghost" title="Undo Last Archive" disabled>
                  <i class="fa-solid fa-rotate-left"></i> <span class="txt">Undo Last Archive</span>
                </button>
              <?php endif; ?>
              <button type="button" onclick="confirmBulk('unarchive')" class="btn btn-pill btn-primary" title="Unarchive selected users">
                <i class="fa-solid fa-box-open"></i> <span class="txt">Unarchive Selected</span>
              </button>
            <?php else: ?>
              <button type="button" onclick="confirmBulk('archive')" class="btn btn-pill btn-ghost" title="Archive selected users">
                <i class="fa-solid fa-box-archive"></i> <span class="txt">Archive</span>
              </button>
              <button type="button" onclick="confirmBulk('unarchive')" class="btn btn-pill btn-ghost" title="Unarchive selected users">
                <i class="fa-solid fa-rotate-left"></i> <span class="txt">Unarchive</span>
              </button>
              <button type="button" onclick="confirmBulk('delete')" class="btn btn-pill btn-ghost" title="Delete selected users">
                <i class="fa-solid fa-trash"></i> <span class="txt">Delete</span>
              </button>
            <?php endif; ?>
            <span id="selCount" class="small">No selection</span>
          </div>

          <div class="d-flex align-center gap-2 w-100" style="flex:1 1 auto;">
            <div class="small">Sort</div>
            <div class="sort-strip" style="flex:1 1 auto;">
              <?php $cols=['id'=>'ID','name'=>'Name','email'=>'Email','role'=>'Role','created'=>'Created','status'=>'Status','locker'=>'Locker'];
              foreach ($cols as $k=>$label):
                $active=($sortKey===$k);
                $dir=($active && strtolower($sortDir)==='asc') ? 'desc' : 'asc'; ?>
                <a class="btn btn-sm <?= $active?'btn-primary':'btn-ghost' ?>" href="?<?= keep(['sort'=>$k,'dir'=>$dir,'page'=>1]) ?>" aria-label="Sort by <?= htmlspecialchars($label) ?>">
                  <?= htmlspecialchars($label) ?><?= $active ? (strtolower($sortDir)==='asc'?' ▲':' ▼') : '' ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table table-hover align-middle" id="usersTable">
            <thead>
              <tr>
                <th style="width:36px"><input class="form-check-input" type="checkbox" id="selectAll" aria-label="Select all"></th>
                <th>ID</th>
                <th>User</th>
                <th class="col-email">Email</th>
                <th class="col-role">Role</th>
                <th class="col-created">Created</th>
                <th class="col-status">Status</th>
                <th class="col-locker">Locker</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($usersRes->num_rows > 0): ?>
              <?php while ($u=$usersRes->fetch_assoc()):
                $uid=(int)$u['id'];
                $full=trim(($u['first_name']??'').' '.($u['last_name']??'')); $full=$full!==''?$full:'—';
                $email=htmlspecialchars($u['email']??'', ENT_QUOTES, 'UTF-8');
                $role=htmlspecialchars($u['role']??'user', ENT_QUOTES, 'UTF-8');
                $isArchived=(int)($u['archived']??0)===1;
                $createdISO=$u['created_at'] ?: null; $created=$createdISO?date('M d, Y • h:i A', strtotime($createdISO)):'—';
                $profileName=!empty($u['profile_image'])?$u['profile_image']:'default.jpg';
                $img='/kandado/assets/uploads/'.htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8').'?t='.time();
                $locker=!empty($u['locker_number'])?('Locker '.(int)$u['locker_number']):'None';
                $rowClass=$isArchived?'row-archived':'';
                $payload=[ 'id'=>$uid,'name'=>$full,'email'=>$u['email']??'','role'=>$role,'created'=>$created,'archived'=>$isArchived,'locker'=>$u['locker_number']??null,'img'=>$img ];
              ?>
              <tr class="<?= $rowClass ?> user-row" data-user='<?= json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>'>
                <td data-label="Select">
                  <?php if ($uid !== (int)$sessionUserId): ?>
                    <input class="form-check-input row-check" type="checkbox" name="user_ids[]" value="<?= $uid ?>" aria-label="Select user <?= $uid ?>">
                  <?php else: ?><input class="form-check-input" type="checkbox" disabled aria-disabled="true"><?php endif; ?>
                </td>
                <td data-label="ID"><?= $uid ?></td>
                <td data-label="User">
                  <div class="d-flex align-center gap-2">
                    <div class="avatar-ring" title="<?= $isArchived ? 'Archived' : 'Active' ?>">
                      <img class="avatar" src="<?= $img ?>" onerror="this.onerror=null;this.src='/kandado/assets/uploads/default.jpg'" alt="<?= htmlspecialchars($full, ENT_QUOTES, 'UTF-8') ?>">
                      <?php if(!$isArchived): ?><span class="ring" aria-hidden="true"></span><?php endif; ?>
                    </div>
                    <div>
                      <div class="name" style="font-weight:900; color:var(--ink)"><?= htmlspecialchars($full, ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="small">ID: <?= $uid ?></div>
                    </div>
                  </div>
                </td>
                <td data-label="Email" class="col-email">
                  <a href="mailto:<?= $email ?>" class="text-reset" style="word-break:break-all;"><?= $email ?></a>
                  <button type="button" class="btn btn-ghost btn-sm ms-1" onclick="copyText('<?= $email ?>')" title="Copy email"><i class="fa-regular fa-copy"></i></button>
                </td>
                <td data-label="Role" class="col-role">
                  <?php if ($role==='admin'): ?>
                    <span class="badge badge-admin"><i class="fas fa-user-shield"></i> Admin</span>
                  <?php else: ?>
                    <span class="badge badge-user"><i class="fas fa-user"></i> User</span>
                  <?php endif; ?>
                </td>
                <td data-label="Created" class="col-created" title="<?= htmlspecialchars($createdISO ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= $created ?></td>
                <td data-label="Status" class="col-status">
                  <?php if ($isArchived): ?>
                    <span class="badge badge-archived"><i class="fa-solid fa-box-archive"></i> Archived</span>
                  <?php else: ?>
                    <span class="badge badge-active"><i class="fa-regular fa-circle-check"></i> Active</span>
                  <?php endif; ?>
                </td>
                <td data-label="Locker" class="col-locker"><?= htmlspecialchars($locker, ENT_QUOTES, 'UTF-8') ?></td>

                
                <td class="text-center" data-label="Actions">
                  <div class="btn-list compact">
                    <button type="button" class="btn btn-view btn-sm viewBtn" title="View">
                      <i class="fa-regular fa-eye"></i> <span class="txt ms-1">View</span>
                    </button>

                    <?php if ($uid !== (int)$sessionUserId): ?>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <input type="hidden" name="action" value="<?= $isArchived ? 'unarchive' : 'archive' ?>">
                        <button class="btn <?= $isArchived ? 'btn-success' : 'btn-warning' ?> btn-sm"
                                onclick="return confirmRow(this.form, '<?= $isArchived ? 'Restore this user?' : 'Archive this user?' ?>')"
                                title="<?= $isArchived?'Unarchive':'Archive' ?>">
                          <i class="<?= $isArchived ? 'fas fa-rotate-left' : 'fa-solid fa-box-archive' ?>"></i>
                          <span class="txt ms-1"><?= $isArchived ? 'Unarchive' : 'Archive' ?></span>
                        </button>
                      </form>

                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-danger btn-sm"
                                onclick="return confirmRow(this.form, 'Delete this user permanently?')"
                                title="Delete">
                          <i class="fas fa-trash"></i> <span class="txt ms-1">Delete</span>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="small">(You)</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="9" class="text-center" style="padding:2.2rem 1rem;"><div class="muted"><i class="fas fa-user-slash me-1"></i>No users found<?= $q? ' for “' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '”' : '' ?>.</div></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>


        <?php if ($totalPages > 1): $window=2; $start=max(1,$page-$window); $end=min($totalPages,$page+$window); ?>
        <div class="card-footer d-flex justify-content-center wrap gap-2">
          <a class="btn btn-ghost <?= $page===1?'disabled':'' ?>" href="<?= $page===1 ? 'javascript:void(0)' : '?'.keep(['page'=>1,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="First page">«</a>
          <a class="btn btn-ghost <?= $page===1?'disabled':'' ?>" href="<?= $page===1 ? 'javascript:void(0)' : '?'.keep(['page'=>$page-1,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="Previous page">Prev</a>
          <?php if ($start>1): ?><span class="px-2">…</span><?php endif; ?>
          <?php for ($i=$start; $i<=$end; $i++): ?>
            <a class="btn <?= $i===$page?'btn-primary':'btn-ghost' ?>" href="<?= $i===$page ? 'javascript:void(0)' : '?'.keep(['page'=>$i,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="Page <?= $i ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($end<$totalPages): ?><span class="px-2">…</span><?php endif; ?>
          <a class="btn btn-ghost <?= $page>=$totalPages?'disabled':'' ?>" href="<?= $page>=$totalPages ? 'javascript:void(0)' : '?'.keep(['page'=>$page+1,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="Next page">Next</a>
          <a class="btn btn-ghost <?= $page>=$totalPages?'disabled':'' ?>" href="<?= $page>=$totalPages ? 'javascript:void(0)' : '?'.keep(['page'=>$totalPages,'sort'=>$sortKey,'dir'=>$sortDir]) ?>" aria-label="Last page">»</a>
        </div>
        <?php endif; ?>
      </form>


      <div id="bulkBar" class="shadow" aria-hidden="true">
        <?php if ($onArchivedView): ?>
          <?php if ($hasUndo): ?>
            <button type="submit" form="undoLastForm" class="btn btn-ghost" title="Undo Last Archive"><i class="fa-solid fa-rotate-left"></i></button>
          <?php endif; ?>
          <button type="button" class="btn btn-primary" onclick="confirmBulk('unarchive')" title="Unarchive Selected"><i class="fa-solid fa-box-open"></i></button>
        <?php else: ?>
          <button type="button" class="btn btn-ghost" onclick="confirmBulk('archive')" title="Archive"><i class="fa-solid fa-box-archive"></i></button>
          <button type="button" class="btn btn-ghost" onclick="confirmBulk('unarchive')" title="Unarchive"><i class="fa-solid fa-rotate-left"></i></button>
          <button type="button" class="btn btn-ghost" onclick="confirmBulk('delete')" title="Delete"><i class="fa-solid fa-trash"></i></button>
        <?php endif; ?>
      </div>

    </div>
  </main>

  <!-- View Modal -->
  <div class="mu-modal" id="viewModal" aria-hidden="true" role="dialog" aria-label="User details">
    <div class="mu-dialog">
      <button class="mu-close" data-close aria-label="Close">&times;</button>
      <div class="mu-body">
        <span class="archived-chip" id="vmArchivedChip"><i class="fa-solid fa-box-archive me-1"></i> Archived</span>
        <div class="d-flex align-center gap-2 mb-2">
          <img id="vmAvatar" src="" alt="" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--brand)">
          <div>
            <div class="h5 fw-black" id="vmName" style="font-weight:900; font-size:1.1rem;">—</div>
            <div class="small">ID: <span id="vmId">—</span></div>
          </div>
        </div>
        <div class="d-flex wrap gap-2 small" style="row-gap:.5rem;">
          <div style="flex:1 1 100%"><i class="fa-regular fa-envelope me-1"></i> <a id="vmEmail" href="#" class="text-reset" style="word-break:break-all;">—</a> <button type="button" class="btn btn-ghost btn-sm ms-1" id="vmCopyEmail" title="Copy email"><i class="fa-regular fa-copy"></i></button></div>
          <div style="flex:1 1 45%"><i class="fa-solid fa-user-shield me-1"></i> <span id="vmRole">—</span></div>
          <div style="flex:1 1 45%"><i class="fa-regular fa-calendar me-1"></i> <span id="vmCreated">—</span></div>
          <div style="flex:1 1 45%"><i class="fa-solid fa-circle-info me-1"></i> <span id="vmStatus">—</span></div>
          <div style="flex:1 1 45%"><i class="fa-solid fa-key me-1"></i> <span id="vmLocker">—</span></div>
        </div>
      </div>
      <div class="mu-footer">
        <button type="button" class="btn btn-primary" data-close>Close</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="../../assets/js/manage_users.js"></script>
</body>
</html>
