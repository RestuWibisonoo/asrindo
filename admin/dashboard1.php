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
 * Fungsi mengambil satu nilai dari database.
 * Tidak menggunakan union type agar kompatibel dengan PHP 7.x.
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

/* ==========================================================
   STATISTIK PENJUALAN
   ========================================================== */

$todaySales = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.subtotal), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE p.tanggal = CURDATE()
      AND UPPER(p.status) NOT IN ('DRAFT', 'CANCELLED')
");

$monthSales = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.subtotal), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE YEAR(p.tanggal) = YEAR(CURDATE())
      AND MONTH(p.tanggal) = MONTH(CURDATE())
      AND UPPER(p.status) NOT IN ('DRAFT', 'CANCELLED')
");

$yearSales = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.subtotal), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE YEAR(p.tanggal) = YEAR(CURDATE())
      AND UPPER(p.status) NOT IN ('DRAFT', 'CANCELLED')
");

$totalSales = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.subtotal), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE UPPER(p.status) NOT IN ('DRAFT', 'CANCELLED')
");

/* ==========================================================
   STATISTIK LABA
   ========================================================== */

$todayProfit = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.laba), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE p.tanggal = CURDATE()
      AND UPPER(p.status) NOT IN ('DRAFT', 'CANCELLED')
");

$monthProfit = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.laba), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE YEAR(p.tanggal) = YEAR(CURDATE())
      AND MONTH(p.tanggal) = MONTH(CURDATE())
      AND UPPER(p.status) NOT IN ('DRAFT', 'CANCELLED')
");

$yearProfit = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.laba), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE YEAR(p.tanggal) = YEAR(CURDATE())
      AND UPPER(p.status) NOT IN ('DRAFT', 'CANCELLED')
");

$totalProfit = getScalar($pdo, "
    SELECT COALESCE(SUM(pd.laba), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE UPPER(p.status) NOT IN ('DRAFT', 'CANCELLED')
");

/* ==========================================================
   STATISTIK MASTER
   ========================================================== */

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

/* ==========================================================
   DATA GRAFIK PENJUALAN 12 BULAN
   ========================================================== */

$monthlyLabels = array();
$monthlyValues = array();

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
          AND UPPER(p.status) NOT IN ('DRAFT', 'CANCELLED')
    ");

    $stmt->execute(array(
        'tahun' => $year,
        'bulan' => $month
    ));

    $monthlyLabels[] = $date->format('M');
    $monthlyValues[] = (float)$stmt->fetchColumn();
}

/* ==========================================================
   STATUS UNIT
   ========================================================== */

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

/* ==========================================================
   TRANSAKSI TERBARU
   ========================================================== */

$recentSales = $pdo->query("
    SELECT
        p.nomor_penjualan,
        p.tanggal,
        c.nama,
        COALESCE(SUM(pd.subtotal), 0) AS total
    FROM penjualan p
    INNER JOIN customer c ON c.id = p.customer_id
    LEFT JOIN penjualan_detail pd ON pd.penjualan_id = p.id
    GROUP BY p.id, p.nomor_penjualan, p.tanggal, c.nama
    ORDER BY p.tanggal DESC, p.id DESC
    LIMIT 5
")->fetchAll();

/* ==========================================================
   UNIT TERBARU
   ========================================================== */

$recentUnits = $pdo->query("
    SELECT kode, tipe, status
    FROM alat_berat
    ORDER BY id DESC
    LIMIT 5
")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <div>
        <h1>Dashboard</h1>
        <p>Ringkasan aktivitas dan kondisi bisnis ASRINDO</p>
    </div>
</section>

<!-- PENJUALAN -->
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

<!-- LABA -->
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

<!-- MASTER -->
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

<!-- GRAFIK -->
<section class="dashboard-grid">

    <article class="dashboard-panel">
        <div class="panel-heading">
            <h2>Grafik Penjualan 12 Bulan</h2>
        </div>

        <div class="chart-box">
            <canvas id="monthlySalesChart"></canvas>
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

<!-- TABEL -->
<section class="dashboard-grid">

    <article class="dashboard-panel">

        <div class="panel-heading">
            <h2>Penjualan Terbaru</h2>
            <a href="penjualan/">Lihat semua</a>
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
            <h2>Unit Terbaru</h2>
            <a href="alat_berat/">Lihat semua</a>
        </div>

        <div class="table-responsive">

            <table class="dashboard-table">

                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Tipe</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($recentUnits)): ?>

                    <tr>
                        <td colspan="3" class="empty-state">
                            Belum ada unit.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($recentUnits as $unit): ?>

                        <tr>
                            <td>
                                <?php echo htmlspecialchars($unit['kode'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($unit['tipe'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <span class="status-badge">
                                    <?php echo htmlspecialchars($unit['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </article>

</section>

<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
var monthlyLabels = <?php echo json_encode($monthlyLabels); ?>;
var monthlyValues = <?php echo json_encode($monthlyValues); ?>;

var unitStatusLabels = <?php echo json_encode($unitStatusLabels); ?>;
var unitStatusValues = <?php echo json_encode($unitStatusValues); ?>;

var salesCanvas = document.getElementById('monthlySalesChart');

if (salesCanvas) {

    new Chart(salesCanvas, {
        type: 'bar',

        data: {
            labels: monthlyLabels,

            datasets: [{
                label: 'Penjualan',
                data: monthlyValues,
                borderWidth: 1
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: false
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
