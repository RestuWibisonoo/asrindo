<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Kategori Keuangan';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectPage(string $type, string $message): void
{
    header('Location: kategori_keuangan.php?' . http_build_query([
        'msg_type' => $type,
        'msg' => $message
    ]));
    exit;
}

$groupOptions = [
    'PENDAPATAN_PENJUALAN' => 'Pendapatan Penjualan',
    'PENDAPATAN_NON_PENJUALAN' => 'Pendapatan Non Penjualan',
    'BELANJA_PEMBELIAN' => 'Belanja Pembelian',
    'MODAL_ASET' => 'Belanja Modal Aset',
    'BEBAN_OPERASIONAL' => 'Beban Operasional',
];

/*
|--------------------------------------------------------------------------
| CRUD KATEGORI
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $kode = strtoupper(trim((string)($_POST['kode'] ?? '')));
            $nama = trim((string)($_POST['nama'] ?? ''));
            $tipe = strtoupper(trim((string)($_POST['tipe'] ?? '')));
            $kelompok = strtoupper(trim((string)($_POST['kelompok_laporan'] ?? '')));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($kode === '' || $nama === '') {
                redirectPage('error', 'Kode dan nama kategori wajib diisi.');
            }

            if (!in_array($tipe, ['PENDAPATAN', 'PENGELUARAN'], true)) {
                redirectPage('error', 'Tipe kategori tidak valid.');
            }

            if (!array_key_exists($kelompok, $groupOptions)) {
                redirectPage('error', 'Kelompok laporan tidak valid.');
            }

            if (
                $tipe === 'PENDAPATAN' &&
                !in_array(
                    $kelompok,
                    ['PENDAPATAN_PENJUALAN', 'PENDAPATAN_NON_PENJUALAN'],
                    true
                )
            ) {
                redirectPage(
                    'error',
                    'Kategori pendapatan harus berada pada kelompok pendapatan.'
                );
            }

            if (
                $tipe === 'PENGELUARAN' &&
                !in_array(
                    $kelompok,
                    ['BELANJA_PEMBELIAN', 'MODAL_ASET', 'BEBAN_OPERASIONAL'],
                    true
                )
            ) {
                redirectPage(
                    'error',
                    'Kategori pengeluaran harus berada pada kelompok pengeluaran.'
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO kategori_keuangan
                (kode, nama, tipe, kelompok_laporan, keterangan)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $kode,
                $nama,
                $tipe,
                $kelompok,
                $keterangan !== '' ? $keterangan : null
            ]);

            redirectPage('success', 'Kategori keuangan berhasil ditambahkan.');
        }

        if ($action === 'update') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $kode = strtoupper(trim((string)($_POST['kode'] ?? '')));
            $nama = trim((string)($_POST['nama'] ?? ''));
            $tipe = strtoupper(trim((string)($_POST['tipe'] ?? '')));
            $kelompok = strtoupper(trim((string)($_POST['kelompok_laporan'] ?? '')));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if (!$id || $kode === '' || $nama === '') {
                redirectPage('error', 'Data kategori tidak lengkap.');
            }

            if (!in_array($tipe, ['PENDAPATAN', 'PENGELUARAN'], true)) {
                redirectPage('error', 'Tipe kategori tidak valid.');
            }

            if (!array_key_exists($kelompok, $groupOptions)) {
                redirectPage('error', 'Kelompok laporan tidak valid.');
            }

            $stmt = $pdo->prepare("
                UPDATE kategori_keuangan
                SET
                    kode = ?,
                    nama = ?,
                    tipe = ?,
                    kelompok_laporan = ?,
                    keterangan = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $kode,
                $nama,
                $tipe,
                $kelompok,
                $keterangan !== '' ? $keterangan : null,
                $id
            ]);

            redirectPage('success', 'Kategori keuangan berhasil diperbarui.');
        }

        if ($action === 'delete') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {
                redirectPage('error', 'ID kategori tidak valid.');
            }

            $stmt = $pdo->prepare("
                SELECT id, kode, nama
                FROM kategori_keuangan
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);

            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                redirectPage('error', 'Kategori tidak ditemukan.');
            }

            /*
             * Cek referensi sebelum DELETE supaya pesan lebih jelas
             * daripada menunggu error foreign key.
             */
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM pemasukan
                WHERE kategori_id = ?
            ");
            $stmt->execute([$id]);
            $incomeUsage = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM pengeluaran
                WHERE kategori_id = ?
            ");
            $stmt->execute([$id]);
            $expenseUsage = (int)$stmt->fetchColumn();

            if ($incomeUsage > 0 || $expenseUsage > 0) {
                redirectPage(
                    'error',
                    'Kategori ' . $category['kode'] .
                    ' tidak dapat dihapus karena sudah digunakan pada transaksi.'
                );
            }

            $stmt = $pdo->prepare("
                DELETE FROM kategori_keuangan
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);

            redirectPage(
                'success',
                'Kategori ' . $category['kode'] . ' berhasil dihapus.'
            );
        }

        redirectPage('error', 'Aksi tidak dikenali.');
    } catch (PDOException $e) {
        $message = $e->getMessage();

        if (stripos($message, 'Duplicate entry') !== false) {
            $message = 'Kode kategori sudah digunakan. Silakan gunakan kode lain.';
        }

        redirectPage('error', $message);
    } catch (Throwable $e) {
        redirectPage('error', $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/
$categories = $pdo->query("
    SELECT
        id,
        kode,
        nama,
        tipe,
        kelompok_laporan,
        keterangan,
        created_at,
        updated_at
    FROM kategori_keuangan
    ORDER BY
        tipe ASC,
        kelompok_laporan ASC,
        nama ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalCategories = count($categories);
$totalIncomeCategories = 0;
$totalExpenseCategories = 0;

foreach ($categories as $category) {
    if ($category['tipe'] === 'PENDAPATAN') {
        $totalIncomeCategories++;
    }

    if ($category['tipe'] === 'PENGELUARAN') {
        $totalExpenseCategories++;
    }
}

$msg = trim((string)($_GET['msg'] ?? ''));
$msgType = trim((string)($_GET['msg_type'] ?? 'success'));

require __DIR__ . '/../includes/header.php';
?>

<style>
.category-page{
    max-width:1500px;
    margin:0 auto;
}

.category-heading{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    margin-bottom:18px;
}

.category-heading h1{
    margin:0;
    color:#172b4d;
}

.category-heading p{
    margin:5px 0 0;
    color:#718096;
    font-size:13px;
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

.btn-primary:hover{
    background:#0b5ed7;
}

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

.category-stats{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
    margin-bottom:18px;
}

.category-stat{
    position:relative;
    background:#fff;
    border:1px solid #dce3eb;
    border-radius:7px;
    padding:16px;
    box-shadow:0 1px 2px rgba(23,43,77,.04);
}

.category-stat::before{
    content:'';
    position:absolute;
    left:0;
    top:10px;
    bottom:10px;
    width:3px;
    border-radius:0 3px 3px 0;
    background:#0d6efd;
}

.category-stat.income::before{
    background:#198754;
}

.category-stat.expense::before{
    background:#dc3545;
}

.category-stat .value{
    display:block;
    padding-left:4px;
    font-size:21px;
    line-height:1.2;
    font-weight:700;
    color:#172b4d;
    margin-bottom:5px;
}

.category-stat.income .value{
    color:#198754;
}

.category-stat.expense .value{
    color:#dc3545;
}

.category-stat .label{
    display:block;
    padding-left:4px;
    color:#526b8d;
    font-size:12px;
}

.content-card{
    background:#fff;
    border:1px solid #dce3eb;
    border-radius:7px;
    overflow:hidden;
}

.card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    padding:14px 18px;
    border-bottom:1px solid #dce3eb;
}

.card-title{
    font-weight:700;
    color:#172b4d;
}

.card-subtitle{
    margin-top:3px;
    color:#8390a3;
    font-size:11px;
}

.toolbar{
    display:flex;
    justify-content:space-between;
    align-items:end;
    gap:15px;
    padding:13px 18px;
    border-bottom:1px solid #e5eaf0;
}

.search-box{
    flex:1;
    max-width:600px;
}

.page-size{
    width:100px;
}

.field-label{
    display:block;
    margin-bottom:5px;
    color:#718096;
    font-size:11px;
}

.form-control{
    width:100%;
    box-sizing:border-box;
    border:1px solid #b9c7d8;
    border-radius:5px;
    padding:9px 10px;
    font-size:12px;
    background:#fff;
}

.table-wrap{
    overflow-x:auto;
}

.data-table{
    width:100%;
    min-width:900px;
    border-collapse:collapse;
}

.data-table th{
    padding:10px;
    background:#f3f6fa;
    border-bottom:1px solid #dce3eb;
    color:#172b4d;
    font-size:11px;
    text-align:left;
    white-space:nowrap;
}

.data-table td{
    padding:10px;
    border-bottom:1px solid #e5eaf0;
    color:#334e68;
    font-size:12px;
    vertical-align:middle;
}

.data-table tr:hover td{
    background:#fafcff;
}

.filter-row th{
    padding:7px 10px;
    background:#f8fafc;
}

.filter-input{
    width:100%;
    box-sizing:border-box;
    padding:7px 8px;
    border:1px solid #c9d4e3;
    border-radius:4px;
    font-size:11px;
}

.badge{
    display:inline-block;
    padding:4px 8px;
    border-radius:5px;
    font-size:10px;
    font-weight:700;
}

.badge-income{
    background:#d1e7dd;
    color:#0f5132;
}

.badge-expense{
    background:#f8d7da;
    color:#842029;
}

.group-badge{
    display:inline-block;
    padding:4px 7px;
    border-radius:4px;
    background:#eef3f8;
    color:#40566f;
    font-size:10px;
    font-weight:600;
}

.code{
    font-family:Consolas,monospace;
    font-size:11px;
    color:#526b8d;
}

.action-cell{
    white-space:nowrap;
}

.btn-small{
    border:1px solid #b8c7dc;
    background:#fff;
    color:#315a8c;
    border-radius:4px;
    padding:6px 9px;
    cursor:pointer;
    font-size:11px;
    margin-right:4px;
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
    padding:12px 18px;
    color:#718096;
    font-size:11px;
}

.pagination{
    display:flex;
    gap:4px;
}

.pagination button{
    border:1px solid #c9d4e3;
    background:#fff;
    padding:6px 9px;
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

.empty-table{
    text-align:center;
    padding:25px!important;
    color:#8390a3;
    font-style:italic;
}

.modal-backdrop{
    display:none;
    position:fixed;
    inset:0;
    z-index:9999;
    background:rgba(15,23,42,.45);
    align-items:center;
    justify-content:center;
    padding:20px;
}

.modal-backdrop.show{
    display:flex;
}

.modal{
    width:min(650px,100%);
    max-height:90vh;
    overflow-y:auto;
    background:#fff;
    border-radius:8px;
    box-shadow:0 15px 45px rgba(0,0,0,.18);
}

.modal-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:14px 18px;
    border-bottom:1px solid #e5eaf0;
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
    display:flex;
    justify-content:flex-end;
    gap:8px;
    padding:13px 18px;
    border-top:1px solid #e5eaf0;
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
    grid-template-columns:1fr 2fr;
    gap:13px;
}

.form-group.full{
    grid-column:1/-1;
}

.help-box{
    margin-bottom:14px;
    padding:10px 12px;
    background:#f8fafc;
    border:1px solid #dce3eb;
    border-radius:5px;
    color:#526b8d;
    font-size:11px;
    line-height:1.5;
}

@media(max-width:800px){
    .category-heading{
        flex-direction:column;
        align-items:stretch;
    }

    .category-stats{
        grid-template-columns:1fr 1fr;
    }

    .toolbar{
        flex-direction:column;
        align-items:stretch;
    }

    .search-box,
    .page-size{
        width:100%;
        max-width:none;
    }

    .form-grid{
        grid-template-columns:1fr;
    }

    .form-group.full{
        grid-column:auto;
    }
}

@media(max-width:600px){
    .category-stats{
        grid-template-columns:1fr;
    }
}
</style>

<div class="category-page">

    <?php if ($msg !== ''): ?>
        <div class="alert <?php echo $msgType === 'error' ? 'alert-error' : 'alert-success'; ?>">
            <?php echo h($msg); ?>
        </div>
    <?php endif; ?>

    <div class="category-heading">
        <div>
            <h1>Kategori Keuangan</h1>
            <p>
                Kelola kategori pemasukan dan pengeluaran serta kelompok laporan keuangan.
            </p>
        </div>

        <button
            type="button"
            class="btn-primary"
            onclick="openCreateModal()"
        >
            + Tambah Kategori
        </button>
    </div>

    <div class="category-stats">

        <div class="category-stat">
            <span class="value">
                <?php echo number_format($totalCategories, 0, ',', '.'); ?>
            </span>
            <span class="label">
                Total Kategori
            </span>
        </div>

        <div class="category-stat income">
            <span class="value">
                <?php echo number_format($totalIncomeCategories, 0, ',', '.'); ?>
            </span>
            <span class="label">
                Kategori Pendapatan
            </span>
        </div>

        <div class="category-stat expense">
            <span class="value">
                <?php echo number_format($totalExpenseCategories, 0, ',', '.'); ?>
            </span>
            <span class="label">
                Kategori Pengeluaran
            </span>
        </div>

    </div>

    <div class="content-card">

        <div class="card-header">
            <div>
                <div class="card-title">Daftar Kategori Keuangan</div>
                <div class="card-subtitle">
                    Kelompok laporan digunakan sebagai dasar pengelompokan pada dashboard laporan.
                </div>
            </div>
        </div>

        <div class="toolbar">

            <div class="search-box">
                <label class="field-label">Pencarian</label>

                <input
                    type="text"
                    id="globalSearch"
                    class="form-control"
                    placeholder="Cari kode, nama, tipe, kelompok..."
                    oninput="currentPage=1;renderTable()"
                >
            </div>

            <div class="page-size">
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
                        <th>No</th>
                        <th>Kode</th>
                        <th>Nama Kategori</th>
                        <th>Tipe</th>
                        <th>Kelompok Laporan</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                    </tr>

                    <tr class="filter-row">
                        <th></th>

                        <th>
                            <input
                                class="filter-input"
                                data-filter="kode"
                                placeholder="Filter"
                            >
                        </th>

                        <th>
                            <input
                                class="filter-input"
                                data-filter="nama"
                                placeholder="Filter"
                            >
                        </th>

                        <th>
                            <input
                                class="filter-input"
                                data-filter="tipe"
                                placeholder="Filter"
                            >
                        </th>

                        <th>
                            <input
                                class="filter-input"
                                data-filter="kelompok"
                                placeholder="Filter"
                            >
                        </th>

                        <th>
                            <input
                                class="filter-input"
                                data-filter="keterangan"
                                placeholder="Filter"
                            >
                        </th>

                        <th></th>
                    </tr>
                </thead>

                <tbody id="categoryBody"></tbody>

            </table>

        </div>

        <div class="pagination-bar">
            <div id="tableInfo"></div>
            <div class="pagination" id="pagination"></div>
        </div>

    </div>

</div>

<!-- ============================================================
     MODAL CREATE / EDIT
     ============================================================ -->

<div class="modal-backdrop" id="categoryModal">

    <div class="modal">

        <div class="modal-header">

            <div class="modal-title" id="modalTitle">
                Tambah Kategori Keuangan
            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeModal()"
            >
                ×
            </button>

        </div>

        <form method="post" id="categoryForm">

            <input
                type="hidden"
                name="action"
                id="formAction"
                value="create"
            >

            <input
                type="hidden"
                name="id"
                id="formId"
                value=""
            >

            <div class="modal-body">

                <div class="help-box">
                    <strong>Kelompok laporan</strong> menentukan posisi kategori
                    pada laporan bulanan. Kategori yang sudah digunakan dalam
                    transaksi sebaiknya tidak dihapus.
                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="field-label">
                            Kode *
                        </label>

                        <input
                            type="text"
                            name="kode"
                            id="formKode"
                            class="form-control"
                            maxlength="50"
                            placeholder="Contoh: E010"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            Nama Kategori *
                        </label>

                        <input
                            type="text"
                            name="nama"
                            id="formNama"
                            class="form-control"
                            maxlength="150"
                            placeholder="Contoh: Gaji Karyawan"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            Tipe *
                        </label>

                        <select
                            name="tipe"
                            id="formTipe"
                            class="form-control"
                            onchange="updateGroupOptions()"
                            required
                        >
                            <option value="PENDAPATAN">
                                PENDAPATAN
                            </option>

                            <option value="PENGELUARAN">
                                PENGELUARAN
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            Kelompok Laporan *
                        </label>

                        <select
                            name="kelompok_laporan"
                            id="formKelompok"
                            class="form-control"
                            required
                        >
                            <option
                                value="PENDAPATAN_PENJUALAN"
                                data-tipe="PENDAPATAN"
                            >
                                Pendapatan Penjualan
                            </option>

                            <option
                                value="PENDAPATAN_NON_PENJUALAN"
                                data-tipe="PENDAPATAN"
                            >
                                Pendapatan Non Penjualan
                            </option>

                            <option
                                value="BELANJA_PEMBELIAN"
                                data-tipe="PENGELUARAN"
                            >
                                Belanja Pembelian
                            </option>

                            <option
                                value="MODAL_ASET"
                                data-tipe="PENGELUARAN"
                            >
                                Belanja Modal Aset
                            </option>

                            <option
                                value="BEBAN_OPERASIONAL"
                                data-tipe="PENGELUARAN"
                            >
                                Beban Operasional
                            </option>
                        </select>
                    </div>

                    <div class="form-group full">

                        <label class="field-label">
                            Keterangan
                        </label>

                        <textarea
                            name="keterangan"
                            id="formKeterangan"
                            class="form-control"
                            rows="3"
                            placeholder="Keterangan kategori..."
                        ></textarea>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModal()"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    class="btn-primary"
                    id="saveButton"
                >
                    Simpan
                </button>

            </div>

        </form>

    </div>

</div>

<script>
const categoryData = <?php
echo json_encode(
    $categories,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
);
?>;

let currentPage = 1;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function groupLabel(value) {
    const labels = {
        'PENDAPATAN_PENJUALAN': 'Pendapatan Penjualan',
        'PENDAPATAN_NON_PENJUALAN': 'Pendapatan Non Penjualan',
        'BELANJA_PEMBELIAN': 'Belanja Pembelian',
        'MODAL_ASET': 'Belanja Modal Aset',
        'BEBAN_OPERASIONAL': 'Beban Operasional'
    };

    return labels[value] || value || '-';
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

    return categoryData.filter(row => {

        if (globalSearch !== '') {
            const haystack = [
                row.kode,
                row.nama,
                row.tipe,
                row.kelompok_laporan,
                groupLabel(row.kelompok_laporan),
                row.keterangan
            ]
                .join(' ')
                .toLowerCase();

            if (!haystack.includes(globalSearch)) {
                return false;
            }
        }

        for (const key in filters) {
            if (filters[key] === '') {
                continue;
            }

            let value = '';

            if (key === 'kelompok') {
                value =
                    row.kelompok_laporan +
                    ' ' +
                    groupLabel(row.kelompok_laporan);
            } else {
                value = row[key] ?? '';
            }

            if (
                !String(value)
                    .toLowerCase()
                    .includes(filters[key])
            ) {
                return false;
            }
        }

        return true;
    });
}

function renderTable() {
    const rows = getFilteredRows();

    const pageSize = Number(
        document.getElementById('pageSize').value
    ) || 10;

    const totalPages = Math.max(
        1,
        Math.ceil(rows.length / pageSize)
    );

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    const start = (currentPage - 1) * pageSize;
    const pageRows = rows.slice(
        start,
        start + pageSize
    );

    const body = document.getElementById('categoryBody');

    if (pageRows.length === 0) {
        body.innerHTML = `
            <tr>
                <td colspan="7" class="empty-table">
                    Tidak ada kategori keuangan.
                </td>
            </tr>
        `;
    } else {
        body.innerHTML = pageRows.map((row, index) => {

            const nomor = start + index + 1;

            const badge = row.tipe === 'PENDAPATAN'
                ? '<span class="badge badge-income">PENDAPATAN</span>'
                : '<span class="badge badge-expense">PENGELUARAN</span>';

            const rowJson = encodeURIComponent(
                JSON.stringify(row)
            );

            return `
                <tr>
                    <td>${nomor}</td>

                    <td>
                        <span class="code">
                            ${escapeHtml(row.kode)}
                        </span>
                    </td>

                    <td>
                        <strong>
                            ${escapeHtml(row.nama)}
                        </strong>
                    </td>

                    <td>
                        ${badge}
                    </td>

                    <td>
                        <span class="group-badge">
                            ${escapeHtml(
                                groupLabel(row.kelompok_laporan)
                            )}
                        </span>
                    </td>

                    <td>
                        ${escapeHtml(row.keterangan || '-')}
                    </td>

                    <td class="action-cell">

                        <button
                            type="button"
                            class="btn-small"
                            onclick="editCategory('${rowJson}')"
                        >
                            Edit
                        </button>

                        <form
                            method="post"
                            style="display:inline"
                            onsubmit="return confirm(
                                'Hapus kategori ' +
                                ${JSON.stringify(row.kode)} +
                                '?'
                            )"
                        >
                            <input
                                type="hidden"
                                name="action"
                                value="delete"
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

                    </td>
                </tr>
            `;
        }).join('');
    }

    const info = document.getElementById('tableInfo');

    if (rows.length === 0) {
        info.textContent = '0 data';
    } else {
        info.textContent =
            `Menampilkan ${start + 1}-${Math.min(
                start + pageSize,
                rows.length
            )} dari ${rows.length} data`;
    }

    renderPagination(totalPages);
}

function renderPagination(totalPages) {
    const container =
        document.getElementById('pagination');

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

    for (
        let page = startPage;
        page <= endPage;
        page++
    ) {
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

function openCreateModal() {
    document.getElementById('modalTitle').textContent =
        'Tambah Kategori Keuangan';

    document.getElementById('formAction').value =
        'create';

    document.getElementById('formId').value = '';

    document.getElementById('formKode').value = '';
    document.getElementById('formNama').value = '';

    document.getElementById('formTipe').value =
        'PENGELUARAN';

    updateGroupOptions();

    document.getElementById('formKelompok').value =
        'BEBAN_OPERASIONAL';

    document.getElementById('formKeterangan').value = '';

    document.getElementById('saveButton').textContent =
        'Simpan';

    document
        .getElementById('categoryModal')
        .classList.add('show');
}

function editCategory(encodedRow) {
    const row = JSON.parse(
        decodeURIComponent(encodedRow)
    );

    document.getElementById('modalTitle').textContent =
        'Edit Kategori Keuangan';

    document.getElementById('formAction').value =
        'update';

    document.getElementById('formId').value =
        row.id;

    document.getElementById('formKode').value =
        row.kode;

    document.getElementById('formNama').value =
        row.nama;

    document.getElementById('formTipe').value =
        row.tipe;

    updateGroupOptions();

    document.getElementById('formKelompok').value =
        row.kelompok_laporan;

    document.getElementById('formKeterangan').value =
        row.keterangan || '';

    document.getElementById('saveButton').textContent =
        'Simpan Perubahan';

    document
        .getElementById('categoryModal')
        .classList.add('show');
}

function updateGroupOptions() {
    const tipe =
        document.getElementById('formTipe').value;

    const select =
        document.getElementById('formKelompok');

    Array.from(select.options).forEach(option => {
        const optionType =
            option.dataset.tipe;

        option.hidden =
            optionType !== tipe;

        option.disabled =
            optionType !== tipe;
    });

    const current =
        select.options[select.selectedIndex];

    if (!current || current.disabled) {
        const firstAvailable =
            Array.from(select.options)
                .find(option => !option.disabled);

        if (firstAvailable) {
            select.value =
                firstAvailable.value;
        }
    }
}

function closeModal() {
    document
        .getElementById('categoryModal')
        .classList.remove('show');
}

document
    .getElementById('categoryModal')
    .addEventListener('click', function(event) {
        if (event.target === this) {
            closeModal();
        }
    });

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

document.querySelectorAll('[data-filter]').forEach(input => {
    input.addEventListener('input', () => {
        currentPage = 1;
        renderTable();
    });
});

updateGroupOptions();
renderTable();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
