<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Supplier';
$adminBase = '../';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage(string $type, string $message): void
{
    header('Location: supplier.php?' . http_build_query([
        'msg_type' => $type,
        'msg'      => $message
    ]));
    exit;
}

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| CRUD SUPPLIER
|--------------------------------------------------------------------------
| Semua proses dilakukan pada file yang sama.
| Tidak ada halaman tambah/edit/hapus terpisah.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        /*
        |------------------------------------------------------------------
        | TAMBAH
        |------------------------------------------------------------------
        */
        if ($action === 'create') {
            $kode = trim((string)($_POST['kode'] ?? ''));
            $nama = trim((string)($_POST['nama'] ?? ''));
            $alamat = trim((string)($_POST['alamat'] ?? ''));
            $telepon = trim((string)($_POST['telepon'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $contactPerson = trim((string)($_POST['contact_person'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($kode === '' || $nama === '') {
                redirectWithMessage('error', 'Kode dan nama perusahaan wajib diisi.');
            }

            $check = $pdo->prepare("
                SELECT id
                FROM supplier
                WHERE kode = ?
                LIMIT 1
            ");
            $check->execute([$kode]);

            if ($check->fetch()) {
                redirectWithMessage('error', 'Kode supplier sudah digunakan.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO supplier
                    (kode, nama, alamat, telepon, email, contact_person, keterangan)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $kode,
                $nama,
                $alamat !== '' ? $alamat : null,
                $telepon !== '' ? $telepon : null,
                $email !== '' ? $email : null,
                $contactPerson !== '' ? $contactPerson : null,
                $keterangan !== '' ? $keterangan : null
            ]);

            redirectWithMessage('success', 'Supplier berhasil ditambahkan.');
        }

        /*
        |------------------------------------------------------------------
        | UPDATE
        |------------------------------------------------------------------
        */
        if ($action === 'update') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $kode = trim((string)($_POST['kode'] ?? ''));
            $nama = trim((string)($_POST['nama'] ?? ''));
            $alamat = trim((string)($_POST['alamat'] ?? ''));
            $telepon = trim((string)($_POST['telepon'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $contactPerson = trim((string)($_POST['contact_person'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if (!$id) {
                redirectWithMessage('error', 'ID supplier tidak valid.');
            }

            if ($kode === '' || $nama === '') {
                redirectWithMessage('error', 'Kode dan nama perusahaan wajib diisi.');
            }

            $check = $pdo->prepare("
                SELECT id
                FROM supplier
                WHERE kode = ?
                  AND id <> ?
                LIMIT 1
            ");
            $check->execute([$kode, $id]);

            if ($check->fetch()) {
                redirectWithMessage('error', 'Kode supplier sudah digunakan oleh supplier lain.');
            }

            $stmt = $pdo->prepare("
                UPDATE supplier
                SET
                    kode = ?,
                    nama = ?,
                    alamat = ?,
                    telepon = ?,
                    email = ?,
                    contact_person = ?,
                    keterangan = ?
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $kode,
                $nama,
                $alamat !== '' ? $alamat : null,
                $telepon !== '' ? $telepon : null,
                $email !== '' ? $email : null,
                $contactPerson !== '' ? $contactPerson : null,
                $keterangan !== '' ? $keterangan : null,
                $id
            ]);

            redirectWithMessage('success', 'Data supplier berhasil diperbarui.');
        }

        /*
        |------------------------------------------------------------------
        | DELETE
        |------------------------------------------------------------------
        */
        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id) {
                redirectWithMessage('error', 'ID supplier tidak valid.');
            }

            $stmt = $pdo->prepare("
                DELETE FROM supplier
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$id]);

            if ($stmt->rowCount() < 1) {
                redirectWithMessage('error', 'Data supplier tidak ditemukan.');
            }

            redirectWithMessage('success', 'Supplier berhasil dihapus.');
        }

    } catch (PDOException $e) {
        /*
        | Jika supplier sudah dipakai transaksi pembelian,
        | foreign key ON DELETE RESTRICT akan menolak penghapusan.
        */
        if ($action === 'delete') {
            redirectWithMessage(
                'error',
                'Supplier tidak dapat dihapus karena sudah digunakan pada transaksi pembelian.'
            );
        }

        redirectWithMessage(
            'error',
            'Proses database gagal. Periksa kembali data yang dimasukkan.'
        );
    } catch (Throwable $e) {
        redirectWithMessage('error', 'Terjadi kesalahan pada proses supplier.');
    }
}

/*
|--------------------------------------------------------------------------
| PESAN DARI REDIRECT
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    $message = trim((string)$_GET['msg']);
    $messageType = ($_GET['msg_type'] ?? '') === 'success'
        ? 'success'
        : 'error';
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA SUPPLIER
|--------------------------------------------------------------------------
*/
$suppliers = $pdo->query("
    SELECT
        id,
        kode,
        nama,
        alamat,
        telepon,
        email,
        contact_person,
        keterangan,
        created_at,
        updated_at
    FROM supplier
    ORDER BY nama ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalSupplier = count($suppliers);

$extraHead = <<<'HTML'
<style>
/*
|--------------------------------------------------------------------------
| CSS halaman supplier
|--------------------------------------------------------------------------
| Tidak membuat file CSS baru. Style diletakkan di halaman agar file ini
| langsung dapat digunakan tanpa mengubah assets/css yang sudah ada.
| Style menggunakan namespace supplier-* agar tidak mengganggu halaman lain.
|--------------------------------------------------------------------------
*/

.page-alert {
    margin: 0 0 18px;
    padding: 11px 14px;
    border-radius: 5px;
    font-size: 13px;
}

.page-alert.success {
    color: #087443;
    background: #effcf5;
    border: 1px solid #bcebd2;
}

.page-alert.error {
    color: #9b1c31;
    background: #fff0f2;
    border: 1px solid #ffc8d0;
}

.supplier-panel {
    overflow: visible;
}

.panel-subtitle {
    display: block;
    margin-top: 3px;
    color: #718096;
    font-size: 12px;
}

.supplier-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    padding: 15px 16px;
    border-bottom: 1px solid #dfe5ed;
}

.supplier-search {
    flex: 1;
    max-width: 480px;
}

.supplier-page-size {
    width: 130px;
}

.supplier-toolbar label {
    display: block;
    margin-bottom: 6px;
    color: #52657f;
    font-size: 11px;
}

.supplier-toolbar input,
.supplier-toolbar select {
    width: 100%;
    height: 36px;
    box-sizing: border-box;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    background: #fff;
    color: #172d4e;
    padding: 7px 10px;
    outline: none;
}

.supplier-toolbar input:focus,
.supplier-toolbar select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13,110,253,.08);
}

.supplier-table {
    min-width: 980px;
}

.supplier-table th,
.supplier-table td {
    vertical-align: middle;
}

.supplier-filter-row th {
    padding: 8px 7px;
    background: #f7f9fc;
}

.supplier-filter-row input {
    width: 100%;
    height: 32px;
    box-sizing: border-box;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    padding: 5px 7px;
    font-size: 11px;
    color: #172d4e;
    outline: none;
}

.supplier-filter-row input:focus {
    border-color: #0d6efd;
}

.supplier-actions {
    display: flex;
    gap: 5px;
    white-space: nowrap;
}

.btn-small {
    min-width: 42px;
    height: 30px;
    padding: 0 9px;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    background: #fff;
    font-size: 11px;
    cursor: pointer;
}

.btn-edit {
    color: #28558f;
}

.btn-edit:hover {
    border-color: #0d6efd;
    color: #0d6efd;
}

.btn-delete {
    color: #dc3545;
    border-color: #f0b9c0;
}

.btn-delete:hover {
    background: #fff5f6;
}

.supplier-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 12px 16px;
    color: #718096;
    font-size: 12px;
}

.supplier-pagination {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.supplier-pagination button {
    min-width: 34px;
    height: 32px;
    padding: 0 9px;
    border: 1px solid #d4deeb;
    border-radius: 4px;
    background: #fff;
    color: #52657f;
    cursor: pointer;
    font-size: 11px;
}

.supplier-pagination button:hover:not(:disabled) {
    border-color: #0d6efd;
    color: #0d6efd;
}

.supplier-pagination button.active {
    border-color: #0d6efd;
    background: #0d6efd;
    color: #fff;
}

.supplier-pagination button:disabled {
    cursor: not-allowed;
    opacity: .5;
}

.supplier-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    box-sizing: border-box;
    background: rgba(23, 45, 78, .55);
}

.supplier-modal.show {
    display: flex;
}

.supplier-modal-box {
    width: min(720px, 100%);
    max-height: calc(100vh - 36px);
    overflow-y: auto;
    border-radius: 6px;
    background: #fff;
    box-shadow: 0 15px 45px rgba(0,0,0,.18);
}

.supplier-modal-small {
    width: min(480px, 100%);
}

.supplier-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 52px;
    padding: 0 16px;
    box-sizing: border-box;
    border-bottom: 1px solid #dfe5ed;
}

.supplier-modal-header h3 {
    margin: 0;
    color: #183052;
    font-size: 15px;
}

.supplier-modal-close {
    width: 34px;
    height: 34px;
    border: 0;
    background: transparent;
    color: #718096;
    font-size: 25px;
    cursor: pointer;
}

.supplier-modal-close:hover {
    color: #183052;
}

.supplier-modal-body {
    padding: 18px 16px;
}

.supplier-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.supplier-field {
    min-width: 0;
}

.supplier-field-full {
    grid-column: 1 / -1;
}

.supplier-field label {
    display: block;
    margin-bottom: 6px;
    color: #183052;
    font-size: 11px;
}

.supplier-field label span {
    color: #dc3545;
}

.supplier-field input,
.supplier-field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    background: #fff;
    color: #172d4e;
    padding: 8px 10px;
    font-size: 12px;
    outline: none;
}

.supplier-field input {
    height: 36px;
}

.supplier-field textarea {
    resize: vertical;
    min-height: 76px;
}

.supplier-field input:focus,
.supplier-field textarea:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13,110,253,.08);
}

