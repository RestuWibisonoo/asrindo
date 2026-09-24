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

$filterDari   = trim($_GET['dari']   ?? '');
$filterSampai = trim($_GET['sampai'] ?? '');

/* Menangani tombol Bulanan */
if (isset($_GET['bulanan'])) {
    $filterDari = date('Y-m-01');
    $filterSampai = date('Y-m-t');
}

$limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT) ?: 50;
if ($limit < 1) $limit = 50;

$saldoAwalPeriode = (float) $rekening['saldo_awal'];

$wherePm = "";
$wherePk = "";
$params = [
    ':rek_id_pm' => $id,
    ':rek_id_pk' => $id
];

if ($filterDari !== '' && strtotime($filterDari)) {
    $wherePm .= " AND tanggal >= :dari_pm";
    $wherePk .= " AND tanggal >= :dari_pk";
    $params[':dari_pm'] = $filterDari;
    $params[':dari_pk'] = $filterDari;
    
    // Hitung saldo sebelum tanggal filter
    $stmtSum = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(SUM(nominal),0) FROM pemasukan WHERE rekening_id = ? AND tanggal < ?) - 
            (SELECT COALESCE(SUM(nominal),0) FROM pengeluaran WHERE rekening_id = ? AND tanggal < ?)
    ");
    $stmtSum->execute([$id, $filterDari, $id, $filterDari]);
    $mutasiSebelumnya = (float) $stmtSum->fetchColumn();
    $saldoAwalPeriode += $mutasiSebelumnya;
}

if ($filterSampai !== '' && strtotime($filterSampai)) {
    $wherePm .= " AND tanggal <= :sampai_pm";
    $wherePk .= " AND tanggal <= :sampai_pk";
    $params[':sampai_pm'] = $filterSampai;
    $params[':sampai_pk'] = $filterSampai;
}

$params[':saldo_awal'] = $saldoAwalPeriode;

/*
 * Gabungkan pemasukan + pengeluaran yang punya rekening_id = $id
 * menjadi satu daftar mutasi, lalu hitung saldo berjalan dengan window function.
 */
$mutasiListQuery = $pdo->prepare("
    SELECT * FROM (
        SELECT
            tanggal,
            keterangan_display,
            sumber_label,
            jenis,
            nominal,
            SUM(CASE WHEN jenis='MASUK' THEN nominal ELSE -nominal END)
                OVER (ORDER BY tanggal ASC, id ASC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)
                + :saldo_awal AS saldo_berjalan,
            id
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
            {$wherePm}
            UNION ALL
            SELECT
                id,
                tanggal,
                CONCAT(
                    CASE WHEN jenis_pengeluaran = 'Angsuran' THEN '[Angsuran] ' ELSE '' END,
                    COALESCE(keterangan, nomor_pengeluaran)
                ) AS keterangan_display,
                'Pengeluaran' AS sumber_label,
                'KELUAR' AS jenis,
                nominal
            FROM pengeluaran
            WHERE rekening_id = :rek_id_pk
            {$wherePk}
        ) m
    ) m_calculated
    ORDER BY tanggal DESC, id DESC
    LIMIT " . (int)$limit . "
");

