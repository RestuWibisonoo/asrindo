<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Laporan Keuangan';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)($value ?? 0), 0, ',', '.');
}

/*
|--------------------------------------------------------------------------
| FILTER PERIODE
|--------------------------------------------------------------------------
*/
$tanggalAwal = trim((string)($_GET['tanggal_awal'] ?? date('Y-m-01')));
$tanggalAkhir = trim((string)($_GET['tanggal_akhir'] ?? date('Y-m-d')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal)) {
    $tanggalAwal = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAkhir)) {
    $tanggalAkhir = date('Y-m-d');
}

if ($tanggalAwal > $tanggalAkhir) {
    [$tanggalAwal, $tanggalAkhir] = [$tanggalAkhir, $tanggalAwal];
}

/*
|--------------------------------------------------------------------------
| PEMASUKAN AKTUAL
|--------------------------------------------------------------------------
| Pemasukan penjualan dibuat ketika pembayaran termin ditandai PAID.
| Pemasukan non-penjualan dibuat manual.
*/
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(nominal), 0) AS total
    FROM pemasukan
    WHERE tanggal BETWEEN ? AND ?
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$incomeSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("
    SELECT
        sumber,
        COUNT(*) AS jumlah,
        COALESCE(SUM(nominal), 0) AS total
    FROM pemasukan
    WHERE tanggal BETWEEN ? AND ?
    GROUP BY sumber
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);

$incomeBySource = [
    'PENJUALAN' => 0.0,
    'NON_PENJUALAN' => 0.0
];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $incomeBySource[$row['sumber']] = (float)$row['total'];
}

/*
|--------------------------------------------------------------------------
| PENGELUARAN AKTUAL
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(nominal), 0) AS total
    FROM pengeluaran
    WHERE tanggal BETWEEN ? AND ?
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$expenseSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("
    SELECT
        sumber,
        COUNT(*) AS jumlah,
        COALESCE(SUM(nominal), 0) AS total
    FROM pengeluaran
    WHERE tanggal BETWEEN ? AND ?
    GROUP BY sumber
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);

$expenseBySource = [
    'PEMBELIAN' => 0.0,
    'NON_PEMBELIAN' => 0.0
];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $expenseBySource[$row['sumber']] = (float)$row['total'];
}

/*
|--------------------------------------------------------------------------
| ARUS KAS
|--------------------------------------------------------------------------
*/
$totalPemasukan = (float)($incomeSummary['total'] ?? 0);
$totalPengeluaran = (float)($expenseSummary['total'] ?? 0);
$arusKasBersih = $totalPemasukan - $totalPengeluaran;

/*
|--------------------------------------------------------------------------
| PIUTANG PENJUALAN
|--------------------------------------------------------------------------
| Total penjualan aktif dikurangi pemasukan aktual yang berasal dari
| penjualan. PPN termasuk dalam total invoice karena pembayaran mengikuti
| grand total penjualan.
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(p.total), 0) AS total_penjualan
    FROM penjualan p
    WHERE UPPER(p.status) <> 'BATAL'
      AND p.tanggal <= ?
");
$stmt->execute([$tanggalAkhir]);
$totalPenjualanSampaiTanggal = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(pm.nominal), 0) AS total_bayar_penjualan
    FROM pemasukan pm
    WHERE pm.sumber = 'PENJUALAN'
      AND pm.tanggal <= ?
");
$stmt->execute([$tanggalAkhir]);
$totalBayarPenjualanSampaiTanggal = (float)$stmt->fetchColumn();

$piutangPenjualan = max(
    0,
    $totalPenjualanSampaiTanggal - $totalBayarPenjualanSampaiTanggal
);

/*
|--------------------------------------------------------------------------
| PEMBELIAN / HUTANG PEMBELIAN
|--------------------------------------------------------------------------
| Untuk alat berat, kewajiban berasal dari jadwal pembayaran.
| Untuk sparepart, status SELESAI langsung dicatat sebagai pengeluaran.
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(nominal), 0) AS total
    FROM pembelian_pembayaran
    WHERE status <> 'BATAL'
      AND tanggal_jatuh_tempo <= ?
");
$stmt->execute([$tanggalAkhir]);
$totalJadwalPembelian = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(nominal), 0) AS total
    FROM pembelian_pembayaran
    WHERE status = 'PAID'
      AND tanggal_bayar <= ?
");
$stmt->execute([$tanggalAkhir]);
$totalBayarPembelianAlat = (float)$stmt->fetchColumn();

$hutangAlatBerat = max(
    0,
    $totalJadwalPembelian - $totalBayarPembelianAlat
);

/*
 * Sparepart:
 * pembelian yang SELESAI dianggap sudah dibayar.
 * Yang belum selesai tidak dianggap sebagai hutang kas pada desain saat ini.
 */