.supplier-modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #dfe5ed;
}

.btn-primary,
.btn-secondary,
.btn-danger {
    min-height: 36px;
    padding: 0 15px;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
}

.btn-primary {
    border: 0;
    background: #0d6efd;
    color: #fff;
}

.btn-primary:hover {
    background: #0b5ed7;
}

.btn-secondary {
    border: 0;
    background: #718096;
    color: #fff;
}

.btn-danger {
    border: 0;
    background: #dc3545;
    color: #fff;
}

.supplier-delete-note {
    margin-top: 12px;
    color: #9b1c31;
    font-size: 11px;
}

@media (max-width: 800px) {
    .supplier-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .supplier-search,
    .supplier-page-size {
        width: 100%;
        max-width: none;
    }

    .supplier-table-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .supplier-pagination {
        justify-content: flex-start;
    }

    .supplier-form-grid {
        grid-template-columns: 1fr;
    }

    .supplier-field-full {
        grid-column: auto;
    }

    .supplier-modal {
        padding: 10px;
    }

    .supplier-modal-box {
        max-height: calc(100vh - 20px);
    }
}
</style>
HTML;

require __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <div>
        <h1>Manajemen Supplier</h1>
        <p>Mengelola data pemasok untuk kebutuhan pembelian ASRINDO</p>
    </div>

    <button type="button" class="btn-primary" id="openCreateModal">
        + Tambah Supplier
    </button>
