<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Dashboard';
$pdo = getPDO();

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/
function getScalar($pdo, $sql, $params = array())
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();

    return ($value !== false && $value !== null) ? $value : 0;
}

function rupiah($value)
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

/*
|--------------------------------------------------------------------------
| PENJUALAN
|--------------------------------------------------------------------------
| Nilai penjualan = subtotal detail penjualan.
| PPN tidak dimasukkan sebagai nilai penjualan/laba.
*/
$todaySales = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.subtotal), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE p.tanggal = CURDATE()
      AND UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
");

$monthSales = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.subtotal), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE YEAR(p.tanggal) = YEAR(CURDATE())
      AND MONTH(p.tanggal) = MONTH(CURDATE())
      AND UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
");

$yearSales = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.subtotal), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE YEAR(p.tanggal) = YEAR(CURDATE())
      AND UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
");

$totalSales = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.subtotal), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
");

/*
|--------------------------------------------------------------------------
| LABA PENJUALAN
|--------------------------------------------------------------------------
*/
$todayProfit = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.laba), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE p.tanggal = CURDATE()
      AND UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
");

$monthProfit = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.laba), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE YEAR(p.tanggal) = YEAR(CURDATE())
      AND MONTH(p.tanggal) = MONTH(CURDATE())
      AND UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
");

$yearProfit = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.laba), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE YEAR(p.tanggal) = YEAR(CURDATE())
      AND UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
");

$totalProfit = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.laba), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
");

