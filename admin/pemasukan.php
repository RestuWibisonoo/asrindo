<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();
$pageTitle = 'Pemasukan';
$adminBase = '../';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)($value ?? 0), 0, ',', '.');
}

function redirectPage(string $type, string $message): void
{
    header('Location: pemasukan.php?' . http_build_query([
        'msg_type' => $type,
        'msg' => $message
    ]));
    exit;
}

function generateNomorPemasukan(PDO $pdo): string
{
    $prefix = 'PM-' . date('Ymd') . '-';

    $stmt = $pdo->prepare("
        SELECT nomor_pemasukan
        FROM pemasukan
        WHERE nomor_pemasukan LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);

    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last) {
        $number = (int)substr((string)$last, -4);
        $next = $number + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

/*
 * Halaman Kas -> Pemasukan hanya membuat pemasukan NON_PENJUALAN.
 * Pemasukan dari penjualan akan dibuat dari penjualan_detail.php
 * ketika customer melakukan DP/cicilan/pelunasan.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_non_penjualan') {
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $kategoriId = filter_var(
                $_POST['kategori_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $jenisPembayaran = trim((string)($_POST['jenis_pembayaran'] ?? ''));
            $rekeningId = filter_var(
                $_POST['rekening_id'] ?? null,
                FILTER_VALIDATE_INT
            ) ?: null;
            $nominalRaw = trim((string)($_POST['nominal'] ?? ''));
            $metode = trim((string)($_POST['metode_pembayaran'] ?? ''));
            $referensi = trim((string)($_POST['referensi'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            /*
             * Form menerima angka seperti:
             * 1000000
             * 1.000.000
             * 1,000,000
             */
            $nominalClean = str_replace(['.', ',', ' '], '', $nominalRaw);
            $nominal = (float)$nominalClean;

            if ($tanggal === '') {
                redirectPage('error', 'Tanggal pemasukan wajib diisi.');
            }

            if (!$kategoriId) {
                redirectPage('error', 'Kategori pendapatan wajib dipilih.');
            }

            if ($nominal <= 0) {
                redirectPage('error', 'Nominal pemasukan harus lebih besar dari 0.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM kategori_keuangan
                WHERE id = ?
                  AND tipe = 'PENDAPATAN'
                LIMIT 1
            ");
            $stmt->execute([$kategoriId]);

            if (!$stmt->fetch()) {
                redirectPage('error', 'Kategori pendapatan tidak ditemukan atau bukan kategori PENDAPATAN.');
            }

            $nomor = generateNomorPemasukan($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO pemasukan
                (
                    nomor_pemasukan,
                    tanggal,
                    sumber,
                    penjualan_id,
                    kategori_id,
                    jenis_pembayaran,
                    nominal,
                    metode_pembayaran,
                    referensi,
                    keterangan,
                    rekening_id,
                    created_by
                )
                VALUES
                (
                    ?, ?, 'NON_PENJUALAN', NULL, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $nomor,
                $tanggal,
                $kategoriId,
                $jenisPembayaran !== '' ? $jenisPembayaran : 'LAINNYA',
                $nominal,
                $metode !== '' ? $metode : null,
                $referensi !== '' ? $referensi : null,
                $keterangan !== '' ? $keterangan : null,
                $rekeningId,
                (int)$_SESSION['admin_id']
            ]);

            redirectPage(
                'success',
                'Pemasukan ' . $nomor . ' berhasil ditambahkan.'
            );
        }

        if ($action === 'delete_non_penjualan') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {
                redirectPage('error', 'ID pemasukan tidak valid.');
            }

            $stmt = $pdo->prepare("
                SELECT id, nomor_pemasukan, sumber
                FROM pemasukan
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                redirectPage('error', 'Data pemasukan tidak ditemukan.');
            }

            if ($row['sumber'] !== 'NON_PENJUALAN') {
                redirectPage(
                    'error',
                    'Pemasukan dari penjualan dikelola melalui halaman detail penjualan.'
                );
            }

            $stmt = $pdo->prepare("
                DELETE FROM pemasukan
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);

            redirectPage(
                'success',
                'Pemasukan ' . $row['nomor_pemasukan'] . ' berhasil dihapus.'
            );
        }
    } catch (Throwable $e) {
        redirectPage('error', $e->getMessage());
    }
}

