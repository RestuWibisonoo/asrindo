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

$today = date('Y-m-d');

/* -------------------------------------------------------------------------
 * DATA QUERY 1: TOTAL JATUH TEMPO
 * Mengambil nominal jadwal pembayaran yang sudah lewat atau sama dengan hari ini.
 * ---------------------------------------------------------------------- */
$sqlJatuhTempo = "SELECT SUM(nominal) 
                  FROM pembelian_pembayaran 
                  WHERE status = 'BELUM_BAYAR' AND tanggal_jatuh_tempo <= ?";
$stmtJT = $pdo->prepare($sqlJatuhTempo);
$stmtJT->execute([$today]);
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

require __DIR__ . '/../includes/header.php';
?>

<style>
    .liabilitas-page { padding: 4px 0 30px; }
    .liabilitas-summary { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; margin-bottom:20px; }
    .liabilitas-summary-card { background:#fff; border:1px solid #dce3eb; border-radius:10px; padding:18px; box-shadow:0 1px 2px rgba(0,0,0,.03); }
    .liabilitas-summary-card .label { font-size:13px; color:#657386; margin-bottom:8px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
    .liabilitas-summary-card .value { font-size:24px; font-weight:700; color:#172334; }
    .liabilitas-summary-card.alert-card .value { color:#b42318; }
    
    .liabilitas-table-wrap { overflow:auto; border:1px solid #dce3eb; border-radius:10px; background:#fff; margin-bottom:20px; }
    .liabilitas-table { width:100%; min-width:1050px; border-collapse:collapse; }
    .liabilitas-table th, .liabilitas-table td { padding:12px 14px; border-bottom:1px solid #edf1f5; vertical-align:top; text-align:left; }
    .liabilitas-table th { background:#f7f9fb; color:#425166; font-size:12px; white-space:nowrap; text-transform:uppercase; letter-spacing:0.5px; }
    .liabilitas-table td { font-size:13px; color:#263445; }
    .liabilitas-table tr:last-child td { border-bottom:0; }
    .liabilitas-money { white-space:nowrap; text-align:right !important; font-weight:600; }
    .liabilitas-center { text-align:center !important; }
    .liabilitas-muted { color:#7a8798; font-size:11px; margin-top:3px; }
    
    .badge { display:inline-flex; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700; }
    .badge-ab { background:#e7f0fd; color:#0c4a6e; }
    .badge-sp { background:#fef3c7; color:#92400e; }
    .badge-rs { background:#f3e8ff; color:#6b21a8; }
    
    .badge-danger { background:#fef2f2; color:#b91c1c; border: 1px solid #fca5a5; }
    .badge-warning { background:#fffbeb; color:#b45309; border: 1px solid #fcd34d; }
    .badge-neutral { background:#f1f5f9; color:#475569; border: 1px solid #cbd5e1; }
    .text-danger { color:#b91c1c; font-weight:700; }
    
    .empty-state { padding:40px 20px; text-align:center; color:#64748b; font-size:14px; }
</style>

<div class="liabilitas-page">
    <div class="admin-page-header">
        <div>
            <h1>Liabilitas (Utang Usaha)</h1>
            <div style="font-size:13px;color:#718096;margin-top:4px;">Rekapitulasi sisa utang pembelian alat berat, sparepart, dan restorasi yang belum lunas.</div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="liabilitas-summary">
        <div class="liabilitas-summary-card">
            <div class="label">Total Sisa Utang Berjalan</div>
            <div class="value"><?php echo rupiah($totalUtang); ?></div>
            <div class="liabilitas-muted">Total keseluruhan sisa pembelian yang belum terbayarkan.</div>
        </div>
        <div class="liabilitas-summary-card alert-card">
            <div class="label">Jatuh Tempo Hari Ini / Terlewat</div>
            <div class="value"><?php echo rupiah($totalJatuhTempo); ?></div>
            <div class="liabilitas-muted">Berdasarkan termin tagihan yang jadwalnya harus segera dilunasi.</div>
        </div>
    </div>

    <!-- Utang Pembelian -->
    <h2 style="font-size:16px; margin-bottom:12px; color:#1e293b;">Daftar Pembelian Belum Lunas</h2>
    <div class="liabilitas-table-wrap">
        <table class="liabilitas-table">
            <thead>
                <tr>
                    <th>Nomor Pembelian</th>
                    <th>Supplier</th>
                    <th>Tgl Beli</th>
                    <th>Jatuh Tempo Terdekat</th>
                    <th class="liabilitas-money">Total Pembelian</th>
                    <th class="liabilitas-money">Sudah Terbayar</th>
                    <th class="liabilitas-money">Sisa Utang</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($liabilitas)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            Tidak ada utang pembelian yang belum dibayar saat ini.<br>Semua transaksi sudah berstatus lunas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($liabilitas as $utang): 
                        $sisa = (float)$utang['total'] - (float)$utang['terbayar'];
                        
                        // Menentukan badge kategori
                        $badgeClass = 'badge-neutral';
                        if ($utang['jenis'] === 'Alat Berat') $badgeClass = 'badge-ab';
                        if ($utang['jenis'] === 'Sparepart') $badgeClass = 'badge-sp';
                        if ($utang['jenis'] === 'Restorasi') $badgeClass = 'badge-rs';

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
                                        <div class="liabilitas-muted text-danger">Terlambat</div>
                                    <?php elseif ($isToday): ?>
                                        <span style="color:#b45309; font-weight:700;"><?php echo h(date('d-m-Y', strtotime($jtDate))); ?></span>
                                        <div class="liabilitas-muted" style="color:#b45309;">Hari Ini</div>
                                    <?php else: ?>
                                        <span><?php echo h(date('d-m-Y', strtotime($jtDate))); ?></span>
                                        <div class="liabilitas-muted">Akan datang</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="liabilitas-money" style="color:#64748b;">
                                <?php echo rupiah($utang['total']); ?>
                            </td>
                            <td class="liabilitas-money" style="color:#15803d;">
                                <?php echo rupiah($utang['terbayar']); ?>
                            </td>
                            <td class="liabilitas-money" style="font-size:14px; color:#b91c1c;">
                                <?php echo rupiah($sisa); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>