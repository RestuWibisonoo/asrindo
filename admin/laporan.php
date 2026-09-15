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
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) ($value ?? 0), 0, ',', '.');
}

/*
|--------------------------------------------------------------------------
| FILTER PERIODE
|--------------------------------------------------------------------------
*/
$tanggalAwal = trim((string) ($_GET['tanggal_awal'] ?? date('Y-m-01')));
$tanggalAkhir = trim((string) ($_GET['tanggal_akhir'] ?? date('Y-m-d')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal)) {
    $tanggalAwal = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAkhir)) {
    $tanggalAkhir = date('Y-m-d');
}

if ($tanggalAwal > $tanggalAkhir) {
    [$tanggalAwal, $tanggalAkhir] = [$tanggalAkhir, $tanggalAwal];
}

$bulanIndonesia = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];
$tanggalAkhirLabel = date('d', strtotime($tanggalAkhir)) . ' ' .
    $bulanIndonesia[(int) date('n', strtotime($tanggalAkhir))] . ' ' .
    date('Y', strtotime($tanggalAkhir));
$tanggalAwalLabel = date('d', strtotime($tanggalAwal)) . ' ' .
    $bulanIndonesia[(int) date('n', strtotime($tanggalAwal))] . ' ' .
    date('Y', strtotime($tanggalAwal));

/*
|--------------------------------------------------------------------------
| PEMASUKAN AKTUAL
|--------------------------------------------------------------------------
| Penjualan otomatis dicatat sebagai pemasukan ketika pembayaran PAID.
| Non-penjualan menggunakan kategori_keuangan.
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
    if (isset($incomeBySource[$row['sumber']])) {
        $incomeBySource[$row['sumber']] = (float) $row['total'];
    }
}

$totalPemasukan = (float) ($incomeSummary['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| PENGELUARAN AKTUAL
|--------------------------------------------------------------------------
| PEMBELIAN:
|   - Alat Berat melalui pembelian_pembayaran
|   - Sparepart melalui pembelian_sparepart
|
| NON_PEMBELIAN:
|   - dikelompokkan berdasarkan kategori_keuangan.kelompok_laporan
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
    if (isset($expenseBySource[$row['sumber']])) {
        $expenseBySource[$row['sumber']] = (float) $row['total'];
    }
}

$totalPengeluaran = (float) ($expenseSummary['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| PENGELOMPOKAN PENGELUARAN NON-PEMBELIAN
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(k.kelompok_laporan, 'LAINNYA') AS kelompok,
        COUNT(*) AS jumlah,
        COALESCE(SUM(e.nominal), 0) AS total
    FROM pengeluaran e
    LEFT JOIN kategori_keuangan k
        ON k.id = e.kategori_id
    WHERE e.tanggal BETWEEN ? AND ?
      AND e.sumber = 'NON_PEMBELIAN'
    GROUP BY COALESCE(k.kelompok_laporan, 'LAINNYA')
    ORDER BY total DESC
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$expenseGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$expenseGroupTotals = [
    'MODAL_ASET' => 0.0,
    'BEBAN_OPERASIONAL' => 0.0,
    'LAINNYA' => 0.0
];

foreach ($expenseGroups as $row) {
    $group = (string) $row['kelompok'];

    if (!isset($expenseGroupTotals[$group])) {
        $expenseGroupTotals[$group] = 0.0;
    }

    $expenseGroupTotals[$group] = (float) $row['total'];
}

$belanjaPembelian = $expenseBySource['PEMBELIAN'];
$modalAset = (float) ($expenseGroupTotals['MODAL_ASET'] ?? 0);
$bebanOperasional = (float) ($expenseGroupTotals['BEBAN_OPERASIONAL'] ?? 0);
$pengeluaranLainnya = (float) ($expenseGroupTotals['LAINNYA'] ?? 0);

/*
|--------------------------------------------------------------------------
| ARUS KAS
|--------------------------------------------------------------------------
*/
$arusKasBersih = $totalPemasukan - $totalPengeluaran;

/*
|--------------------------------------------------------------------------
| PIUTANG PENJUALAN
|--------------------------------------------------------------------------
| Piutang = total invoice aktif sampai tanggal akhir
|           - pembayaran penjualan yang sudah masuk sampai tanggal akhir.
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(total), 0) AS total_penjualan
    FROM penjualan
    WHERE tanggal <= ?
      AND UPPER(status) <> 'BATAL'
");
$stmt->execute([$tanggalAkhir]);
$totalPenjualanSampaiTanggal = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(nominal), 0) AS total_bayar_penjualan
    FROM pemasukan
    WHERE sumber = 'PENJUALAN'
      AND tanggal <= ?
");
$stmt->execute([$tanggalAkhir]);
$totalBayarPenjualanSampaiTanggal = (float) $stmt->fetchColumn();

$piutangPenjualan = max(
    0,
    $totalPenjualanSampaiTanggal - $totalBayarPenjualanSampaiTanggal
);

/*
|--------------------------------------------------------------------------
| HUTANG PEMBELIAN ALAT BERAT
|--------------------------------------------------------------------------
| Berdasarkan jadwal pembayaran yang belum PAID.
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(nominal), 0) AS total
    FROM pembelian_pembayaran
    WHERE status <> 'BATAL'
      AND tanggal_jatuh_tempo <= ?
");
$stmt->execute([$tanggalAkhir]);
$totalJadwalPembelian = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(nominal), 0) AS total
    FROM pembelian_pembayaran
    WHERE status = 'PAID'
      AND tanggal_bayar <= ?
");
$stmt->execute([$tanggalAkhir]);
$totalBayarPembelianAlat = (float) $stmt->fetchColumn();

$hutangPembelian = max(
    0,
    $totalJadwalPembelian - $totalBayarPembelianAlat
);

/*
|--------------------------------------------------------------------------
| DATA LAPORAN KEUANGAN UTAMA
|--------------------------------------------------------------------------
| Seluruh saldo di bawah dihitung dari transaksi yang sudah ada. Tidak ada
| INSERT, UPDATE, DELETE, atau perubahan struktur database pada laporan.
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(nominal), 0)
    FROM pemasukan
    WHERE tanggal <= ?
");
$stmt->execute([$tanggalAkhir]);
$kasMasukSampaiTanggal = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(nominal), 0)
    FROM pengeluaran
    WHERE tanggal <= ?
");
$stmt->execute([$tanggalAkhir]);
$kasKeluarSampaiTanggal = (float) $stmt->fetchColumn();
$kasAkhirPeriode = $kasMasukSampaiTanggal - $kasKeluarSampaiTanggal;
$arusKasOperasional = $totalPemasukan
    - $belanjaPembelian
    - $bebanOperasional
    - $pengeluaranLainnya;

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(e.nominal), 0)
    FROM pengeluaran e
    LEFT JOIN kategori_keuangan k ON k.id = e.kategori_id
    WHERE e.tanggal <= ?
      AND e.sumber = 'NON_PEMBELIAN'
      AND k.kelompok_laporan = 'MODAL_ASET'
");
$stmt->execute([$tanggalAkhir]);
$asetTetap = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(nominal), 0)
    FROM pembelian_pembayaran
    WHERE status <> 'BATAL'
      AND tanggal_jatuh_tempo <= ?
");
$stmt->execute([$tanggalAkhir]);
$totalKewajibanPembelian = (float) $stmt->fetchColumn();

$totalAset = $kasAkhirPeriode + $piutangPenjualan + $asetTetap;
$totalLiabilitas = max(0, $totalKewajibanPembelian - $totalBayarPembelianAlat);
$totalEkuitas = $totalAset - $totalLiabilitas;

/*
|--------------------------------------------------------------------------
| PENJUALAN PERIODE
|--------------------------------------------------------------------------
| Subtotal berasal dari penjualan_detail.subtotal.
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
            COALESCE(SUM(pd.subtotal), 0) AS subtotal,
            (
                COALESCE(SUM(pd.subtotal), 0)
                * COALESCE(p.ppn, 0) / 100
            ) AS ppn_nominal,
            p.total
        FROM penjualan p
        LEFT JOIN penjualan_detail pd
            ON pd.penjualan_id = p.id
        WHERE p.tanggal BETWEEN ? AND ?
          AND UPPER(p.status) <> 'BATAL'
        GROUP BY p.id, p.ppn, p.total
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

$profitSales = (float) ($profitSummary['penjualan'] ?? 0);
$profitValue = (float) ($profitSummary['laba'] ?? 0);

$margin = $profitSales > 0
    ? ($profitValue / $profitSales) * 100
    : 0;

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(pd.laba), 0)
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.id = pd.penjualan_id
    WHERE p.tanggal <= ?
      AND UPPER(p.status) NOT IN ('DRAFT', 'BATAL')
");
$stmt->execute([$tanggalAkhir]);
$labaRugiKumulatif = (float) $stmt->fetchColumn();

$labaRugiPeriode = $profitValue
    + (float) $incomeBySource['NON_PENJUALAN']
    - $bebanOperasional
    - $pengeluaranLainnya;
$modalAwal = $totalEkuitas - $labaRugiKumulatif;

/*
|--------------------------------------------------------------------------
| LAPORAN BULANAN
|--------------------------------------------------------------------------
| Inilah format utama sesuai kebutuhan client:
| - Pendapatan Penjualan
| - Pendapatan Non Penjualan
| - Total Pendapatan
| - Belanja Pembelian
| - Modal Aset
| - Beban Operasional
| - Total Pengeluaran
| - Arus Kas Bersih
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        periode,

        SUM(penjualan) AS penjualan,
        SUM(non_penjualan) AS non_penjualan,
        SUM(pemasukan) AS pemasukan,

        SUM(belanja_pembelian) AS belanja_pembelian,
        SUM(modal_aset) AS modal_aset,
        SUM(beban_operasional) AS beban_operasional,
        SUM(pengeluaran_lainnya) AS pengeluaran_lainnya,
        SUM(pengeluaran) AS pengeluaran

    FROM (
        SELECT
            DATE_FORMAT(pm.tanggal, '%Y-%m') AS periode,

            CASE
                WHEN pm.sumber = 'PENJUALAN'
                    THEN pm.nominal
                ELSE 0
            END AS penjualan,

            CASE
                WHEN pm.sumber = 'NON_PENJUALAN'
                    THEN pm.nominal
                ELSE 0
            END AS non_penjualan,

            pm.nominal AS pemasukan,

            0 AS belanja_pembelian,
            0 AS modal_aset,
            0 AS beban_operasional,
            0 AS pengeluaran_lainnya,
            0 AS pengeluaran

        FROM pemasukan pm
        WHERE pm.tanggal BETWEEN ? AND ?

        UNION ALL

        SELECT
            DATE_FORMAT(pe.tanggal, '%Y-%m') AS periode,

            0 AS penjualan,
            0 AS non_penjualan,
            0 AS pemasukan,

            CASE
                WHEN pe.sumber = 'PEMBELIAN'
                    THEN pe.nominal
                ELSE 0
            END AS belanja_pembelian,

            CASE
                WHEN pe.sumber = 'NON_PEMBELIAN'
                     AND COALESCE(k.kelompok_laporan, '') = 'MODAL_ASET'
                    THEN pe.nominal
                ELSE 0
            END AS modal_aset,

            CASE
                WHEN pe.sumber = 'NON_PEMBELIAN'
                     AND COALESCE(k.kelompok_laporan, '') = 'BEBAN_OPERASIONAL'
                    THEN pe.nominal
                ELSE 0
            END AS beban_operasional,

            CASE
                WHEN pe.sumber = 'NON_PEMBELIAN'
                     AND COALESCE(k.kelompok_laporan, '') NOT IN
                         ('MODAL_ASET', 'BEBAN_OPERASIONAL')
                    THEN pe.nominal
                ELSE 0
            END AS pengeluaran_lainnya,

            pe.nominal AS pengeluaran

        FROM pengeluaran pe
        LEFT JOIN kategori_keuangan k
            ON k.id = pe.kategori_id
        WHERE pe.tanggal BETWEEN ? AND ?
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
| NORMALISASI BULAN + DETAIL LAPORAN BULANAN
|--------------------------------------------------------------------------
*/
$monthlyMap = [];
foreach ($monthlyRows as $row) {
    foreach ([
        'penjualan',
        'non_penjualan',
        'pemasukan',
        'belanja_pembelian',
        'modal_aset',
        'beban_operasional',
        'pengeluaran_lainnya',
        'pengeluaran'
    ] as $numericKey) {
        $row[$numericKey] = (float) $row[$numericKey];
    }
    $monthlyMap[$row['periode']] = $row;
}