</section>

<?php if ($message !== ''): ?>
    <div class="page-alert <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
        <?php echo h($message); ?>
    </div>
<?php endif; ?>

<section class="dashboard-panel supplier-panel">

    <div class="panel-heading">
        <div>
            <h2>Daftar Supplier</h2>
            <span class="panel-subtitle">
                <?php echo number_format($totalSupplier, 0, ',', '.'); ?> supplier
            </span>
        </div>
    </div>

    <!-- TOOLBAR -->
    <div class="supplier-toolbar">
        <div class="supplier-search">
            <label for="supplierSearch">Pencarian</label>
            <input
                type="search"
                id="supplierSearch"
                placeholder="Cari nama perusahaan, kode, kontak..."
                autocomplete="off"
            >
        </div>

        <div class="supplier-page-size">
            <label for="supplierPageSize">Tampilkan</label>
            <select id="supplierPageSize">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dashboard-table supplier-table" id="supplierTable">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Perusahaan</th>
                    <th>Nama Kontak</th>
                    <th>Alamat</th>
                    <th>Telepon</th>
                    <th>Email</th>
                    <th>Aksi</th>
                </tr>

                <!-- FILTER TIAP KOLOM -->
                <tr class="supplier-filter-row">
                    <th>
                        <input type="text" data-filter-column="0" placeholder="Filter kode">
                    </th>
                    <th>
                        <input type="text" data-filter-column="1" placeholder="Filter perusahaan">
                    </th>
                    <th>
                        <input type="text" data-filter-column="2" placeholder="Filter kontak">
                    </th>
                    <th>
                        <input type="text" data-filter-column="3" placeholder="Filter alamat">
                    </th>
                    <th>
                        <input type="text" data-filter-column="4" placeholder="Filter telepon">
                    </th>
                    <th>
                        <input type="text" data-filter-column="5" placeholder="Filter email">
                    </th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($suppliers)): ?>

                <tr>
                    <td colspan="7" class="empty-state">
                        Belum ada data supplier.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($suppliers as $supplier): ?>
                    <tr
                        data-id="<?php echo (int)$supplier['id']; ?>"
                        data-kode="<?php echo h($supplier['kode']); ?>"
                        data-nama="<?php echo h($supplier['nama']); ?>"
                        data-contact="<?php echo h($supplier['contact_person']); ?>"
                        data-alamat="<?php echo h($supplier['alamat']); ?>"
                        data-telepon="<?php echo h($supplier['telepon']); ?>"
                        data-email="<?php echo h($supplier['email']); ?>"
                        data-keterangan="<?php echo h($supplier['keterangan']); ?>"
                    >
                        <td>
                            <?php echo h($supplier['kode']); ?>
                        </td>

                        <td>
                            <?php echo h($supplier['nama']); ?>
                        </td>

                        <td>
                            <?php echo h($supplier['contact_person'] ?: '-'); ?>
                        </td>

                        <td>
                            <?php echo h($supplier['alamat'] ?: '-'); ?>
                        </td>

                        <td>
                            <?php echo h($supplier['telepon'] ?: '-'); ?>
                        </td>

                        <td>
                            <?php echo h($supplier['email'] ?: '-'); ?>
                        </td>

                        <td>
                            <div class="supplier-actions">
                                <button
                                    type="button"
                                    class="btn-small btn-edit"
                                    data-action="edit"
                                >
                                    Edit
                                </button>

                                <button
                                    type="button"
                                    class="btn-small btn-delete"
                                    data-action="delete"
                                >
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="supplier-table-footer">
        <div id="supplierInfo">
            Menampilkan 0 supplier
        </div>

        <div class="supplier-pagination" id="supplierPagination"></div>
    </div>

