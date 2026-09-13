<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$adminBase = isset($adminBase) ? $adminBase : '../';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . $adminBase . 'admin/index.php');
    exit;
}

$pageTitle = isset($pageTitle) ? $pageTitle : 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = isset($_SESSION['admin_nama']) ? $_SESSION['admin_nama'] : 'Admin';
$adminRole = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'ADMIN';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - ASRINDO</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($adminBase, ENT_QUOTES, 'UTF-8'); ?>assets/css/admin.css">
</head>
<body class="admin-page">

<header class="admin-header">
    <div class="admin-header-inner">

        <a href="<?php echo $adminBase; ?>admin/dashboard.php" class="admin-brand">
            <span class="brand-mark">A</span>
            <span class="brand-name">ASRINDO</span>
        </a>

        <button type="button" class="mobile-menu-button" id="mobileMenuButton"
                aria-label="Buka menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <nav class="admin-nav" id="adminNav" aria-label="Navigasi utama">

            <a href="<?php echo $adminBase; ?>admin/dashboard.php">Dashboard</a>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">Transaksi <span>⌄</span></button>
                <div class="nav-dropdown-menu">
                    <a href="<?php echo $adminBase; ?>admin/penjualan.php">Penjualan</a>
                    <a href="<?php echo $adminBase; ?>admin/pembelian_alat_berat.php">Pembelian Alat Berat</a>
                    <a href="<?php echo $adminBase; ?>admin/pembelian_sparepart.php">Pembelian Sparepart</a>
                    <a href="<?php echo $adminBase; ?>admin/pembelian_restorasi.php">Pembelian Bahan Restorasi</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">Produk <span>⌄</span></button>
                <div class="nav-dropdown-menu">
                    <a href="<?php echo $adminBase; ?>admin/alat_berat.php">Alat Berat</a>
                    <a href="<?php echo $adminBase; ?>admin/sparepart.php">Sparepart</a>
                    <a href="<?php echo $adminBase; ?>admin/restorasi.php">Bahan Restorasi</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">Rekanan <span>⌄</span></button>
                <div class="nav-dropdown-menu">
                    <a href="<?php echo $adminBase; ?>admin/supplier.php">Supplier</a>
                    <a href="<?php echo $adminBase; ?>admin/customer.php">Customer</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">Kas <span>⌄</span></button>
                <div class="nav-dropdown-menu">
                    <a href="<?php echo $adminBase; ?>admin/pemasukan.php">Pemasukan</a>
                    <a href="<?php echo $adminBase; ?>admin/pengeluaran.php">Pengeluaran</a>
                    <a href="<?php echo $adminBase; ?>admin/kategori_keuangan.php">Kategori Keuangan</a>
                </div>
            </div>

            <a href="<?php echo $adminBase; ?>admin/laporan.php">Laporan</a>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">Pengaturan <span>⌄</span></button>
                <div class="nav-dropdown-menu nav-dropdown-right">
                    <a href="<?php echo $adminBase; ?>admin/karyawan.php">Karyawan</a>
                    <a href="<?php echo $adminBase; ?>admin/admin.php">Admin/User</a>
                </div>
            </div>

        </nav>

        <div class="admin-user">
            <div class="admin-user-info">
                <strong><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <small><?php echo htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
            <a href="<?php echo $adminBase; ?>admin/logout.php" class="logout-link">Logout</a>
        </div>

    </div>
</header>

<main class="admin-content">
