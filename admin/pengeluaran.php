<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Pengeluaran';
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
    header('Location: pengeluaran.php?' . http_build_query([
        'msg_type' => $type,
        'msg' => $message
    ]));
    exit;
}

function generateNomorPengeluaran(PDO $pdo): string
{
    $prefix = 'PK-' . date('Ymd') . '-';

    $stmt = $pdo->prepare("
        SELECT nomor_pengeluaran
        FROM pengeluaran
        WHERE nomor_pengeluaran LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);

    $last = (string)$stmt->fetchColumn();

    $next = 1;

    if ($last !== '') {
        $suffix = substr($last, -4);

        if (ctype_digit($suffix)) {
            $next = (int)$suffix + 1;
        }
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

/*
|--------------------------------------------------------------------------
| CRUD PENGELUARAN NON-PEMBELIAN
|--------------------------------------------------------------------------
|
| Pengeluaran dari pembelian tidak dibuat manual dari halaman ini.
|
| 1. Pembelian Alat Berat:
|    ketika termin pembayaran ditandai PAID pada
|    pembelian_alat_berat_detail.php, sistem membuat pengeluaran.
|
| 2. Pembelian Sparepart:
|    ketika pembelian berstatus SELESAI, sistem membuat pengeluaran.
|
| Halaman ini hanya membuat pengeluaran NON_PEMBELIAN.
|
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_non_pembelian') {
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $kategoriId = filter_var(
                $_POST['kategori_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $rekeningId = filter_var(
                $_POST['rekening_id'] ?? null,
                FILTER_VALIDATE_INT
            ) ?: null;
            // Jenis pengeluaran mengikuti kategori_keuangan.
            // Field jenis_pengeluaran tetap diisi untuk kompatibilitas database.
            $jenisPengeluaran = '';
            $nominalRaw = trim((string)($_POST['nominal'] ?? ''));
            $metode = strtoupper(
                trim((string)($_POST['metode_pembayaran'] ?? ''))
            );
            $referensi = trim((string)($_POST['referensi'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            /*
             * Form menerima angka:
             * 1000000
             * 1.000.000
             * 1,000,000
             */
            $nominalClean = str_replace(
                ['.', ',', ' '],
                '',
                $nominalRaw
            );
            $nominal = (float)$nominalClean;

            if ($tanggal === '') {
                redirectPage(
                    'error',
                    'Tanggal pengeluaran wajib diisi.'
                );
            }

            if (!$kategoriId) {
                redirectPage(
                    'error',
                    'Kategori pengeluaran wajib dipilih.'
                );
            }

            if ($nominal <= 0) {
                redirectPage(
                    'error',
                    'Nominal pengeluaran harus lebih besar dari 0.'
                );
            }

            /*
             * Validasi kategori harus bertipe PENGELUARAN.
             */
            $stmt = $pdo->prepare("
                SELECT id, kode, nama
                FROM kategori_keuangan
                WHERE id = ?
                  AND tipe = 'PENGELUARAN'
                LIMIT 1
            ");
            $stmt->execute([$kategoriId]);

            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                redirectPage(
                    'error',
                    'Kategori pengeluaran tidak ditemukan atau bukan kategori pengeluaran.'
                );
            }

            $nomor = generateNomorPengeluaran($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO pengeluaran
                (
                    nomor_pengeluaran,
                    tanggal,
                    sumber,
                    pembelian_pembayaran_id,
                    pembelian_sparepart_id,
                    kategori_id,
                    jenis_pengeluaran,
                    nominal,
                    metode_pembayaran,
                    referensi,
                    keterangan,
                    rekening_id,
                    created_by
                )
                VALUES
                (
                    ?, ?, 'NON_PEMBELIAN', NULL, NULL, ?,
                    ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $nomor,
                $tanggal,
                $kategoriId,
                $category['nama'],
                $nominal,
                $metode !== '' ? $metode : null,
                $referensi !== '' ? $referensi : null,
                $keterangan !== '' ? $keterangan : null,
                $rekeningId,
                (int)$_SESSION['admin_id']
            ]);

            redirectPage(
                'success',
                'Pengeluaran ' . $nomor . ' berhasil ditambahkan.'
            );
        }

        if ($action === 'delete_non_pembelian') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {
                redirectPage(
                    'error',
                    'ID pengeluaran tidak valid.'
                );
            }

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    nomor_pengeluaran,
                    sumber,
                    pembelian_pembayaran_id,
                    pembelian_sparepart_id
                FROM pengeluaran
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                redirectPage(
                    'error',
                    'Data pengeluaran tidak ditemukan.'
                );
            }

            if (
                $row['sumber'] !== 'NON_PEMBELIAN' ||
                $row['pembelian_pembayaran_id'] !== null ||
                $row['pembelian_sparepart_id'] !== null
            ) {
                redirectPage(
                    'error',
                    'Pengeluaran yang berasal dari pembelian dikelola dari transaksi pembelian.'
                );
            }

            $stmt = $pdo->prepare("
                DELETE FROM pengeluaran
                WHERE id = ?
                  AND sumber = 'NON_PEMBELIAN'
                LIMIT 1
            ");
            $stmt->execute([$id]);

            redirectPage(
                'success',
                'Pengeluaran ' . $row['nomor_pengeluaran'] .
                ' berhasil dihapus.'
            );
        }

        redirectPage('error', 'Aksi tidak dikenali.');
    } catch (Throwable $e) {
        redirectPage('error', $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| KATEGORI KEUANGAN — PENGELUARAN
|--------------------------------------------------------------------------
*/
$categories = $pdo->query("
    SELECT
        id,
        kode,
        nama,
        keterangan
    FROM kategori_keuangan
    WHERE tipe = 'PENGELUARAN'
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
|--------------------------------------------------------------------------
| DAFTAR PENGELUARAN
|--------------------------------------------------------------------------
|
| Satu daftar kas keluar menggabungkan:
| - Pembelian Alat Berat melalui pembayaran termin
| - Pembelian Sparepart saat transaksi SELESAI
| - Pengeluaran Non-Pembelian
|
*/
$expenseRows = $pdo->query("
    SELECT
        e.id,
        e.nomor_pengeluaran,
        e.tanggal,
        e.sumber,

        e.pembelian_pembayaran_id,
        e.pembelian_sparepart_id,

        e.kategori_id,
        e.jenis_pengeluaran,
        e.nominal,
        e.metode_pembayaran,
        e.referensi,
        e.keterangan,
        e.created_at,

        k.kode AS kategori_kode,
        k.nama AS kategori_nama,

        pp.nomor_pembayaran,
        pp.termin_ke,
        pp.jenis_pembayaran,
        pp.tanggal_jatuh_tempo,
        pp.tanggal_bayar,

        pab.id AS pembelian_alat_berat_id,
        pab.nomor_pembelian AS nomor_pembelian_alat_berat,
        sab.nama AS supplier_alat_berat,

        psp.id AS pembelian_sparepart_id_join,
        psp.nomor_pembelian AS nomor_pembelian_sparepart,
        ssp.nama AS supplier_sparepart

    FROM pengeluaran e

    LEFT JOIN kategori_keuangan k
        ON k.id = e.kategori_id

    LEFT JOIN pembelian_pembayaran pp
        ON pp.id = e.pembelian_pembayaran_id

    LEFT JOIN pembelian_alat_berat pab
        ON pab.id = pp.pembelian_alat_berat_id

    LEFT JOIN supplier sab
        ON sab.id = pab.supplier_id

    LEFT JOIN pembelian_sparepart psp
        ON psp.id = e.pembelian_sparepart_id

    LEFT JOIN supplier ssp
        ON ssp.id = psp.supplier_id

    ORDER BY e.tanggal DESC, e.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| RINGKASAN
|--------------------------------------------------------------------------
*/
$totalPengeluaran = 0.0;
$totalPembelian = 0.0;
$totalAlatBerat = 0.0;
$totalSparepart = 0.0;
$totalNonPembelian = 0.0;

foreach ($expenseRows as $row) {
    $nominal = (float)$row['nominal'];

    $totalPengeluaran += $nominal;

    if ($row['sumber'] === 'PEMBELIAN') {
        $totalPembelian += $nominal;

        if (!empty($row['pembelian_pembayaran_id'])) {
            $totalAlatBerat += $nominal;
        }

        if (!empty($row['pembelian_sparepart_id'])) {
            $totalSparepart += $nominal;
        }
    } else {
        $totalNonPembelian += $nominal;
    }
}

$msg = trim((string)($_GET['msg'] ?? ''));
$msgType = trim((string)($_GET['msg_type'] ?? 'success'));

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
.alert-success{
    background:#d1e7dd;
    color:#0f5132;
}
.alert-error{
    background:#f8d7da;
    color:#842029;
}

/* Ringkasan */
.expense-stats{
    display:grid;
    grid-template-columns:repeat(5, minmax(0, 1fr));
    gap:12px;
    margin-bottom:22px;
}

.expense-stat-card{
    position:relative;
    min-width:0;
    background:#fff;
    border:1px solid #dce3eb;
    border-radius:7px;
    padding:17px 16px 15px;
    box-sizing:border-box;
    box-shadow:0 1px 2px rgba(23,43,77,.04);
}

.expense-stat-card::before{
    content:'';
    position:absolute;
    left:0;
    top:10px;
    bottom:10px;
    width:3px;
    border-radius:0 3px 3px 0;
    background:#b8c7d8;
}

.expense-stat-card.stat-accent-blue::before{background:#0d6efd}
.expense-stat-card.stat-accent-red::before{background:#dc3545}
.expense-stat-card.stat-accent-orange::before{background:#d97706}
.expense-stat-card.stat-accent-purple::before{background:#7c3aed}

.expense-stat-value{
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

.expense-stat-value.value-red{color:#dc3545}
.expense-stat-value.value-blue{color:#145fc1}
.expense-stat-value.value-orange{color:#a85f00}
.expense-stat-value.value-purple{color:#6d28d9}

.expense-stat-label{
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

.search-box{
    flex:1;
    max-width:560px;
}
.page-size-box{
    width:100px;
}

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

.table-wrap{
    overflow-x:auto;
}

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

.badge-pembelian{
    background:#cfe2ff;
    color:#084298;
}

.badge-non{
    background:#e2e3e5;
    color:#41464b;
}

.source-detail{
    display:block;
    margin-top:4px;
    color:#526b8d;
    font-size:11px;
    line-height:1.4;
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

.btn-small:hover{
    background:#f3f6fa;
}

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

.modal-backdrop.show{
    display:flex!important;
}

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

.modal-body{
    padding:18px;
}

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

.form-group.full{
    grid-column:1/-1;
}

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
    .expense-stats{
        grid-template-columns:repeat(3, minmax(0, 1fr));
    }
}

@media(max-width:800px){
    .expense-stats{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }
}

@media(max-width:700px){
    .expense-stats{
        grid-template-columns:1fr;
    }

    .form-grid{
        grid-template-columns:1fr;
    }

    .form-group.full{
        grid-column:auto;
    }

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
        <h1>Pengeluaran</h1>
        <p>Mengelola seluruh uang keluar ASRINDO</p>
    </div>
</section>

<div class="page-actions">
    <button
        type="button"
        class="btn-primary"
        onclick="openExpenseModal()"
    >
        + Tambah Pengeluaran Non Pembelian
    </button>
</div>

<div class="expense-stats">
    <div class="expense-stat-card stat-accent-blue">
        <div class="expense-stat-value value-blue">
            <?php echo number_format(count($expenseRows), 0, ',', '.'); ?>
        </div>
        <div class="expense-stat-label">
            Total Transaksi Pengeluaran
        </div>
    </div>

    <div class="expense-stat-card stat-accent-red">
        <div class="expense-stat-value value-red">
            <?php echo rupiah($totalPengeluaran); ?>
        </div>
        <div class="expense-stat-label">
            Total Pengeluaran
        </div>
    </div>

    <div class="expense-stat-card stat-accent-blue">
        <div class="expense-stat-value value-blue">
            <?php echo rupiah($totalAlatBerat); ?>
        </div>
        <div class="expense-stat-label">
            Pembelian Alat Berat
        </div>
    </div>

    <div class="expense-stat-card stat-accent-orange">
        <div class="expense-stat-value value-orange">
            <?php echo rupiah($totalSparepart); ?>
        </div>
        <div class="expense-stat-label">
            Pembelian Sparepart
        </div>
    </div>

    <div class="expense-stat-card stat-accent-purple">
        <div class="expense-stat-value value-purple">
            <?php echo rupiah($totalNonPembelian); ?>
        </div>
        <div class="expense-stat-label">
            Non Pembelian
        </div>
    </div>
</div>

<div class="content-card">
    <div class="content-card-header">
        Daftar Pengeluaran
    </div>

    <div class="table-toolbar">
        <div class="search-box">
            <label class="field-label">Pencarian</label>
            <input
                id="globalSearch"
                class="form-control"
                placeholder="Cari nomor, pembelian, supplier, kategori, termin, jenis..."
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
                        <button
                            class="sort-btn"
                            onclick="sortTable('nomor_pengeluaran')"
                        >
                            No. Pengeluaran
                        </button>
                    </th>

                    <th>
                        <button
                            class="sort-btn"
                            onclick="sortTable('tanggal')"
                        >
                            Tanggal
                        </button>
                    </th>

                    <th>
                        <button
                            class="sort-btn"
                            onclick="sortTable('sumber')"
                        >
                            Sumber
                        </button>
                    </th>

                    <th>
                        <button
                            class="sort-btn"
                            onclick="sortTable('nomor_pembelian')"
                        >
                            Pembelian
                        </button>
                    </th>

                    <th>
                        <button
                            class="sort-btn"
                            onclick="sortTable('supplier_nama')"
                        >
                            Supplier
                        </button>
                    </th>

                    <th>
                        <button
                            class="sort-btn"
                            onclick="sortTable('termin_ke')"
                        >
                            Termin
                        </button>
                    </th>

                    <th>
                        <button
                            class="sort-btn"
                            onclick="sortTable('kategori_nama')"
                        >
                            Kategori
                        </button>
                    </th>

                    <th>
                        <button
                            class="sort-btn"
                            onclick="sortTable('jenis_pengeluaran')"
                        >
                            Jenis
                        </button>
                    </th>

                    <th>
                        <button
                            class="sort-btn"
                            onclick="sortTable('nominal')"
                        >
                            Nominal
                        </button>
                    </th>

                    <th>Metode</th>
                    <th>Referensi</th>
                    <th>Aksi</th>
                </tr>

                <tr class="filter-row">
                    <th>
                        <input
                            class="filter-input"
                            data-filter="nomor_pengeluaran"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="tanggal"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="sumber"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="nomor_pembelian"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="supplier_nama"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="termin_ke"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="kategori_nama"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="jenis_pengeluaran"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="nominal"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="metode_pembayaran"
                            placeholder="Filter"
                        >
                    </th>

                    <th>
                        <input
                            class="filter-input"
                            data-filter="referensi"
                            placeholder="Filter"
                        >
                    </th>

                    <th></th>
                </tr>
            </thead>

            <tbody id="expenseBody"></tbody>
        </table>
    </div>

    <div class="pagination-bar">
        <div id="tableInfo"></div>
        <div class="pagination" id="pagination"></div>
    </div>
</div>

<div class="modal-backdrop" id="expenseModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">
                Tambah Pengeluaran Non Pembelian
            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeExpenseModal()"
            >
                ×
            </button>
        </div>

        <form method="post">
            <input
                type="hidden"
                name="action"
                value="create_non_pembelian"
            >

            <div class="modal-body">
                <div class="info-box">
                    Pengeluaran pembelian alat berat dan sparepart
                    dibuat otomatis dari transaksi pembelian.
                    Form ini khusus untuk biaya yang bukan berasal
                    dari pembelian.
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="field-label">
                            Tanggal *
                        </label>

                        <input
                            type="date"
                            name="tanggal"
                            class="form-control"
                            value="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            Kategori Pengeluaran *
                        </label>

                        <select
                            name="kategori_id"
                            class="form-control"
                            required
                        >
                            <option value="">
                                -- Pilih Kategori --
                            </option>

                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?php echo (int)$category['id']; ?>"
                                >
                                    <?php
                                    echo h(
                                        $category['kode'] .
                                        ' - ' .
                                        $category['nama']
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            Jenis Pengeluaran
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="Mengikuti kategori keuangan"
                            readonly
                            style="background:#f8fafc;color:#718096;"
                        >

                        <div class="help-text">
                            Jenis pengeluaran otomatis mengikuti kategori yang dipilih.
                            Klasifikasi laporan dikelola melalui menu Kategori Keuangan.
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            Nominal *
                        </label>

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
                        <label class="field-label">
                            Metode Pembayaran
                        </label>

                        <select
                            name="metode_pembayaran"
                            class="form-control"
                        >
                            <option value="">
                                -- Pilih Metode --
                            </option>
                            <option value="TRANSFER">
                                TRANSFER
                            </option>
                            <option value="TUNAI">
                                TUNAI
                            </option>
                            <option value="GIRO">
                                GIRO
                            </option>
                            <option value="LAINNYA">
                                LAINNYA
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            Rekening Sumber
                        </label>
                        <select name="rekening_id" class="form-control">
                            <option value="">-- Pilih Rekening (Opsional) --</option>
                            <?php foreach ($rekeningOptions as $rek): ?>
                                <option value="<?php echo (int)$rek['id']; ?>">
                                    <?php echo h($rek['nama_rekening']); ?> — <?php echo h($rek['nama_bank']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            Referensi
                        </label>

                        <input
                            type="text"
                            name="referensi"
                            class="form-control"
                            placeholder="No. bukti / transfer / kuitansi"
                        >
                    </div>

                    <div class="form-group full">
                        <label class="field-label">
                            Keterangan
                        </label>

                        <textarea
                            name="keterangan"
                            class="form-control"
                            rows="3"
                            placeholder="Keterangan pengeluaran..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeExpenseModal()"
                >
                    Batal
                </button>

                <button type="submit" class="btn-primary">
                    Simpan Pengeluaran
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const expenseData = <?php
    echo json_encode(
        $expenseRows,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
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
    const number = Number(value || 0);

    return 'Rp ' + new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0
    }).format(number);
}

function sourceLabel(row) {
    if (row.sumber === 'NON_PEMBELIAN') {
        return '<span class="badge badge-non">NON PEMBELIAN</span>';
    }

    return '<span class="badge badge-pembelian">PEMBELIAN</span>';
}

function purchaseNumber(row) {
    if (row.pembelian_pembayaran_id) {
        return escapeHtml(row.nomor_pembelian_alat_berat || '-');
    }

    if (row.pembelian_sparepart_id) {
        return escapeHtml(row.nomor_pembelian_sparepart || '-');
    }

    return '-';
}

function supplierName(row) {
    if (row.pembelian_pembayaran_id) {
        return escapeHtml(row.supplier_alat_berat || '-');
    }

    if (row.pembelian_sparepart_id) {
        return escapeHtml(row.supplier_sparepart || '-');
    }

    return '-';
}

function purchaseDetail(row) {
    if (row.pembelian_pembayaran_id) {
        const termin = row.termin_ke
            ? 'Termin ' + escapeHtml(row.termin_ke)
            : 'Pembayaran';

        const jenis = row.jenis_pembayaran
            ? ' · ' + escapeHtml(row.jenis_pembayaran)
            : '';

        return '<span class="source-detail">' +
            termin + jenis +
            '</span>';
    }

    if (row.pembelian_sparepart_id) {
        return '<span class="source-detail">Pembelian Sparepart</span>';
    }

    return '<span class="source-detail">Pengeluaran manual</span>';
}

function actionHtml(row) {
    if (row.sumber === 'NON_PEMBELIAN') {
        return `
            <form
                method="post"
                onsubmit="return confirm('Hapus pengeluaran ini?');"
                style="display:inline"
            >
                <input
                    type="hidden"
                    name="action"
                    value="delete_non_pembelian"
                >
                <input
                    type="hidden"
                    name="id"
                    value="${Number(row.id)}"
                >
                <button
                    type="submit"
                    class="btn-small btn-danger"
                >
                    Hapus
                </button>
            </form>
        `;
    }

    if (row.pembelian_pembayaran_id) {
        return `
            <a
                class="btn-small"
                href="pembelian_alat_berat_detail.php?id=${Number(row.pembelian_alat_berat_id)}"
            >
                Lihat Pembelian
            </a>
        `;
    }

    if (row.pembelian_sparepart_id) {
        return `
            <a
                class="btn-small"
                href="pembelian_sparepart.php"
            >
                Lihat Pembelian
            </a>
        `;
    }

    return '-';
}

function searchableValue(row, key) {
    let value = row[key];

    if (key === 'nomor_pembelian') {
        value =
            row.nomor_pembelian_alat_berat ||
            row.nomor_pembelian_sparepart ||
            '';
    }

    if (key === 'supplier_nama') {
        value =
            row.supplier_alat_berat ||
            row.supplier_sparepart ||
            '';
    }

    return String(value ?? '').toLowerCase();
}

function getFilteredRows() {
    const globalSearch = (
        document.getElementById('globalSearch').value || ''
    ).toLowerCase().trim();

    const filters = {};

    document.querySelectorAll('[data-filter]').forEach(input => {
        filters[input.dataset.filter] =
            (input.value || '').toLowerCase().trim();
    });

    return expenseData.filter(row => {
        if (globalSearch !== '') {
            const haystack = [
                row.nomor_pengeluaran,
                row.tanggal,
                row.sumber,
                row.nomor_pembayaran,
                row.nomor_pembelian_alat_berat,
                row.nomor_pembelian_sparepart,
                row.supplier_alat_berat,
                row.supplier_sparepart,
                row.termin_ke,
                row.jenis_pembayaran,
                row.kategori_kode,
                row.kategori_nama,
                row.jenis_pengeluaran,
                row.nominal,
                row.metode_pembayaran,
                row.referensi,
                row.keterangan
            ]
                .join(' ')
                .toLowerCase();

            if (!haystack.includes(globalSearch)) {
                return false;
            }
        }

        for (const key in filters) {
            if (
                filters[key] !== '' &&
                !searchableValue(row, key).includes(filters[key])
            ) {
                return false;
            }
        }

        return true;
    });
}

function sortTable(key) {
    if (sortKey === key) {
        sortDirection = sortDirection === 'asc'
            ? 'desc'
            : 'asc';
    } else {
        sortKey = key;
        sortDirection = 'asc';
    }

    currentPage = 1;
    renderTable();
}

function compareValues(a, b, key) {
    let av;
    let bv;

    if (key === 'nomor_pembelian') {
        av =
            a.nomor_pembelian_alat_berat ||
            a.nomor_pembelian_sparepart ||
            '';

        bv =
            b.nomor_pembelian_alat_berat ||
            b.nomor_pembelian_sparepart ||
            '';
    } else if (key === 'supplier_nama') {
        av =
            a.supplier_alat_berat ||
            a.supplier_sparepart ||
            '';

        bv =
            b.supplier_alat_berat ||
            b.supplier_sparepart ||
            '';
    } else if (key === 'nominal') {
        av = Number(a.nominal || 0);
        bv = Number(b.nominal || 0);
    } else {
        av = a[key] ?? '';
        bv = b[key] ?? '';
    }

    if (typeof av === 'number' && typeof bv === 'number') {
        return av - bv;
    }

    return String(av).localeCompare(
        String(bv),
        'id',
        {
            numeric: true,
            sensitivity: 'base'
        }
    );
}

function renderTable() {
    const filtered = getFilteredRows();

    filtered.sort((a, b) => {
        const result = compareValues(a, b, sortKey);

        return sortDirection === 'asc'
            ? result
            : -result;
    });

    const pageSize = Number(
        document.getElementById('pageSize').value
    ) || 10;

    const totalPages = Math.max(
        1,
        Math.ceil(filtered.length / pageSize)
    );

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    const start = (currentPage - 1) * pageSize;
    const pageRows = filtered.slice(start, start + pageSize);

    const body = document.getElementById('expenseBody');

    if (pageRows.length === 0) {
        body.innerHTML = `
            <tr>
                <td colspan="12" class="empty-table">
                    Tidak ada data pengeluaran.
                </td>
            </tr>
        `;
    } else {
        body.innerHTML = pageRows.map(row => `
            <tr>
                <td>
                    <strong>
                        ${escapeHtml(row.nomor_pengeluaran)}
                    </strong>
                </td>

                <td>
                    ${escapeHtml(row.tanggal)}
                </td>

                <td>
                    ${sourceLabel(row)}
                </td>

                <td>
                    ${purchaseNumber(row)}
                    ${purchaseDetail(row)}
                </td>

                <td>
                    ${supplierName(row)}
                </td>

                <td>
                    ${
                        row.termin_ke
                            ? `<span class="payment-badge">
                                Termin ${escapeHtml(row.termin_ke)}
                               </span>`
                            : '-'
                    }
                </td>

                <td>
                    ${
                        row.kategori_nama
                            ? `<strong>${escapeHtml(
                                row.kategori_nama
                              )}</strong>
                               <span class="source-detail">
                                ${escapeHtml(
                                    row.kategori_kode || ''
                                )}
                               </span>`
                            : '-'
                    }
                </td>

                <td>
                    ${escapeHtml(row.jenis_pengeluaran || '-')}
                </td>

                <td class="money">
                    ${formatRupiah(row.nominal)}
                </td>

                <td>
                    ${escapeHtml(row.metode_pembayaran || '-')}
                </td>

                <td>
                    ${escapeHtml(row.referensi || '-')}
                </td>

                <td class="action-cell">
                    ${actionHtml(row)}
                </td>
            </tr>
        `).join('');
    }

    const info = document.getElementById('tableInfo');

    if (filtered.length === 0) {
        info.textContent = '0 data';
    } else {
        const from = start + 1;
        const to = Math.min(
            start + pageSize,
            filtered.length
        );

        info.textContent =
            `Menampilkan ${from}-${to} dari ${filtered.length} data`;
    }

    renderPagination(totalPages);
    updateSortIndicators();
}

function renderPagination(totalPages) {
    const container = document.getElementById('pagination');

    container.innerHTML = '';

    const prev = document.createElement('button');
    prev.textContent = '‹';
    prev.disabled = currentPage <= 1;

    prev.onclick = () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    };

    container.appendChild(prev);

    const maxButtons = 7;

    let startPage = Math.max(
        1,
        currentPage - Math.floor(maxButtons / 2)
    );

    let endPage = Math.min(
        totalPages,
        startPage + maxButtons - 1
    );

    if (endPage - startPage + 1 < maxButtons) {
        startPage = Math.max(
            1,
            endPage - maxButtons + 1
        );
    }

    for (let page = startPage; page <= endPage; page++) {
        const button = document.createElement('button');

        button.textContent = page;

        if (page === currentPage) {
            button.classList.add('active');
        }

        button.onclick = () => {
            currentPage = page;
            renderTable();
        };

        container.appendChild(button);
    }

    const next = document.createElement('button');
    next.textContent = '›';
    next.disabled = currentPage >= totalPages;

    next.onclick = () => {
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    };

    container.appendChild(next);
}

function updateSortIndicators() {
    document.querySelectorAll('.sort-btn').forEach(button => {
        button.classList.remove('active', 'asc', 'desc');
    });

    const buttons = document.querySelectorAll('.sort-btn');

    buttons.forEach(button => {
        const onclick = button.getAttribute('onclick') || '';
        const match = onclick.match(
            /sortTable\('([^']+)'\)/
        );

        if (!match) {
            return;
        }

        if (match[1] === sortKey) {
            button.classList.add(
                'active',
                sortDirection
            );
        }
    });
}

document.querySelectorAll('[data-filter]').forEach(input => {
    input.addEventListener('input', () => {
        currentPage = 1;
        renderTable();
    });
});

function openExpenseModal() {
    document
        .getElementById('expenseModal')
        .classList.add('show');
}

function closeExpenseModal() {
    document
        .getElementById('expenseModal')
        .classList.remove('show');
}

document
    .getElementById('expenseModal')
    .addEventListener('click', function(event) {
        if (event.target === this) {
            closeExpenseModal();
        }
    });

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeExpenseModal();
    }
});

renderTable();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