</section>


<!-- ============================================================
     MODAL TAMBAH
============================================================ -->
<div class="supplier-modal" id="createModal" aria-hidden="true">
    <div class="supplier-modal-box">

        <div class="supplier-modal-header">
            <h3>Tambah Supplier</h3>

            <button
                type="button"
                class="supplier-modal-close"
                data-close-modal="createModal"
                aria-label="Tutup"
            >
                &times;
            </button>
        </div>

        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="create">

            <div class="supplier-modal-body">

                <div class="supplier-form-grid">

                    <div class="supplier-field">
                        <label>
                            Kode Supplier <span>*</span>
                        </label>
                        <input
                            type="text"
                            name="kode"
                            maxlength="50"
                            required
                            placeholder="Contoh: SUP004"
                        >
                    </div>

                    <div class="supplier-field">
                        <label>
                            Nama Perusahaan <span>*</span>
                        </label>
                        <input
                            type="text"
                            name="nama"
                            maxlength="200"
                            required
                            placeholder="Nama perusahaan supplier"
                        >
                    </div>

                    <div class="supplier-field">
                        <label>Nama Kontak</label>
                        <input
                            type="text"
                            name="contact_person"
                            maxlength="100"
                            placeholder="Nama PIC / kontak"
                        >
                    </div>

                    <div class="supplier-field">
                        <label>Telepon</label>
                        <input
                            type="text"
                            name="telepon"
                            maxlength="50"
                            placeholder="Nomor telepon"
                        >
                    </div>

                    <div class="supplier-field">
                        <label>Email</label>
                        <input
                            type="email"
                            name="email"
                            maxlength="150"
                            placeholder="email@perusahaan.com"
                        >
                    </div>

                    <div class="supplier-field supplier-field-full">
                        <label>Alamat</label>
                        <textarea
                            name="alamat"
                            rows="3"
                            placeholder="Alamat supplier"
                        ></textarea>
                    </div>

                    <div class="supplier-field supplier-field-full">
                        <label>Keterangan</label>
                        <textarea
                            name="keterangan"
                            rows="3"
                            placeholder="Keterangan tambahan"
                        ></textarea>
                    </div>

                </div>

            </div>

            <div class="supplier-modal-footer">
                <button
                    type="button"
                    class="btn-secondary"
                    data-close-modal="createModal"
                >
                    Batal
                </button>

                <button type="submit" class="btn-primary">
                    Simpan Supplier
                </button>
            </div>
        </form>

    </div>
</div>


<!-- ============================================================
     MODAL EDIT
