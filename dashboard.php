<?php
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$breadcrumb = 'Ringkasan operasional & keuangan';
require_once __DIR__ . '/../includes/header_admin.php';

$pdo = getDB();

/* ---------- Alat Berat ---------- */
$abTotal = (int) $pdo->query('SELECT COUNT(*) FROM alat_berat')->fetchColumn();
$abNilai = (float) $pdo->query('SELECT COALESCE(SUM(harga_beli_idr),0) FROM alat_berat')->fetchColumn();
$abStatus = $pdo->query('SELECT status, COUNT(*) jumlah FROM alat_berat GROUP BY status ORDER BY jumlah DESC')->fetchAll();
$abTersedia = 0;
foreach ($abStatus as $row) {
    if (stripos($row['status'], 'tersedia') !== false) { $abTersedia += (int) $row['jumlah']; }
}

/* ---------- Suku Cadang ---------- */
$scTotalJenis = (int) $pdo->query('SELECT COUNT(*) FROM sparepart')->fetchColumn();
$scNilai = (float) $pdo->query('SELECT COALESCE(SUM(stok * harga_jual),0) FROM sparepart')->fetchColumn();
$scHampirHabis = (int) $pdo->query('SELECT COUNT(*) FROM sparepart WHERE stok <= stok_minimum')->fetchColumn();

/* ---------- Pelanggan ---------- */
$custTotal = (int) $pdo->query('SELECT COUNT(*) FROM customer')->fetchColumn();
$custByStatus = $pdo->query("SELECT status_pelanggan, COUNT(*) jumlah FROM customer GROUP BY status_pelanggan")->fetchAll();
$custMap = array_column($custByStatus, 'jumlah', 'status_pelanggan');

/* ---------- Supplier ---------- */
$supTotal = (int) $pdo->query('SELECT COUNT(*) FROM supplier')->fetchColumn();
$supAktif = (int) $pdo->query("SELECT COUNT(*) FROM supplier WHERE status = 'Aktif'")->fetchColumn();

