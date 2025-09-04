<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT email, first_name, last_name, profile_image FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die('User not found.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['error'], $_SESSION['message']); // clear old flashes

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $imagePath = $user['profile_image'] ?: 'default.jpg';

    if ($firstName === '' || $lastName === '') {
        $_SESSION['error'] = 'First and last name cannot be empty.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Reset to default avatar
    if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        $imagePath = 'default.jpg';
    }

    // Handle image upload
    if (!empty($_FILES['profile_image']['name']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $file    = $_FILES['profile_image'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (($file['size'] ?? 0) > $maxSize) {
            $_SESSION['error'] = 'Image too large. Max size is 2MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif'
            ];

            if (!isset($allowed[$mime])) {
                $_SESSION['error'] = 'Unsupported image format. Allowed: JPG, PNG, GIF.';
            } elseif (!@getimagesize($file['tmp_name'])) {
                $_SESSION['error'] = 'Uploaded file is not a valid image.';
            } else {
                try {
                    $ext       = $allowed[$mime];
                    $filename  = bin2hex(random_bytes(16)) . ".{$ext}";
                    $uploadDir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $target = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        @chmod($target, 0644);
                        $imagePath = $filename;
                    } else {
                        $_SESSION['error'] = 'File upload failed.';
                    }
                } catch (Throwable $e) {
                    $_SESSION['error'] = 'Unexpected error while handling the image.';
                }
            }
        }
    }

    if (!isset($_SESSION['error'])) {
        $update = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, profile_image = ? WHERE id = ?");
        if ($update->execute([$firstName, $lastName, $imagePath, $userId])) {
            $_SESSION['message']       = 'Profile updated successfully.';
            $_SESSION['user_name']     = $firstName . ' ' . $lastName;
            $_SESSION['profile_image'] = $imagePath;
        } else {
            $_SESSION['error'] = 'Failed to update profile.';
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Generate CSRF token
$csrf = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf;

// Set back URL based on role
$backUrl = (($_SESSION['role'] ?? 'user') === 'admin') ? '../public/admin/dashboard.php' : '../public/user/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Profile</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root{
      --page-bg:#f6f8fc;
      --card-bg:#fff;
      --text:#0f172a;
      --muted:#64748b;
      --border:#e5e7eb;
      --ring:#3b82f6;
      --primary:#3b82f6;
      --primary-600:#2563eb;
      --danger:#ef4444;
      --danger-600:#dc2626;
      --radius:16px;
      --shadow:0 10px 30px rgba(2,6,23,.06);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;background:var(--page-bg);color:var(--text);
      font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Apple Color Emoji","Segoe UI Emoji";
      display:grid;place-items:start center;padding:clamp(16px,1.8vw,32px);
    }
    .shell{width:100%;max-width:980px}
    .header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .breadcrumbs{font-size:14px;color:var(--muted)}
    .breadcrumbs a{color:inherit;text-decoration:none}
    .breadcrumbs a:hover{color:var(--text);text-decoration:underline}

    .card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
    .card-head{
      display:grid;grid-template-columns:140px 1fr;gap:clamp(16px,2.2vw,24px);
      padding:clamp(16px,2.2vw,24px);border-bottom:1px solid var(--border);
    }
    .avatar-wrap{display:grid;place-items:center}
    .avatar{width:clamp(88px,10vw,120px);aspect-ratio:1/1;border-radius:999px;object-fit:cover;border:2px solid #fff;box-shadow:0 4px 16px rgba(2,6,23,.12)}
    .head-copy h1{margin:0 0 6px;font-size:clamp(18px,2.4vw,22px);font-weight:700}
    .head-copy p{margin:0;color:var(--muted);font-size:14px}

    .card-body{display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(16px,2.4vw,24px);padding:clamp(16px,2.4vw,24px)}
    .panel{background:#fff;border:1px solid var(--border);border-radius:12px;padding:clamp(14px,2vw,16px)}
    .panel h2{margin:0 0 12px;font-size:16px;font-weight:700}

    .field{margin-bottom:14px}
    .field label{display:block;font-size:13px;color:var(--muted);margin-bottom:6px}
    .input{width:100%;padding:12px 14px;border:1px solid var(--border);background:#fff;color:var(--text);border-radius:10px;outline:none;transition:box-shadow .2s,border-color .2s}
    .input:focus{border-color:var(--ring);box-shadow:0 0 0 4px rgba(59,130,246,.15)}
    .input[readonly]{background:#f9fafb;color:#6b7280;cursor:not-allowed}

    /* Uploader: vertical layout (text on top, preview, mid-size buttons under text) */
    .uploader{
      position:relative;border:1px dashed #cbd5e1;background:#f9fafb;border-radius:12px;
      padding:18px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:12px;
      transition:background .2s,border-color .2s;
    }
    .uploader.dragover{background:#eff6ff;border-color:var(--ring)}
    .uploader .copy{font-size:13px;color:var(--muted)}
    .uploader .copy strong{color:var(--text)}
    .thumb{width:72px;height:72px;border-radius:999px;object-fit:cover;border:1px solid var(--border);background:#fff}
    .actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:center}

    /* Buttons: mid-size */
    .btn{
      appearance:none;border:1px solid var(--border);cursor:pointer;
      padding:9px 16px;border-radius:10px;font-weight:600;font-size:14px;
      background:#ffffff;color:var(--text);
      text-decoration:none; /* remove underline on anchors styled as buttons */
      display:inline-flex;align-items:center;justify-content:center;
      transition:transform .05s ease, background .2s, border-color .2s;
      -webkit-tap-highlight-color: transparent;
    }
    .btn:hover{background:#f3f4f6;text-decoration:none}
    .btn:active{transform:translateY(1px)}
    .btn[disabled]{opacity:.7;cursor:not-allowed}
    .btn-primary{background:var(--primary);color:#fff;border-color:transparent}
    .btn-primary:hover{background:var(--primary-600)}
    .btn-danger{background:var(--danger);color:#fff;border-color:transparent}
    .btn-danger:hover{background:var(--danger-600)}
    .btn-ghost{background:#fff;border-color:var(--border)}

    .helper{font-size:12px;color:var(--muted)}

    .card-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 24px;border-top:1px solid var(--border);background:#fff}

    /* Responsive tweaks */
    @media (max-width:920px){
      .card-body{grid-template-columns:1fr}
      .card-head{grid-template-columns:1fr;text-align:center}
    }
    @media (max-width:520px){
      .card-foot{flex-direction:column;align-items:stretch}
      .actions-right,.btn-primary{width:100%}
      .actions{width:100%}
      .actions .btn{width:100%} /* full width on small screens only */
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <div class="breadcrumbs">
        <a href="<?= htmlspecialchars($backUrl) ?>">Dashboard</a> / <span>Edit Profile</span>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div class="avatar-wrap">
          <img id="profileImagePreview" class="avatar" src="../assets/uploads/<?= htmlspecialchars($user['profile_image'] ?? 'default.jpg') ?>" alt="Profile image" onerror="this.src='../assets/uploads/default.jpg'"/>
        </div>
        <div class="head-copy">
          <h1>Edit your profile</h1>
          <p>Update your name and profile image. Your email stays the same.</p>
        </div>
      </div>

      <form method="POST" enctype="multipart/form-data" id="profileForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="remove_image" id="removeImageInput" value="0">

        <div class="card-body">
          <!-- Left: Account details -->
          <div class="panel" aria-labelledby="acc-details-heading">
            <h2 id="acc-details-heading">Account details</h2>

            <div class="field">
              <label for="first_name">First name</label>
              <input id="first_name" class="input" type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required />
            </div>

            <div class="field">
              <label for="last_name">Last name</label>
              <input id="last_name" class="input" type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required />
            </div>

            <div class="field">
              <label for="email">Email</label>
              <input id="email" class="input" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" readonly />
            </div>
          </div>

          <!-- Right: Photo uploader -->
          <div class="panel" aria-labelledby="photo-heading">
            <h2 id="photo-heading">Profile photo</h2>

            <div id="uploader" class="uploader" tabindex="0" aria-label="Profile photo uploader (drag and drop or choose a file)">
              <!-- Text on top -->
              <div class="copy">
                <div><strong>Drag &amp; drop</strong> your photo here</div>
                <div class="helper">or use the buttons below — JPG, PNG or GIF. Max 2MB.</div>
              </div>

              <!-- Preview -->
              <img id="thumb" class="thumb" src="../assets/uploads/<?= htmlspecialchars($user['profile_image'] ?? 'default.jpg') ?>" alt="Current photo preview" onerror="this.src='../assets/uploads/default.jpg'"/>

              <!-- Mid-size buttons -->
              <div class="actions">
                <label class="btn btn-primary" for="fileInput">Choose file</label>
                <input id="fileInput" type="file" name="profile_image" accept="image/*" hidden />
                <button type="button" id="btnResetImage" class="btn btn-danger">Reset to default</button>
              </div>
            </div>
          </div>
        </div>

        <div class="card-foot">
          <a class="btn btn-ghost" href="<?= htmlspecialchars($backUrl) ?>">← Back to Dashboard</a>
          <div class="actions-right">
            <button id="saveBtn" type="submit" class="btn btn-primary">💾 Save changes</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script>
    // SweetAlert2 feedback (server-side messages)
    <?php if(!empty($_SESSION['message'])): ?>
      Swal.fire({ icon: 'success', title: 'Success', text: '<?= $_SESSION['message']; ?>' });
      <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if(!empty($_SESSION['error'])): ?>
      Swal.fire({ icon: 'error', title: 'Error', text: '<?= $_SESSION['error']; ?>' });
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    const fileInput   = document.getElementById('fileInput');
    const uploader    = document.getElementById('uploader');
    const thumb       = document.getElementById('thumb');
    const avatar      = document.getElementById('profileImagePreview');
    const resetBtn    = document.getElementById('btnResetImage');
    const removeInput = document.getElementById('removeImageInput');
    const chooseLabel = document.querySelector('label[for=fileInput]');
    const form        = document.getElementById('profileForm');
    const saveBtn     = document.getElementById('saveBtn');

    // Allow re-selecting the same file
    fileInput.addEventListener('click', () => { fileInput.value = ''; });

    function updatePreviewFromFile(file) {
      if (!file) return;
      const allowed = ['image/jpeg', 'image/png', 'image/gif'];
      if (!allowed.includes(file.type)) {
        Swal.fire({ icon: 'error', title: 'Unsupported format', text: 'Please choose a JPG, PNG, or GIF image.' });
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        Swal.fire({ icon: 'error', title: 'Too large', text: 'Max file size is 2MB.' });
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        thumb.src = reader.result;
        avatar.src = reader.result;
        removeInput.value = '0';
      };
      reader.readAsDataURL(file);
    }

    // Keep label click from bubbling to uploader
    if (chooseLabel) chooseLabel.addEventListener('click', (e) => { e.stopPropagation(); });

    fileInput.addEventListener('change', (e) => updatePreviewFromFile(e.target.files[0]));

    // Drag & drop support
    ['dragenter','dragover'].forEach(evt => {
      uploader.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); uploader.classList.add('dragover'); });
    });
    ['dragleave','drop'].forEach(evt => {
      uploader.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); uploader.classList.remove('dragover'); });
    });
    uploader.addEventListener('drop', (e) => {
      const dt = e.dataTransfer; const file = dt.files && dt.files[0];
      if (file) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput.files = dataTransfer.files;
        updatePreviewFromFile(file);
      }
    });

    // Click empty space to open picker
    uploader.addEventListener('click', (e) => {
      const isAction = e.target.closest('.actions');
      const isThumb  = e.target.id === 'thumb' || e.target.closest('#thumb');
      if (!isAction && !isThumb) fileInput.click();
    });

    uploader.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });

    // Reset to default (immediate save)
    resetBtn.addEventListener('click', (ev) => {
      ev.stopPropagation();
      Swal.fire({
        title: 'Reset photo to default?',
        text: 'This will immediately save your profile with the default avatar.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, reset & save',
      }).then((result) => {
        if (result.isConfirmed) {
          const defaultSrc = '../assets/uploads/default.jpg';
          thumb.src = defaultSrc;
          avatar.src = defaultSrc;
          removeInput.value = '1';
          fileInput.value = '';
          saveBtn.disabled = true; // avoid double submit
          form.submit();
        }
      });
    });
  </script>
</body>
</html>