============================================================ -->
<div class="supplier-modal" id="editModal" aria-hidden="true">
    <div class="supplier-modal-box">

        <div class="supplier-modal-header">
            <h3>Edit Supplier</h3>

            <button
                type="button"
                class="supplier-modal-close"
                data-close-modal="editModal"
                aria-label="Tutup"
            >
                &times;
            </button>
        </div>

        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="supplier-modal-body">

                <div class="supplier-form-grid">

                    <div class="supplier-field">
                        <label>
                            Kode Supplier <span>*</span>
                        </label>
                        <input
                            type="text"
                            name="kode"
                            id="edit_kode"
                            maxlength="50"
                            required
                        >
                    </div>

                    <div class="supplier-field">
                        <label>
                            Nama Perusahaan <span>*</span>
                        </label>
                        <input
                            type="text"
                            name="nama"
                            id="edit_nama"
                            maxlength="200"
                            required
                        >
                    </div>

                    <div class="supplier-field">
                        <label>Nama Kontak</label>
                        <input
                            type="text"
                            name="contact_person"
                            id="edit_contact"
                            maxlength="100"
                        >
                    </div>

                    <div class="supplier-field">
                        <label>Telepon</label>
                        <input
                            type="text"
                            name="telepon"
                            id="edit_telepon"
                            maxlength="50"
                        >
                    </div>

                    <div class="supplier-field">
                        <label>Email</label>
                        <input
                            type="email"
                            name="email"
                            id="edit_email"
                            maxlength="150"
                        >
                    </div>

                    <div class="supplier-field supplier-field-full">
                        <label>Alamat</label>
                        <textarea
                            name="alamat"
                            id="edit_alamat"
                            rows="3"
                        ></textarea>
                    </div>

                    <div class="supplier-field supplier-field-full">
                        <label>Keterangan</label>
                        <textarea
                            name="keterangan"
                            id="edit_keterangan"
                            rows="3"
                        ></textarea>
                    </div>

                </div>

            </div>

            <div class="supplier-modal-footer">
                <button
                    type="button"
                    class="btn-secondary"
                    data-close-modal="editModal"
                >
                    Batal
                </button>

                <button type="submit" class="btn-primary">
                    Update Supplier
                </button>
            </div>
        </form>

    </div>
</div>


<!-- ============================================================
     MODAL KONFIRMASI HAPUS
============================================================ -->
<div class="supplier-modal" id="deleteModal" aria-hidden="true">
    <div class="supplier-modal-box supplier-modal-small">

        <div class="supplier-modal-header">
            <h3>Hapus Supplier</h3>

            <button
                type="button"
                class="supplier-modal-close"
                data-close-modal="deleteModal"
                aria-label="Tutup"
            >
                &times;
            </button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_id">

            <div class="supplier-modal-body">
                <p>
                    Apakah Anda yakin ingin menghapus supplier
                    <strong id="delete_supplier_name"></strong>?
                </p>

                <p class="supplier-delete-note">
                    Data yang sudah digunakan pada transaksi pembelian
                    tidak dapat dihapus.
                </p>
            </div>

            <div class="supplier-modal-footer">
                <button
                    type="button"
                    class="btn-secondary"
                    data-close-modal="deleteModal"
                >
                    Batal
                </button>

                <button type="submit" class="btn-danger">
                    Hapus Supplier
                </button>
            </div>
        </form>

    </div>
</div>




