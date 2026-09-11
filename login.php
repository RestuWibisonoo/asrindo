<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (current_admin()) {
  redirect('/admin/dashboard.php');
}

$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($username === '' || $password === '') {
    $error = 'Username dan password wajib diisi.';
  } else {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM admin WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || $admin['status'] !== 'AKTIF' || !password_verify($password, $admin['password'])) {
      $error = 'Username atau password salah, atau akun tidak aktif.';
    } else {
      $_SESSION['admin'] = [
        'id' => $admin['id'],
        'username' => $admin['username'],
        'nama' => $admin['nama'],
        'role' => $admin['role'],
      ];
      $pdo->prepare('UPDATE admin SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);
      redirect('/admin/dashboard.php');
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk Admin — Asrindo Global Mandiri</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body>
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="brand">
        <img class="brand-logo" src="<?= BASE_URL ?>/assets/img/logo.png" alt="Asrindo Global"
          style="width:56px;height:56px;max-width:56px;object-fit:contain;flex:none;">
        <span class="brand-text">Asrindo Global Mandiri<small>Panel Admin</small></span>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" autofocus
            required>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-amber btn-block">Masuk</button>
      </form>
      <a href="<?= BASE_URL ?>/index.php" class="auth-back">&larr; Kembali ke halaman utama</a>
    </div>
  </div>
</body>

</html>