/*
|--------------------------------------------------------------------------
| KAS AKTUAL
|--------------------------------------------------------------------------
| Berbeda dengan nilai penjualan/pembelian:
| pemasukan dan pengeluaran menunjukkan uang yang benar-benar masuk/keluar.
*/
$todayIncome = getScalar($pdo, "
    SELECT COALESCE(SUM(nominal), 0)
    FROM pemasukan
    WHERE tanggal = CURDATE()
");

$monthIncome = getScalar($pdo, "
    SELECT COALESCE(SUM(nominal), 0)
    FROM pemasukan
    WHERE YEAR(tanggal) = YEAR(CURDATE())
      AND MONTH(tanggal) = MONTH(CURDATE())
");

$monthExpense = getScalar($pdo, "
    SELECT COALESCE(SUM(nominal), 0)
    FROM pengeluaran
    WHERE YEAR(tanggal) = YEAR(CURDATE())
      AND MONTH(tanggal) = MONTH(CURDATE())
");

$monthNetCash = (float)$monthIncome - (float)$monthExpense;

/*
|--------------------------------------------------------------------------
| PIUTANG PENJUALAN
|--------------------------------------------------------------------------
*/
$totalSalesToDate = getScalar($pdo, "
    SELECT COALESCE(SUM(total), 0)
    FROM penjualan
    WHERE tanggal <= CURDATE()
      AND UPPER(status) <> 'BATAL'
");

$totalSalesPaidToDate = getScalar($pdo, "
    SELECT COALESCE(SUM(nominal), 0)
    FROM pemasukan
    WHERE sumber = 'PENJUALAN'
      AND tanggal <= CURDATE()
");

$receivable = max(
    0,
    (float)$totalSalesToDate - (float)$totalSalesPaidToDate
);

/*
|--------------------------------------------------------------------------
| HUTANG PEMBELIAN
|--------------------------------------------------------------------------
| Jadwal pembayaran pembelian alat berat yang belum PAID.
| Pembelian sparepart tidak menggunakan termin.
*/
$purchaseDebt = getScalar($pdo, "
    SELECT COALESCE(SUM(nominal), 0)
    FROM pembelian_pembayaran
    WHERE status = 'BELUM_BAYAR'
      AND tanggal_jatuh_tempo <= CURDATE()
");

/*
|--------------------------------------------------------------------------
| MASTER
|--------------------------------------------------------------------------
*/
$totalUnit = getScalar($pdo, "SELECT COUNT(*) FROM alat_berat");

$unitTersedia = getScalar($pdo, "
    SELECT COUNT(*)
    FROM alat_berat
    WHERE UPPER(status) IN ('TERSEDIA', 'SIAP JUAL')
");

$totalSparepart = getScalar($pdo, "SELECT COUNT(*) FROM sparepart");
$totalCustomer = getScalar($pdo, "SELECT COUNT(*) FROM customer");
$totalSupplier = getScalar($pdo, "SELECT COUNT(*) FROM supplier");
$totalKaryawan = getScalar($pdo, "SELECT COUNT(*) FROM karyawan");

/*
|--------------------------------------------------------------------------
| GRAFIK 12 BULAN
|--------------------------------------------------------------------------
| Satu grafik menampilkan:
| 1. Penjualan
| 2. Pemasukan kas aktual
| 3. Pengeluaran kas aktual
|--------------------------------------------------------------------------
*/
$monthlyLabels = array();
$monthlySales = array();
$monthlyIncome = array();
$monthlyExpense = array();

for ($i = 11; $i >= 0; $i--) {

    $date = new DateTime();
    $date->modify("-" . $i . " months");

    $year = $date->format('Y');
    $month = $date->format('m');

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(pd.subtotal), 0)
        FROM penjualan_detail pd
        INNER JOIN penjualan p ON p.id = pd.penjualan_id
        WHERE YEAR(p.tanggal) = :tahun
          AND MONTH(p.tanggal) = :bulan
          AND UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
    ");

    $stmt->execute(array(
        'tahun' => $year,
        'bulan' => $month
    ));

    $monthlySales[] = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(nominal), 0)
        FROM pemasukan
        WHERE YEAR(tanggal) = :tahun
          AND MONTH(tanggal) = :bulan
    ");

    $stmt->execute(array(
        'tahun' => $year,
        'bulan' => $month
    ));

    $monthlyIncome[] = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(nominal), 0)
        FROM pengeluaran
        WHERE YEAR(tanggal) = :tahun
          AND MONTH(tanggal) = :bulan
    ");

    $stmt->execute(array(
        'tahun' => $year,
        'bulan' => $month
    ));

    $monthlyExpense[] = (float)$stmt->fetchColumn();

    $monthlyLabels[] = $date->format('M Y');
}

/*
|--------------------------------------------------------------------------
| STATUS UNIT
|--------------------------------------------------------------------------
*/
$unitStatusRows = $pdo->query("
    SELECT status, COUNT(*) AS jumlah
    FROM alat_berat
    GROUP BY status
    ORDER BY jumlah DESC
")->fetchAll();

$unitStatusLabels = array();
$unitStatusValues = array();

foreach ($unitStatusRows as $row) {
    $unitStatusLabels[] = $row['status'];
    $unitStatusValues[] = (int)$row['jumlah'];
}

/*
|--------------------------------------------------------------------------
| TRANSAKSI TERBARU - PENJUALAN
|--------------------------------------------------------------------------
*/
$recentSales = $pdo->query("
    SELECT
        p.nomor_penjualan,
        p.tanggal,
        c.nama,
        COALESCE(SUM(pd.subtotal), 0) AS total
    FROM penjualan p
    INNER JOIN customer c ON c.id = p.customer_id
    LEFT JOIN penjualan_detail pd ON pd.penjualan_id = p.id
    WHERE UPPER(p.status) <> 'BATAL'
    GROUP BY p.id, p.nomor_penjualan, p.tanggal, c.nama
    ORDER BY p.tanggal DESC, p.id DESC
    LIMIT 5
")->fetchAll();

/*
|--------------------------------------------------------------------------
| KAS TERBARU
|--------------------------------------------------------------------------
*/
$recentCash = $pdo->query("
    SELECT *
    FROM (
        SELECT
            pm.id,
            pm.tanggal,
            pm.nomor_pemasukan AS nomor,
            'PEMASUKAN' AS tipe,
            CASE
                WHEN pm.sumber = 'PENJUALAN' THEN 'Penjualan'
                ELSE COALESCE(k.nama, 'Pendapatan Non Penjualan')
            END AS kategori,
            pm.nominal AS nominal
        FROM pemasukan pm
        LEFT JOIN kategori_keuangan k ON k.id = pm.kategori_id

        UNION ALL

        SELECT
            pe.id,
            pe.tanggal,
            pe.nomor_pengeluaran AS nomor,
            'PENGELUARAN' AS tipe,
            CASE
                WHEN pe.sumber = 'PEMBELIAN' THEN 'Belanja Pembelian'
                ELSE COALESCE(k2.nama, 'Tanpa Kategori')
            END AS kategori,
            pe.nominal AS nominal
        FROM pengeluaran pe
        LEFT JOIN kategori_keuangan k2 ON k2.id = pe.kategori_id
    ) x
    ORDER BY tanggal DESC, id DESC
    LIMIT 5
")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<style>
.dashboard-finance-grid{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
    margin-bottom:16px;
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

.dashboard-finance-card.income::before{background:#198754}
.dashboard-finance-card.expense::before{background:#dc3545}
.dashboard-finance-card.net::before{background:#0d6efd}
.dashboard-finance-card.receivable::before{background:#d97706}
.dashboard-finance-card.payable::before{background:#7c3aed}

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

.dashboard-finance-card.income strong{color:#198754}
.dashboard-finance-card.expense strong{color:#dc3545}
.dashboard-finance-card.net strong{color:#0d6efd}
.dashboard-finance-card.receivable strong{color:#b45f00}
.dashboard-finance-card.payable strong{color:#6d28d9}

.cash-badge{
    display:inline-block;
    padding:3px 7px;
    border-radius:4px;
    font-size:9px;
    font-weight:700;
}

.cash-in{
    background:#d1e7dd;
    color:#0f5132;
}

.cash-out{
    background:#f8d7da;
    color:#842029;
}

.cash-positive{
    color:#198754;
    font-weight:700;
}

.cash-negative{
    color:#dc3545;
    font-weight:700;
}

@media(max-width:1250px){
    .dashboard-finance-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
}

@media(max-width:800px){
    .dashboard-finance-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:600px){
    .dashboard-finance-grid{
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-heading">
    <div>
        <h1>Dashboard</h1>
        <p>Ringkasan aktivitas dan kondisi bisnis ASRINDO</p>
    </div>
</section>

<!-- =========================================================
     RINGKASAN KAS
========================================================== -->
<section class="dashboard-finance-grid">

    <article class="dashboard-finance-card income">
        <strong><?php echo rupiah($monthIncome); ?></strong>
        <span>Pemasukan Kas Bulan Ini</span>
    </article>

    <article class="dashboard-finance-card expense">
        <strong><?php echo rupiah($monthExpense); ?></strong>
        <span>Pengeluaran Kas Bulan Ini</span>
    </article>

    <article class="dashboard-finance-card net">
        <strong><?php echo rupiah($monthNetCash); ?></strong>
        <span>Arus Kas Bersih Bulan Ini</span>
    </article>

    <article class="dashboard-finance-card receivable">
        <strong><?php echo rupiah($receivable); ?></strong>
        <span>Piutang Penjualan</span>
    </article>

    <article class="dashboard-finance-card payable">
        <strong><?php echo rupiah($purchaseDebt); ?></strong>
        <span>Hutang Pembelian Jatuh Tempo</span>
    </article>

</section>

<!-- =========================================================
     PENJUALAN
========================================================== -->
<section class="stat-grid stat-grid-four">

    <article class="stat-card stat-red">
        <div>
            <strong><?php echo rupiah($todaySales); ?></strong>
            <span>Penjualan Hari Ini</span>
        </div>
        <div class="stat-icon">▥</div>
    </article>

    <article class="stat-card stat-blue">
        <div>
            <strong><?php echo rupiah($monthSales); ?></strong>
            <span>Penjualan Bulan Ini</span>
        </div>
        <div class="stat-icon">▥</div>
    </article>

    <article class="stat-card stat-orange">
        <div>
            <strong><?php echo rupiah($yearSales); ?></strong>
            <span>Penjualan Tahun Ini</span>
        </div>
        <div class="stat-icon">▥</div>
    </article>

    <article class="stat-card stat-dark">
        <div>
            <strong><?php echo rupiah($totalSales); ?></strong>
            <span>Total Seluruh Penjualan</span>
        </div>
        <div class="stat-icon">▥</div>
    </article>

</section>

<!-- =========================================================
     LABA
========================================================== -->
<section class="stat-grid stat-grid-four">

    <article class="stat-card stat-red">
        <div>
            <strong><?php echo rupiah($todayProfit); ?></strong>
            <span>Laba Hari Ini</span>
        </div>
        <div class="stat-icon">▥</div>
    </article>

    <article class="stat-card stat-blue">
        <div>
            <strong><?php echo rupiah($monthProfit); ?></strong>
            <span>Laba Bulan Ini</span>
        </div>
        <div class="stat-icon">▥</div>
    </article>

    <article class="stat-card stat-orange">
        <div>
            <strong><?php echo rupiah($yearProfit); ?></strong>
            <span>Laba Tahun Ini</span>
        </div>
        <div class="stat-icon">▥</div>
    </article>

    <article class="stat-card stat-dark">
        <div>
            <strong><?php echo rupiah($totalProfit); ?></strong>
            <span>Total Seluruh Laba</span>
        </div>
        <div class="stat-icon">▥</div>
    </article>

</section>

<!-- =========================================================
     MASTER
========================================================== -->
<section class="stat-grid stat-grid-six">

    <article class="mini-stat">
        <strong><?php echo number_format($totalUnit, 0, ',', '.'); ?></strong>
        <span>Total Unit</span>
    </article>

    <article class="mini-stat">
        <strong><?php echo number_format($unitTersedia, 0, ',', '.'); ?></strong>
        <span>Unit Tersedia</span>
    </article>

    <article class="mini-stat">
        <strong><?php echo number_format($totalSparepart, 0, ',', '.'); ?></strong>
        <span>Jumlah Sparepart</span>
    </article>

    <article class="mini-stat">
        <strong><?php echo number_format($totalCustomer, 0, ',', '.'); ?></strong>
        <span>Jumlah Customer</span>
    </article>

    <article class="mini-stat">
        <strong><?php echo number_format($totalSupplier, 0, ',', '.'); ?></strong>
        <span>Jumlah Supplier</span>
    </article>

    <article class="mini-stat">
        <strong><?php echo number_format($totalKaryawan, 0, ',', '.'); ?></strong>
        <span>Jumlah Karyawan</span>
    </article>

</section>

<!-- =========================================================
     GRAFIK
========================================================== -->
<section class="dashboard-grid">

    <article class="dashboard-panel">
        <div class="panel-heading">
            <h2>Penjualan & Arus Kas 12 Bulan</h2>
        </div>

        <div class="chart-box">
            <canvas id="financialTrendChart"></canvas>
        </div>
    </article>

    <article class="dashboard-panel">
        <div class="panel-heading">
            <h2>Status Unit Alat Berat</h2>
        </div>

        <div class="chart-box chart-small">
            <canvas id="unitStatusChart"></canvas>
        </div>
    </article>

</section>

<!-- =========================================================
     TRANSAKSI TERBARU
========================================================== -->
<section class="dashboard-grid">

    <article class="dashboard-panel">

        <div class="panel-heading">
            <h2>Penjualan Terbaru</h2>
            <a href="penjualan.php">Lihat semua</a>
        </div>

        <div class="table-responsive">

            <table class="dashboard-table">

                <thead>
                    <tr>
                        <th>No. Penjualan</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($recentSales)): ?>

                    <tr>
                        <td colspan="4" class="empty-state">
                            Belum ada transaksi.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($recentSales as $row): ?>

                        <tr>
                            <td>
                                <?php echo htmlspecialchars($row['nomor_penjualan'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <?php echo date('d-m-Y', strtotime($row['tanggal'])); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <?php echo rupiah($row['total']); ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </article>

    <article class="dashboard-panel">

        <div class="panel-heading">
            <h2>Kas Terbaru</h2>
            <a href="pemasukan.php">Lihat pemasukan</a>
        </div>

        <div class="table-responsive">

            <table class="dashboard-table">

                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Kategori</th>
                        <th>Nominal</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($recentCash)): ?>

                    <tr>
                        <td colspan="4" class="empty-state">
                            Belum ada transaksi kas.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($recentCash as $row): ?>

                        <tr>

                            <td>
                                <?php echo date('d-m-Y', strtotime($row['tanggal'])); ?>
                            </td>

                            <td>

                                <?php if ($row['tipe'] === 'PEMASUKAN'): ?>

                                    <span class="cash-badge cash-in">
                                        PEMASUKAN
                                    </span>

                                <?php else: ?>

                                    <span class="cash-badge cash-out">
                                        PENGELUARAN
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $row['kategori'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td class="<?php echo $row['tipe'] === 'PEMASUKAN'
                                ? 'cash-positive'
                                : 'cash-negative'; ?>">
                                <?php echo rupiah($row['nominal']); ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </article>

</section>

<!-- =========================================================
     CHART.JS
========================================================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
var monthlyLabels = <?php echo json_encode($monthlyLabels); ?>;
var monthlySales = <?php echo json_encode($monthlySales); ?>;
var monthlyIncome = <?php echo json_encode($monthlyIncome); ?>;
var monthlyExpense = <?php echo json_encode($monthlyExpense); ?>;

var unitStatusLabels = <?php echo json_encode($unitStatusLabels); ?>;
var unitStatusValues = <?php echo json_encode($unitStatusValues); ?>;

var financialCanvas = document.getElementById('financialTrendChart');

if (financialCanvas) {

    new Chart(financialCanvas, {
        type: 'bar',

        data: {
            labels: monthlyLabels,

            datasets: [
                {
                    label: 'Penjualan',
                    data: monthlySales,
                    borderWidth: 1
                },
                {
                    label: 'Pemasukan Kas',
                    data: monthlyIncome,
                    borderWidth: 1
                },
                {
                    label: 'Pengeluaran Kas',
                    data: monthlyExpense,
                    borderWidth: 1
                }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    position: 'bottom'
                }
            },

            scales: {
                y: {
                    beginAtZero: true,

                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + Number(value).toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
}

var statusCanvas = document.getElementById('unitStatusChart');

if (statusCanvas) {

    new Chart(statusCanvas, {
        type: 'doughnut',

        data: {
            labels: unitStatusLabels,

            datasets: [{
                data: unitStatusValues,
                borderWidth: 1
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
