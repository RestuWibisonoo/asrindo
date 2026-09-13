<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['admin_nama'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'ADMIN';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - ASRINDO</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="admin-page">

<header class="admin-header">
    <div class="admin-header-inner">

        <a href="dashboard.php" class="admin-brand">
            <span class="brand-mark">A</span>
            <span class="brand-name">ASRINDO</span>
        </a>

        <button type="button" class="mobile-menu-button" id="mobileMenuButton"
                aria-label="Buka menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <nav class="admin-nav" id="adminNav" aria-label="Navigasi utama">
            <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                Dashboard
            </a>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">
                    Transaksi <span>⌄</span>
                </button>
                <div class="nav-dropdown-menu">
                    <a href="penjualan/">Penjualan</a>
                    <a href="penjualan/">Pembelian</a>
                    <a href="penjualan/">Rental</a>

                </div>
            </div>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">
                    Produk <span>⌄</span>
                </button>
                <div class="nav-dropdown-menu">
                    <a href="alat_berat/">Alat Berat</a>
                    <a href="sparepart/">Sparepart</a>
                    <a href="sparepart/">Perawatan</a>

                </div>
            </div>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">
                    Rekanan <span>⌄</span>
                </button>
                <div class="nav-dropdown-menu">
                    <a href="supplier/">Supplier</a>
                    <a href="customer/">Customer</a>
                </div>
            </div>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">
                    Kas <span>⌄</span>
                </button>
                <div class="nav-dropdown-menu">
                    <a href="keuangan/">Transaksi Keuangan</a>
                    <a href="keuangan/kategori.php">Kategori Keuangan</a>
                </div>
            </div>

            <a href="laporan.php">Laporan</a>

            <div class="nav-dropdown">
                <button type="button" class="nav-dropdown-button">
                    Pengaturan <span>⌄</span>
                </button>
                <div class="nav-dropdown-menu nav-dropdown-right">
                    <a href="karyawan/">Karyawan</a>
                    <a href="admin/">Admin/User</a>
                </div>
            </div>
        </nav>

        <div class="admin-user">
            <div class="admin-user-info">
                <strong><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></small>
            </div>
            <a href="logout.php" class="logout-link">Logout</a>
        </div>

    </div>
</header>

<main class="admin-content">
