<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();
$pageTitle = 'Liabilitas (Utang Usaha)';

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) ($value ?? 0), 0, ',', '.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_utang') {
        $nomor_referensi = trim($_POST['nomor_referensi'] ?? '');
        $kreditur = trim($_POST['kreditur'] ?? '');
        
        $kategoriInput = trim((string)($_POST['kategori'] ?? ''));
        $kategori = $kategoriInput !== '' ? strtoupper(str_replace(' ', '_', $kategoriInput)) : 'LAINNYA';
        
        $rekening_id = filter_var($_POST['rekening_id'] ?? null, FILTER_VALIDATE_INT);
        $tanggal = trim($_POST['tanggal'] ?? '');
        $jatuh_tempo = trim($_POST['jatuh_tempo'] ?? '');
        $total = (float) str_replace(['Rp', '.', ' '], '', $_POST['total'] ?? '0');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($nomor_referensi === '' || $kreditur === '' || $tanggal === '' || $total <= 0) {
            $error = 'Silakan isi Nomor Referensi, Kreditur, Tanggal, dan Total Utang yang valid.';
        } else {
            try {
                $pdo->beginTransaction();
                
                $sql = "INSERT INTO utang_lainnya (nomor_referensi, kreditur, kategori, tanggal, jatuh_tempo, total, keterangan, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'BELUM_LUNAS')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nomor_referensi,
                    $kreditur,
                    $kategori,
                    $tanggal,
                    $jatuh_tempo !== '' ? $jatuh_tempo : null,
                    $total,
                    $keterangan
                ]);
                
                if ($rekening_id) {
                    $prefix = 'PM-' . date('Ymd', strtotime($tanggal)) . '-';
                    $stmt = $pdo->prepare("SELECT nomor_pemasukan FROM pemasukan WHERE nomor_pemasukan LIKE ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$prefix . '%']);
                    $last = (string) $stmt->fetchColumn();
                    $next = 1;
                    if ($last !== '') {
                        $suffix = substr($last, -4);
                        if (ctype_digit($suffix)) $next = (int) $suffix + 1;
                    }
                    $nomorPemasukan = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO pemasukan (nomor_pemasukan, tanggal, sumber, rekening_id, nominal, metode_pembayaran, referensi, keterangan)
                        VALUES (?, ?, 'NON_PENJUALAN', ?, ?, 'Transfer', ?, ?)
                    ");
                    $stmt->execute([$nomorPemasukan, $tanggal, $rekening_id, $total, $nomor_referensi, 'Penerimaan utang manual dari ' . $kreditur . ($keterangan ? ' - ' . $keterangan : '')]);
                }
                
                $pdo->commit();
                $success = 'Utang manual berhasil ditambahkan.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal menyimpan data utang: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'pay_utang') {
        $pay_id = filter_var($_POST['utang_id'] ?? null, FILTER_VALIDATE_INT);
        $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
        $nominal = (float) str_replace(['Rp', '.', ' '], '', $_POST['nominal'] ?? '0');
        $metode = trim($_POST['metode_pembayaran'] ?? '');
        $referensi = trim($_POST['referensi'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $rekeningId = filter_var($_POST['rekening_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

        if (!$pay_id || $nominal <= 0) {
            $error = 'Data pembayaran tidak valid.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT total, status FROM utang_lainnya WHERE id = ? FOR UPDATE");
                $stmt->execute([$pay_id]);
                $utang = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$utang) throw new RuntimeException('Data utang tidak ditemukan.');
                if ($utang['status'] === 'BATAL' || $utang['status'] === 'LUNAS') {
                    throw new RuntimeException('Utang sudah lunas atau dibatalkan.');
                }

                $stmt = $pdo->prepare("SELECT COALESCE(SUM(nominal), 0) FROM pengeluaran WHERE utang_lainnya_id = ?");
                $stmt->execute([$pay_id]);
                $terbayarLama = (float) $stmt->fetchColumn();
                
                $sisaLama = (float)$utang['total'] - $terbayarLama;

                if ($nominal > $sisaLama + 0.0001) {
                    throw new RuntimeException('Nominal pembayaran melebihi sisa utang.');
                }

                $prefix = 'PK-' . date('Ymd', strtotime($tanggal)) . '-';
                $stmt = $pdo->prepare("SELECT nomor_pengeluaran FROM pengeluaran WHERE nomor_pengeluaran LIKE ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$prefix . '%']);
                $last = (string) $stmt->fetchColumn();
                $next = 1;
                if ($last !== '') {
                    $suffix = substr($last, -4);
                    if (ctype_digit($suffix)) $next = (int) $suffix + 1;
                }
                $nomorPengeluaran = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO pengeluaran (nomor_pengeluaran, tanggal, sumber, utang_lainnya_id, jenis_pengeluaran, nominal, metode_pembayaran, referensi, keterangan, rekening_id)
                    VALUES (?, ?, 'NON_PEMBELIAN', ?, 'Angsuran', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nomorPengeluaran, $tanggal, $pay_id, $nominal, $metode, $referensi, $keterangan, $rekeningId]);

                $sisaBaru = $sisaLama - $nominal;
                if ($sisaBaru <= 0.0001) {
                    $stmt = $pdo->prepare("UPDATE utang_lainnya SET status = 'LUNAS' WHERE id = ?");
                    $stmt->execute([$pay_id]);
                }

                $pdo->commit();
                $success = 'Pembayaran berhasil dicatat.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$today = date('Y-m-d');

/* -------------------------------------------------------------------------
 * DATA QUERY 1: TOTAL JATUH TEMPO
 * Mengambil nominal jadwal pembayaran yang sudah lewat atau sama dengan hari ini.
 * ---------------------------------------------------------------------- */
$sqlJatuhTempo = "SELECT SUM(nominal) FROM (
                      SELECT nominal FROM pembelian_pembayaran WHERE status = 'BELUM_BAYAR' AND tanggal_jatuh_tempo <= ?
                      UNION ALL
                      SELECT (total - COALESCE((SELECT SUM(nominal) FROM pengeluaran WHERE utang_lainnya_id = ul.id), 0)) as nominal
                      FROM utang_lainnya ul
                      WHERE ul.status = 'BELUM_LUNAS' AND ul.jatuh_tempo <= ?
                  ) as gabungan_jt";
$stmtJT = $pdo->prepare($sqlJatuhTempo);
$stmtJT->execute([$today, $today]);
$totalJatuhTempo = (float) $stmtJT->fetchColumn();

/* -------------------------------------------------------------------------
 * DATA QUERY 2: DAFTAR SELURUH PEMBELIAN YANG BELUM LUNAS
 * (Sisa = Total Pembelian - Total Terbayar > 0)
 * ---------------------------------------------------------------------- */
$sql = "SELECT * FROM (
            -- 1. UTANG ALAT BERAT
            SELECT 
                'Alat Berat' AS jenis,
                pab.id,
                pab.nomor_pembelian AS nomor,
                pab.tanggal,
                s.nama AS nama_supplier,
                pab.total,
                COALESCE((SELECT SUM(nominal) FROM pembelian_pembayaran WHERE pembelian_alat_berat_id = pab.id AND status = 'PAID'), 0) AS terbayar,
                (SELECT MIN(tanggal_jatuh_tempo) FROM pembelian_pembayaran WHERE pembelian_alat_berat_id = pab.id AND status = 'BELUM_BAYAR') AS jatuh_tempo_terdekat
            FROM pembelian_alat_berat pab
            LEFT JOIN supplier s ON pab.supplier_id = s.id
            WHERE pab.status != 'BATAL'

            UNION ALL

            -- 2. UTANG SPAREPART
            SELECT 
                'Sparepart' AS jenis,
                psp.id,
                psp.nomor_pembelian AS nomor,
                psp.tanggal,
                s.nama AS nama_supplier,
                psp.total,
                COALESCE((SELECT SUM(nominal) FROM pengeluaran WHERE pembelian_sparepart_id = psp.id), 0) AS terbayar,
                (SELECT MIN(tanggal_jatuh_tempo) FROM pembelian_pembayaran WHERE pembelian_sparepart_id = psp.id AND status = 'BELUM_BAYAR') AS jatuh_tempo_terdekat
            FROM pembelian_sparepart psp
            LEFT JOIN supplier s ON psp.supplier_id = s.id
            WHERE psp.status != 'BATAL'

            UNION ALL

            -- 3. UTANG RESTORASI
            SELECT 
                'Restorasi' AS jenis,
                pr.id,
                pr.nomor_pembelian AS nomor,
                pr.tanggal,
                s.nama AS nama_supplier,
                pr.total,
                COALESCE((SELECT SUM(nominal) FROM pengeluaran WHERE pembelian_restorasi_id = pr.id), 0) AS terbayar,
                NULL AS jatuh_tempo_terdekat
            FROM pembelian_restorasi pr
            LEFT JOIN supplier s ON pr.supplier_id = s.id
            WHERE pr.status != 'BATAL'

            UNION ALL

            -- 4. UTANG LAINNYA (MANUAL)
            SELECT 
                COALESCE(NULLIF(ul.kategori, ''), 'Utang Lainnya') AS jenis,
                ul.id,
                ul.nomor_referensi AS nomor,
                ul.tanggal,
                ul.kreditur AS nama_supplier,
                ul.total,
                COALESCE((SELECT SUM(nominal) FROM pengeluaran WHERE utang_lainnya_id = ul.id), 0) AS terbayar,
                ul.jatuh_tempo AS jatuh_tempo_terdekat
            FROM utang_lainnya ul
            WHERE ul.status != 'BATAL'
        ) AS gabungan
        WHERE (total - terbayar) > 0
        ORDER BY COALESCE(jatuh_tempo_terdekat, '9999-12-31') ASC, tanggal ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$liabilitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total sisa utang dari keseluruhan pembelian
$totalUtang = 0.0;
foreach ($liabilitas as $row) {
    $sisa = (float) $row['total'] - (float) $row['terbayar'];
    $totalUtang += $sisa;
}

// ---------------------------------------------------------
// DATA UNTUK MODAL TAMBAH UTANG
// ---------------------------------------------------------
// 1. Rekening Aktif
$stmtRekening = $pdo->query("SELECT id, nama_rekening, nama_bank FROM rekening WHERE status = 'Aktif' ORDER BY nama_rekening ASC");
$rekeningList = $stmtRekening->fetchAll(PDO::FETCH_ASSOC);

// 2. Kategori Utang
$stmtCat = $pdo->query("SELECT DISTINCT kategori FROM utang_lainnya WHERE kategori IS NOT NULL AND kategori != ''");
$existingCats = $stmtCat->fetchAll(PDO::FETCH_COLUMN);
$allCats = [
    'PINJAMAN_BANK' => 'Pinjaman Bank',
    'PINJAMAN_DIREKSI' => 'Pinjaman Direksi',
    'PINJAMAN_PIHAK_KETIGA' => 'Pinjaman Pihak Ketiga',
    'LAINNYA' => 'Lainnya'
];
foreach ($existingCats as $c) {
    if (!isset($allCats[$c]) && trim($c) !== '') {
        $allCats[$c] = ucwords(strtolower(str_replace('_', ' ', $c)));
    }
}

require __DIR__ . '/../includes/header.php';
?>

<style>
    /* Mengadopsi style grid dashboard & laporan */
    .dashboard-finance-grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
        margin-bottom:20px;
    }
    
    .dashboard-finance-card{
        position:relative;
        background:#fff;
        border:1px solid #dce3eb;
        border-radius:7px;
        padding:15px 16px;
        box-shadow:0 1px 2px rgba(23,43,77,.04);
    }
    
    .dashboard-finance-card::before{
        content:'';
        position:absolute;
        left:0;
        top:10px;
        bottom:10px;
        width:3px;
        border-radius:0 3px 3px 0;
        background:#0d6efd;
    }
    
    .dashboard-finance-card.net::before{background:#0d6efd}
    .dashboard-finance-card.expense::before{background:#dc3545}
    
    .dashboard-finance-card strong{
        display:block;
        padding-left:4px;
        color:#172b4d;
        font-size:18px;
        margin-bottom:5px;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    
    .dashboard-finance-card span{
        display:block;
        padding-left:4px;
        color:#64748b;
        font-size:11px;
    }
    
    .dashboard-finance-card.net strong{color:#0d6efd}
    .dashboard-finance-card.expense strong{color:#dc3545}
    
    /* Tombol utama */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #0d6efd;
        color: #fff;
        padding: 9px 15px;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s;
        text-decoration: none;
    }
    
    .btn-primary:hover {
        background: #0b5ed7;
    }
    
    /* Table utilities */
    .dashboard-table .money { text-align: right !important; font-weight: 600; }
    .dashboard-table .center { text-align: center !important; }
    .dashboard-table .muted { color: #7a8798; font-size: 11px; margin-top: 3px; display: block; }
    
    .badge { display:inline-flex; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700; }
    .badge-ab { background:#e7f0fd; color:#0c4a6e; }
    .badge-sp { background:#fef3c7; color:#92400e; }
    .badge-rs { background:#f3e8ff; color:#6b21a8; }
    .badge-ul { background:#ffedd5; color:#c2410c; }
    
    .badge-neutral { background:#f1f5f9; color:#475569; border: 1px solid #cbd5e1; }
    .text-danger { color:#b91c1c; font-weight:700; }
    .empty-state { padding:40px 20px !important; text-align:center !important; color:#64748b; font-size:14px; }
    
    /* Modal styles (diambil dari detail pembelian alat berat) */
    .liability-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        box-sizing: border-box;
        background: rgba(8, 19, 37, .58);
    }
    
    .liability-modal.show {
        display: flex;
        touch-action: none;
    }
    
    .liability-modal-box {
        width: min(760px, 100%);
        max-height: calc(100vh - 36px);
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        border-radius: 6px;
        background: #fff;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
        display: block;
    }
    
    .liability-modal-large {
        width: min(1050px, 100%);
    }
    
    .liability-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 15px;
        padding: 16px 18px;
        border-bottom: 1px solid #dbe2ec;
    }
    
    .liability-modal-header h3 {
        margin: 0 0 4px;
        color: #0a2347;
        font-size: 18px;
    }
    
    .liability-modal-header p {
        margin: 0;
        color: #7c8ba1;
        font-size: 12px;
    }
    
    .liability-modal-close {
        width: 34px;
        height: 34px;
        border: 0;
        background: transparent;
        color: #7b899e;
        font-size: 26px;
        cursor: pointer;
        line-height: 1;
        flex-shrink: 0;
    }
    
    .liability-modal-body {
        padding: 20px 18px;
    }
    
    .liability-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 13px 18px;
        border-top: 1px solid #dbe2ec;
    }
    
    .liability-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    
    .liability-field {
        min-width: 0;
    }
    
    .liability-field-full {
        grid-column: 1 / -1;
    }
    
    .liability-field label {
        display: block;
        margin-bottom: 6px;
        color: #263d60;
        font-size: 12px;
    }
    
    .liability-field label span {
        color: #e53935;
    }
    
    .liability-field input,
    .liability-field select,
    .liability-field textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #bdcadc;
        border-radius: 4px;
        background: #fff;
        color: #183457;
        padding: 8px 10px;
        outline: none;
        font: inherit;
        font-size: 13px;
    }
    
    .liability-field input,
    .liability-field select {
        height: 36px;
    }
    
    .liability-field textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .liability-field input:focus,
    .liability-field select:focus,
    .liability-field textarea:focus {
        border-color: #1473e6;
        box-shadow: 0 0 0 2px rgba(20, 115, 230, .08);
    }
    
    .form-help {
        display: block;
        margin-top: 5px;
        color: #73839a;
        font-size: 11px;
    }
    
    .payment-confirm-box {
        padding: 12px 14px;
        margin-bottom: 15px;
        border: 1px solid #dbe2ec;
        border-radius: 5px;
        background: #f8fafc;
    }
    
    .payment-confirm-box span {
        display: block;
        color: #718096;
        font-size: 10px;
    }
    
    .payment-confirm-box strong {
        display: block;
        margin-top: 3px;
        color: #0b2853;
        font-size: 18px;
    }
    
    .btn-secondary {
        border: 1px solid #bdcadc;
        background: #fff;
        color: #314b72;
        border-radius: 4px;
        padding: 8px 14px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-secondary:hover {
        background: #f1f4f9;
    }
</style>

<div>
    <section class="page-heading">
        <div>
            <h1>Liabilitas (Utang Usaha)</h1>
            <p>Rekapitulasi sisa utang pembelian dan hutang manual yang belum lunas.</p>
        </div>
        <div>
            <button class="btn-primary" onclick="openTambahModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Utang Manual
            </button>
        </div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success" style="background:#f0fdf4; color:#15803d; padding:12px 14px; border-radius:6px; margin-bottom:18px; border:1px solid #bbf7d0; font-size:14px;"><?php echo h($success); ?></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <section class="dashboard-finance-grid">
        <article class="dashboard-finance-card net">
            <strong><?php echo rupiah($totalUtang); ?></strong>
            <span>Total Sisa Utang Berjalan</span>
            <div class="muted" style="margin-top:6px; padding-left:4px;">Total keseluruhan sisa pembelian yang belum terbayarkan.</div>
        </article>
        <article class="dashboard-finance-card expense">
            <strong><?php echo rupiah($totalJatuhTempo); ?></strong>
            <span>Jatuh Tempo Hari Ini / Terlewat</span>
            <div class="muted" style="margin-top:6px; padding-left:4px;">Berdasarkan termin tagihan yang jadwalnya harus segera dilunasi.</div>
        </article>
    </section>

    <!-- Utang Pembelian -->
    <section class="dashboard-panel" style="margin-top:20px;">
        <div class="panel-heading">
            <h2>Daftar Pembelian Belum Lunas</h2>
        </div>
        <div class="table-responsive">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Nomor Pembelian / Ref</th>
                        <th>Supplier / Kreditur</th>
                        <th>Tgl Beli / Utang</th>
                        <th>Jatuh Tempo Terdekat</th>
                        <th class="money">Total Pembelian</th>
                        <th class="money">Sudah Terbayar</th>
                        <th class="money">Sisa Utang</th>
                        <th class="center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($liabilitas)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            Tidak ada utang pembelian yang belum dibayar saat ini.<br>Semua transaksi sudah berstatus lunas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($liabilitas as $utang): 
                        $sisa = (float)$utang['total'] - (float)$utang['terbayar'];
                        
                        // Menentukan badge kategori
                        $badgeClass = 'badge-ul';
                        if ($utang['jenis'] === 'Alat Berat') $badgeClass = 'badge-ab';
                        elseif ($utang['jenis'] === 'Sparepart') $badgeClass = 'badge-sp';
                        elseif ($utang['jenis'] === 'Restorasi') $badgeClass = 'badge-rs';
                        
                        $isUtangManual = !in_array($utang['jenis'], ['Alat Berat', 'Sparepart', 'Restorasi']);

                        // Menentukan status jatuh tempo
                        $jtDate = $utang['jatuh_tempo_terdekat'];
                        $isOverdue = false;
                        $isToday = false;
                        if ($jtDate) {
                            $isOverdue = $jtDate < $today;
                            $isToday = $jtDate === $today;
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo h($utang['nomor']); ?></strong>
                                <div style="margin-top:4px;"><span class="badge <?php echo $badgeClass; ?>"><?php echo h($utang['jenis']); ?></span></div>
                            </td>
                            <td>
                                <strong><?php echo h($utang['nama_supplier'] ?? 'Unknown Supplier'); ?></strong>
                            </td>
                            <td>
                                <?php echo h(date('d-m-Y', strtotime($utang['tanggal']))); ?>
                            </td>
                            <td>
                                <?php if (!$jtDate): ?>
                                    <span class="badge badge-neutral">Belum Dijadwalkan</span>
                                <?php else: ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="text-danger"><?php echo h(date('d-m-Y', strtotime($jtDate))); ?></span>
                                        <div class="muted text-danger">Terlambat</div>
                                    <?php elseif ($isToday): ?>
                                        <span style="color:#b45309; font-weight:700;"><?php echo h(date('d-m-Y', strtotime($jtDate))); ?></span>
                                        <div class="muted" style="color:#b45309;">Hari Ini</div>
                                    <?php else: ?>
                                        <span><?php echo h(date('d-m-Y', strtotime($jtDate))); ?></span>
                                        <div class="muted">Akan datang</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="money" style="color:#64748b;">
                                <?php echo rupiah($utang['total']); ?>
                            </td>
                            <td class="money" style="color:#15803d;">
                                <?php echo rupiah($utang['terbayar']); ?>
                            </td>
                            <td class="money" style="font-size:14px; color:#b91c1c;">
                                <?php echo rupiah($sisa); ?>
                            </td>
                            <td class="center">
                                <?php if ($isUtangManual): ?>
                                    <button onclick="openBayarModal(<?php echo $utang['id']; ?>, <?php echo $sisa; ?>)" style="color:#0d6efd; background:none; text-decoration:none; font-weight:600; font-size:12px; border:1px solid #0d6efd; padding:4px 8px; border-radius:4px; display:inline-block; cursor:pointer;">Bayar Utang</button>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

</div>

<!-- Modal Tambah Utang -->
<div class="liability-modal" id="modalTambahUtang" aria-hidden="true">
    <div class="liability-modal-box">
        <div class="liability-modal-header">
            <div>
                <h3>Tambah Utang Manual</h3>
                <p>Jadwalkan atau catat utang dari pihak lain (seperti pinjaman bank).</p>
            </div>
            <button type="button" class="liability-modal-close" data-close-modal="modalTambahUtang">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_utang">
            <div class="liability-modal-body">
                <div class="liability-form-grid">
                    <div class="liability-field liability-field-full">
                        <label>Nomor Referensi <span>*</span></label>
                        <input type="text" name="nomor_referensi" placeholder="Contoh: Nomor Kontrak / Pinjaman" required>
                    </div>
                    
                    <div class="liability-field">
                        <label>Kreditur <span>*</span></label>
                        <input type="text" name="kreditur" placeholder="Contoh: Bank BCA, dll" required>
                    </div>
                    <div class="liability-field">
                        <label>Kategori <span>*</span></label>
                        <select name="kategori" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($allCats as $val => $label): ?>
                                <option value="<?php echo h($val); ?>"><?php echo h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="liability-field">
                        <label>Tanggal Hutang <span>*</span></label>
                        <input type="date" name="tanggal" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="liability-field">
                        <label>Jatuh Tempo</label>
                        <input type="date" name="jatuh_tempo">
                    </div>
                    
                    <div class="liability-field liability-field-full">
                        <label>Total Hutang (Rp) <span>*</span></label>
                        <input type="text" name="total" required onkeyup="formatRupiah(this)">
                    </div>
                    
                    <div class="liability-field liability-field-full">
                        <label>Rekening Penerima Uang</label>
                        <select name="rekening_id">
                            <option value="">-- Tidak Dimasukkan ke Saldo Rekening --</option>
                            <?php foreach ($rekeningList as $rek): ?>
                                <option value="<?php echo $rek['id']; ?>"><?php echo h($rek['nama_rekening'] . ' - ' . $rek['nama_bank']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-help">Jika dipilih, dana utang akan otomatis dicatat sebagai pemasukan pada rekening ini.</span>
                    </div>
                    
                    <div class="liability-field liability-field-full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="liability-modal-footer">
                <button type="button" class="btn-secondary" data-close-modal="modalTambahUtang">Batal</button>
                <button type="submit" class="btn-primary">Tambah Utang</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Bayar Utang -->
<div class="liability-modal" id="modalBayarUtang" aria-hidden="true">
    <div class="liability-modal-box">
        <div class="liability-modal-header">
            <div>
                <h3>Bayar Utang</h3>
                <p id="paidSubtitle">Catat pembayaran utang ini.</p>
            </div>
            <button type="button" class="liability-modal-close" data-close-modal="modalBayarUtang">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="pay_utang">
            <input type="hidden" name="utang_id" id="pay_utang_id" value="">
            <div class="liability-modal-body">
                <div class="payment-confirm-box">
                    <span>Nominal sisa utang yang harus dibayar:</span>
                    <strong id="labelMaksimal"></strong>
                </div>
                
                <div class="liability-form-grid">
                    <div class="liability-field liability-field-full">
                        <label>Rekening Sumber Dana <span>*</span></label>
                        <select name="rekening_id" required>
                            <option value="">-- Pilih Rekening --</option>
                            <?php foreach ($rekeningList as $rek): ?>
                                <option value="<?php echo $rek['id']; ?>"><?php echo h($rek['nama_rekening'] . ' - ' . $rek['nama_bank']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-help">Dana akan dikurangi dari rekening ini.</span>
                    </div>
                    
                    <div class="liability-field">
                        <label>Tanggal Pembayaran <span>*</span></label>
                        <input type="date" name="tanggal" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="liability-field">
                        <label>Nominal Bayar (Rp) <span>*</span></label>
                        <input type="text" name="nominal" required onkeyup="formatRupiah(this)">
                    </div>
                    
                    <div class="liability-field">
                        <label>Metode Pembayaran <span>*</span></label>
                        <select name="metode_pembayaran" required>
                            <option value="">-- Pilih Metode --</option>
                            <option value="TRANSFER">TRANSFER</option>
                            <option value="TUNAI">TUNAI</option>
                            <option value="GIRO">GIRO</option>
                            <option value="LAINNYA">LAINNYA</option>
                        </select>
                    </div>
                    
                    <div class="liability-field">
                        <label>Nomor Referensi Transfer / Bukti</label>
                        <input type="text" name="referensi">
                    </div>
                    
                    <div class="liability-field liability-field-full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="liability-modal-footer">
                <button type="button" class="btn-secondary" data-close-modal="modalBayarUtang">Batal</button>
                <button type="submit" class="btn-primary">Simpan Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    var m = document.getElementById(id);
    if (m) {
        m.classList.add('show');
        m.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    var m = document.getElementById(id);
    if (m) {
        m.classList.remove('show');
        m.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.liability-modal.show')) {
            document.body.style.overflow = '';
        }
    }
}

document.querySelectorAll('[data-close-modal]').forEach(function (b) {
    b.addEventListener('click', function () {
        closeModal(this.getAttribute('data-close-modal'));
    });
});

document.querySelectorAll('.liability-modal').forEach(function (m) {
    m.addEventListener('click', function (e) {
        if (e.target === m) closeModal(m.id);
    });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.liability-modal.show').forEach(function (m) {
            closeModal(m.id);
        });
    }
});

function openTambahModal() {
    openModal('modalTambahUtang');
}

function openBayarModal(id, sisa) {
    document.getElementById('pay_utang_id').value = id;
    let sisaRupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(sisa);
    document.getElementById('labelMaksimal').innerText = sisaRupiah;
    document.getElementById('paidSubtitle').textContent = 'Catat pelunasan/cicilan utang. Pembayaran akan otomatis masuk ke Pengeluaran.';
    openModal('modalBayarUtang');
}

function formatRupiah(obj) {
    let value = obj.value.replace(/[^,\d]/g, '').toString();
    let split = value.split(',');
    let sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    obj.value = rupiah ? 'Rp ' + rupiah : '';
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>