$hutangPembelian = $hutangAlatBerat;

/*
|--------------------------------------------------------------------------
| PENJUALAN PERIODE
|--------------------------------------------------------------------------
*/
/*
 * Tabel penjualan tidak memiliki kolom p.subtotal.
 * Subtotal penjualan berasal dari SUM(penjualan_detail.subtotal).
 * PPN dihitung dari subtotal tersebut, sedangkan total mengikuti
 * nilai total yang tersimpan pada header penjualan.
 */
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(x.subtotal), 0) AS subtotal,
        COALESCE(SUM(x.ppn_nominal), 0) AS ppn,
        COALESCE(SUM(x.total), 0) AS total
    FROM (
        SELECT
            p.id,
            COALESCE((
                SELECT SUM(pd.subtotal)
                FROM penjualan_detail pd
                WHERE pd.penjualan_id = p.id
            ), 0) AS subtotal,
            (
                COALESCE((
                    SELECT SUM(pd.subtotal)
                    FROM penjualan_detail pd
                    WHERE pd.penjualan_id = p.id
                ), 0) * COALESCE(p.ppn, 0) / 100
            ) AS ppn_nominal,
            COALESCE(p.total, 0) AS total
        FROM penjualan p
        WHERE p.tanggal BETWEEN ? AND ?
          AND UPPER(COALESCE(p.status, '')) <> 'BATAL'
    ) x
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$salesSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/*
|--------------------------------------------------------------------------
| PEMBELIAN PERIODE
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(total), 0) AS total
    FROM pembelian_sparepart
    WHERE tanggal BETWEEN ? AND ?
      AND UPPER(status) <> 'BATAL'
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$sparepartPurchaseSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(total), 0) AS total
    FROM pembelian_alat_berat
    WHERE tanggal BETWEEN ? AND ?
      AND UPPER(status) <> 'BATAL'
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$heavyPurchaseSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/*
|--------------------------------------------------------------------------
| LABA PENJUALAN
|--------------------------------------------------------------------------
| Laba diambil dari penjualan_detail agar menggunakan HPP yang tersimpan
| pada saat transaksi.
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(pd.subtotal), 0) AS penjualan,
        COALESCE(SUM(pd.hpp), 0) AS hpp,
        COALESCE(SUM(pd.laba), 0) AS laba
    FROM penjualan_detail pd
    INNER JOIN penjualan p
        ON p.id = pd.penjualan_id
    WHERE p.tanggal BETWEEN ? AND ?
      AND UPPER(p.status) <> 'BATAL'
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$profitSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/*
|--------------------------------------------------------------------------
| RINGKASAN BULANAN
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        periode,
        SUM(pemasukan) AS pemasukan,
        SUM(pengeluaran) AS pengeluaran
    FROM (
        SELECT
            DATE_FORMAT(tanggal, '%Y-%m') AS periode,
            nominal AS pemasukan,
            0 AS pengeluaran
        FROM pemasukan
        WHERE tanggal BETWEEN ? AND ?

        UNION ALL

        SELECT
            DATE_FORMAT(tanggal, '%Y-%m') AS periode,
            0 AS pemasukan,
            nominal AS pengeluaran
        FROM pengeluaran
        WHERE tanggal BETWEEN ? AND ?
    ) x
    GROUP BY periode
    ORDER BY periode ASC
");
$stmt->execute([
    $tanggalAwal,
    $tanggalAkhir,
    $tanggalAwal,
    $tanggalAkhir
]);
$monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| KATEGORI PENGELUARAN
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(k.nama, 'Tanpa Kategori') AS kategori,
        COUNT(*) AS jumlah,
        COALESCE(SUM(e.nominal), 0) AS total
    FROM pengeluaran e
    LEFT JOIN kategori_keuangan k
        ON k.id = e.kategori_id
    WHERE e.tanggal BETWEEN ? AND ?
    GROUP BY e.kategori_id, k.nama
    ORDER BY total DESC
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$expenseCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$topExpenseCategory = $expenseCategories[0] ?? null;

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.report-page{
    max-width:1600px;
    margin:0 auto;
}

.report-heading{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:20px;
    margin-bottom:18px;
}

.report-heading h1{
    margin:0;
    color:#172b4d;
}

.report-heading p{
    margin:5px 0 0;
    color:#718096;
    font-size:13px;
}

.period-form{
    display:flex;
    align-items:end;
    gap:10px;
    background:#fff;
    border:1px solid #dce3eb;
    border-radius:7px;
    padding:10px;
}

.period-field{
    min-width:145px;
}

.period-field label{
    display:block;
    margin-bottom:5px;
    color:#718096;
    font-size:11px;
}

.period-field input{
    width:100%;
    box-sizing:border-box;
    border:1px solid #b9c7d8;
    border-radius:5px;
    padding:8px 9px;
    font-size:12px;
}

.btn-report{
    border:1px solid #0d6efd;
    background:#0d6efd;
    color:#fff;
    border-radius:5px;
    padding:9px 14px;
    cursor:pointer;
    font-weight:600;
    font-size:12px;
}

.btn-report:hover{
    background:#0b5ed7;
}

.report-section-title{
    margin:24px 0 10px;
    color:#172b4d;
    font-size:15px;
    font-weight:700;
}

.finance-cards{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
}

.finance-card{
    position:relative;
    background:#fff;
    border:1px solid #dce3eb;
    border-radius:7px;
    padding:17px 16px;
    min-width:0;
    box-shadow:0 1px 2px rgba(23,43,77,.04);
}

.finance-card::before{
    content:'';
    position:absolute;
    left:0;
    top:10px;
    bottom:10px;
    width:3px;
    border-radius:0 3px 3px 0;
    background:#b8c7d8;
}

.finance-card.income::before{background:#198754}
.finance-card.expense::before{background:#dc3545}
.finance-card.net::before{background:#0d6efd}
.finance-card.receivable::before{background:#d97706}
.finance-card.payable::before{background:#7c3aed}

.finance-value{
    display:block;
    font-size:20px;
    font-weight:700;
    color:#172b4d;
    margin-bottom:7px;
    padding-left:4px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.finance-card.income .finance-value{color:#198754}
.finance-card.expense .finance-value{color:#dc3545}
.finance-card.net .finance-value{color:#0d6efd}
.finance-card.receivable .finance-value{color:#b45f00}
.finance-card.payable .finance-value{color:#6d28d9}

.finance-label{
    display:block;
    padding-left:4px;
    color:#526b8d;
    font-size:12px;
}

.dashboard-grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:14px;
}

.dashboard-grid.equal{
    grid-template-columns:1fr 1fr;
}

.report-card{
    background:#fff;
    border:1px solid #dce3eb;
    border-radius:7px;
    overflow:hidden;
}

.report-card-header{
    padding:13px 16px;
    border-bottom:1px solid #dce3eb;
    font-weight:700;
    color:#172b4d;
}

.report-card-header small{
    display:block;
    margin-top:3px;
    color:#8390a3;
    font-size:11px;
    font-weight:400;
}

.report-card-body{
    padding:16px;
}

.mini-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
}

.mini-box{
    border:1px solid #e1e7ef;
    border-radius:6px;
    padding:13px;
}

.mini-box .label{
    color:#718096;
    font-size:11px;
    margin-bottom:6px;
}

.mini-box .value{
    color:#172b4d;
    font-weight:700;
    font-size:17px;
}

.mini-box .sub{
    color:#8390a3;
    font-size:10px;
    margin-top:4px;
}

.progress-row{
    margin-bottom:14px;
}

.progress-row:last-child{
    margin-bottom:0;
}

.progress-label{
    display:flex;
    justify-content:space-between;
    gap:10px;
    font-size:11px;
    color:#526b8d;
    margin-bottom:5px;
}

.progress-track{
    height:7px;
    background:#edf1f5;
    border-radius:10px;
    overflow:hidden;
}

.progress-fill{
    height:100%;
    background:#7a8da6;
    border-radius:10px;
}

.report-table{
    width:100%;
    border-collapse:collapse;
}

.report-table th{
    background:#f5f7fa;
    color:#526b8d;
    font-size:11px;
    text-align:left;
    padding:9px 10px;
    border-bottom:1px solid #dce3eb;
}

.report-table td{
    padding:9px 10px;
    font-size:12px;
    border-bottom:1px solid #e8edf2;
}

.report-table .money{
    text-align:right;
    white-space:nowrap;
    font-weight:600;
}

.report-table .right{
    text-align:right;
}

.net-positive{
    color:#198754;
    font-weight:700;
}

.net-negative{
    color:#dc3545;
    font-weight:700;
}

.empty-report{
    padding:20px;
    text-align:center;
    color:#8390a3;
    font-size:12px;
}

.status-info{
    padding:8px 10px;
    border-radius:5px;
    background:#f5f7fa;
    color:#526b8d;
    font-size:11px;
    line-height:1.5;
}

.chart-wrap{
    min-height:230px;
    display:flex;
    align-items:flex-end;
    gap:8px;
    padding:10px 4px 0;
    overflow-x:auto;
}

.chart-column{
    flex:1;
    min-width:45px;
    height:205px;
    display:flex;
    flex-direction:column;
    justify-content:flex-end;
    align-items:center;
    gap:6px;
}

.chart-bars{
    width:100%;
    max-width:52px;
    height:170px;
    display:flex;
    align-items:flex-end;
    justify-content:center;
    gap:3px;
}

.chart-bar{
    width:18px;
    min-height:2px;
    border-radius:3px 3px 0 0;
}

.chart-bar.in{
    background:#5b8def;
}

.chart-bar.out{
    background:#d98282;
}

.chart-month{
    color:#718096;
    font-size:9px;
    white-space:nowrap;
}

.chart-legend{
    display:flex;
    gap:16px;
    justify-content:center;
    margin-top:8px;
    color:#718096;
    font-size:10px;
}

.legend-item{
    display:flex;
    align-items:center;
    gap:5px;
}

.legend-dot{
    width:9px;
    height:9px;
    border-radius:2px;
}

.legend-in{background:#5b8def}
.legend-out{background:#d98282}

.report-note{
    margin-top:10px;
    color:#8390a3;
    font-size:10px;
    line-height:1.5;
}

@media(max-width:1250px){
    .finance-cards{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .dashboard-grid,
    .dashboard-grid.equal{
        grid-template-columns:1fr;
    }
}

@media(max-width:800px){
    .report-heading{
        flex-direction:column;
        align-items:stretch;
    }

    .period-form{
        flex-wrap:wrap;
    }

    .period-field{
        flex:1;
    }

    .finance-cards{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .mini-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:600px){
    .finance-cards{
        grid-template-columns:1fr;
    }

    .period-form{
        display:grid;
        grid-template-columns:1fr 1fr;
    }

    .period-field{
        min-width:0;
    }

    .period-form .btn-report{
        grid-column:1/-1;
    }
}
</style>

<div class="report-page">

    <div class="report-heading">
        <div>
            <h1>Laporan Keuangan</h1>
            <p>
                Dashboard ringkasan seluruh data yang berhubungan dengan keuangan ASRINDO.
            </p>
        </div>

        <form method="get" class="period-form">
            <div class="period-field">
                <label>Tanggal Awal</label>
                <input
                    type="date"
                    name="tanggal_awal"
                    value="<?php echo h($tanggalAwal); ?>"
                    required
                >
            </div>

            <div class="period-field">
                <label>Tanggal Akhir</label>
                <input
                    type="date"
                    name="tanggal_akhir"
                    value="<?php echo h($tanggalAkhir); ?>"
                    required
                >
            </div>

            <button type="submit" class="btn-report">
                Tampilkan
            </button>
        </form>
    </div>

    <div class="status-info">
        Periode laporan:
        <strong>
            <?php echo h(date('d-M-Y', strtotime($tanggalAwal))); ?>
        </strong>
        sampai
        <strong>
            <?php echo h(date('d-M-Y', strtotime($tanggalAkhir))); ?>
        </strong>
    </div>

    <div class="report-section-title">
        Ringkasan Keuangan
    </div>

    <div class="finance-cards">

        <div class="finance-card income">
            <div class="finance-value">
                <?php echo rupiah($totalPemasukan); ?>
            </div>
            <div class="finance-label">
                Total Pemasukan
            </div>
        </div>

        <div class="finance-card expense">
            <div class="finance-value">
                <?php echo rupiah($totalPengeluaran); ?>
            </div>
            <div class="finance-label">
                Total Pengeluaran
            </div>
        </div>

        <div class="finance-card net">
            <div class="finance-value">
                <?php echo rupiah($arusKasBersih); ?>
            </div>
            <div class="finance-label">
                Arus Kas Bersih
            </div>
        </div>

        <div class="finance-card receivable">
            <div class="finance-value">
                <?php echo rupiah($piutangPenjualan); ?>
            </div>
            <div class="finance-label">
                Piutang Penjualan
            </div>
        </div>

        <div class="finance-card payable">
            <div class="finance-value">
                <?php echo rupiah($hutangPembelian); ?>
            </div>
            <div class="finance-label">
                Hutang Pembelian
            </div>
        </div>

    </div>

    <div class="report-section-title">
        Aktivitas Pendapatan & Pengeluaran
    </div>

    <div class="dashboard-grid">

        <div class="report-card">
            <div class="report-card-header">
                Pemasukan
                <small>Uang yang benar-benar masuk pada periode terpilih</small>
            </div>

            <div class="report-card-body">
                <div class="mini-grid">

                    <div class="mini-box">
                        <div class="label">Dari Penjualan</div>
                        <div class="value">
                            <?php echo rupiah($incomeBySource['PENJUALAN']); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Non Penjualan</div>
                        <div class="value">
                            <?php echo rupiah($incomeBySource['NON_PENJUALAN']); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Transaksi</div>
                        <div class="value">
                            <?php echo number_format(
                                (int)($incomeSummary['jumlah_transaksi'] ?? 0),
                                0,
                                ',',
                                '.'
                            ); ?>
                        </div>
                        <div class="sub">Transaksi pemasukan aktual</div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Penjualan Belum Tertagih</div>
                        <div class="value">
                            <?php echo rupiah($piutangPenjualan); ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="report-card">
            <div class="report-card-header">
                Pengeluaran
                <small>Uang yang benar-benar keluar pada periode terpilih</small>
            </div>

            <div class="report-card-body">
                <div class="mini-grid">

                    <div class="mini-box">
                        <div class="label">Pembelian</div>
                        <div class="value">
                            <?php echo rupiah($expenseBySource['PEMBELIAN']); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Non Pembelian</div>
                        <div class="value">
                            <?php echo rupiah($expenseBySource['NON_PEMBELIAN']); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Transaksi</div>
                        <div class="value">
                            <?php echo number_format(
                                (int)($expenseSummary['jumlah_transaksi'] ?? 0),
                                0,
                                ',',
                                '.'
                            ); ?>
                        </div>
                        <div class="sub">Transaksi pengeluaran aktual</div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Kategori Terbesar</div>
                        <div class="value">
                            <?php
                            echo h(
                                $topExpenseCategory['kategori']
                                ?? '-'
                            );
                            ?>
                        </div>
                        <div class="sub">
                            <?php
                            echo $topExpenseCategory
                                ? rupiah($topExpenseCategory['total'])
                                : 'Belum ada data';
                            ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <div class="report-section-title">
        Penjualan & Pembelian
    </div>

    <div class="dashboard-grid equal">

        <div class="report-card">
            <div class="report-card-header">
                Penjualan
                <small>Transaksi penjualan pada periode laporan</small>
            </div>

            <div class="report-card-body">
                <div class="mini-grid">

                    <div class="mini-box">
                        <div class="label">Jumlah Transaksi</div>
                        <div class="value">
                            <?php echo number_format(
                                (int)($salesSummary['jumlah'] ?? 0),
                                0,
                                ',',
                                '.'
                            ); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Subtotal</div>
                        <div class="value">
                            <?php echo rupiah($salesSummary['subtotal'] ?? 0); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">PPN</div>
                        <div class="value">
                            <?php echo rupiah($salesSummary['ppn'] ?? 0); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Total Penjualan</div>
                        <div class="value">
                            <?php echo rupiah($salesSummary['total'] ?? 0); ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="report-card">
            <div class="report-card-header">
                Pembelian
                <small>Transaksi pembelian pada periode laporan</small>
            </div>

            <div class="report-card-body">
                <div class="mini-grid">

                    <div class="mini-box">
                        <div class="label">Pembelian Alat Berat</div>
                        <div class="value">
                            <?php echo rupiah($heavyPurchaseSummary['total'] ?? 0); ?>
                        </div>
                        <div class="sub">
                            <?php echo number_format(
                                (int)($heavyPurchaseSummary['jumlah'] ?? 0),
                                0,
                                ',',
                                '.'
                            ); ?> transaksi
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Pembelian Sparepart</div>
                        <div class="value">
                            <?php echo rupiah($sparepartPurchaseSummary['total'] ?? 0); ?>
                        </div>
                        <div class="sub">
                            <?php echo number_format(
                                (int)($sparepartPurchaseSummary['jumlah'] ?? 0),
                                0,
                                ',',
                                '.'
                            ); ?> transaksi
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Total Pembelian</div>
                        <div class="value">
                            <?php echo rupiah(
                                (float)($heavyPurchaseSummary['total'] ?? 0)
                                + (float)($sparepartPurchaseSummary['total'] ?? 0)
                            ); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Hutang Pembelian</div>
                        <div class="value">
                            <?php echo rupiah($hutangPembelian); ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <div class="report-section-title">
        Profitabilitas Penjualan
    </div>

    <div class="report-card">
        <div class="report-card-body">
            <div class="mini-grid">

                <div class="mini-box">
                    <div class="label">Nilai Penjualan</div>
                    <div class="value">
                        <?php echo rupiah($profitSummary['penjualan'] ?? 0); ?>
                    </div>
                </div>

                <div class="mini-box">
                    <div class="label">HPP</div>
                    <div class="value">
                        <?php echo rupiah($profitSummary['hpp'] ?? 0); ?>
                    </div>
                </div>

                <div class="mini-box">
                    <div class="label">Laba Kotor</div>
                    <div class="value net-positive">
                        <?php echo rupiah($profitSummary['laba'] ?? 0); ?>
                    </div>
                </div>

                <div class="mini-box">
                    <div class="label">Margin Kotor</div>
                    <div class="value">
                        <?php
                        $profitSales = (float)($profitSummary['penjualan'] ?? 0);
                        $profitValue = (float)($profitSummary['laba'] ?? 0);
                        $margin = $profitSales > 0
                            ? ($profitValue / $profitSales) * 100
                            : 0;
                        echo number_format($margin, 2, ',', '.') . '%';
                        ?>
                    </div>
                </div>

            </div>

            <div class="report-note">
                Laba kotor dihitung berdasarkan subtotal penjualan dikurangi HPP
                yang tersimpan pada detail penjualan. PPN tidak dihitung sebagai laba.
            </div>
        </div>
    </div>

    <div class="report-section-title">
        Tren Arus Kas
    </div>

    <div class="report-card">
        <div class="report-card-header">
            Pemasukan vs Pengeluaran per Bulan
            <small>Agregasi transaksi aktual dalam periode yang dipilih</small>
        </div>

        <div class="report-card-body">

            <?php if (!$monthlyRows): ?>

                <div class="empty-report">
                    Belum ada transaksi keuangan pada periode tersebut.
                </div>

            <?php else: ?>

                <?php
                $maxMonthly = 0.0;

                foreach ($monthlyRows as $monthly) {
                    $maxMonthly = max(
                        $maxMonthly,
                        (float)$monthly['pemasukan'],
                        (float)$monthly['pengeluaran']
                    );
                }
                ?>

                <div class="chart-wrap">

                    <?php foreach ($monthlyRows as $monthly): ?>

                        <?php
                        $inValue = (float)$monthly['pemasukan'];
                        $outValue = (float)$monthly['pengeluaran'];

                        $inHeight = $maxMonthly > 0
                            ? ($inValue / $maxMonthly) * 165
                            : 2;

                        $outHeight = $maxMonthly > 0
                            ? ($outValue / $maxMonthly) * 165
                            : 2;
                        ?>

                        <div class="chart-column">

                            <div
                                class="chart-bars"
                                title="<?php
                                echo h(
                                    $monthly['periode'] .
                                    ' | Masuk: ' . rupiah($inValue) .
                                    ' | Keluar: ' . rupiah($outValue)
                                );
                                ?>"
                            >
                                <div
                                    class="chart-bar in"
                                    style="height:<?php echo max(2, $inHeight); ?>px"
                                ></div>

                                <div
                                    class="chart-bar out"
                                    style="height:<?php echo max(2, $outHeight); ?>px"
                                ></div>
                            </div>

                            <div class="chart-month">
                                <?php echo h($monthly['periode']); ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

                <div class="chart-legend">
                    <div class="legend-item">
                        <span class="legend-dot legend-in"></span>
                        Pemasukan
                    </div>

                    <div class="legend-item">
                        <span class="legend-dot legend-out"></span>
                        Pengeluaran
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <div class="report-section-title">
        Pengeluaran Berdasarkan Kategori
    </div>

    <div class="report-card">

        <?php if (!$expenseCategories): ?>

            <div class="empty-report">
                Belum ada data pengeluaran pada periode tersebut.
            </div>

        <?php else: ?>

            <table class="report-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Transaksi</th>
                        <th class="right">Total</th>
                        <th style="width:35%">Proporsi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($expenseCategories as $category): ?>

                        <?php
                        $categoryTotal = (float)$category['total'];
                        $percentage = $totalPengeluaran > 0
                            ? ($categoryTotal / $totalPengeluaran) * 100
                            : 0;
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?php echo h($category['kategori']); ?>
                                </strong>
                            </td>

                            <td>
                                <?php echo number_format(
                                    (int)$category['jumlah'],
                                    0,
                                    ',',
                                    '.'
                                ); ?>
                            </td>

                            <td class="money">
                                <?php echo rupiah($categoryTotal); ?>
                            </td>

                            <td>
                                <div class="progress-row">
                                    <div class="progress-label">
                                        <span>
                                            <?php echo number_format(
                                                $percentage,
                                                1,
                                                ',',
                                                '.'
                                            ); ?>%
                                        </span>

                                        <span>
                                            <?php echo rupiah($categoryTotal); ?>
                                        </span>
                                    </div>

                                    <div class="progress-track">
                                        <div
                                            class="progress-fill"
                                            style="width:<?php echo min(100, max(0, $percentage)); ?>%"
                                        ></div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        <?php endif; ?>

    </div>

    <div class="report-note">
        Catatan: dashboard ini memisahkan transaksi keuangan aktual
        (pemasukan dan pengeluaran) dari nilai transaksi penjualan/pembelian.
        Dengan demikian penjualan kredit tidak langsung dianggap sebagai kas masuk,
        dan pembayaran pembelian alat berat tidak langsung dianggap sebagai kas keluar
        sebelum termin dibayar.
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