<script>
(function () {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | MODAL
    |--------------------------------------------------------------------------
    */
    function openModal(id) {
        var modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        var modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.getElementById('openCreateModal').addEventListener('click', function () {
        openModal('createModal');
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(this.getAttribute('data-close-modal'));
        });
    });

    document.querySelectorAll('.supplier-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.supplier-modal.show').forEach(function (modal) {
                closeModal(modal.id);
            });
        }
    });


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */
    document.querySelectorAll('[data-action="edit"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var row = this.closest('tr');

            if (!row) {
                return;
            }

            document.getElementById('edit_id').value = row.dataset.id || '';
            document.getElementById('edit_kode').value = row.dataset.kode || '';
            document.getElementById('edit_nama').value = row.dataset.nama || '';
            document.getElementById('edit_contact').value = row.dataset.contact || '';
            document.getElementById('edit_alamat').value = row.dataset.alamat || '';
            document.getElementById('edit_telepon').value = row.dataset.telepon || '';
            document.getElementById('edit_email').value = row.dataset.email || '';
            document.getElementById('edit_keterangan').value = row.dataset.keterangan || '';

            openModal('editModal');
        });
    });


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    document.querySelectorAll('[data-action="delete"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var row = this.closest('tr');

            if (!row) {
                return;
            }

            document.getElementById('delete_id').value = row.dataset.id || '';
            document.getElementById('delete_supplier_name').textContent =
                row.dataset.nama || '';

            openModal('deleteModal');
        });
    });


    /*
    |--------------------------------------------------------------------------
    | TABLE SEARCH + FILTER + PAGINATION
    |--------------------------------------------------------------------------
    */
    var table = document.getElementById('supplierTable');
    var tbody = table ? table.querySelector('tbody') : null;
    var searchInput = document.getElementById('supplierSearch');
    var pageSizeSelect = document.getElementById('supplierPageSize');
    var info = document.getElementById('supplierInfo');
    var pagination = document.getElementById('supplierPagination');

    if (!table || !tbody) {
        return;
    }

    var rows = Array.prototype.slice.call(
        tbody.querySelectorAll('tr[data-id]')
    );

    var filterInputs = Array.prototype.slice.call(
        document.querySelectorAll('[data-filter-column]')
    );

    var currentPage = 1;

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .trim();
    }

    function getFilteredRows() {
        var globalSearch = normalize(searchInput.value);

        var filters = {};

        filterInputs.forEach(function (input) {
            filters[input.getAttribute('data-filter-column')] =
                normalize(input.value);
        });

        return rows.filter(function (row) {
            var cells = row.children;

            if (globalSearch !== '') {
                var fullText = normalize(row.textContent);

                if (fullText.indexOf(globalSearch) === -1) {
                    return false;
                }
            }

            for (var column in filters) {
                if (!filters[column]) {
                    continue;
                }

                var cell = cells[parseInt(column, 10)];

                if (!cell) {
                    continue;
                }

                if (normalize(cell.textContent).indexOf(filters[column]) === -1) {
                    return false;
                }
            }

            return true;
        });
    }

    function renderPagination(totalPages) {
        pagination.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        var previous = document.createElement('button');
        previous.type = 'button';
        previous.textContent = '‹';
        previous.disabled = currentPage === 1;

        previous.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                render();
            }
        });

        pagination.appendChild(previous);

        var start = Math.max(1, currentPage - 2);
        var end = Math.min(totalPages, currentPage + 2);

        if (start > 1) {
            addPageButton(1);

            if (start > 2) {
                var dotsStart = document.createElement('span');
                dotsStart.textContent = '…';
                dotsStart.style.padding = '7px 4px';
                pagination.appendChild(dotsStart);
            }
        }

        for (var page = start; page <= end; page++) {
            addPageButton(page);
        }

        if (end < totalPages) {
            if (end < totalPages - 1) {
                var dotsEnd = document.createElement('span');
                dotsEnd.textContent = '…';
                dotsEnd.style.padding = '7px 4px';
                pagination.appendChild(dotsEnd);
            }

            addPageButton(totalPages);
        }

        var next = document.createElement('button');
        next.type = 'button';
        next.textContent = '›';
        next.disabled = currentPage === totalPages;

        next.addEventListener('click', function () {
            if (currentPage < totalPages) {
                currentPage++;
                render();
            }
        });

        pagination.appendChild(next);
    }

    function addPageButton(page) {
        var button = document.createElement('button');

        button.type = 'button';
        button.textContent = page;

        if (page === currentPage) {
            button.classList.add('active');
        }

        button.addEventListener('click', function () {
            currentPage = page;
            render();
        });

        pagination.appendChild(button);
    }

    function render() {
        var filtered = getFilteredRows();

        var pageSize = parseInt(pageSizeSelect.value, 10) || 10;
        var total = filtered.length;
        var totalPages = Math.max(1, Math.ceil(total / pageSize));

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        rows.forEach(function (row) {
            row.style.display = 'none';
        });

        var start = (currentPage - 1) * pageSize;
        var end = Math.min(start + pageSize, total);

        for (var i = start; i < end; i++) {
            filtered[i].style.display = '';
        }

        if (total === 0) {
            info.textContent = 'Tidak ada data yang sesuai.';
        } else {
            info.textContent =
                'Menampilkan ' +
                (start + 1) +
                ' sampai ' +
                end +
                ' dari ' +
                total +
                ' supplier';
        }

        renderPagination(totalPages);
    }

    searchInput.addEventListener('input', function () {
        currentPage = 1;
        render();
    });

    pageSizeSelect.addEventListener('change', function () {
        currentPage = 1;
        render();
    });

    filterInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            currentPage = 1;
            render();
        });
    });

    render();

})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
