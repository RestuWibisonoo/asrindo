<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();
$pageTitle = 'Detail Mutasi Rekening';

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return number_format((float) ($value ?? 0), 0, ',', '.');
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: rekening.php');
    exit;
}

/* Ambil data rekening */
$stmt = $pdo->prepare("SELECT * FROM rekening WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$rekening = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rekening) {
    header('Location: rekening.php');
    exit;
}

/* Filter periode */
$filterDari   = trim($_GET['dari']   ?? '');
$filterSampai = trim($_GET['sampai'] ?? '');

$params = [$id, $id];
$whereDari = $whereSampai = '';

if ($filterDari !== '' && strtotime($filterDari)) {
    $whereDari  = " AND tanggal >= ?";
    $params[] = $filterDari;
}
if ($filterSampai !== '' && strtotime($filterSampai)) {
    $whereSampai = " AND tanggal <= ?";
    $params[] = $filterSampai;
}

/*
 * Gabungkan pemasukan + pengeluaran yang punya rekening_id = $id
 * menjadi satu daftar mutasi, lalu hitung saldo berjalan dengan window function.
 */
$mutasiList = $pdo->prepare("
    SELECT
        tanggal,
        keterangan_display,
        sumber_label,
        jenis,
        nominal,
        SUM(CASE WHEN jenis='MASUK' THEN nominal ELSE -nominal END)
            OVER (ORDER BY tanggal ASC, id ASC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)
            + :saldo_awal AS saldo_berjalan
    FROM (
        SELECT
            id,
            tanggal,
            CONCAT(
                COALESCE(keterangan, nomor_pemasukan),
                CASE WHEN sumber='PENJUALAN' AND penjualan_id IS NOT NULL THEN CONCAT(' — Penjualan #', penjualan_id) ELSE '' END
            ) AS keterangan_display,
            'Pemasukan' AS sumber_label,
            'MASUK' AS jenis,
            nominal
        FROM pemasukan
        WHERE rekening_id = :rek_id_pm
        {$whereDari}{$whereSampai}
        UNION ALL
        SELECT
            id,
            tanggal,
            COALESCE(keterangan, nomor_pengeluaran) AS keterangan_display,
            'Pengeluaran' AS sumber_label,
            'KELUAR' AS jenis,
            nominal
        FROM pengeluaran
        WHERE rekening_id = :rek_id_pk
        {$whereDari}{$whereSampai}
    ) m
    ORDER BY tanggal ASC, id ASC
");

$mutasiList->execute(array_merge(
    [':saldo_awal' => (float) $rekening['saldo_awal'], ':rek_id_pm' => $id, ':rek_id_pk' => $id],
    $filterDari    !== '' ? [$filterDari, $filterDari]     : [],
    $filterSampai  !== '' ? [$filterSampai, $filterSampai] : []
));
$mutasiList = $mutasiList->fetchAll(PDO::FETCH_ASSOC);

/* Ringkasan */
$totalMasuk  = 0;
$totalKeluar = 0;
foreach ($mutasiList as $m) {
    if ($m['jenis'] === 'MASUK') {
        $totalMasuk += (float) $m['nominal'];
    } else {
        $totalKeluar += (float) $m['nominal'];
    }
}
$saldoAkhir = (float) $rekening['saldo_awal'] + $totalMasuk - $totalKeluar;

$extraHead = <<<'HTML'
<style>
.page-content { padding: 26px; }
.page-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 22px; }
.page-heading h1 { margin: 0 0 4px; font-size: 25px; color: #071b3a; }
.page-heading p  { margin: 0; color: #71809a; font-size: 13px; }
.btn { border: 1px solid transparent; border-radius: 4px; padding: 9px 15px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; gap: 5px; box-sizing: border-box; }
.btn-sm { padding: 6px 11px; font-size: 12px; }
.btn-light { background: #eef1f5; border-color: #d8dfe8; color: #52647d; }
.btn-light:hover { background: #e4e9f0; }
.btn-primary { background: #086cff; color: #fff; }
.btn-primary:hover { background: #0058d8; }
.card { background: #fff; border: 1px solid #dbe2ec; border-radius: 4px; overflow: hidden; margin-bottom: 20px; }
.card-header { padding: 16px 18px; border-bottom: 1px solid #dbe2ec; color: #0b2853; }
.card-body    { padding: 16px 18px; }
.detail-summary { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); border: 1px solid #dbe2ec; border-radius: 4px; margin-bottom: 20px; background: #fff; }
.summary-item { padding: 15px; border-right: 1px solid #dbe2ec; }
.summary-item:last-child { border-right: 0; }
.summary-item span   { display: block; color: #7a889d; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 5px; }
.summary-item strong { color: #09264d; font-size: 16px; display: block; }
.text-success { color: #10b95d; }
.text-danger  { color: #e74c3c; }
.info-grid { display: grid; grid-template-columns: 130px 1fr; gap: 8px 15px; font-size: 13px; color: #263d60; }
.info-label { color: #7a889d; }
.table-wrap { width: 100%; overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; min-width: 820px; }
.data-table th, .data-table td { padding: 11px 12px; border-bottom: 1px solid #dbe2ec; text-align: left; font-size: 13px; color: #0b2853; vertical-align: middle; }
.data-table thead th { background: #f5f7fa; font-weight: 600; white-space: nowrap; }
.data-table tbody tr:hover { background: #fafcff; }
.money-cell { text-align: right !important; white-space: nowrap; }
.filter-bar { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid #dbe2ec; }
.filter-group label { display: block; font-size: 11px; color: #7a889d; margin-bottom: 4px; }
.filter-group input { padding: 7px 10px; border: 1px solid #bdcadc; border-radius: 4px; font: inherit; font-size: 13px; }
@media (max-width: 768px) {
    .detail-summary { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .summary-item:nth-child(1), .summary-item:nth-child(2) { border-bottom: 1px solid #dbe2ec; }
    .summary-item:nth-child(2) { border-right: 0; }
}
</style>
HTML;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="page-heading">
        <div>
            <h1>Mutasi: <?php echo h($rekening['nama_rekening']); ?></h1>
            <p>Riwayat transaksi masuk &amp; keluar untuk rekening ini.</p>
        </div>
        <a href="rekening.php" class="btn btn-light">&larr; Kembali</a>
    </div>

    <!-- Info Rekening -->
    <div class="card">
        <div class="card-header"><strong>Informasi Rekening</strong></div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-label">Nama / Alias</div>
                <div><strong><?php echo h($rekening['nama_rekening']); ?></strong></div>
                <div class="info-label">Bank</div>
                <div><?php echo h($rekening['nama_bank']); ?></div>
                <div class="info-label">No. Rekening</div>
                <div><?php echo h($rekening['nomor_rekening'] ?? '-'); ?></div>
                <div class="info-label">Atas Nama</div>
                <div><?php echo h($rekening['atas_nama'] ?? '-'); ?></div>
                <div class="info-label">Saldo Awal</div>
                <div>Rp <?php echo rupiah($rekening['saldo_awal']); ?></div>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="detail-summary">
        <div class="summary-item">
            <span>Saldo Awal</span>
            <strong>Rp <?php echo rupiah($rekening['saldo_awal']); ?></strong>
        </div>
        <div class="summary-item">
            <span>Total Pemasukan</span>
            <strong class="text-success">Rp <?php echo rupiah($totalMasuk); ?></strong>
        </div>
        <div class="summary-item">
            <span>Total Pengeluaran</span>
            <strong class="text-danger">Rp <?php echo rupiah($totalKeluar); ?></strong>
        </div>
        <div class="summary-item" style="background:#f8fbff;">
            <span>Saldo Akhir</span>
            <strong style="color:#086cff;">Rp <?php echo rupiah($saldoAkhir); ?></strong>
        </div>
    </div>

    <!-- Tabel Mutasi -->
    <div class="card">
        <form method="GET" action="rekening_detail.php">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <div class="filter-bar">
                <div class="filter-group">
                    <label>Dari Tanggal</label>
                    <input type="date" name="dari" value="<?php echo h($filterDari); ?>">
                </div>
                <div class="filter-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="sampai" value="<?php echo h($filterSampai); ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if ($filterDari !== '' || $filterSampai !== ''): ?>
                    <a href="rekening_detail.php?id=<?php echo (int)$id; ?>" class="btn btn-light btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Sumber</th>
                        <th class="money-cell">Masuk</th>
                        <th class="money-cell">Keluar</th>
                        <th class="money-cell" style="background:#f8fbff;">Saldo Berjalan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mutasiList)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:24px;color:#8a96aa;">
                                Belum ada riwayat transaksi untuk rekening ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mutasiList as $m): ?>
                            <tr>
                                <td><?php echo h(date('d M Y', strtotime($m['tanggal']))); ?></td>
                                <td><?php echo h($m['keterangan_display']); ?></td>
                                <td><span style="font-size:11px;color:#6b7e99;"><?php echo h($m['sumber_label']); ?></span></td>
                                <td class="money-cell">
                                    <?php if ($m['jenis'] === 'MASUK'): ?>
                                        <span class="text-success">Rp <?php echo rupiah($m['nominal']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#bbb;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="money-cell">
                                    <?php if ($m['jenis'] === 'KELUAR'): ?>
                                        <span class="text-danger">Rp <?php echo rupiah($m['nominal']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#bbb;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="money-cell" style="background:#fdfdff;font-weight:500;">
                                    Rp <?php echo rupiah($m['saldo_berjalan']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