$rangeStart = new DateTimeImmutable(date('Y-m-01', strtotime($tanggalAwal)));
$rangeEnd = new DateTimeImmutable(date('Y-m-01', strtotime($tanggalAkhir)));
$monthlyRows = [];
for ($cursor = $rangeStart; $cursor <= $rangeEnd; $cursor = $cursor->modify('+1 month')) {
    $periode = $cursor->format('Y-m');
    $monthlyRows[] = $monthlyMap[$periode] ?? [
        'periode' => $periode,
        'penjualan' => 0,
        'non_penjualan' => 0,
        'pemasukan' => 0,
        'belanja_pembelian' => 0,
        'modal_aset' => 0,
        'beban_operasional' => 0,
        'pengeluaran_lainnya' => 0,
        'pengeluaran' => 0,
    ];
}

/* Detail pemasukan aktual per bulan. */
$stmt = $pdo->prepare("
    SELECT
        pm.id,
        pm.tanggal,
        DATE_FORMAT(pm.tanggal, '%Y-%m') AS periode,
        pm.nomor_pemasukan AS nomor,
        pm.sumber,
        pm.jenis_pembayaran,
        pm.nominal,
        pm.metode_pembayaran,
        pm.referensi,
        pm.keterangan,
        p.status AS penjualan_status,
        psp.status AS penjualan_sparepart_status,
        pp.termin_ke,
        p.nomor_penjualan,
        COALESCE(
            GROUP_CONCAT(
                DISTINCT sps.kode
                ORDER BY sps.kode SEPARATOR ', '
            ),
            ''
        ) AS detail_penjualan_sparepart,
        COALESCE(
            GROUP_CONCAT(
                DISTINCT ab.kode
                ORDER BY ab.kode SEPARATOR ', '
            ),
            ''
        ) AS detail_penjualan,
        k.nama AS kategori_nama
    FROM pemasukan pm
    LEFT JOIN penjualan p ON p.id = pm.penjualan_id
    LEFT JOIN penjualan_sparepart psp ON psp.id = pm.penjualan_sparepart_id
    LEFT JOIN penjualan_sparepart_detail psd ON psd.penjualan_id = psp.id
    LEFT JOIN sparepart sps ON sps.id = psd.sparepart_id
    LEFT JOIN penjualan_pembayaran pp ON pp.id = pm.jadwal_pembayaran_id
    LEFT JOIN penjualan_detail pd ON pd.penjualan_id = p.id
    LEFT JOIN alat_berat ab ON ab.id = pd.alat_berat_id
    LEFT JOIN kategori_keuangan k ON k.id = pm.kategori_id
        WHERE pm.tanggal BETWEEN ? AND ?
            AND (
                    pm.sumber <> 'PENJUALAN'
                      OR UPPER(CONVERT(COALESCE(p.status, psp.status, '') USING utf8mb4)) IN ('CONFIRMED', 'PAID', 'SELESAI')
            )
    GROUP BY
        pm.id, pm.tanggal, pm.nomor_pemasukan, pm.sumber,
        pm.jenis_pembayaran, pm.nominal, pm.metode_pembayaran,
        pm.referensi, pm.keterangan, p.status, psp.status,
        pp.termin_ke, p.nomor_penjualan, k.nama
    ORDER BY pm.tanggal ASC, pm.id ASC
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$incomeDetailRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Detail pengeluaran aktual per bulan. */
$stmt = $pdo->prepare("
    SELECT
        e.id,
        e.tanggal,
        DATE_FORMAT(e.tanggal, '%Y-%m') AS periode,
        e.nomor_pengeluaran AS nomor,
        e.sumber,
        e.nominal,
        e.pembelian_pembayaran_id,
        e.pembelian_sparepart_id,
        e.pembelian_restorasi_id,
        e.metode_pembayaran,
        e.referensi,
        e.keterangan,
        e.jenis_pengeluaran,
        k.nama AS kategori_nama,
        k.kelompok_laporan,
        pp.nomor_pembayaran,
        pp.termin_ke,
        pab.nomor_pembelian AS nomor_pembelian_alat_berat,
        psp.nomor_pembelian AS nomor_pembelian_sparepart
    FROM pengeluaran e
    LEFT JOIN kategori_keuangan k ON k.id = e.kategori_id
    LEFT JOIN pembelian_pembayaran pp ON pp.id = e.pembelian_pembayaran_id
    LEFT JOIN pembelian_alat_berat pab ON pab.id = pp.pembelian_alat_berat_id
    LEFT JOIN pembelian_sparepart psp ON psp.id = e.pembelian_sparepart_id
                LEFT JOIN pembelian_restorasi pr ON pr.id = e.pembelian_restorasi_id
    WHERE e.tanggal BETWEEN ? AND ?
            AND (
                    e.sumber <> 'PEMBELIAN'
                      OR (e.pembelian_pembayaran_id IS NOT NULL AND UPPER(CONVERT(COALESCE(pp.status, '') USING utf8mb4)) = 'PAID')
                      OR (e.pembelian_sparepart_id IS NOT NULL AND UPPER(CONVERT(COALESCE(psp.status, '') USING utf8mb4)) = 'SELESAI')
                      OR (e.pembelian_restorasi_id IS NOT NULL AND UPPER(CONVERT(COALESCE(pr.status, '') USING utf8mb4)) = 'SELESAI')
            )
    ORDER BY e.tanggal ASC, e.id ASC
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$expenseDetailRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$profitIncomeRows = [];
$profitSparepartIndex = [];
foreach ($incomeDetailRows as $row) {
    if ($row['sumber'] === 'PENJUALAN') {
        $sparepartDetail = trim((string) $row['detail_penjualan_sparepart']);
        $detail = trim((string) $row['detail_penjualan']);

        if ($sparepartDetail !== '') {
            $sparepartGroupKey = 'PENJUALAN_SPAREPART';
            if (!isset($profitSparepartIndex[$sparepartGroupKey])) {
                $profitSparepartIndex[$sparepartGroupKey] = count($profitIncomeRows);
                $profitIncomeRows[] = [
                    'label' => 'Penjualan Sparepart',
                    'nominal' => 0.0,
                ];
            }

            $profitIncomeRows[$profitSparepartIndex[$sparepartGroupKey]]['nominal'] += (float) $row['nominal'];
            continue;
        }

        $label = $detail !== ''
            ? $detail
            : ($row['nomor_penjualan'] ?: 'Pendapatan Penjualan');

        if (!empty($row['termin_ke'])) {
            $label .= ' Termin ' . (int) $row['termin_ke'];
        }
    } else {
        $label = $row['kategori_nama'] ?: ($row['jenis_pembayaran'] ?: 'Pendapatan Non Penjualan');
    }

    $profitIncomeRows[] = [
        'label' => $label,
        'nominal' => (float) $row['nominal'],
    ];
}

$profitCogsGroups = [
    'Pembelian Alat Berat' => 0.0,
    'Pembelian Sparepart' => 0.0,
    'Pembayaran Pembelian Alat Berat' => 0.0,
    'Pembayaran Pembelian Sparepart' => 0.0,
];
$profitOperatingRows = [];
$investmentRows = [];
foreach ($expenseDetailRows as $row) {
    if ((string) $row['kelompok_laporan'] === 'MODAL_ASET') {
        $investmentRows[] = [
            'label' => $row['kategori_nama'] ?: ($row['jenis_pengeluaran'] ?: 'Perolehan Aset Tetap'),
            'nominal' => (float) $row['nominal'],
        ];
        continue;
    }

    if ($row['sumber'] === 'PEMBELIAN') {
        if (!empty($row['pembelian_pembayaran_id'])) {
            $profitCogsGroups['Pembayaran Pembelian Alat Berat'] += (float) $row['nominal'];
        } elseif (!empty($row['pembelian_sparepart_id'])) {
            $profitCogsGroups['Pembayaran Pembelian Sparepart'] += (float) $row['nominal'];
        } else {
            $profitCogsGroups['Pembelian Alat Berat'] += (float) $row['nominal'];
        }
        continue;
    }

    $profitOperatingRows[] = [
        'label' => $row['kategori_nama'] ?: ($row['jenis_pengeluaran'] ?: 'Tanpa Kategori'),
        'nominal' => (float) $row['nominal'],
    ];
}

$profitCogsRows = [];
foreach ($profitCogsGroups as $label => $nominal) {
    if ($nominal > 0) {
        $profitCogsRows[] = [
            'label' => $label,
            'nominal' => $nominal,
        ];
    }
}

$positionUnitRows = [];
$positionSparepartRows = [];
$positionRestorasiRows = [];
try {
    $positionUnits = $pdo->query("SELECT id, kode, tipe FROM alat_berat ORDER BY kode ASC")->fetchAll(PDO::FETCH_ASSOC);
    $hppByUnit = [];
    $stmt = $pdo->query("
        SELECT d.alat_berat_id,
            d.harga_beli + (
                COALESCE(p.biaya_bea_cukai, 0) +
                COALESCE(p.biaya_pengiriman, 0) +
                COALESCE(p.biaya_lain, 0)
            ) / NULLIF((SELECT COUNT(*) FROM pembelian_alat_berat_detail d2 WHERE d2.pembelian_id = p.id), 0) AS hpp
        FROM pembelian_alat_berat_detail d
        INNER JOIN pembelian_alat_berat p ON p.id = d.pembelian_id
        INNER JOIN (
            SELECT d3.alat_berat_id,
                MAX(CONCAT(LPAD(p3.tanggal, 10, '0'), LPAD(p3.id, 10, '0'))) AS latest_key
            FROM pembelian_alat_berat_detail d3
            INNER JOIN pembelian_alat_berat p3 ON p3.id = d3.pembelian_id
            WHERE UPPER(COALESCE(p3.status, '')) <> 'BATAL'
            GROUP BY d3.alat_berat_id
        ) latest ON latest.alat_berat_id = d.alat_berat_id
            AND latest.latest_key = CONCAT(LPAD(p.tanggal, 10, '0'), LPAD(p.id, 10, '0'))
        WHERE UPPER(COALESCE(p.status, '')) <> 'BATAL'
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stmt as $row) {
        $hppByUnit[(int) $row['alat_berat_id']] = (float) $row['hpp'];
    }

    foreach ($positionUnits as $unit) {
        $positionUnitRows[] = [
            'label' => $unit['kode'],
            'nominal' => $hppByUnit[(int) $unit['id']] ?? 0,
        ];
    }

    $positionSparepartRows = $pdo->query("
        SELECT s.kode, s.stok, COALESCE(lp.harga_terakhir, 0) AS harga_terakhir
        FROM sparepart s
        LEFT JOIN (
            SELECT d.sparepart_id, d.harga AS harga_terakhir
            FROM pembelian_sparepart_detail d
            INNER JOIN pembelian_sparepart p ON p.id = d.pembelian_id
            INNER JOIN (
                SELECT d2.sparepart_id,
                    MAX(CONCAT(LPAD(p2.tanggal, 10, '0'), LPAD(p2.id, 10, '0'), LPAD(d2.id, 10, '0'))) AS latest_key
                FROM pembelian_sparepart_detail d2
                INNER JOIN pembelian_sparepart p2 ON p2.id = d2.pembelian_id
                WHERE UPPER(COALESCE(p2.status, '')) <> 'BATAL'
                GROUP BY d2.sparepart_id
            ) latest ON latest.sparepart_id = d.sparepart_id
                AND latest.latest_key = CONCAT(LPAD(p.tanggal, 10, '0'), LPAD(p.id, 10, '0'), LPAD(d.id, 10, '0'))
            WHERE UPPER(COALESCE(p.status, '')) <> 'BATAL'
        ) lp ON lp.sparepart_id = s.id
        ORDER BY s.kode ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $positionRestorasiRows = $pdo->query("
        SELECT r.kode, r.stok, COALESCE(lp.harga_terakhir, 0) AS harga_terakhir
        FROM restorasi r
        LEFT JOIN (
            SELECT d.restorasi_id, d.harga AS harga_terakhir
            FROM pembelian_restorasi_detail d
            INNER JOIN pembelian_restorasi p ON p.id = d.pembelian_id
            INNER JOIN (
                SELECT d2.restorasi_id,
                    MAX(CONCAT(LPAD(p2.tanggal, 10, '0'), LPAD(p2.id, 10, '0'), LPAD(d2.id, 10, '0'))) AS latest_key
                FROM pembelian_restorasi_detail d2
                INNER JOIN pembelian_restorasi p2 ON p2.id = d2.pembelian_id
                WHERE UPPER(COALESCE(p2.status, '')) <> 'BATAL'
                GROUP BY d2.restorasi_id
            ) latest ON latest.restorasi_id = d.restorasi_id
                AND latest.latest_key = CONCAT(LPAD(p.tanggal, 10, '0'), LPAD(p.id, 10, '0'), LPAD(d.id, 10, '0'))
            WHERE UPPER(COALESCE(p.status, '')) <> 'BATAL'
        ) lp ON lp.restorasi_id = r.id
        ORDER BY r.kode ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $positionUnitRows = [];
    $positionSparepartRows = [];
    $positionRestorasiRows = [];
}

$positionUnitTotal = array_sum(array_column($positionUnitRows, 'nominal'));
$positionSparepartTotal = 0.0;
foreach ($positionSparepartRows as $row) {
    $positionSparepartTotal += (float) $row['stok'] * (float) $row['harga_terakhir'];
}
$positionRestorasiTotal = 0.0;
foreach ($positionRestorasiRows as $row) {
    $positionRestorasiTotal += (float) $row['stok'] * (float) $row['harga_terakhir'];
}
$asetTetapRinci = $positionUnitTotal + $positionSparepartTotal + $positionRestorasiTotal;
$asetTetap = $asetTetapRinci;
$totalAset = $kasAkhirPeriode + $piutangPenjualan + $asetTetap;
$totalEkuitas = $totalAset - $totalLiabilitas;
$modalAwal = $totalEkuitas - $labaRugiKumulatif;

$printIncomeTotal = array_sum(array_column($profitIncomeRows, 'nominal'));
$printCogsTotal = array_sum(array_column($profitCogsRows, 'nominal'));
$printOperatingTotal = array_sum(array_column($profitOperatingRows, 'nominal'));
$printInvestmentTotal = array_sum(array_column($investmentRows, 'nominal'));
$printNetTotal = $printIncomeTotal - $printCogsTotal - $printOperatingTotal - $printInvestmentTotal;
$printCashEnd = $printNetTotal;

$monthlyReportData = [];
foreach ($monthlyRows as $monthly) {
    $monthlyReportData[$monthly['periode']] = [
        'periode' => $monthly['periode'],
        'pendapatan_penjualan' => [],
        'pendapatan_non_penjualan' => [],
        'belanja_pembelian' => [],
        'modal_aset' => [],
        'beban_operasional' => [],
        'pengeluaran_lainnya' => [],
    ];
}

foreach ($incomeDetailRows as $row) {
    $periode = (string) $row['periode'];
    if (!isset($monthlyReportData[$periode]))
        continue;

    if ($row['sumber'] === 'PENJUALAN') {
        $detail = trim((string) $row['detail_penjualan']);
        $nomorPenjualan = trim((string) $row['nomor_penjualan']);
        $label = $detail !== ''
            ? $detail
            : ($nomorPenjualan !== '' ? 'Penjualan ' . $nomorPenjualan : 'Pendapatan Penjualan');

        if (!empty($row['termin_ke'])) {
            $label .= ' Termin ' . (int) $row['termin_ke'];
        }

        $monthlyReportData[$periode]['pendapatan_penjualan'][] = [
            'label' => $label,
            'nomor' => $nomorPenjualan,
            'tanggal' => $row['tanggal'],
            'nominal' => (float) $row['nominal'],
            'metode' => $row['metode_pembayaran'],
            'referensi' => $row['referensi'],
        ];
    } else {
        $monthlyReportData[$periode]['pendapatan_non_penjualan'][] = [
            'label' => $row['kategori_nama'] ?: ($row['jenis_pembayaran'] ?: 'Pendapatan Non Penjualan'),
            'nomor' => $row['nomor'],
            'tanggal' => $row['tanggal'],
            'nominal' => (float) $row['nominal'],
            'metode' => $row['metode_pembayaran'],
            'referensi' => $row['referensi'],
        ];
    }
}

foreach ($expenseDetailRows as $row) {
    $periode = (string) $row['periode'];
    if (!isset($monthlyReportData[$periode]))
        continue;

    if ($row['sumber'] === 'PEMBELIAN') {
        if (!empty($row['pembelian_pembayaran_id'])) {
            $purchaseNo = trim((string) $row['nomor_pembelian_alat_berat']);
            $label = 'Pembelian Alat Berat' . ($purchaseNo !== '' ? ' ' . $purchaseNo : '');
            $detail = $row['termin_ke'] ? 'Termin ' . $row['termin_ke'] : 'Pembayaran';
        } else {
            $purchaseNo = trim((string) $row['nomor_pembelian_sparepart']);
            $label = 'Pembelian Sparepart' . ($purchaseNo !== '' ? ' ' . $purchaseNo : '');
            $detail = 'Pembelian Sparepart';
        }

        $monthlyReportData[$periode]['belanja_pembelian'][] = [
            'label' => $label,
            'detail' => $detail,
            'nomor' => $row['nomor'],
            'tanggal' => $row['tanggal'],
            'nominal' => (float) $row['nominal'],
            'metode' => $row['metode_pembayaran'],
            'referensi' => $row['referensi'],
        ];
        continue;
    }

    $group = (string) ($row['kelompok_laporan'] ?? 'LAINNYA');

    // Gunakan if/elseif agar kompatibel dengan PHP 7.x.
    if ($group === 'MODAL_ASET') {
        $target = 'modal_aset';
    } elseif ($group === 'BEBAN_OPERASIONAL') {
        $target = 'beban_operasional';
    } else {
        $target = 'pengeluaran_lainnya';
    }

    $monthlyReportData[$periode][$target][] = [
        'label' => $row['kategori_nama'] ?: ($row['jenis_pengeluaran'] ?: 'Tanpa Kategori'),
        'nomor' => $row['nomor'],
        'tanggal' => $row['tanggal'],
        'nominal' => (float) $row['nominal'],
        'metode' => $row['metode_pembayaran'],
        'referensi' => $row['referensi'],
        'keterangan' => $row['keterangan'],
    ];
}

$monthlyReportDataJson = json_encode(
    $monthlyReportData,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$reportMonthOptions = array_map(
    static function (array $row): string {
        return $row['periode'];
    },
    $monthlyRows
);
$selectedReportMonth = $reportMonthOptions[count($reportMonthOptions) - 1] ?? date('Y-m');

/*
|--------------------------------------------------------------------------
| DETAIL SEMUA TRANSAKSI KAS
|--------------------------------------------------------------------------
| Gabungan pemasukan + pengeluaran.
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM (
        SELECT
            pm.id,
            pm.tanggal,
            pm.nomor_pemasukan AS nomor,
            'PEMASUKAN' AS tipe,
            pm.sumber AS sumber,
            COALESCE(k.nama, 'Penjualan') AS kategori,
            pm.jenis_pembayaran AS jenis,
            pm.nominal AS masuk,
            0 AS keluar,
            pm.metode_pembayaran AS metode,
            pm.referensi,
            pm.keterangan
        FROM pemasukan pm
        LEFT JOIN kategori_keuangan k
            ON k.id = pm.kategori_id
        WHERE pm.tanggal BETWEEN ? AND ?

        UNION ALL

        SELECT
            pe.id,
            pe.tanggal,
            pe.nomor_pengeluaran AS nomor,
            'PENGELUARAN' AS tipe,
            pe.sumber AS sumber,
            CASE
                WHEN pe.sumber = 'PEMBELIAN'
                    THEN 'Belanja Pembelian'
                ELSE COALESCE(k2.nama, 'Tanpa Kategori')
            END AS kategori,
            pe.jenis_pengeluaran AS jenis,
            0 AS masuk,
            pe.nominal AS keluar,
            pe.metode_pembayaran AS metode,
            pe.referensi,
            pe.keterangan
        FROM pengeluaran pe
        LEFT JOIN kategori_keuangan k2
            ON k2.id = pe.kategori_id
        WHERE pe.tanggal BETWEEN ? AND ?
    ) z
    ORDER BY tanggal DESC, id DESC
");
$stmt->execute([
    $tanggalAwal,
    $tanggalAkhir,
    $tanggalAwal,
    $tanggalAkhir
]);

$cashRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| KATEGORI PENGELUARAN
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        COALESCE(k.nama, 'Tanpa Kategori') AS kategori,
        COALESCE(k.kelompok_laporan, 'LAINNYA') AS kelompok,
        COUNT(*) AS jumlah,
        COALESCE(SUM(e.nominal), 0) AS total
    FROM pengeluaran e
    LEFT JOIN kategori_keuangan k
        ON k.id = e.kategori_id
    WHERE e.tanggal BETWEEN ? AND ?
    GROUP BY e.kategori_id, k.nama, k.kelompok_laporan
    ORDER BY total DESC
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$expenseCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$topExpenseCategory = $expenseCategories[0] ?? null;

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .report-page {
        max-width: 1600px;
        margin: 0 auto;
    }

    .report-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 20px;
        margin-bottom: 18px;
    }

    .report-heading h1 {
        margin: 0;
        color: #172b4d;
    }

    .report-heading p {
        margin: 5px 0 0;
        color: #718096;
        font-size: 13px;
    }

    .period-form {
        display: flex;
        align-items: end;
        gap: 10px;
        background: #fff;
        border: 1px solid #dce3eb;
        border-radius: 7px;
        padding: 10px;
    }

    .period-field {
        min-width: 145px;
    }

    .period-field label {
        display: block;
        margin-bottom: 5px;
        color: #718096;
        font-size: 11px;
    }

    .period-field input {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #b9c7d8;
        border-radius: 5px;
        padding: 8px 9px;
        font-size: 12px;
    }

    .btn-report {
        border: 1px solid #0d6efd;
        background: #0d6efd;
        color: #fff;
        border-radius: 5px;
        padding: 9px 14px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px;
    }

    .btn-report:hover {
        background: #0b5ed7;
    }

    .report-section-title {
        margin: 24px 0 10px;
        color: #172b4d;
        font-size: 15px;
        font-weight: 700;
    }

    .finance-cards {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
    }

    .finance-card {
        position: relative;
        background: #fff;
        border: 1px solid #dce3eb;
        border-radius: 7px;
        padding: 17px 16px;
        min-width: 0;
        box-shadow: 0 1px 2px rgba(23, 43, 77, .04);
    }

    .finance-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 10px;
        bottom: 10px;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: #b8c7d8;
    }

    .finance-card.income::before {
        background: #198754
    }

    .finance-card.expense::before {
        background: #dc3545
    }

    .finance-card.net::before {
        background: #0d6efd
    }

    .finance-card.receivable::before {
        background: #d97706
    }

    .finance-card.payable::before {
        background: #7c3aed
    }

    .finance-value {
        display: block;
        font-size: 20px;
        font-weight: 700;
        color: #172b4d;
        margin-bottom: 7px;
        padding-left: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .finance-card.income .finance-value {
        color: #198754
    }

    .finance-card.expense .finance-value {
        color: #dc3545
    }

    .finance-card.net .finance-value {
        color: #0d6efd
    }

    .finance-card.receivable .finance-value {
        color: #b45f00
    }

    .finance-card.payable .finance-value {
        color: #6d28d9
    }

    .finance-label {
        display: block;
        padding-left: 4px;
        color: #526b8d;
        font-size: 12px;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 14px;
    }

    .dashboard-grid.equal {
        grid-template-columns: 1fr 1fr;
    }

    .report-card {
        background: #fff;
        border: 1px solid #dce3eb;
        border-radius: 7px;
        overflow: hidden;
    }

    .report-card-header {
        padding: 13px 16px;
        border-bottom: 1px solid #dce3eb;
        font-weight: 700;
        color: #172b4d;
    }

    .report-card-header small {
        display: block;
        margin-top: 3px;
        color: #8390a3;
        font-size: 11px;
        font-weight: 400;
    }

    .report-card-body {
        padding: 16px;
    }

    .mini-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .mini-box {
        border: 1px solid #e1e7ef;
        border-radius: 6px;
        padding: 13px;
    }

    .mini-box .label {
        color: #718096;
        font-size: 11px;
        margin-bottom: 6px;
    }

    .mini-box .value {
        color: #172b4d;
        font-weight: 700;
        font-size: 17px;
    }

    .mini-box .sub {
        color: #8390a3;
        font-size: 10px;
        margin-top: 4px;
    }

    .chart-wrap {
        min-height: 230px;
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding: 10px 4px 0;
        overflow-x: auto;
    }

    .chart-column {
        flex: 1;
        min-width: 55px;
        height: 205px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        gap: 6px;
    }

    .chart-bars {
        width: 100%;
        max-width: 52px;
        height: 170px;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 3px;
    }

    .chart-bar {
        width: 18px;
        min-height: 2px;
        border-radius: 3px 3px 0 0;
    }

    .chart-bar.in {
        background: #5b8def;
    }

    .chart-bar.out {
        background: #d98282;
    }

    .chart-month {
        color: #718096;
        font-size: 9px;
        white-space: nowrap;
    }

    .chart-legend {
        display: flex;
        gap: 16px;
        justify-content: center;
        margin-top: 8px;
        color: #718096;
        font-size: 10px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .legend-dot {
        width: 9px;
        height: 9px;
        border-radius: 2px;
    }

    .legend-in {
        background: #5b8def
    }

    .legend-out {
        background: #d98282
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
    }

    .report-table th {
        background: #f5f7fa;
        color: #526b8d;
        font-size: 11px;
        text-align: left;
        padding: 9px 10px;
        border-bottom: 1px solid #dce3eb;
        white-space: nowrap;
    }

    .report-table td {
        padding: 9px 10px;
        font-size: 12px;
        border-bottom: 1px solid #e8edf2;
    }

    .report-table .money {
        text-align: right;
        white-space: nowrap;
        font-weight: 600;
    }

    .report-table .right {
        text-align: right;
    }

    .monthly-table-wrap,
    .cash-table-wrap {
        overflow-x: auto;
    }

    .monthly-table {
        min-width: 1050px;
    }

    .cash-table {
        min-width: 1100px;
    }

    .month-total {
        font-weight: 700;
    }

    .total-row td {
        background: #f7f9fb;
        font-weight: 700;
    }

    .net-positive {
        color: #198754;
        font-weight: 700;
    }

    .net-negative {
        color: #dc3545;
        font-weight: 700;
    }

    .badge {
        display: inline-block;
        padding: 4px 7px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
    }

    .badge-income {
        background: #d1e7dd;
        color: #0f5132;
    }

    .badge-expense {
        background: #f8d7da;
        color: #842029;
    }

    .badge-group {
        background: #eef2f6;
        color: #526b8d;
    }

    .empty-report {
        padding: 20px;
        text-align: center;
        color: #8390a3;
        font-size: 12px;
    }

    .progress-row {
        margin-bottom: 14px;
    }

    .progress-row:last-child {
        margin-bottom: 0;
    }

    .progress-label {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: 11px;
        color: #526b8d;
        margin-bottom: 5px;
    }

    .progress-track {
        height: 7px;
        background: #edf1f5;
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: #7a8da6;
        border-radius: 10px;
    }

    .report-note {
        margin-top: 10px;
        color: #8390a3;
        font-size: 10px;
        line-height: 1.5;
    }


    .month-selector-card {
        margin: 16px 0 4px;
        background: #fff;
        border: 1px solid #dce3eb;
        border-radius: 7px;
        padding: 14px 16px;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 18px
    }

    .month-selector-copy strong {
        display: block;
        color: #172b4d;
        font-size: 13px;
        margin-bottom: 3px
    }

    .month-selector-copy span {
        color: #8390a3;
        font-size: 11px
    }

    .month-selector-form {
        display: flex;
        align-items: flex-end;
        gap: 8px
    }

    .month-selector-form label {
        display: block;
        color: #718096;
        font-size: 11px;
        margin-bottom: 5px
    }

    .month-selector-form select {
        min-width: 170px;
        border: 1px solid #b9c7d8;
        border-radius: 5px;
        padding: 8px 9px;
        font-size: 12px;
        background: #fff
    }

    .btn-month-detail,
    .btn-detail-month {
        border: 1px solid #0d6efd;
        background: #0d6efd;
        color: #fff;
        border-radius: 5px;
        padding: 9px 13px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px
    }

    .btn-month-detail:hover {
        background: #0b5ed7
    }

    .month-summary-table {
        min-width: 1100px
    }

    .month-summary-table th:last-child,
    .month-summary-table td:last-child {
        width: 75px;
        text-align: center
    }

    .btn-detail-month {
        border-color: #b8c7dc;
        background: #fff;
        color: #315a8c;
        padding: 6px 10px;
        font-size: 11px
    }

    .btn-detail-month:hover {
        background: #f3f6fa
    }

    .month-value-positive {
        color: #198754;
        font-weight: 700
    }

    .month-value-negative {
        color: #dc3545;
        font-weight: 700
    }

    .month-modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .5);
        z-index: 10000;
        padding: 20px;
        overflow: auto;
        box-sizing: border-box
    }

    .month-modal-backdrop.show {
        display: block
    }

    .month-modal {
        width: min(1120px, 100%);
        margin: 20px auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 18px 55px rgba(0, 0, 0, .2);
        overflow: hidden
    }

    .month-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 18px;
        border-bottom: 1px solid #dce3eb
    }

    .month-modal-title {
        font-weight: 700;
        color: #172b4d;
        font-size: 16px
    }

    .month-modal-title small {
        display: block;
        color: #8390a3;
        font-size: 10px;
        font-weight: 400;
        margin-top: 3px
    }

    .month-modal-close {
        border: 0;
        background: transparent;
        color: #718096;
        font-size: 24px;
        cursor: pointer
    }

    .client-report {
        padding: 20px
    }

    .client-report-title {
        text-align: center;
        margin-bottom: 18px;
        color: #172b4d
    }

    .client-report-title strong {
        display: block;
        font-size: 16px
    }

    .client-report-title span {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-top: 3px
    }

    .client-report-section {
        margin-top: 16px
    }

    .client-report-section-title {
        font-weight: 700;
        color: #172b4d;
        font-size: 12px;
        text-transform: uppercase;
        border-bottom: 1px solid #cfd8e3;
        padding: 6px 8px;
        background: #f7f9fb
    }

    .client-report-table {
        width: 100%;
        border-collapse: collapse
    }

    .client-report-table td {
        border-bottom: 1px solid #e6ebf0;
        padding: 6px 8px;
        font-size: 11px
    }

    .client-report-table .money {
        text-align: right;
        white-space: nowrap;
        font-weight: 600
    }

    .client-report-table .indent {
        padding-left: 28px
    }

    .client-report-table .subtotal td {
        background: #f7f9fb;
        font-weight: 700
    }

    .client-report-table .grand-total td {
        background: #eef3f8;
        font-weight: 700
    }

    .client-report-table .profit td {
        font-size: 12px;
        font-weight: 700;
        border-top: 2px solid #cfd8e3
    }

    .client-report-empty {
        padding: 8px 28px;
        color: #9aa7b8;
        font-size: 11px;
        font-style: italic
    }

    .client-report-note {
        margin-top: 14px;
        color: #8390a3;
        font-size: 10px;
        line-height: 1.5
    }

    .statement-heading {
        margin: 24px 0 10px;
        color: #172b4d;
        font-size: 15px;
        font-weight: 700
    }

    .statement-period {
        margin: -5px 0 12px;
        color: #718096;
        font-size: 11px
    }

    .statement-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        align-items: start
    }

    .statement-card {
        background: #fff;
        border: 1px solid #aeb8c5;
        overflow: hidden
    }

    .statement-title {
        min-height: 48px;
        padding: 8px 7px;
        text-align: center;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.3;
        color: #1f2937;
        border-bottom: 1px solid #aeb8c5
    }

    .statement-title span {
        display: block;
        font-weight: 400
    }

    .statement-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed
    }

    .statement-table td {
        padding: 5px 7px;
        border-bottom: 1px solid #edf0f3;
        font-size: 10px;
        vertical-align: top
    }

    .statement-table td:last-child {
        width: 43%;
        text-align: right;
        white-space: nowrap
    }

    .statement-table .section td {
        padding-top: 7px;
        padding-bottom: 4px;
        background: #d9ead3;
        font-weight: 700;
        border-bottom: 1px solid #b8cdb2
    }

    .statement-table .section.liability td {
        background: #fcecc5;
        border-bottom-color: #e6d39f
    }

    .statement-table .section.equity td {
        background: #e7d9f5;
        border-bottom-color: #cdb5e2
    }

    .statement-table .total td {
        font-weight: 700;
        border-top: 1px solid #7f8b99;
        background: #f7f9fb
    }

    .statement-table .grand-total td {
        font-weight: 700;
        border-top: 2px double #596575;
        background: #eef3f8
    }

    .statement-table .indent td:first-child {
        padding-left: 17px
    }

    .statement-table .muted td {
        color: #718096
    }

    @media(max-width:1150px) {
        .statement-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
    }

    @media(max-width:600px) {
        .statement-grid {
            grid-template-columns: 1fr
        }
    }

    .statement-table .section td {
        text-align: left !important
    }

    .statement-table .subsection td {
        padding-top: 6px;
        padding-bottom: 3px;
        font-weight: 700;
        color: #526b8d;
        text-align: left !important;
        border-bottom: 0
    }

    .statement-print {
        display: none
    }

    .btn-print-report {
        border: 1px solid #526b8d;
        background: #fff;
        color: #315a8c;
        border-radius: 5px;
        padding: 9px 13px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 10mm
        }

        body {
            background: #fff !important
        }

        body>* {
            display: none !important
        }

        body>main.admin-content {
            display: block !important;
            margin: 0 !important;
            padding: 0 !important
        }

        main.admin-content>* {
            display: none !important
        }

        main.admin-content>.report-page {
            display: block !important;
            width: 100%;
            max-width: none;
            margin: 0 !important
        }

        .report-page>*:not(.statement-print) {
            display: none !important
        }

        .statement-print {
            display: block !important;
            width: 100%;
            margin: 0
        }

        .statement-heading {
            margin: 0 0 4px;
            font-size: 13px
        }

        .statement-period {
            margin: 0 0 6px;
            font-size: 9px
        }

        .statement-grid {
            display: block !important;
            width: 100%
        }

        .statement-card {
            display: block;
            width: 100%;
            box-sizing: border-box;
            break-inside: auto;
            page-break-inside: auto
        }

        .statement-card+.statement-card {
            margin-top: 0;
            break-before: page;
            page-break-before: always
        }

        .statement-title {
            min-height: 42px;
            padding: 7px 6px;
            font-size: 11px
        }

        .statement-table td {
            padding: 7px 8px;
            font-size: 10px
        }

        .statement-table td:last-child {
            width: 35%
        }

        .statement-table .section td {
            padding-top: 8px;
            padding-bottom: 6px
        }
    }

    @media(max-width:1250px) {
        .finance-cards {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .dashboard-grid,
        .dashboard-grid.equal {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width:800px) {
        .report-heading {
            flex-direction: column;
            align-items: stretch;
        }

        .period-form {
            flex-wrap: wrap;
        }

        .period-field {
            flex: 1;
        }

        .finance-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .mini-grid {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width:600px) {
        .finance-cards {
            grid-template-columns: 1fr;
        }

        .period-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .period-field {
            min-width: 0;
        }

        .period-form .btn-report {
            grid-column: 1/-1;
        }
    }
</style>

<div class="report-page">

    <div class="report-heading">
        <div>
            <h1>Laporan Keuangan</h1>
            <p>
                Dashboard dan laporan bulanan seluruh transaksi keuangan ASRINDO.
            </p>
        </div>

        <form method="get" class="period-form">
            <div class="period-field">
                <label>Tanggal Awal</label>
                <input type="date" name="tanggal_awal" value="<?php echo h($tanggalAwal); ?>" required>
            </div>

            <div class="period-field">
                <label>Tanggal Akhir</label>
                <input type="date" name="tanggal_akhir" value="<?php echo h($tanggalAkhir); ?>" required>
            </div>

            <button type="submit" class="btn-report">
                Tampilkan
            </button>

            <button type="button" class="btn-print-report" onclick="printReportWithCustomName()">
                Cetak PDF
            </button>
        </form>
    </div>

    <div class="report-note">
        Periode:
        <strong>
            <?php echo h(date('d-M-Y', strtotime($tanggalAwal))); ?>
        </strong>
        s.d.
        <strong>
            <?php echo h(date('d-M-Y', strtotime($tanggalAkhir))); ?>
        </strong>
    </div>

    <div class="statement-print">
        <div class="statement-heading">Laporan Keuangan Utama</div>
        <div class="statement-period">
            Periode laporan: <?php echo h(date('d F Y', strtotime($tanggalAwal))); ?>
            s.d. <?php echo h(date('d F Y', strtotime($tanggalAkhir))); ?>
        </div>

        <div class="statement-grid">
            <section class="statement-card">
                <div class="statement-title">Laporan Laba/Rugi<span>Asrindo</span><span>Periode
                        <?php echo h($tanggalAwalLabel); ?> s.d.
                        <?php echo h($tanggalAkhirLabel); ?></span><span>Disajikan dalam bentuk rupiah</span></div>
                <table class="statement-table">
                    <tbody>
                        <tr class="section">
                            <td colspan="2">PENDAPATAN</td>
                        </tr>
                        <?php foreach ($profitIncomeRows as $row): ?>
                            <tr class="indent">
                                <td><?php echo h($row['label']); ?></td>
                                <td><?php echo rupiah($row['nominal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total">
                            <td>TOTAL PENDAPATAN</td>
                            <td><?php echo rupiah($printIncomeTotal); ?></td>
                        </tr>
                        <tr class="section">
                            <td colspan="2">BEBAN POKOK PENDAPATAN</td>
                        </tr>
                        <?php foreach ($profitCogsRows as $row): ?>
                            <tr class="indent">
                                <td><?php echo h($row['label']); ?></td>
                                <td><?php echo rupiah($row['nominal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total">
                            <td>TOTAL BEBAN POKOK PENDAPATAN</td>
                            <td><?php echo rupiah($printCogsTotal); ?></td>
                        </tr>
                        <tr class="section">
                            <td colspan="2">BEBAN OPERASIONAL</td>
                        </tr>
                        <?php foreach ($profitOperatingRows as $row): ?>
                            <tr class="indent">
                                <td><?php echo h($row['label']); ?></td>
                                <td><?php echo rupiah($row['nominal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total">
                            <td>TOTAL BEBAN OPERASIONAL</td>
                            <td><?php echo rupiah($printOperatingTotal); ?></td>
                        </tr>
                        <tr class="grand-total">
                            <td>LABA / RUGI BERSIH</td>
                            <td><?php echo rupiah($printNetTotal); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="statement-card">
                <div class="statement-title">Laporan Perubahan Modal<span>Asrindo</span><span>Periode
                        <?php echo h($tanggalAwalLabel); ?> s.d.
                        <?php echo h($tanggalAkhirLabel); ?></span><span>Disajikan dalam bentuk rupiah</span></div>
                <table class="statement-table">
                    <tbody>
                        <tr class="indent">
                            <td>Setoran Modal</td>
                            <td><?php echo rupiah($modalAwal); ?></td>
                        </tr>
                        <tr class="indent">
                            <td>Laba Bersih</td>
                            <td><?php echo rupiah($labaRugiPeriode); ?></td>
                        </tr>
                        <tr class="indent">
                            <td>Penghasilan Komprehensif</td>
                            <td><?php echo rupiah($labaRugiPeriode); ?></td>
                        </tr>
                        <tr class="grand-total">
                            <td>Saldo Per <?php echo h($tanggalAkhirLabel); ?></td>
                            <td><?php echo rupiah($modalAwal + $labaRugiPeriode); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="statement-card">
                <div class="statement-title">Laporan Arus Kas<span>Asrindo</span><span>Periode
                        <?php echo h($tanggalAwalLabel); ?> s.d.
                        <?php echo h($tanggalAkhirLabel); ?></span><span>Disajikan dalam bentuk rupiah</span></div>
                <table class="statement-table">
                    <tbody>
                        <tr class="section">
                            <td colspan="2">ARUS KAS DARI AKTIVITAS OPERASIONAL</td>
                        </tr>
                        <?php foreach ($profitIncomeRows as $row): ?>
                            <tr class="indent">
                                <td><?php echo h('Penerimaan ' . $row['label']); ?></td>
                                <td><?php echo rupiah($row['nominal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach (array_merge($profitCogsRows, $profitOperatingRows) as $row): ?>
                            <tr class="indent">
                                <td><?php echo h('Pembayaran ' . $row['label']); ?></td>
                                <td><?php echo rupiah(-$row['nominal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total">
                            <td>Kas Bersih Operasional</td>
                            <td><?php echo rupiah($printIncomeTotal - $printCogsTotal - $printOperatingTotal); ?></td>
                        </tr>
                        <tr class="section">
                            <td colspan="2">ARUS KAS DARI AKTIVITAS INVESTASI</td>
                        </tr>
                        <?php foreach ($investmentRows as $row): ?>
                            <tr class="indent">
                                <td><?php echo h($row['label']); ?></td>
                                <td><?php echo rupiah(-$row['nominal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total">
                            <td>Kas Bersih Aktivitas Investasi</td>
                            <td><?php echo rupiah(-$printInvestmentTotal); ?></td>
                        </tr>
                        <tr class="section">
                            <td colspan="2">ARUS KAS DARI AKTIVITAS PENDANAAN</td>
                        </tr>
                        <tr class="indent">
                            <td>Penerimaan / Pembayaran Modal</td>
                            <td><?php echo rupiah(0); ?></td>
                        </tr>
                        <tr class="grand-total">
                            <td>KAS AKHIR PERIODE</td>
                            <td><?php echo rupiah($printCashEnd); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="statement-card">
                <div class="statement-title">Laporan Posisi Keuangan<span>Asrindo</span><span>Periode
                        <?php echo h($tanggalAwalLabel); ?> s.d.
                        <?php echo h($tanggalAkhirLabel); ?></span><span>Disajikan dalam bentuk rupiah</span></div>
                <table class="statement-table">
                    <tbody>
                        <tr class="section">
                            <td colspan="2">ASET LANCAR</td>
                        </tr>
                        <tr class="indent">
                            <td>Kas</td>
                            <td><?php echo rupiah($printCashEnd); ?></td>
                        </tr>
                        <tr class="indent">
                            <td>Piutang Penjualan</td>
                            <td><?php echo rupiah($piutangPenjualan); ?></td>
                        </tr>
                        <tr class="subsection">
                            <td colspan="2">UNIT ALAT BERAT</td>
                        </tr>
                        <?php foreach ($positionUnitRows as $row): ?>
                            <tr class="indent">
                                <td><?php echo h($row['label']); ?></td>
                                <td><?php echo rupiah($row['nominal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="subsection">
                            <td colspan="2">SPAREPART</td>
                        </tr>
                        <?php foreach ($positionSparepartRows as $row): ?>
                            <?php $stockValue = (float) $row['stok'] * (float) $row['harga_terakhir']; ?>
                            <tr class="indent">
                                <td><?php echo h($row['kode']); ?></td>
                                <td><?php echo rupiah($stockValue); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="subsection">
                            <td colspan="2">BAHAN RESTORASI</td>
                        </tr>
                        <?php foreach ($positionRestorasiRows as $row): ?>
                            <?php $restorationValue = (float) $row['stok'] * (float) $row['harga_terakhir']; ?>
                            <tr class="indent">
                                <td><?php echo h($row['kode']); ?></td>
                                <td><?php echo rupiah($restorationValue); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total">
                            <td>Total Aset Lancar</td>
                            <td><?php echo rupiah($printCashEnd + $piutangPenjualan + $asetTetap); ?></td>
                        </tr>
                        <tr class="section">
                            <td colspan="2">ASET TETAP</td>
                        </tr>
                        <tr class="indent">
                            <td>Aset Tetap</td>
                            <td><?php echo rupiah(0); ?></td>
                        </tr>
                        <tr class="grand-total">
                            <td>TOTAL ASET</td>
                            <td><?php echo rupiah($totalAset); ?></td>
                        </tr>
                        <tr class="section liability">
                            <td colspan="2">LIABILITAS</td>
                        </tr>
                        <tr class="indent">
                            <td>Utang Usaha</td>
                            <td><?php echo rupiah($totalLiabilitas); ?></td>
                        </tr>
                        <tr class="total">
                            <td>TOTAL LIABILITAS</td>
                            <td><?php echo rupiah($totalLiabilitas); ?></td>
                        </tr>
                        <tr class="section equity">
                            <td colspan="2">EKUITAS</td>
                        </tr>
                        <tr class="indent">
                            <td>Modal Disetor</td>
                            <td><?php echo rupiah($modalAwal); ?></td>
                        </tr>
                        <tr class="indent">
                            <td>Laba / Rugi Bersih</td>
                            <td><?php echo rupiah($labaRugiPeriode); ?></td>
                        </tr>
                        <tr class="total">
                            <td>TOTAL EKUITAS</td>
                            <td><?php echo rupiah($totalEkuitas); ?></td>
                        </tr>
                        <tr class="grand-total">
                            <td>TOTAL LIABILITAS DAN EKUITAS</td>
                            <td><?php echo rupiah($totalLiabilitas + $totalEkuitas); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>

    <div class="month-selector-card">
        <div class="month-selector-copy">
            <strong>Laporan Bulanan Client</strong>
            <span>Pilih bulan untuk melihat laporan pendapatan, pembelian, modal aset, dan beban operasional.</span>
        </div>
        <div class="month-selector-form">
            <div>
                <label for="reportMonth">Pilih Bulan</label>
                <select id="reportMonth">
                    <?php foreach ($reportMonthOptions as $monthOption): ?>
                        <option value="<?php echo h($monthOption); ?>" <?php echo $monthOption === $selectedReportMonth ? 'selected' : ''; ?>>
                            <?php echo h(date('F Y', strtotime($monthOption . '-01'))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn-month-detail" onclick="showMonthlyReport()">Tampilkan Laporan</button>
        </div>
    </div>

    <!-- =========================================================
         RINGKASAN UTAMA
    ========================================================== -->
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

    <!-- =========================================================
         AKTIVITAS
    ========================================================== -->
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
                        <div class="label">Pendapatan Penjualan</div>
                        <div class="value">
                            <?php echo rupiah($incomeBySource['PENJUALAN']); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Pendapatan Non Penjualan</div>
                        <div class="value">
                            <?php echo rupiah($incomeBySource['NON_PENJUALAN']); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Transaksi</div>
                        <div class="value">
                            <?php echo number_format(
                                (int) ($incomeSummary['jumlah_transaksi'] ?? 0),
                                0,
                                ',',
                                '.'
                            ); ?>
                        </div>
                        <div class="sub">Pemasukan aktual</div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Piutang Penjualan</div>
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
                        <div class="label">Belanja Pembelian</div>
                        <div class="value">
                            <?php echo rupiah($belanjaPembelian); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Modal Aset</div>
                        <div class="value">
                            <?php echo rupiah($modalAset); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Beban Operasional</div>
                        <div class="value">
                            <?php echo rupiah($bebanOperasional); ?>
                        </div>
                    </div>

                    <div class="mini-box">
                        <div class="label">Transaksi</div>
                        <div class="value">
                            <?php echo number_format(
                                (int) ($expenseSummary['jumlah_transaksi'] ?? 0),
                                0,
                                ',',
                                '.'
                            ); ?>
                        </div>
                        <div class="sub">Pengeluaran aktual</div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- =========================================================
         PENJUALAN & PEMBELIAN
    ========================================================== -->
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
                                (int) ($salesSummary['jumlah'] ?? 0),
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
                                (int) ($heavyPurchaseSummary['jumlah'] ?? 0),
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
                                (int) ($sparepartPurchaseSummary['jumlah'] ?? 0),
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
                                (float) ($heavyPurchaseSummary['total'] ?? 0)
                                + (float) ($sparepartPurchaseSummary['total'] ?? 0)
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

    <!-- =========================================================
         PROFITABILITAS
    ========================================================== -->
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
                        <?php echo number_format($margin, 2, ',', '.'); ?>%
                    </div>
                </div>

            </div>

            <div class="report-note">
                Laba kotor dihitung dari subtotal penjualan dikurangi HPP pada
                detail penjualan. PPN tidak dihitung sebagai laba.
            </div>

        </div>
    </div>

    <!-- =========================================================
         GRAFIK
    ========================================================== -->
    <div class="report-section-title">
        Tren Arus Kas
    </div>

    <div class="report-card">
        <div class="report-card-header">
            Pemasukan vs Pengeluaran per Bulan
            <small>Transaksi kas aktual pada periode yang dipilih</small>
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
                        (float) $monthly['pemasukan'],
                        (float) $monthly['pengeluaran']
                    );
                }
                ?>

                <div class="chart-wrap">

                    <?php foreach ($monthlyRows as $monthly): ?>

                        <?php
                        $inValue = (float) $monthly['pemasukan'];
                        $outValue = (float) $monthly['pengeluaran'];

                        $inHeight = $maxMonthly > 0
                            ? ($inValue / $maxMonthly) * 165
                            : 2;

                        $outHeight = $maxMonthly > 0
                            ? ($outValue / $maxMonthly) * 165
                            : 2;
                        ?>

                        <div class="chart-column">

                            <div class="chart-bars" title="<?php
                            echo h(
                                $monthly['periode'] .
                                ' | Masuk: ' . rupiah($inValue) .
                                ' | Keluar: ' . rupiah($outValue)
                            );
                            ?>">
                                <div class="chart-bar in" style="height:<?php echo max(2, $inHeight); ?>px"></div>

                                <div class="chart-bar out" style="height:<?php echo max(2, $outHeight); ?>px"></div>
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

    <!-- =========================================================
         RINGKASAN PER BULAN
    ========================================================== -->
    <div class="report-section-title">Laporan Keuangan Per Bulan</div>
    <div class="report-card">
        <div class="report-card-header">
            Ringkasan Pendapatan & Pengeluaran Bulanan
            <small>Semua bulan dalam periode filter ditampilkan. Tombol Detail membuka laporan bulan sesuai format
                client.</small>
        </div>
        <?php if (!$monthlyRows): ?>
            <div class="empty-report">Belum ada bulan pada periode tersebut.</div>
        <?php else: ?>
            <div class="monthly-table-wrap">
                <table class="report-table month-summary-table">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="right">Pendapatan Penjualan</th>
                            <th class="right">Pendapatan Non Penjualan</th>
                            <th class="right">Total Pendapatan</th>
                            <th class="right">Belanja Pembelian</th>
                            <th class="right">Modal Aset</th>
                            <th class="right">Beban Operasional</th>
                            <th class="right">Total Pengeluaran</th>
                            <th class="right">Arus Kas Bersih</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyRows as $monthly): ?>
                            <?php $monthNet = (float) $monthly['pemasukan'] - (float) $monthly['pengeluaran']; ?>
                            <tr>
                                <td><strong><?php echo h(date('F Y', strtotime($monthly['periode'] . '-01'))); ?></strong>
                                    <div class="report-note" style="margin-top:2px"><?php echo h($monthly['periode']); ?></div>
                                </td>
                                <td class="money"><?php echo rupiah($monthly['penjualan']); ?></td>
                                <td class="money"><?php echo rupiah($monthly['non_penjualan']); ?></td>
                                <td class="money"><strong><?php echo rupiah($monthly['pemasukan']); ?></strong></td>
                                <td class="money"><?php echo rupiah($monthly['belanja_pembelian']); ?></td>
                                <td class="money"><?php echo rupiah($monthly['modal_aset']); ?></td>
                                <td class="money"><?php echo rupiah($monthly['beban_operasional']); ?></td>
                                <td class="money"><strong><?php echo rupiah($monthly['pengeluaran']); ?></strong></td>
                                <td
                                    class="money <?php echo $monthNet >= 0 ? 'month-value-positive' : 'month-value-negative'; ?>">
                                    <?php echo rupiah($monthNet); ?></td>
                                <td><button type="button" class="btn-detail-month"
                                        onclick="showMonthlyReport('<?php echo h($monthly['periode']); ?>')">Detail</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="month-modal-backdrop" id="monthlyReportModal">
        <div class="month-modal">
            <div class="month-modal-header">
                <div class="month-modal-title">Laporan Keuangan Bulanan<small id="monthlyReportModalSubtitle">Format
                        laporan sesuai kebutuhan client</small></div>
                <button type="button" class="month-modal-close" onclick="closeMonthlyReport()">×</button>
            </div>
            <div id="monthlyReportContent"></div>
        </div>
    </div>

    <!-- =========================================================
         PENGELUARAN BERDASARKAN KATEGORI
    ========================================================== -->
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
                        <th>Kelompok</th>
                        <th>Transaksi</th>
                        <th class="right">Total</th>
                        <th style="width:35%">Proporsi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($expenseCategories as $category): ?>

                        <?php
                        $categoryTotal = (float) $category['total'];

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
                                <span class="badge badge-group">
                                    <?php
                                    echo h(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $category['kelompok']
                                        )
                                    );
                                    ?>
                                </span>
                            </td>

                            <td>
                                <?php echo number_format(
                                    (int) $category['jumlah'],
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

                                        <div class="progress-fill" style="width:<?php echo min(100, max(0, $percentage)); ?>%">
                                        </div>

                                    </div>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

    <!-- =========================================================
         DETAIL SEMUA TRANSAKSI KAS
    ========================================================== -->
    <div class="report-section-title">
        Detail Semua Transaksi Kas
    </div>

    <div class="report-card">

        <div class="report-card-header">
            Gabungan Pemasukan & Pengeluaran
            <small>
                Menampilkan seluruh transaksi kas aktual pada periode terpilih.
            </small>
        </div>

        <?php if (!$cashRows): ?>

            <div class="empty-report">
                Belum ada transaksi kas pada periode tersebut.
            </div>

        <?php else: ?>

            <div class="cash-table-wrap">

                <table class="report-table cash-table">

                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nomor</th>
                            <th>Tipe</th>
                            <th>Sumber</th>
                            <th>Kategori</th>
                            <th>Jenis</th>
                            <th class="right">Pemasukan</th>
                            <th class="right">Pengeluaran</th>
                            <th>Metode</th>
                            <th>Referensi</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($cashRows as $row): ?>

                            <tr>

                                <td>
                                    <?php echo h($row['tanggal']); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo h($row['nomor']); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php if ($row['tipe'] === 'PEMASUKAN'): ?>

                                        <span class="badge badge-income">
                                            PEMASUKAN
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-expense">
                                            PENGELUARAN
                                        </span>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php echo h(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $row['sumber']
                                        )
                                    ); ?>
                                </td>

                                <td>
                                    <?php echo h($row['kategori']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['jenis'] ?: '-'); ?>
                                </td>

                                <td class="money net-positive">
                                    <?php
                                    echo (float) $row['masuk'] > 0
                                        ? rupiah($row['masuk'])
                                        : '-';
                                    ?>
                                </td>

                                <td class="money net-negative">
                                    <?php
                                    echo (float) $row['keluar'] > 0
                                        ? rupiah($row['keluar'])
                                        : '-';
                                    ?>
                                </td>

                                <td>
                                    <?php echo h($row['metode'] ?: '-'); ?>
                                </td>

                                <td>
                                    <?php echo h($row['referensi'] ?: '-'); ?>
                                </td>

                                <td>
                                    <?php echo h($row['keterangan'] ?: '-'); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

    <div class="report-note">
        <strong>Catatan laporan:</strong>
        pembayaran penjualan dicatat sebagai pemasukan ketika pembayaran
        benar-benar diterima. Pembayaran pembelian alat berat dicatat sebagai
        pengeluaran ketika termin dibayar, sedangkan pembelian sparepart
        dicatat sebagai pengeluaran ketika transaksi berstatus SELESAI.
        Pengeluaran non-pembelian dikelompokkan berdasarkan
        <em>kelompok_laporan</em> pada kategori keuangan.
    </div>

</div>


<script>
    const monthlyReportData = <?php echo $monthlyReportDataJson ?: '{}'; ?>;
    function escapeReportText(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
    function formatReportMoney(value) { return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value || 0)); }
    function formatReportDate(value) { if (!value) return '-'; const p = String(value).split('-'); return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : escapeReportText(value); }
    function monthTitle(period) { const p = String(period || '').split('-'); const n = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']; return p.length === 2 ? (n[Number(p[1]) - 1] || p[1]) + ' ' + p[0] : (period || '-'); }
    function reportRowsHtml(rows, emptyText) { if (!rows || rows.length === 0) return '<div class="client-report-empty">' + escapeReportText(emptyText) + '</div>'; return '<table class="client-report-table"><tbody>' + rows.map(row => '<tr><td class="indent"><strong>' + escapeReportText(row.label || '-') + '</strong>' + (row.detail ? '<div class="report-note" style="margin-top:2px">' + escapeReportText(row.detail) + '</div>' : '') + (row.tanggal ? '<div class="report-note" style="margin-top:2px">' + escapeReportText(formatReportDate(row.tanggal)) + '</div>' : '') + '</td><td class="money">' + formatReportMoney(row.nominal) + '</td></tr>').join('') + '</tbody></table>'; }
    function sectionHtml(title, rows, emptyText) { const total = (rows || []).reduce((sum, row) => sum + Number(row.nominal || 0), 0); return '<div class="client-report-section"><div class="client-report-section-title">' + escapeReportText(title) + '</div>' + reportRowsHtml(rows, emptyText) + '<table class="client-report-table"><tbody><tr class="subtotal"><td>Jumlah ' + escapeReportText(title) + '</td><td class="money">' + formatReportMoney(total) + '</td></tr></tbody></table></div>'; }
    function showMonthlyReport(period) { const selector = document.getElementById('reportMonth'); const selected = period || (selector ? selector.value : ''); const data = monthlyReportData[selected]; if (!data) { alert('Data laporan bulan tersebut tidak tersedia.'); return; } const sales = (data.pendapatan_penjualan || []).reduce((s, r) => s + Number(r.nominal || 0), 0); const nonSales = (data.pendapatan_non_penjualan || []).reduce((s, r) => s + Number(r.nominal || 0), 0); const purchases = (data.belanja_pembelian || []).reduce((s, r) => s + Number(r.nominal || 0), 0); const assets = (data.modal_aset || []).reduce((s, r) => s + Number(r.nominal || 0), 0); const ops = (data.beban_operasional || []).reduce((s, r) => s + Number(r.nominal || 0), 0); const other = (data.pengeluaran_lainnya || []).reduce((s, r) => s + Number(r.nominal || 0), 0); const income = sales + nonSales; const expense = purchases + assets + ops + other; const net = income - expense; let html = '<div class="client-report"><div class="client-report-title"><strong>PT. ASRINDO GLOBAL MANDIRI</strong><span>LAPORAN LABA RUGI</span><span>' + escapeReportText(monthTitle(selected)) + '</span></div><div class="client-report-section"><div class="client-report-section-title">Pendapatan</div>' + reportRowsHtml(data.pendapatan_penjualan, 'Tidak ada pendapatan penjualan pada bulan ini.') + reportRowsHtml(data.pendapatan_non_penjualan, 'Tidak ada pendapatan non penjualan pada bulan ini.') + '<table class="client-report-table"><tbody><tr class="subtotal"><td>Jumlah Pendapatan</td><td class="money">' + formatReportMoney(income) + '</td></tr></tbody></table></div>' + sectionHtml('Beban Belanja Modal', data.belanja_pembelian, 'Tidak ada pembayaran pembelian pada bulan ini.') + sectionHtml('Beban Belanja Modal Aset', data.modal_aset, 'Tidak ada pengeluaran modal aset pada bulan ini.') + sectionHtml('Beban Operasional', data.beban_operasional, 'Tidak ada beban operasional pada bulan ini.'); if (other > 0) html += sectionHtml('Pengeluaran Lainnya', data.pengeluaran_lainnya, 'Tidak ada pengeluaran lainnya pada bulan ini.'); html += '<div class="client-report-section"><table class="client-report-table"><tbody><tr class="grand-total"><td>TOTAL PENGELUARAN</td><td class="money">' + formatReportMoney(expense) + '</td></tr><tr class="profit"><td>LABA / RUGI</td><td class="money ' + (net >= 0 ? 'month-value-positive' : 'month-value-negative') + '">' + formatReportMoney(net) + '</td></tr></tbody></table></div><div class="client-report-note">Pendapatan penjualan dihitung dari pemasukan yang benar-benar diterima pada bulan tersebut. Pembayaran pembelian alat berat dihitung ketika termin dibayar, sedangkan pembelian sparepart dihitung ketika transaksi menghasilkan pengeluaran. Pengeluaran non-pembelian mengikuti kelompok laporan pada kategori keuangan.</div></div>'; document.getElementById('monthlyReportContent').innerHTML = html; document.getElementById('monthlyReportModalSubtitle').textContent = 'Laporan bulan ' + monthTitle(selected); document.getElementById('monthlyReportModal').classList.add('show'); }
    function closeMonthlyReport() { document.getElementById('monthlyReportModal').classList.remove('show'); }
    function printReportWithCustomName() {
    const tanggalAwal = "<?php echo h($tanggalAwal); ?>";
    const tanggalAkhir = "<?php echo h($tanggalAkhir); ?>";
    const originalTitle = document.title;
    document.title = "Laporan_Keuangan_Periode_<?php echo h($tanggalAwalLabel); ?>_sd_<?php echo h($tanggalAkhirLabel); ?>";
    window.print();
    setTimeout(() => {
        document.title = originalTitle;
    }, 1000);
}
    document.getElementById('reportMonth').addEventListener('change', function () { showMonthlyReport(this.value); });
    document.getElementById('monthlyReportModal').addEventListener('click', function (e) { if (e.target === this) closeMonthlyReport(); }); document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMonthlyReport(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>