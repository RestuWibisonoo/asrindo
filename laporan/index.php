<?php
$pageTitle = "Laporan";
$activeMenu = "laporan";
$breadcrumb = "Laporan";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/auth.php";
require_login();

$pdo = getDB();
$report = $_GET['report'] ?? 'keuangan';
$allowedReports = ['keuangan', 'penjualan', 'pembelian', 'perawatan', 'sparepart', 'alat_berat'];
if (!in_array($report, $allowedReports, true)) $report = 'keuangan';
$from = $_GET['dari'] ?? date('Y-m-01');
$to = $_GET['sampai'] ?? date('Y-m-t');

$params = [':dari' => $from, ':sampai' => $to];
$rows = [];
$title = '';
$summary = [];
if ($report === 'keuangan') {
  $title = 'Laporan Kas & Keuangan';
  $stmt = $pdo->prepare("SELECT tk.id AS sumber_id, tk.tanggal, tk.nomor_transaksi AS nomor, 'Kas & Keuangan' AS sumber, kk.nama AS kategori, kk.tipe, tk.nominal, tk.keterangan FROM transaksi_keuangan tk INNER JOIN kategori_keuangan kk ON kk.id = tk.kategori_id WHERE tk.tanggal BETWEEN :dari_kas AND :sampai_kas
    UNION ALL
    SELECT p.id AS sumber_id, p.tanggal, p.nomor_penjualan AS nomor, 'Penjualan' AS sumber, 'Penjualan' AS kategori, 'PENDAPATAN' AS tipe, p.total AS nominal, p.keterangan FROM penjualan p WHERE p.tanggal BETWEEN :dari_penjualan AND :sampai_penjualan AND p.status <> 'CANCELLED'
    UNION ALL
    SELECT po.id AS sumber_id, po.tanggal, po.nomor_pembelian AS nomor, 'Pembelian' AS sumber, 'Pembelian Sparepart' AS kategori, 'PENGELUARAN' AS tipe, po.total AS nominal, po.keterangan FROM pembelian_sparepart po WHERE po.tanggal BETWEEN :dari_pembelian AND :sampai_pembelian AND po.status = 'SELESAI'
    UNION ALL
    SELECT ap.id AS sumber_id, ap.tanggal, CONCAT('PRW-', ap.id) AS nomor, 'Perawatan' AS sumber, 'Perawatan Alat Berat' AS kategori, 'PENGELUARAN' AS tipe, ap.total AS nominal, ap.keterangan FROM alat_berat_perawatan ap WHERE ap.tanggal BETWEEN :dari_perawatan AND :sampai_perawatan
    ORDER BY tanggal DESC, nomor DESC");
  $stmt->execute([
    ':dari_kas' => $from, ':sampai_kas' => $to,
    ':dari_penjualan' => $from, ':sampai_penjualan' => $to,
    ':dari_pembelian' => $from, ':sampai_pembelian' => $to,
    ':dari_perawatan' => $from, ':sampai_perawatan' => $to,
  ]); $rows = $stmt->fetchAll();
  $summary = ['Pendapatan' => 0, 'Pengeluaran' => 0];
  foreach ($rows as $row) {
    $tipe = strtoupper(trim((string) $row['tipe']));
    if ($tipe === 'PENDAPATAN') $summary['Pendapatan'] += (float) $row['nominal'];
    if ($tipe === 'PENGELUARAN') $summary['Pengeluaran'] += (float) $row['nominal'];
  }
  $summary['Laba/Rugi'] = $summary['Pendapatan'] - $summary['Pengeluaran'];
} elseif ($report === 'penjualan') {
  $title = 'Laporan Penjualan';
  $stmt = $pdo->prepare('SELECT p.id, p.nomor_penjualan, p.tanggal, p.status, p.keterangan, c.kode AS customer_kode, c.nama AS customer, ab.kode AS alat_kode, ab.tipe, pd.harga_jual, pd.diskon, pd.subtotal, pd.hpp, pd.laba FROM penjualan p INNER JOIN customer c ON c.id = p.customer_id INNER JOIN penjualan_detail pd ON pd.penjualan_id = p.id INNER JOIN alat_berat ab ON ab.id = pd.alat_berat_id WHERE p.tanggal BETWEEN :dari AND :sampai ORDER BY p.tanggal DESC, p.id DESC');
  $stmt->execute($params); $rows = $stmt->fetchAll();
  $summary = ['Transaksi' => count($rows), 'Total Penjualan' => array_sum(array_column($rows, 'subtotal')), 'Laba' => array_sum(array_column($rows, 'laba'))];
} elseif ($report === 'pembelian') {
  $title = 'Laporan Pembelian Sparepart';
  $stmt = $pdo->prepare('SELECT po.id, po.nomor_pembelian, po.tanggal, po.status, po.keterangan, s.kode AS supplier_kode, s.nama AS supplier, COUNT(pd.id) AS item_count, COALESCE(SUM(pd.subtotal), 0) AS total_detail FROM pembelian_sparepart po INNER JOIN supplier s ON s.id = po.supplier_id INNER JOIN pembelian_sparepart_detail pd ON pd.pembelian_id = po.id WHERE po.tanggal BETWEEN :dari AND :sampai GROUP BY po.id, s.id ORDER BY po.tanggal DESC, po.id DESC');
  $stmt->execute($params); $rows = $stmt->fetchAll();
  $summary = ['Transaksi' => count($rows), 'Total Pembelian' => array_sum(array_column($rows, 'total_detail'))];
} elseif ($report === 'perawatan') {
  $title = 'Laporan Perawatan';
  $stmt = $pdo->prepare('SELECT ap.*, ab.kode, ab.tipe FROM alat_berat_perawatan ap INNER JOIN alat_berat ab ON ab.id = ap.alat_berat_id WHERE ap.tanggal BETWEEN :dari AND :sampai ORDER BY ap.tanggal DESC, ap.id DESC');
  $stmt->execute($params); $rows = $stmt->fetchAll();
  $summary = ['Perawatan' => count($rows), 'Total Biaya' => array_sum(array_column($rows, 'total'))];
} elseif ($report === 'sparepart') {
  $title = 'Laporan Stok Sparepart';
  $rows = $pdo->query('SELECT * FROM sparepart ORDER BY stok ASC, nama ASC')->fetchAll();
  $summary = ['Jenis Item' => count($rows), 'Total Stok' => array_sum(array_column($rows, 'stok')), 'Nilai Modal' => array_sum(array_map(static fn ($row) => $row['stok'] * $row['harga_modal'], $rows))];
} else {
  $title = 'Laporan Inventori Alat Berat';
  $rows = $pdo->query('SELECT * FROM alat_berat ORDER BY status ASC, kode ASC')->fetchAll();
  $summary = ['Total Unit' => count($rows), 'Tersedia' => count(array_filter($rows, static fn ($row) => stripos($row['status'], 'tersedia') !== false)), 'Nilai Inventori' => array_sum(array_column($rows, 'harga_beli_idr'))];
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="laporan-' . $report . '-' . date('Ymd-His') . '.csv"');
  $output = fopen('php://output', 'w');
  if ($rows) { fputcsv($output, array_keys($rows[0])); foreach ($rows as $row) fputcsv($output, $row); }
  fclose($output); exit;
}

require_once __DIR__ . "/../../includes/header_admin.php";

function laporan_value(string $key, $value): string
{
  if ($value === null || $value === '') return '-';
  if (strpos($key, 'tanggal') !== false) return e(tanggal_indo((string) $value));
  if (is_numeric($value) && (strpos($key, 'total') !== false || strpos($key, 'nominal') !== false || strpos($key, 'harga') !== false || strpos($key, 'laba') !== false || strpos($key, 'biaya') !== false)) return rupiah($value);
  return e((string) $value);
}

function laporan_summary(string $label, $value): string
{
  return is_numeric($value) && (strpos($label, 'Total') !== false || strpos($label, 'Laba') !== false || strpos($label, 'Nilai') !== false || in_array($label, ['Pendapatan', 'Pengeluaran'], true)) ? rupiah($value) : e((string) $value);
}
?>

<div class="card"><div class="toolbar"><div><div class="card-title"><?= e($title) ?></div><div class="card-sub">Laporan diambil langsung dari database dan relasi transaksi terkait</div></div><form method="get" class="toolbar-form-inline"><select name="report" aria-label="Jenis laporan"><option value="keuangan" <?= $report === 'keuangan' ? 'selected' : '' ?>>Kas &amp; Keuangan</option><option value="penjualan" <?= $report === 'penjualan' ? 'selected' : '' ?>>Penjualan</option><option value="pembelian" <?= $report === 'pembelian' ? 'selected' : '' ?>>Pembelian</option><option value="perawatan" <?= $report === 'perawatan' ? 'selected' : '' ?>>Perawatan</option><option value="sparepart" <?= $report === 'sparepart' ? 'selected' : '' ?>>Stok Sparepart</option><option value="alat_berat" <?= $report === 'alat_berat' ? 'selected' : '' ?>>Inventori Alat Berat</option></select><input type="date" name="dari" value="<?= e($from) ?>" aria-label="Tanggal mulai"><input type="date" name="sampai" value="<?= e($to) ?>" aria-label="Tanggal sampai"><button type="submit" class="btn btn-amber btn-sm">Tampilkan</button><button type="submit" name="export" value="csv" class="btn btn-outline-dark btn-sm">Export CSV</button></form></div>
<div class="stat-cards"><?php foreach ($summary as $label => $value): ?><div class="stat-card"><div class="label"><?= e($label) ?></div><div class="value"><?= laporan_summary($label, $value) ?></div><div class="sub">Periode laporan terpilih</div></div><?php endforeach; ?></div>
<?php if (empty($rows)): ?><div class="empty-state"><div class="icon">&#9881;</div><h3 style="margin-bottom:6px;">Tidak ada data laporan</h3><p style="max-width:44ch; margin:0 auto;">Belum ada data pada periode atau jenis laporan yang dipilih.</p></div><?php else: ?><div style="overflow-x:auto;"><table class="data-table"><thead><tr><?php foreach (array_keys($rows[0]) as $heading): ?><th><?= e(ucwords(str_replace('_', ' ', $heading))) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $key => $value): ?><td><?= laporan_value($key, $value) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>

<?php require __DIR__ . "/../../includes/footer_admin.php"; ?>

