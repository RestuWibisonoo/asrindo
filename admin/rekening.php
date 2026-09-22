<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Rekening';
require __DIR__ . '/../includes/header.php';
?>

<div class="admin-page-header">
    <h1>Rekening</h1>
</div>

<div class="admin-card">
    <p>Halaman ini masih kosong.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