foreach ($params as $key => $val) {
    $mutasiListQuery->bindValue($key, $val);
}
$mutasiListQuery->execute();
$mutasiList = $mutasiListQuery->fetchAll(PDO::FETCH_ASSOC);

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

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .dashboard-finance-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px;}
    @media(max-width:800px){.dashboard-finance-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media(max-width:480px){.dashboard-finance-grid{grid-template-columns:1fr;}}
    .dashboard-finance-card{position:relative;background:#fff;border:1px solid #dce3eb;border-radius:7px;padding:15px 16px;box-shadow:0 1px 2px rgba(23,43,77,.04);}
    .dashboard-finance-card::before{content:'';position:absolute;left:0;top:10px;bottom:10px;width:3px;border-radius:0 3px 3px 0;background:#0d6efd;}
    .dashboard-finance-card.net::before{background:#0d6efd}
    .dashboard-finance-card.expense::before{background:#dc3545}
    .dashboard-finance-card.success::before{background:#10b981}
    .dashboard-finance-card strong{display:block;padding-left:4px;color:#172b4d;font-size:18px;margin-bottom:5px;}
    .dashboard-finance-card span{display:block;padding-left:4px;color:#64748b;font-size:11px;}
    .dashboard-finance-card.net strong{color:#0d6efd}
    .dashboard-finance-card.expense strong{color:#dc3545}
    .dashboard-finance-card.success strong{color:#047857}
    
    .dashboard-panel{background:#fff;border:1px solid #dce3eb;border-radius:6px;box-shadow:0 2px 4px rgba(23,43,77,.02);margin-bottom:20px;overflow:hidden;}
    .dashboard-panel-header{padding:14px 18px;background:#fbfcfd;border-bottom:1px solid #dce3eb;color:#0a2347;font-weight:600;font-size:14px;}
    
    .dashboard-table{width:100%;border-collapse:collapse;min-width:600px;}
    .dashboard-table th{background:#f8f9fa;color:#475569;font-size:11px;font-weight:600;text-transform:uppercase;padding:10px 14px;border-bottom:2px solid #dce3eb;text-align:left;}
    .dashboard-table td{padding:12px 14px;border-bottom:1px solid #dce3eb;color:#334155;font-size:13px;vertical-align:middle;}
    .dashboard-table tr:hover td{background:#f8f9fa;}
    .dashboard-table .money{text-align:right !important;font-weight:600;}
    
    .btn-primary {display:inline-flex;align-items:center;gap:6px;background:#0d6efd;color:#fff;padding:9px 15px;border-radius:5px;border:none;cursor:pointer;font-weight:600;font-size:13px;transition:background 0.2s;}
    .btn-primary:hover {background:#0b5ed7;}
    .btn-secondary {display:inline-flex;align-items:center;border:1px solid #bdcadc;background:#fff;color:#314b72;border-radius:4px;padding:8px 14px;cursor:pointer;font-weight:600;font-size:12px;text-decoration:none;}
    
    .admin-page-header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;}
    .admin-page-header h1 {margin:0; font-size:20px; color:#172b4d;}
</style>


<div class="admin-page-header">
    <div>
        <h1>Mutasi: <?php echo h($rekening['nama_rekening']); ?></h1>
        <div style="font-size:12px;color:#718096;margin-top:3px;">Riwayat transaksi masuk &amp; keluar untuk rekening ini.</div>
    </div>
    <div>
        <a href="rekening.php" class="btn-secondary">&larr; Kembali</a>
    </div>
</div>

<!-- Summary -->
<div class="dashboard-finance-grid">
    <div class="dashboard-finance-card">
        <strong>Rp <?php echo rupiah($rekening['saldo_awal']); ?></strong>
        <span>Saldo Awal</span>
    </div>
    <div class="dashboard-finance-card success">
        <strong>Rp <?php echo rupiah($totalMasuk); ?></strong>
        <span>Total Pemasukan</span>
    </div>
    <div class="dashboard-finance-card expense">
        <strong>Rp <?php echo rupiah($totalKeluar); ?></strong>
        <span>Total Pengeluaran</span>
    </div>
    <div class="dashboard-finance-card net">
        <strong>Rp <?php echo rupiah($saldoAkhir); ?></strong>
        <span>Saldo Akhir</span>
    </div>
</div>

<!-- Info Rekening -->
<div class="dashboard-panel" style="margin-bottom: 20px;">
    <div class="dashboard-panel-header">Informasi Rekening</div>
    <div style="padding: 16px 20px; display: grid; grid-template-columns: 130px 1fr; gap: 8px 15px; font-size: 13px; color: #263d60;">
        <div style="color: #7a889d;">Nama / Alias</div>
        <div><strong><?php echo h($rekening['nama_rekening']); ?></strong></div>
        <div style="color: #7a889d;">Bank</div>
        <div><?php echo h($rekening['nama_bank']); ?></div>
        <div style="color: #7a889d;">No. Rekening</div>
        <div><?php echo h($rekening['nomor_rekening'] ?? '-'); ?></div>
        <div style="color: #7a889d;">Atas Nama</div>
        <div><?php echo h($rekening['atas_nama'] ?? '-'); ?></div>
    </div>
</div>


<!-- Tabel Mutasi -->
<div class="dashboard-panel">
    <div class="dashboard-panel-header">Riwayat Transaksi</div>
    <div style="padding: 14px 18px; border-bottom: 1px solid #dce3eb; background: #f8f9fa;">
        <form method="GET" action="rekening_detail.php" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <div>
                <label style="display:block; font-size:11px; color:#64748b; margin-bottom:4px;">Tampilkan</label>
                <select name="limit" onchange="this.form.submit()" style="padding:6px 10px; border:1px solid #dce3eb; border-radius:4px; font-size:13px; background:#fff;">
                    <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10 Baris</option>
                    <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25 Baris</option>
                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50 Baris</option>
                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100 Baris</option>
                    <option value="500" <?php echo $limit === 500 ? 'selected' : ''; ?>>500 Baris</option>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:11px; color:#64748b; margin-bottom:4px;">Dari Tanggal</label>
                <input type="date" name="dari" value="<?php echo h($filterDari); ?>" style="padding:6px 10px; border:1px solid #dce3eb; border-radius:4px; font-size:13px;">
            </div>
            <div>
                <label style="display:block; font-size:11px; color:#64748b; margin-bottom:4px;">Sampai Tanggal</label>
                <input type="date" name="sampai" value="<?php echo h($filterSampai); ?>" style="padding:6px 10px; border:1px solid #dce3eb; border-radius:4px; font-size:13px;">
            </div>
            <button type="submit" class="btn-primary" style="padding: 7px 12px;">Filter</button>
            <button type="submit" name="bulanan" value="1" class="btn-secondary" style="padding: 7px 12px;">Bulan Ini</button>
            <?php if ($filterDari !== '' || $filterSampai !== ''): ?>
                <a href="rekening_detail.php?id=<?php echo (int)$id; ?>" class="btn-secondary" style="padding: 7px 12px; text-decoration:none;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div style="overflow-x:auto;">
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Sumber</th>
                    <th class="money">Masuk</th>
                    <th class="money">Keluar</th>
                    <th class="money">Saldo Berjalan</th>
                </tr>
            </thead>
                <tbody>
                    <?php if (empty($mutasiList)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:30px;color:#64748b;">
                                Belum ada riwayat transaksi untuk rekening ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mutasiList as $m): ?>
                            <tr>
                                <td><?php echo h(date('d M Y', strtotime($m['tanggal']))); ?></td>
                                <td><?php echo h($m['keterangan_display']); ?></td>
                                <td><span style="font-size:11px;color:#64748b;"><?php echo h($m['sumber_label']); ?></span></td>
                                <td class="money">
                                    <?php if ($m['jenis'] === 'MASUK'): ?>
                                        <span style="color: #10b981;">Rp <?php echo rupiah($m['nominal']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#cbd5e1;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="money">
                                    <?php if ($m['jenis'] === 'KELUAR'): ?>
                                        <span style="color: #dc3545;">Rp <?php echo rupiah($m['nominal']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#cbd5e1;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="money" style="background:#f8f9fa;">
                                    <strong>Rp <?php echo rupiah($m['saldo_berjalan']); ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
