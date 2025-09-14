<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT email, first_name, last_name, profile_image FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die('User not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['error'], $_SESSION['message']);

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

    if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        $imagePath = 'default.jpg';
    }

    if (!empty($_FILES['profile_image']['name']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $file    = $_FILES['profile_image'];
        $maxSize = 2 * 1024 * 1024;

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

$csrf = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf;

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
  <link rel="stylesheet" href="../assets/css/profile.css">


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

          <div class="panel" aria-labelledby="photo-heading">
            <h2 id="photo-heading">Profile photo</h2>

            <div id="uploader" class="uploader" tabindex="0" aria-label="Profile photo uploader (drag and drop or choose a file)">
              <div class="copy">
                <div><strong>Drag &amp; drop</strong> your photo here</div>
                <div class="helper">or use the buttons below — JPG, PNG or GIF. Max 2MB.</div>
              </div>

              <img id="thumb" class="thumb" src="../assets/uploads/<?= htmlspecialchars($user['profile_image'] ?? 'default.jpg') ?>" alt="Current photo preview" onerror="this.src='../assets/uploads/default.jpg'"/>

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
    window.SERVER_MESSAGE = <?= json_encode($_SESSION['message'] ?? null) ?>;
    window.SERVER_ERROR   = <?= json_encode($_SESSION['error'] ?? null) ?>;
    <?php unset($_SESSION['message'], $_SESSION['error']); ?>
  </script>
  <script src="../assets/js/profile.js"></script>
</body>
</html>