/* Kategori pendapatan untuk pemasukan non-penjualan. Sumber master: kategori_keuangan. */
$categories = $pdo->query("
    SELECT
        id,
        kode,
        nama
    FROM kategori_keuangan
    WHERE tipe = 'PENDAPATAN'
    ORDER BY nama ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* Daftar rekening aktif untuk dropdown */
$rekeningOptions = $pdo->query("
    SELECT id, nama_rekening, nama_bank
    FROM rekening
    WHERE status = 'Aktif'
    ORDER BY nama_rekening ASC
")->fetchAll(PDO::FETCH_ASSOC);


/*
 * Daftar pemasukan:
 * - PENJUALAN berasal dari pembayaran DP/cicilan/pelunasan.
 * - NON_PENJUALAN berasal dari input manual halaman ini.
 */
$incomeRows = $pdo->query("
    SELECT
        pm.id,
        pm.nomor_pemasukan,
        pm.tanggal,
        pm.sumber,
        pm.penjualan_id,
        pm.kategori_id,
        pm.jadwal_pembayaran_id,
        pm.jenis_pembayaran,
        pm.nominal,
        pm.metode_pembayaran,
        pm.referensi,
        pm.keterangan,
        k.kode AS kategori_kode,
        k.nama AS kategori_nama,
        pj.nomor_penjualan,
        c.nama AS customer_nama,
        pp.termin_ke,
        pp.tanggal_jatuh_tempo AS jatuh_tempo_pembayaran,
        pp.status AS status_jadwal
    FROM pemasukan pm
    LEFT JOIN kategori_keuangan k
        ON k.id = pm.kategori_id
    LEFT JOIN penjualan pj
        ON pj.id = pm.penjualan_id
    LEFT JOIN customer c
        ON c.id = pj.customer_id
    LEFT JOIN penjualan_pembayaran pp
        ON pp.id = pm.jadwal_pembayaran_id
       AND pp.penjualan_id = pm.penjualan_id
    ORDER BY pm.tanggal DESC, pm.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalPemasukan = 0.0;
$totalPenjualanIncome = 0.0;
$totalNonPenjualan = 0.0;

foreach ($incomeRows as $row) {
    $nominal = (float)$row['nominal'];

    $totalPemasukan += $nominal;

    if ($row['sumber'] === 'PENJUALAN') {
        $totalPenjualanIncome += $nominal;
    } else {
        $totalNonPenjualan += $nominal;
    }
}

/*
 * Ringkasan piutang penjualan.
 * Nilai penjualan tidak dianggap pemasukan sebelum ada pembayaran
 * yang tercatat di tabel pemasukan.
 */
$totalPiutang = (float)$pdo->query("
    SELECT COALESCE(SUM(
        CASE
            WHEN p.total > COALESCE(pm.total_dibayar, 0)
            THEN p.total - COALESCE(pm.total_dibayar, 0)
            ELSE 0
        END
    ), 0)
    FROM penjualan p
    LEFT JOIN (
        SELECT
            penjualan_id,
            SUM(nominal) AS total_dibayar
        FROM pemasukan
        WHERE sumber = 'PENJUALAN'
          AND penjualan_id IS NOT NULL
        GROUP BY penjualan_id
    ) pm
        ON pm.penjualan_id = p.id
    WHERE UPPER(COALESCE(p.status, '')) <> 'BATAL'
")->fetchColumn();

$msg = trim((string)($_GET['msg'] ?? ''));
$msgType = $_GET['msg_type'] ?? 'success';

require __DIR__ . '/../includes/header.php';
?>

<style>
.page-actions{
    display:flex;
    justify-content:flex-end;
    margin-bottom:18px;
}

.btn-primary{
    border:0;
    background:#0d6efd;
    color:#fff;
    border-radius:6px;
    padding:10px 16px;
    font-weight:600;
    cursor:pointer;
}
.btn-primary:hover{background:#0b5ed7}

.alert{
    padding:11px 14px;
    border-radius:5px;
    margin-bottom:16px;
    font-size:13px;
}
.alert-success{background:#d1e7dd;color:#0f5132}
.alert-error{background:#f8d7da;color:#842029}

/* Ringkasan pemasukan */
.income-stats{
    display:grid;
    grid-template-columns:repeat(5, minmax(0, 1fr));
    gap:12px;
    margin-bottom:22px;
}

.income-stat-card{
    position:relative;
    min-width:0;
    background:#fff;
    border:1px solid #dce3eb;
    border-radius:7px;
    padding:17px 16px 15px;
    box-sizing:border-box;
    box-shadow:0 1px 2px rgba(23,43,77,.04);
}

.income-stat-card::before{
    content:'';
    position:absolute;
    left:0;
    top:10px;
    bottom:10px;
    width:3px;
    border-radius:0 3px 3px 0;
    background:#b8c7d8;
}

.income-stat-card.stat-accent-blue::before{background:#0d6efd}
.income-stat-card.stat-accent-green::before{background:#16a34a}
.income-stat-card.stat-accent-orange::before{background:#d97706}
.income-stat-card.stat-accent-red::before{background:#dc3545}

.income-stat-value{
    display:block;
    margin:0 0 7px;
    padding-left:4px;
    font-size:19px;
    line-height:1.25;
    font-weight:700;
    color:#172b4d;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.income-stat-value.value-green{color:#008a4a}
.income-stat-value.value-red{color:#dc3545}
.income-stat-value.value-blue{color:#145fc1}
.income-stat-value.value-orange{color:#a85f00}

.income-stat-label{
    display:block;
    padding-left:4px;
    font-size:12px;
    line-height:1.4;
    color:#526b8d;
    font-weight:500;
}

.content-card{
    background:#fff;
    border:1px solid #dce3eb;
    border-radius:6px;
    overflow:hidden;
}
.content-card-header{
    padding:14px 20px;
    border-bottom:1px solid #dce3eb;
    font-weight:600;
    color:#172b4d;
}

.table-toolbar{
    display:flex;
    justify-content:space-between;
    align-items:end;
    gap:20px;
    padding:14px 20px;
    border-bottom:1px solid #dce3eb;
}
.search-box{flex:1;max-width:500px}
.page-size-box{width:100px}

.field-label{
    display:block;
    font-size:12px;
    color:#718096;
    margin-bottom:6px;
}

.form-control,
.filter-input{
    width:100%;
    box-sizing:border-box;
    border:1px solid #b9c7d8;
    border-radius:5px;
    padding:9px 10px;
    font-size:13px;
    background:#fff;
}

.table-wrap{overflow-x:auto}

.data-table{
    width:100%;
    border-collapse:collapse;
    min-width:1450px;
}

.data-table th{
    background:#f3f6fa;
    color:#172b4d;
    font-size:12px;
    text-align:left;
    padding:11px 10px;
    border-bottom:1px solid #dce3eb;
    white-space:nowrap;
}

.data-table td{
    padding:11px 10px;
    border-bottom:1px solid #e5eaf0;
    font-size:13px;
    vertical-align:middle;
}

.filter-row th{
    background:#f8fafc;
    padding:8px 10px;
}
.filter-row input{
    font-size:12px;
    padding:7px 8px;
}

.sort-btn{
    border:0;
    background:transparent;
    padding:0;
    font:inherit;
    color:inherit;
    cursor:pointer;
}
.sort-btn::after{
    content:' ↕';
    color:#7b8794;
}
.sort-btn.active.asc::after{content:' ↑'}
.sort-btn.active.desc::after{content:' ↓'}

.badge{
    display:inline-block;
    padding:4px 8px;
    border-radius:5px;
    font-size:10px;
    font-weight:700;
}
.badge-penjualan{
    background:#cfe2ff;
    color:#084298;
}
.badge-non{
    background:#e2e3e5;
    color:#41464b;
}

.payment-badge{
    display:inline-block;
    padding:3px 7px;
    border-radius:4px;
    background:#eef3f8;
    color:#40566f;
    font-size:10px;
    font-weight:600;
}

.money{
    text-align:right;
    white-space:nowrap;
    font-weight:600;
}

.action-cell{
    white-space:nowrap;
}

.btn-small{
    border:1px solid #b8c7dc;
    background:#fff;
    color:#315a8c;
    border-radius:5px;
    padding:7px 10px;
    cursor:pointer;
    font-size:11px;
    text-decoration:none;
    display:inline-block;
}
.btn-small:hover{background:#f3f6fa}

.btn-danger{
    color:#b42318;
    border-color:#e2a8a8;
}

.pagination-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    padding:12px 20px;
    font-size:12px;
    color:#718096;
}

.pagination{
    display:flex;
    gap:5px;
}
.pagination button{
    border:1px solid #c9d4e3;
    background:#fff;
    padding:6px 10px;
    border-radius:4px;
    cursor:pointer;
}
.pagination button.active{
    background:#0d6efd;
    color:#fff;
    border-color:#0d6efd;
}
.pagination button:disabled{
    opacity:.5;
    cursor:not-allowed;
}

.modal-backdrop{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.45);
    z-index:9999;
    align-items:center;
    justify-content:center;
    padding:20px;
}
.modal-backdrop.show{display:flex!important}

.modal{
    width:min(680px,100%);
    max-height:90vh;
    overflow-y:auto;
    background:#fff;
    border-radius:8px;
    box-shadow:0 15px 45px rgba(0,0,0,.18);
}

.modal-header,
.modal-footer{
    padding:14px 18px;
    border-bottom:1px solid #e5eaf0;
}

.modal-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.modal-title{
    font-weight:700;
    color:#172b4d;
}

.modal-close{
    border:0;
    background:transparent;
    font-size:23px;
    color:#718096;
    cursor:pointer;
}

.modal-body{padding:18px}

.modal-footer{
    border-top:1px solid #e5eaf0;
    border-bottom:0;
    display:flex;
    justify-content:flex-end;
    gap:8px;
}

.btn-secondary{
    border:1px solid #b8c7dc;
    background:#fff;
    color:#526b8d;
    border-radius:5px;
    padding:9px 13px;
    cursor:pointer;
}

.form-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:14px;
}

.form-group.full{grid-column:1/-1}

.help-text{
    margin-top:6px;
    color:#718096;
    font-size:11px;
    line-height:1.5;
}

.info-box{
    padding:11px 13px;
    border:1px solid #dce3eb;
    background:#f8fafc;
    border-radius:5px;
    color:#526b8d;
    font-size:11px;
    line-height:1.5;
    margin-bottom:14px;
}

.empty-table{
    text-align:center;
    padding:25px!important;
    color:#8390a3;
    font-style:italic;
}

@media(max-width:1200px){
    .income-stats{grid-template-columns:repeat(3, minmax(0, 1fr));}
}

@media(max-width:800px){
    .income-stats{grid-template-columns:repeat(2, minmax(0, 1fr));}
}

@media(max-width:700px){
    .income-stats{grid-template-columns:1fr;}
    .form-grid{grid-template-columns:1fr}
    .form-group.full{grid-column:auto}
    .table-toolbar{
        align-items:stretch;
        flex-direction:column;
    }
    .search-box,
    .page-size-box{
        max-width:none;
        width:100%;
    }
    .pagination-bar{
        flex-direction:column;
        align-items:flex-start;
    }
}
</style>

<?php if ($msg !== ''): ?>
    <div class="alert <?php echo $msgType === 'error' ? 'alert-error' : 'alert-success'; ?>">
        <?php echo h($msg); ?>
    </div>
<?php endif; ?>

<section class="page-heading">
    <div>
        <h1>Pemasukan</h1>
        <p>Mengelola seluruh uang masuk ASRINDO</p>
    </div>
</section>

<div class="page-actions">
    <button type="button" class="btn-primary" onclick="openIncomeModal()">
        + Tambah Pemasukan Non Penjualan
    </button>
</div>

<div class="income-stats">
    <div class="income-stat-card stat-accent-blue">
        <div class="income-stat-value value-blue">
            <?php echo number_format(count($incomeRows), 0, ',', '.'); ?>
        </div>
        <div class="income-stat-label">Total Transaksi Pemasukan</div>
    </div>

    <div class="income-stat-card stat-accent-green">
        <div class="income-stat-value value-green">
            <?php echo rupiah($totalPemasukan); ?>
        </div>
        <div class="income-stat-label">Total Pemasukan</div>
    </div>

    <div class="income-stat-card stat-accent-green">
        <div class="income-stat-value value-green">
            <?php echo rupiah($totalPenjualanIncome); ?>
        </div>
        <div class="income-stat-label">Pemasukan Penjualan</div>
    </div>

    <div class="income-stat-card stat-accent-orange">
        <div class="income-stat-value value-orange">
            <?php echo rupiah($totalNonPenjualan); ?>
        </div>
        <div class="income-stat-label">Pemasukan Non Penjualan</div>
    </div>

    <div class="income-stat-card stat-accent-red">
        <div class="income-stat-value value-red">
            <?php echo rupiah($totalPiutang); ?>
        </div>
        <div class="income-stat-label">Piutang Penjualan</div>
    </div>
</div>

<div class="content-card">
    <div class="content-card-header">Daftar Pemasukan</div>

    <div class="table-toolbar">
        <div class="search-box">
            <label class="field-label">Pencarian</label>
            <input
                id="globalSearch"
                class="form-control"
                placeholder="Cari nomor pemasukan, penjualan, customer, termin, jenis, kategori..."
                oninput="currentPage=1;renderTable()"
            >
        </div>

        <div class="page-size-box">
            <label class="field-label">Tampilkan</label>
            <select
                id="pageSize"
                class="form-control"
                onchange="currentPage=1;renderTable()"
            >
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>
                        <button class="sort-btn" onclick="sortTable('nomor_pemasukan')">
                            No. Pemasukan
                        </button>
                    </th>
                    <th>
                        <button class="sort-btn" onclick="sortTable('tanggal')">
                            Tanggal
                        </button>
                    </th>
                    <th>
                        <button class="sort-btn" onclick="sortTable('sumber')">
                            Sumber
                        </button>
                    </th>
                    <th>
                        <button class="sort-btn" onclick="sortTable('termin_ke')">
                            Termin
                        </button>
                    </th>
                    <th>
                        <button class="sort-btn" onclick="sortTable('jenis_pembayaran')">
                            Jenis
                        </button>
                    </th>
                    <th>
                        <button class="sort-btn" onclick="sortTable('kategori_nama')">
                            Kategori
                        </button>
                    </th>
                    <th>
                        <button class="sort-btn" onclick="sortTable('jatuh_tempo_pembayaran')">
                            Jatuh Tempo
                        </button>
                    </th>
                    <th>
                        <button class="sort-btn" onclick="sortTable('nomor_penjualan')">
                            Penjualan
                        </button>
                    </th>
                    <th>
                        <button class="sort-btn" onclick="sortTable('customer_nama')">
                            Customer
                        </button>
                    </th>
                    <th>
                        <button class="sort-btn" onclick="sortTable('nominal')">
                            Nominal
                        </button>
                    </th>
                    <th>Metode</th>
                    <th>Referensi</th>
                    <th>Aksi</th>
                </tr>

                <tr class="filter-row">
                    <th>
                        <input class="filter-input" data-filter="nomor_pemasukan" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="tanggal" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="sumber" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="termin_ke" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="jenis_pembayaran" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="kategori_nama" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="jatuh_tempo_pembayaran" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="nomor_penjualan" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="customer_nama" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="nominal" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="metode_pembayaran" placeholder="Filter">
                    </th>
                    <th>
                        <input class="filter-input" data-filter="referensi" placeholder="Filter">
                    </th>
                    <th></th>
                </tr>
            </thead>

            <tbody id="incomeBody"></tbody>
        </table>
    </div>

    <div class="pagination-bar">
        <div id="tableInfo"></div>
        <div class="pagination" id="pagination"></div>
    </div>
</div>

<div class="modal-backdrop" id="incomeModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Tambah Pemasukan Non Penjualan</div>
            <button
                type="button"
                class="modal-close"
                onclick="closeIncomeModal()"
            >×</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="create_non_penjualan">

            <div class="modal-body">
                <div class="info-box">
                    Pemasukan dari penjualan seperti DP, cicilan, dan pelunasan
                    dicatat melalui halaman <strong>Detail Penjualan</strong>.
                    Form ini khusus untuk pemasukan yang bukan berasal dari penjualan.
                    Kategori pendapatan diambil langsung dari master
                    <strong>Kategori Keuangan</strong> dengan tipe
                    <strong>PENDAPATAN</strong>.
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="field-label">Tanggal *</label>
                        <input
                            type="date"
                            name="tanggal"
                            class="form-control"
                            value="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="field-label">Kategori Pendapatan *</label>
                        <select name="kategori_id" class="form-control" required>
                            <option value="">-- Pilih Kategori Pendapatan --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo (int)$category['id']; ?>">
                                    <?php echo h($category['kode'] . ' - ' . $category['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Jenis Pemasukan</label>
                        <select name="jenis_pembayaran" class="form-control">
                            <option value="PENDAPATAN">PENDAPATAN</option>
                            <option value="LAINNYA">LAINNYA</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Nominal *</label>
                        <input
                            type="text"
                            name="nominal"
                            class="form-control"
                            inputmode="numeric"
                            placeholder="Contoh: 5000000"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="field-label">Metode Pembayaran</label>
                        <select name="metode_pembayaran" class="form-control">
                            <option value="">-- Pilih Metode --</option>
                            <option value="TRANSFER">TRANSFER</option>
                            <option value="TUNAI">TUNAI</option>
                            <option value="GIRO">GIRO</option>
                            <option value="LAINNYA">LAINNYA</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Referensi</label>
                        <input
                            type="text"
                            name="referensi"
                            class="form-control"
                            placeholder="No. bukti / transfer / kuitansi"
                        >
                    </div>

                    <div class="form-group">
                        <label class="field-label">Rekening Tujuan</label>
                        <select name="rekening_id" class="form-control">
                            <option value="">-- Pilih Rekening (Opsional) --</option>
                            <?php foreach ($rekeningOptions as $rek): ?>
                                <option value="<?php echo (int)$rek['id']; ?>">
                                    <?php echo h($rek['nama_rekening']); ?> — <?php echo h($rek['nama_bank']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label class="field-label">Keterangan</label>
                        <textarea
                            name="keterangan"
                            class="form-control"
                            rows="3"
                            placeholder="Keterangan pemasukan..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeIncomeModal()"
                >Batal</button>

                <button type="submit" class="btn-primary">
                    Simpan Pemasukan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const incomeData = <?php
echo json_encode(
    $incomeRows,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
?>;

let currentPage = 1;
let sortKey = 'tanggal';
let sortDirection = 'desc';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatRupiah(value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID', {
        maximumFractionDigits: 0
    });
}

function sourceBadge(source) {
    if (String(source).toUpperCase() === 'PENJUALAN') {
        return '<span class="badge badge-penjualan">PENJUALAN</span>';
    }

    return '<span class="badge badge-non">NON PENJUALAN</span>';
}

function getFilters() {
    const filters = {};

    document.querySelectorAll('.filter-input').forEach(function(input) {
        filters[input.dataset.filter] = input.value
            .toLowerCase()
            .trim();
    });

    return filters;
}

function renderTable() {
    const search = document
        .getElementById('globalSearch')
        .value
        .toLowerCase()
        .trim();

    const pageSize = parseInt(
        document.getElementById('pageSize').value,
        10
    );

    const filters = getFilters();

    let rows = incomeData.filter(function(row) {
        const haystack = [
            row.nomor_pemasukan,
            row.tanggal,
            row.sumber,
            row.termin_ke,
            row.jenis_pembayaran,
            row.kategori_nama,
            row.jatuh_tempo_pembayaran,
            row.nomor_penjualan,
            row.customer_nama,
            row.nominal,
            row.metode_pembayaran,
            row.referensi,
            row.keterangan
        ].join(' ').toLowerCase();

        if (search && !haystack.includes(search)) {
            return false;
        }

        for (const key in filters) {
            if (
                filters[key] &&
                !String(row[key] ?? '')
                    .toLowerCase()
                    .includes(filters[key])
            ) {
                return false;
            }
        }

        return true;
    });

    rows.sort(function(a, b) {
        let av = a[sortKey] ?? '';
        let bv = b[sortKey] ?? '';

        if (sortKey === 'nominal' || sortKey === 'termin_ke') {
            av = Number(av);
            bv = Number(bv);
        } else {
            av = String(av).toLowerCase();
            bv = String(bv).toLowerCase();
        }

        if (av < bv) {
            return sortDirection === 'asc' ? -1 : 1;
        }

        if (av > bv) {
            return sortDirection === 'asc' ? 1 : -1;
        }

        return 0;
    });

    const total = rows.length;
    const pages = Math.max(1, Math.ceil(total / pageSize));

    if (currentPage > pages) {
        currentPage = pages;
    }

    const start = (currentPage - 1) * pageSize;
    const pageRows = rows.slice(start, start + pageSize);
    const tbody = document.getElementById('incomeBody');

    if (!pageRows.length) {
        tbody.innerHTML =
            '<tr><td colspan="13" class="empty-table">Belum ada data pemasukan.</td></tr>';
    } else {
        tbody.innerHTML = pageRows.map(function(row) {
            let action = '';

            if (String(row.sumber).toUpperCase() === 'PENJUALAN') {
                action =
                    '<a class="btn-small" href="penjualan_detail.php?id=' +
                    encodeURIComponent(row.penjualan_id) +
                    '">Detail Penjualan</a>';
            } else {
                action =
                    '<form method="post" style="display:inline" ' +
                    'onsubmit="return confirm(\'Hapus data pemasukan ini?\')">' +
                    '<input type="hidden" name="action" value="delete_non_penjualan">' +
                    '<input type="hidden" name="id" value="' +
                    escapeHtml(row.id) +
                    '">' +
                    '<button type="submit" class="btn-small btn-danger">Hapus</button>' +
                    '</form>';
            }

            return (
                '<tr>' +
                '<td><strong>' + escapeHtml(row.nomor_pemasukan) + '</strong></td>' +
                '<td>' + escapeHtml(row.tanggal) + '</td>' +
                '<td>' + sourceBadge(row.sumber) + '</td>' +
                '<td>' + (row.termin_ke ? '<span class="payment-badge">Termin ' + escapeHtml(row.termin_ke) + '</span>' : '-') + '</td>' +
                '<td><span class="payment-badge">' +
                    escapeHtml(row.jenis_pembayaran || '-') +
                    '</span></td>' +
                '<td>' + escapeHtml(row.kategori_nama || '-') + '</td>' +
                '<td>' + escapeHtml(row.jatuh_tempo_pembayaran || '-') + '</td>' +
                '<td>' + escapeHtml(row.nomor_penjualan || '-') + '</td>' +
                '<td>' + escapeHtml(row.customer_nama || '-') + '</td>' +
                '<td class="money">' + formatRupiah(row.nominal) + '</td>' +
                '<td>' + escapeHtml(row.metode_pembayaran || '-') + '</td>' +
                '<td>' + escapeHtml(row.referensi || '-') + '</td>' +
                '<td class="action-cell">' + action + '</td>' +
                '</tr>'
            );
        }).join('');
    }

    document.getElementById('tableInfo').textContent =
        total === 0
            ? '0 data'
            : 'Menampilkan ' +
              (start + 1) +
              '–' +
              Math.min(start + pageSize, total) +
              ' dari ' +
              total +
              ' data';

    renderPagination(pages);
    updateSortButtons();
}

function renderPagination(pages) {
    const container = document.getElementById('pagination');
    container.innerHTML = '';

    const prev = document.createElement('button');
    prev.type = 'button';
    prev.textContent = '‹';
    prev.disabled = currentPage <= 1;
    prev.onclick = function() {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    };
    container.appendChild(prev);

    const maxButtons = 7;
    let startPage = Math.max(1, currentPage - 3);
    let endPage = Math.min(pages, startPage + maxButtons - 1);

    if (endPage - startPage + 1 < maxButtons) {
        startPage = Math.max(1, endPage - maxButtons + 1);
    }

    for (let page = startPage; page <= endPage; page++) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = page;

        if (page === currentPage) {
            button.classList.add('active');
        }

        button.onclick = function() {
            currentPage = page;
            renderTable();
        };

        container.appendChild(button);
    }

    const next = document.createElement('button');
    next.type = 'button';
    next.textContent = '›';
    next.disabled = currentPage >= pages;
    next.onclick = function() {
        if (currentPage < pages) {
            currentPage++;
            renderTable();
        }
    };
    container.appendChild(next);
}

function sortTable(key) {
    if (sortKey === key) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey = key;
        sortDirection = key === 'tanggal' || key === 'nominal'
            ? 'desc'
            : 'asc';
    }

    currentPage = 1;
    renderTable();
}

function updateSortButtons() {
    document.querySelectorAll('.sort-btn').forEach(function(button) {
        button.classList.remove('active', 'asc', 'desc');

        const text = button.textContent.trim().toLowerCase();

        const map = {
            'no. pemasukan': 'nomor_pemasukan',
            'tanggal': 'tanggal',
            'sumber': 'sumber',
            'termin': 'termin_ke',
            'jenis': 'jenis_pembayaran',
            'kategori': 'kategori_nama',
            'jatuh tempo': 'jatuh_tempo_pembayaran',
            'penjualan': 'nomor_penjualan',
            'customer': 'customer_nama',
            'nominal': 'nominal'
        };

        const key = map[text];

        if (key === sortKey) {
            button.classList.add(
                'active',
                sortDirection
            );
        }
    });
}

function openIncomeModal() {
    document.getElementById('incomeModal').classList.add('show');
}

function closeIncomeModal() {
    document.getElementById('incomeModal').classList.remove('show');
}

document.getElementById('incomeModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeIncomeModal();
    }
});

document.querySelectorAll('.filter-input').forEach(function(input) {
    input.addEventListener('input', function() {
        currentPage = 1;
        renderTable();
    });
});

renderTable();
</script>
