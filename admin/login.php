<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/koneksi.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                'SELECT id,karyawan_id,username,password,nama,role,status
                 FROM admin WHERE username=:username LIMIT 1'
            );
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch();

            if ($admin && $admin['status'] === 'AKTIF' &&
                password_verify($password, $admin['password'])) {

                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_karyawan_id'] = $admin['karyawan_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_nama'] = $admin['nama'];
                $_SESSION['admin_role'] = $admin['role'];

                $update = $pdo->prepare('UPDATE admin SET last_login=NOW() WHERE id=:id');
                $update->execute(['id' => $admin['id']]);

                header('Location: dashboard.php');
                exit;
            }
            $error = 'Username atau password salah.';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Terjadi kesalahan saat mengakses database.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Login Admin - ASRINDO</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login-page">
<main class="login-wrapper">
<section class="login-card">
    <div class="login-brand">
        <img src="../assets/images/logo.png" alt="ASRINDO" class="login-logo"
             onerror="this.style.display='none';document.querySelector('.logo-fallback').style.display='flex';">
        <div class="logo-fallback">ASRINDO</div>
    </div>

    <div class="login-header">
        <h1>Admin Login</h1>
        <p>Masuk untuk mengelola aplikasi ASRINDO</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="login-form" autocomplete="on">
        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" name="username" type="text"
                   value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Username" autocomplete="username" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-field">
                <input id="password" name="password" type="password"
                       placeholder="Enter your password" autocomplete="current-password" required>
                <button type="button" id="togglePassword" class="password-toggle">Lihat</button>
            </div>
        </div>

        <button type="submit" class="btn-login">Log In</button>
    </form>
</section>
</main>
<script>
const p=document.getElementById('password');
const b=document.getElementById('togglePassword');
b.addEventListener('click',()=>{const show=p.type==='password';p.type=show?'text':'password';b.textContent=show?'Sembunyikan':'Lihat';});
</script>
</body>
</html>
