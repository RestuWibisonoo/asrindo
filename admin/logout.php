<?php
declare(strict_types=1);

session_start();

// Hapus seluruh data session admin
$_SESSION = [];

// Hapus cookie session jika digunakan
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Hancurkan session
session_destroy();

// Kembali ke halaman login admin
header('Location: index.php');
exit;