/* ---------- Pembelian (PO) bulan ini ---------- */
$poAktif = (int) $pdo->query("SELECT COUNT(*) FROM pembelian_sparepart WHERE status IN ('DRAFT','PROSES')")->fetchColumn();
$poBulanIni = (int) $pdo->query("SELECT COUNT(*) FROM pembelian_sparepart WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();
$poNilaiBulanIni = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM pembelian_sparepart WHERE status = 'SELESAI' AND MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();

/* ---------- Penjualan bulan ini ---------- */
$penjualanBulanIni = (int) $pdo->query("SELECT COUNT(*) FROM penjualan WHERE status <> 'CANCELLED' AND MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();
$penjualanNilaiBulanIni = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM penjualan WHERE status <> 'CANCELLED' AND MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();

/* ---------- Perawatan bulan ini ---------- */
$perawatanBulanIni = (int) $pdo->query("SELECT COUNT(*) FROM alat_berat_perawatan WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();
$perawatanBiayaBulanIni = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM alat_berat_perawatan WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();

/* ---------- Keuangan bulan ini ---------- */
$pendapatanBulanIni = (float) $pdo->query("
    SELECT COALESCE(SUM(tk.nominal),0) FROM transaksi_keuangan tk
    JOIN kategori_keuangan k ON k.id = tk.kategori_id
    WHERE k.tipe = 'PENDAPATAN' AND MONTH(tk.tanggal)=MONTH(CURDATE()) AND YEAR(tk.tanggal)=YEAR(CURDATE())
")->fetchColumn();
$pendapatanBulanIni += $penjualanNilaiBulanIni;
$pengeluaranBulanIni = (float) $pdo->query("
    SELECT COALESCE(SUM(tk.nominal),0) FROM transaksi_keuangan tk
    JOIN kategori_keuangan k ON k.id = tk.kategori_id
    WHERE k.tipe = 'PENGELUARAN' AND MONTH(tk.tanggal)=MONTH(CURDATE()) AND YEAR(tk.tanggal)=YEAR(CURDATE())
")->fetchColumn();
$pengeluaranBulanIni += $poNilaiBulanIni + $perawatanBiayaBulanIni;
$labaBulanIni = $pendapatanBulanIni - $pengeluaranBulanIni;

$chartYear = (int) date('Y');
$chartData = array_fill(1, 12, ['pendapatan' => 0, 'pengeluaran' => 0]);
$chartStmt = $pdo->prepare("SELECT MONTH(tanggal) AS bulan, SUM(pendapatan) AS pendapatan, SUM(pengeluaran) AS pengeluaran FROM (
  SELECT tk.tanggal, IF(k.tipe = 'PENDAPATAN', tk.nominal, 0) AS pendapatan, IF(k.tipe = 'PENGELUARAN', tk.nominal, 0) AS pengeluaran FROM transaksi_keuangan tk INNER JOIN kategori_keuangan k ON k.id = tk.kategori_id WHERE tk.tanggal BETWEEN :awal_kas AND :akhir_kas
  UNION ALL
  SELECT p.tanggal, p.total AS pendapatan, 0 AS pengeluaran FROM penjualan p WHERE p.tanggal BETWEEN :awal_penjualan AND :akhir_penjualan AND p.status <> 'CANCELLED'
  UNION ALL
  SELECT po.tanggal, 0 AS pendapatan, po.total AS pengeluaran FROM pembelian_sparepart po WHERE po.tanggal BETWEEN :awal_pembelian AND :akhir_pembelian AND po.status = 'SELESAI'
  UNION ALL
  SELECT ap.tanggal, 0 AS pendapatan, ap.total AS pengeluaran FROM alat_berat_perawatan ap WHERE ap.tanggal BETWEEN :awal_perawatan AND :akhir_perawatan
) AS arus_kas GROUP BY MONTH(tanggal) ORDER BY bulan");
$chartParams = [
  ':awal_kas' => "$chartYear-01-01", ':akhir_kas' => "$chartYear-12-31",
  ':awal_penjualan' => "$chartYear-01-01", ':akhir_penjualan' => "$chartYear-12-31",
  ':awal_pembelian' => "$chartYear-01-01", ':akhir_pembelian' => "$chartYear-12-31",
  ':awal_perawatan' => "$chartYear-01-01", ':akhir_perawatan' => "$chartYear-12-31",
];
$chartStmt->execute($chartParams);
foreach ($chartStmt->fetchAll() as $chartRow) {
  $chartData[(int) $chartRow['bulan']] = ['pendapatan' => (float) $chartRow['pendapatan'], 'pengeluaran' => (float) $chartRow['pengeluaran']];
}
$chartMax = max(1, ...array_map(static fn ($month) => max($month['pendapatan'], $month['pengeluaran']), $chartData));
$monthLabels = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/* ---------- Aktivitas terbaru ---------- */
$penjualanTerbaru = $pdo->query("
    SELECT p.nomor_penjualan, p.tanggal, p.total, p.status, c.nama AS customer
    FROM penjualan p JOIN customer c ON c.id = p.customer_id
    ORDER BY p.tanggal DESC, p.id DESC LIMIT 5
")->fetchAll();

$pembelianTerbaru = $pdo->query("
    SELECT po.nomor_pembelian, po.tanggal, po.total, po.status, s.nama AS supplier
    FROM pembelian_sparepart po JOIN supplier s ON s.id = po.supplier_id
    ORDER BY po.tanggal DESC, po.id DESC LIMIT 5
")->fetchAll();
?>

<div class="stat-cards">
  <div class="stat-card">
    <div class="label">Laba / Rugi Bulan Ini</div>
    <div class="value"><?= rupiah($labaBulanIni) ?></div>
    <div class="sub">Pendapatan <?= rupiah($pendapatanBulanIni) ?> &middot; Pengeluaran <?= rupiah($pengeluaranBulanIni) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Unit Alat Berat</div>
    <div class="value"><?= $abTotal ?></div>
    <div class="sub"><?= $abTersedia ?> unit tersedia &middot; Nilai <?= rupiah($abNilai) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">PO Aktif</div>
    <div class="value"><?= $poAktif ?></div>
    <div class="sub"><?= $poBulanIni ?> PO bulan ini &middot; <?= rupiah($poNilaiBulanIni) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Penjualan Bulan Ini</div>
    <div class="value"><?= $penjualanBulanIni ?></div>
    <div class="sub"><?= rupiah($penjualanNilaiBulanIni) ?></div>
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card">
    <div class="label">Suku Cadang</div>
    <div class="value"><?= $scTotalJenis ?></div>
    <div class="sub">Nilai <?= rupiah($scNilai) ?> &middot; <?= $scHampirHabis ?> hampir habis</div>
  </div>
  <div class="stat-card">
    <div class="label">Perawatan Bulan Ini</div>
    <div class="value"><?= $perawatanBulanIni ?></div>
    <div class="sub">Biaya <?= rupiah($perawatanBiayaBulanIni) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Pelanggan</div>
    <div class="value"><?= $custTotal ?></div>
    <div class="sub"><?= $custMap['Baru'] ?? 0 ?> baru &middot; <?= $custMap['Reguler'] ?? 0 ?> reguler &middot; <?= $custMap['Blacklist'] ?? 0 ?> blacklist</div>
  </div>
  <div class="stat-card">
    <div class="label">Supplier</div>
    <div class="value"><?= $supTotal ?></div>
    <div class="sub"><?= $supAktif ?> aktif</div>
  </div>
</div>

<div class="card cashflow-card">
  <div class="card-title">Grafik Arus Kas <?= $chartYear ?></div>
  <div class="card-sub">Gabungan transaksi keuangan, penjualan, pembelian selesai, dan perawatan</div>
  <div class="chart-legend"><span><i class="legend-income"></i>Pendapatan</span><span><i class="legend-expense"></i>Pengeluaran</span></div>
  <div class="cashflow-chart" role="img" aria-label="Grafik pendapatan dan pengeluaran bulanan tahun <?= $chartYear ?>">
    <?php foreach ($chartData as $monthNumber => $month): ?>
      <div class="chart-column" title="<?= e($monthLabels[$monthNumber]) ?>: Pendapatan <?= e(rupiah($month['pendapatan'])) ?>, Pengeluaran <?= e(rupiah($month['pengeluaran'])) ?>">
        <div class="chart-bars"><span class="chart-bar chart-bar-income" style="height:<?= max(3, round(($month['pendapatan'] / $chartMax) * 180)) ?>px;"></span><span class="chart-bar chart-bar-expense" style="height:<?= max(3, round(($month['pengeluaran'] / $chartMax) * 180)) ?>px;"></span></div>
        <span class="chart-label"><?= e($monthLabels[$monthNumber]) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card" style="border-top:3px solid var(--steel); margin-bottom:20px;">
  <div class="card-title">Modul Rental</div>
  <div class="card-sub">Skema kontrak, timesheet, dan pembayaran rental akan aktif pada tahap pengerjaan modul Transaksi berikutnya. Statistik rental akan tampil di sini setelah tabel terkait ditambahkan.</div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
  <div class="card">
    <div class="card-title">Penjualan Terbaru</div>
    <div class="card-sub">5 transaksi penjualan terakhir</div>
    <?php if (empty($penjualanTerbaru)): ?>
      <div class="empty-state">Belum ada data penjualan.</div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Nomor</th><th>Pelanggan</th><th>Status</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($penjualanTerbaru as $row): ?>
        <tr>
          <td><?= e($row['nomor_penjualan']) ?><br><span style="color:var(--ink-soft); font-size:.78rem;"><?= tanggal_indo($row['tanggal']) ?></span></td>
          <td><?= e($row['customer']) ?></td>
          <td><span class="badge <?= status_badge_class($row['status']) ?>"><?= e($row['status']) ?></span></td>
          <td><?= rupiah($row['total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-title">Pembelian Terbaru</div>
    <div class="card-sub">5 purchase order terakhir</div>
    <?php if (empty($pembelianTerbaru)): ?>
      <div class="empty-state">Belum ada data pembelian.</div>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Nomor</th><th>Supplier</th><th>Status</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($pembelianTerbaru as $row): ?>
        <tr>
          <td><?= e($row['nomor_pembelian']) ?><br><span style="color:var(--ink-soft); font-size:.78rem;"><?= tanggal_indo($row['tanggal']) ?></span></td>
          <td><?= e($row['supplier']) ?></td>
          <td><span class="badge <?= status_badge_class($row['status']) ?>"><?= e($row['status']) ?></span></td>
          <td><?= rupiah($row['total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-title">Status Unit Alat Berat</div>
  <div class="card-sub">Distribusi status seluruh unit dalam inventori</div>
  <table class="data-table">
    <thead><tr><th>Status</th><th>Jumlah Unit</th></tr></thead>
    <tbody>
      <?php foreach ($abStatus as $row): ?>
      <tr>
        <td><span class="badge <?= status_badge_class($row['status']) ?>"><?= e($row['status']) ?></span></td>
        <td><?= (int) $row['jumlah'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/footer_admin.php'; ?>
