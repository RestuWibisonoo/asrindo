<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Sparepart';
$adminBase = '../';
$pdo = getPDO();

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_sparepart'])) {
        $_SESSION['csrf_sparepart'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_sparepart'];
}

function verifyCsrf(): void
{
    if (!hash_equals(
        $_SESSION['csrf_sparepart'] ?? '',
        (string)($_POST['csrf_token'] ?? '')
    )) {
        throw new RuntimeException('Token keamanan tidak valid. Silakan coba lagi.');
    }
}

function formatStock($value): string
{
    $number = (float)$value;
    if (floor($number) === $number) {
        return number_format($number, 0, ',', '.');
    }

    return number_format($number, 2, ',', '.');
}

function stockClass($value): string
{
    $stock = (float)$value;

    if ($stock < 10) {
        return 'stock-low';
    }

    return 'stock-ok';
}

$csrf = csrfToken();
$message = '';
$error = '';
$openModal = '';
$editData = null;

/* =========================================================
   CRUD MASTER SPAREPART
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'add_sparepart' || $action === 'edit_sparepart') {
            $id = (int)($_POST['id'] ?? 0);
            $kode = trim((string)($_POST['kode'] ?? ''));
            $nama = trim((string)($_POST['nama'] ?? ''));
            $partNumber = trim((string)($_POST['part_number'] ?? ''));
            $merk = trim((string)($_POST['merk'] ?? ''));
            $satuan = trim((string)($_POST['satuan'] ?? 'PCS'));
            $stokRaw = trim((string)($_POST['stok'] ?? '0'));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($kode === '' || $nama === '') {
                throw new RuntimeException('Kode dan nama suku cadang wajib diisi.');
            }

            if ($satuan === '') {
                $satuan = 'PCS';
            }

            if ($stokRaw === '' || !is_numeric(str_replace(',', '.', $stokRaw))) {
                throw new RuntimeException('Stok harus berupa angka yang valid.');
            }

            $stok = (float)str_replace(',', '.', $stokRaw);
            if ($stok < 0) {
                throw new RuntimeException('Stok tidak boleh bernilai negatif.');
            }

            $stmt = $pdo->prepare('SELECT id FROM sparepart WHERE kode = ? AND id <> ? LIMIT 1');
            $stmt->execute([$kode, $id]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Kode sparepart sudah digunakan.');
            }

            if ($action === 'add_sparepart') {
                $stmt = $pdo->prepare('
                    INSERT INTO sparepart
                        (kode, nama, part_number, merk, satuan, stok, keterangan)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $kode,
                    $nama,
                    $partNumber === '' ? null : $partNumber,
                    $merk === '' ? null : $merk,
                    $satuan,
                    $stok,
                    $keterangan === '' ? null : $keterangan
                ]);

                $message = 'Suku cadang ' . $kode . ' berhasil ditambahkan.';
            } else {
                if ($id <= 0) {
                    throw new RuntimeException('Data sparepart tidak valid.');
                }

                $stmt = $pdo->prepare('
                    UPDATE sparepart
                    SET kode = ?, nama = ?, part_number = ?, merk = ?, satuan = ?, stok = ?, keterangan = ?
                    WHERE id = ?
                ');
                $stmt->execute([
                    $kode,
                    $nama,
                    $partNumber === '' ? null : $partNumber,
                    $merk === '' ? null : $merk,
                    $satuan,
                    $stok,
                    $keterangan === '' ? null : $keterangan,
                    $id
                ]);

                $message = 'Suku cadang ' . $kode . ' berhasil diperbarui.';
            }
        } elseif ($action === 'delete_sparepart') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Data sparepart tidak valid.');
            }

            $stmt = $pdo->prepare('SELECT kode FROM sparepart WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new RuntimeException('Suku cadang tidak ditemukan.');
            }

            $stmt = $pdo->prepare('DELETE FROM sparepart WHERE id = ?');
            $stmt->execute([$id]);

            $message = 'Suku cadang ' . $row['kode'] . ' berhasil dihapus.';
        } else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();

        if ($action === 'add_sparepart') {
            $openModal = 'modalTambahSparepart';
        } elseif ($action === 'edit_sparepart') {
            $openModal = 'modalEditSparepart';
            $editData = [
                'id' => (int)($_POST['id'] ?? 0),
                'kode' => (string)($_POST['kode'] ?? ''),
                'nama' => (string)($_POST['nama'] ?? ''),
                'part_number' => (string)($_POST['part_number'] ?? ''),
                'merk' => (string)($_POST['merk'] ?? ''),
                'satuan' => (string)($_POST['satuan'] ?? 'PCS'),
                'stok' => (string)($_POST['stok'] ?? '0'),
                'keterangan' => (string)($_POST['keterangan'] ?? '')
            ];
        }
    }
}

/* =========================================================
   DATA MASTER + HARGA PEMBELIAN TERAKHIR
   ========================================================= */
$spareparts = [];

try {
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.kode,
            s.nama,
            s.part_number,
            s.merk,
            s.satuan,
            s.stok,
            s.keterangan,
            COALESCE(lp.harga_terakhir, 0) AS harga_terakhir
        FROM sparepart s
        LEFT JOIN (
            SELECT d.sparepart_id, d.harga AS harga_terakhir
            FROM pembelian_sparepart_detail d
            INNER JOIN pembelian_sparepart p ON p.id = d.pembelian_id
            INNER JOIN (
                SELECT
                    d2.sparepart_id,
                    MAX(CONCAT(LPAD(p2.tanggal, 10, '0'), LPAD(p2.id, 10, '0'), LPAD(d2.id, 10, '0'))) AS latest_key
                FROM pembelian_sparepart_detail d2
                INNER JOIN pembelian_sparepart p2 ON p2.id = d2.pembelian_id
                WHERE UPPER(COALESCE(p2.status, '')) <> 'BATAL'
                GROUP BY d2.sparepart_id
            ) latest
                ON latest.sparepart_id = d.sparepart_id
               AND latest.latest_key = CONCAT(LPAD(p.tanggal, 10, '0'), LPAD(p.id, 10, '0'), LPAD(d.id, 10, '0'))
            WHERE UPPER(COALESCE(p.status, '')) <> 'BATAL'
        ) lp ON lp.sparepart_id = s.id
        ORDER BY s.id DESC
    ");
    $spareparts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Tetap tampilkan master jika tabel pembelian belum tersedia.
    $stmt = $pdo->query('
        SELECT id, kode, nama, part_number, merk, satuan, stok, keterangan, 0 AS harga_terakhir
        FROM sparepart
        ORDER BY id DESC
    ');
    $spareparts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalItem = count($spareparts);
$totalStok = 0.0;
$itemStokRendah = 0;
$totalNilaiInventori = 0.0;

foreach ($spareparts as $item) {
    $stok = (float)$item['stok'];
    $hargaTerakhir = (float)$item['harga_terakhir'];

    $totalStok += $stok;
    if ($stok < 10) {
        $itemStokRendah++;
    }

    $totalNilaiInventori += $stok * $hargaTerakhir;
}

/* Daftar satuan yang umum digunakan. Nilai tetap bisa diketik melalui form. */
$units = ['PCS', 'SET', 'UNIT', 'LITER', 'KG', 'METER', 'ROLL', 'BOX'];

// Muat layout admin dan CSS global sebelum konten halaman.
require __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================================
   HALAMAN SPAREPART - SELARAS DENGAN MODUL ALAT BERAT
   ========================================================= */
.sparepart-panel { overflow: visible; }

.sparepart-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    padding: 15px 16px;
    border-bottom: 1px solid #dfe5ed;
}

.sparepart-search { flex: 1; max-width: 480px; }
.sparepart-page-size { width: 130px; }

.sparepart-toolbar label {
    display: block;
    margin-bottom: 6px;
    color: #52657f;
    font-size: 11px;
}

.sparepart-toolbar input,
.sparepart-toolbar select,
.sparepart-filter-row input,
.sparepart-filter-row select {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    background: #fff;
    color: #172d4e;
    padding: 8px 10px;
    outline: none;
    font-size: 12px;
}

.sparepart-toolbar input,
.sparepart-toolbar select { height: 36px; }

.sparepart-toolbar input:focus,
.sparepart-toolbar select:focus,
.sparepart-filter-row input:focus,
.sparepart-filter-row select:focus,
.sparepart-form-field input:focus,
.sparepart-form-field select:focus,
.sparepart-form-field textarea:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13,110,253,.08);
}

.sparepart-table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
}

.sparepart-table th,
.sparepart-table td { vertical-align: middle; }

.sparepart-table thead > tr:first-child th {
    padding: 10px 8px;
    background: #fff;
    border-bottom: 1px solid #dfe5ed;
    color: #183052;
    font-size: 11px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.sparepart-table thead > tr:first-child th.sortable { cursor: pointer; }
.sparepart-table thead > tr:first-child th.sortable:hover { color: #0d6efd; }

.sparepart-filter-row th {
    padding: 8px 7px;
    background: #f7f9fc;
    border-bottom: 1px solid #dfe5ed;
}

.sparepart-filter-row input,
.sparepart-filter-row select {
    height: 32px;
    padding: 5px 7px;
    font-size: 11px;
}

.sparepart-table tbody td {
    padding: 11px 9px;
    border-bottom: 1px solid #e2e7ee;
    color: #183052;
    font-size: 11px;
    white-space: nowrap;
}

.sparepart-table tbody tr:hover { background: #fbfdff; }

.sparepart-code {
    color: #0d6efd;
    font-weight: 600;
}

.sparepart-stock {
    text-align: right;
    font-weight: 600;
}

.stock-badge {
    display: inline-block;
    min-width: 25px;
    padding: 3px 7px;
    border-radius: 4px;
    text-align: center;
    font-size: 10px;
    font-weight: 600;
}

.stock-low { background: #ffb400; color: #17243d; }
.stock-ok { background: #10b981; color: #fff; }

.sparepart-price {
    color: #008f4c;
    font-weight: 600;
    text-align: right;
}

.sparepart-action {
    width: 135px;
    text-align: center;
}

.btn-sparepart-action {
    min-width: 50px;
    height: 30px;
    padding: 0 9px;
    margin: 0 2px;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    background: #fff;
    color: #526b8d;
    font-size: 11px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-sparepart-action:hover { border-color: #0d6efd; color: #0d6efd; }
.btn-sparepart-delete:hover { border-color: #dc3545; color: #dc3545; }

.sparepart-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 13px 16px;
    color: #6d7e96;
    font-size: 11px;
}

.sparepart-pagination {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    flex-wrap: wrap;
}

.page-button {
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    background: #fff;
    color: #526b8d;
    font-size: 11px;
    cursor: pointer;
}

.page-button:hover:not(:disabled),
.page-button.active { border-color: #0d6efd; color: #0d6efd; }
.page-button.active { background: #eef5ff; font-weight: 600; }
.page-button:disabled { opacity: .45; cursor: default; }
.pagination-ellipsis { padding: 0 3px; color: #8795a8; }
.empty-table { padding: 35px !important; text-align: center; color: #8190a5 !important; }

.sparepart-stat-panel {
    margin-bottom: 18px;
    background: #fff;
    border: 1px solid #dfe5ed;
    border-radius: 4px;
    overflow: hidden;
}

.sparepart-stat-title {
    padding: 15px 20px;
    border-bottom: 1px solid #dfe5ed;
    color: #183052;
    font-size: 13px;
    font-weight: 500;
}

.sparepart-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
}

.sparepart-stat-item {
    min-height: 85px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px 10px;
    text-align: center;
}

.sparepart-stat-item strong {
    color: #006cff;
    font-size: 19px;
    font-weight: 500;
}

.sparepart-stat-item span {
    margin-top: 7px;
    color: #71839b;
    font-size: 11px;
}

.text-green { color: #00b85a !important; }
.text-orange { color: #f0a000 !important; }

.sparepart-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 30, 50, .42);
}

.sparepart-modal.show { display: flex; }

.sparepart-modal-box {
    width: min(680px, 100%);
    max-height: calc(100vh - 40px);
    overflow: auto;
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 15px 45px rgba(0,0,0,.18);
}

.sparepart-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 18px 20px;
    border-bottom: 1px solid #e1e6ee;
}

.sparepart-modal-header h3 { margin: 0; color: #183052; font-size: 16px; }
.sparepart-modal-header p { margin: 5px 0 0; color: #71839b; font-size: 11px; }

.sparepart-modal-close {
    border: 0;
    background: transparent;
    color: #71839b;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}

.sparepart-modal-body { padding: 20px; }

.sparepart-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.sparepart-form-field.full { grid-column: 1 / -1; }
.sparepart-form-field label { display: block; margin-bottom: 6px; color: #52657f; font-size: 11px; font-weight: 600; }
.sparepart-form-field input,
.sparepart-form-field select,
.sparepart-form-field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    padding: 9px 10px;
    background: #fff;
    color: #183052;
    font: inherit;
    font-size: 12px;
    outline: none;
}
.sparepart-form-field textarea { min-height: 80px; resize: vertical; }

.sparepart-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 20px;
    border-top: 1px solid #e1e6ee;
}

.sparepart-btn-primary,
.sparepart-btn-secondary {
    border: 0;
    border-radius: 4px;
    padding: 9px 14px;
    font-size: 11px;
    cursor: pointer;
}
.sparepart-btn-primary { background: #0d6efd; color: #fff; }
.sparepart-btn-secondary { background: #718096; color: #fff; }

.sparepart-alert {
    padding: 10px 13px;
    margin: 0 0 16px;
    border-radius: 4px;
    font-size: 12px;
}
.sparepart-alert.success { background: #effcf5; border: 1px solid #bcebd2; color: #087443; }
.sparepart-alert.error { background: #fff0f2; border: 1px solid #ffc8d0; color: #9b1c31; }

@media (max-width: 800px) {
    .sparepart-toolbar { align-items: stretch; flex-direction: column; }
    .sparepart-search, .sparepart-page-size { width: 100%; max-width: none; }
    .sparepart-table-footer { align-items: flex-start; flex-direction: column; }
    .sparepart-pagination { justify-content: flex-start; }
    .sparepart-stat-grid { grid-template-columns: repeat(2, 1fr); }
    .sparepart-form-grid { grid-template-columns: 1fr; }
    .sparepart-form-field.full { grid-column: auto; }
    .sparepart-modal { padding: 10px; }
    .sparepart-modal-box { max-height: calc(100vh - 20px); }
}

@media (max-width: 480px) {
    .sparepart-stat-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<section class="page-heading">
    <div>
        <h1>Manajemen Suku Cadang</h1>
        <p>Mengelola data inventori suku cadang ASRINDO</p>
    </div>
    <button type="button" class="btn-primary" id="btnTambahSparepart">
        <span>+</span> Tambah Suku Cadang
    </button>
</section>

<?php if ($message): ?>
    <div class="sparepart-alert success"><?php echo h($message); ?></div>
<?php endif; ?>

<?php if ($error && !$openModal): ?>
    <div class="sparepart-alert error"><?php echo h($error); ?></div>
<?php endif; ?>

<section class="sparepart-stat-panel">
    <div class="sparepart-stat-title">Statistik Suku Cadang</div>

    <div class="sparepart-stat-grid">
        <div class="sparepart-stat-item">
            <strong><?php echo number_format($totalItem, 0, ',', '.'); ?></strong>
            <span>Total Item</span>
        </div>

        <div class="sparepart-stat-item">
            <strong class="text-green"><?php echo rupiah($totalNilaiInventori); ?></strong>
            <span>Total Nilai Inventori</span>
        </div>

        <div class="sparepart-stat-item">
            <strong class="text-orange"><?php echo number_format($itemStokRendah, 0, ',', '.'); ?></strong>
            <span>Item Stok Rendah (&lt;10)</span>
        </div>

        <div class="sparepart-stat-item">
            <strong><?php echo formatStock($totalStok); ?></strong>
            <span>Total Stok</span>
        </div>
    </div>
</section>

<section class="dashboard-panel sparepart-panel">
    <div class="panel-heading">
        <div>
            <h2>Daftar Suku Cadang</h2>
            <span class="panel-subtitle">
                <?php echo number_format($totalItem, 0, ',', '.'); ?> item
            </span>
        </div>
    </div>

    <div class="sparepart-toolbar">
        <div class="sparepart-search">
            <label for="globalSearchSparepart">Pencarian</label>
            <input
                type="search"
                id="globalSearchSparepart"
                placeholder="Cari kode, nama, part number, merk..."
                autocomplete="off"
            >
        </div>

        <div class="sparepart-page-size">
            <label for="pageLengthSparepart">Tampilkan</label>
            <select id="pageLengthSparepart">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dashboard-table sparepart-table" id="sparepartTable">
            <thead>
                <tr>
                    <th class="sortable" data-column="0">Kode</th>
                    <th class="sortable" data-column="1">Nama Suku Cadang</th>
                    <th class="sortable" data-column="2">Part Number</th>
                    <th class="sortable" data-column="3">Merk</th>
                    <th class="sortable" data-column="4">Satuan</th>
                    <th class="sortable" data-column="5">Stok</th>
                    <th class="sortable" data-column="6">Hrg Beli Terakhir</th>
                    <th class="sortable" data-column="7">Keterangan</th>
                    <th class="sparepart-action">Aksi</th>
                </tr>
                <tr class="sparepart-filter-row">
                    <th><input type="text" class="column-filter" data-filter-column="0" placeholder="Filter kode"></th>
                    <th><input type="text" class="column-filter" data-filter-column="1" placeholder="Filter nama"></th>
                    <th><input type="text" class="column-filter" data-filter-column="2" placeholder="Filter part number"></th>
                    <th><input type="text" class="column-filter" data-filter-column="3" placeholder="Filter merk"></th>
                    <th><input type="text" class="column-filter" data-filter-column="4" placeholder="Filter satuan"></th>
                    <th><input type="text" class="column-filter" data-filter-column="5" placeholder="Filter stok"></th>
                    <th><input type="text" class="column-filter" data-filter-column="6" placeholder="Filter harga"></th>
                    <th><input type="text" class="column-filter" data-filter-column="7" placeholder="Filter keterangan"></th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($spareparts)): ?>
                <tr>
                    <td colspan="9" class="empty-table">Belum ada data suku cadang.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($spareparts as $item): ?>
                    <tr data-id="<?php echo (int)$item['id']; ?>">
                        <td><strong class="sparepart-code"><?php echo h($item['kode']); ?></strong></td>
                        <td><?php echo h($item['nama']); ?></td>
                        <td><?php echo h($item['part_number']); ?></td>
                        <td><?php echo h($item['merk']); ?></td>
                        <td><?php echo h($item['satuan']); ?></td>
                        <td class="sparepart-stock">
                            <span class="stock-badge <?php echo stockClass($item['stok']); ?>">
                                <?php echo formatStock($item['stok']); ?>
                            </span>
                        </td>
                        <td class="sparepart-price"><?php echo rupiah($item['harga_terakhir']); ?></td>
                        <td title="<?php echo h($item['keterangan']); ?>"><?php echo h($item['keterangan']); ?></td>
                        <td class="sparepart-action">
                            <button
                                type="button"
                                class="btn-sparepart-action btn-edit-sparepart"
                                data-id="<?php echo (int)$item['id']; ?>"
                                data-kode="<?php echo h($item['kode']); ?>"
                                data-nama="<?php echo h($item['nama']); ?>"
                                data-part-number="<?php echo h($item['part_number']); ?>"
                                data-merk="<?php echo h($item['merk']); ?>"
                                data-satuan="<?php echo h($item['satuan']); ?>"
                                data-stok="<?php echo h($item['stok']); ?>"
                                data-keterangan="<?php echo h($item['keterangan']); ?>"
                            >Edit</button>

                            <form method="post" style="display:inline" onsubmit="return confirm('Hapus suku cadang <?php echo h($item['kode']); ?>? Data yang masih digunakan transaksi tidak dapat dihapus.');">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                                <input type="hidden" name="action" value="delete_sparepart">
                                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                <button type="submit" class="btn-sparepart-action btn-sparepart-delete">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="sparepart-table-footer">
        <div id="paginationInfoSparepart">Menampilkan 0 item</div>
        <div class="sparepart-pagination" id="paginationSparepart"></div>
    </div>
</section>

<!-- MODAL TAMBAH -->
<div class="sparepart-modal" id="modalTambahSparepart" aria-hidden="true">
    <div class="sparepart-modal-box">
        <div class="sparepart-modal-header">
            <div>
                <h3>Tambah Suku Cadang</h3>
                <p>Masukkan data master suku cadang.</p>
            </div>
            <button type="button" class="sparepart-modal-close" data-close-sparepart-modal>&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="add_sparepart">

            <div class="sparepart-modal-body">
                <?php if ($openModal === 'modalTambahSparepart' && $error): ?>
                    <div class="sparepart-alert error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="sparepart-form-grid">
                    <div class="sparepart-form-field">
                        <label for="tambah_kode">Kode *</label>
                        <input type="text" id="tambah_kode" name="kode" required value="<?php echo h($_POST['kode'] ?? ''); ?>" placeholder="Contoh: SP004">
                    </div>

                    <div class="sparepart-form-field">
                        <label for="tambah_nama">Nama Suku Cadang *</label>
                        <input type="text" id="tambah_nama" name="nama" required value="<?php echo h($_POST['nama'] ?? ''); ?>" placeholder="Contoh: Oil Filter">
                    </div>

                    <div class="sparepart-form-field">
                        <label for="tambah_part_number">Part Number</label>
                        <input type="text" id="tambah_part_number" name="part_number" value="<?php echo h($_POST['part_number'] ?? ''); ?>">
                    </div>

                    <div class="sparepart-form-field">
                        <label for="tambah_merk">Merk</label>
                        <input type="text" id="tambah_merk" name="merk" value="<?php echo h($_POST['merk'] ?? ''); ?>">
                    </div>

                    <div class="sparepart-form-field">
                        <label for="tambah_satuan">Satuan *</label>
                        <select id="tambah_satuan" name="satuan" required>
                            <?php $selectedSatuan = (string)($_POST['satuan'] ?? 'PCS'); ?>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo h($unit); ?>" <?php echo $selectedSatuan === $unit ? 'selected' : ''; ?>><?php echo h($unit); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sparepart-form-field">
                        <label for="tambah_stok">Stok Awal</label>
                        <input type="number" id="tambah_stok" name="stok" min="0" step="0.01" value="<?php echo h($_POST['stok'] ?? '0'); ?>">
                    </div>

                    <div class="sparepart-form-field full">
                        <label for="tambah_keterangan">Keterangan</label>
                        <textarea id="tambah_keterangan" name="keterangan" placeholder="Keterangan tambahan..."><?php echo h($_POST['keterangan'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="sparepart-modal-footer">
                <button type="button" class="sparepart-btn-secondary" data-close-sparepart-modal>Batal</button>
                <button type="submit" class="sparepart-btn-primary">Simpan Suku Cadang</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="sparepart-modal" id="modalEditSparepart" aria-hidden="true">
    <div class="sparepart-modal-box">
        <div class="sparepart-modal-header">
            <div>
                <h3>Edit Suku Cadang</h3>
                <p>Perbarui data master suku cadang.</p>
            </div>
            <button type="button" class="sparepart-modal-close" data-close-sparepart-modal>&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="edit_sparepart">
            <input type="hidden" name="id" id="edit_id" value="<?php echo (int)($editData['id'] ?? 0); ?>">

            <div class="sparepart-modal-body">
                <?php if ($openModal === 'modalEditSparepart' && $error): ?>
                    <div class="sparepart-alert error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="sparepart-form-grid">
                    <div class="sparepart-form-field">
                        <label for="edit_kode">Kode *</label>
                        <input type="text" id="edit_kode" name="kode" required value="<?php echo h($editData['kode'] ?? ''); ?>">
                    </div>

                    <div class="sparepart-form-field">
                        <label for="edit_nama">Nama Suku Cadang *</label>
                        <input type="text" id="edit_nama" name="nama" required value="<?php echo h($editData['nama'] ?? ''); ?>">
                    </div>

                    <div class="sparepart-form-field">
                        <label for="edit_part_number">Part Number</label>
                        <input type="text" id="edit_part_number" name="part_number" value="<?php echo h($editData['part_number'] ?? ''); ?>">
                    </div>

                    <div class="sparepart-form-field">
                        <label for="edit_merk">Merk</label>
                        <input type="text" id="edit_merk" name="merk" value="<?php echo h($editData['merk'] ?? ''); ?>">
                    </div>

                    <div class="sparepart-form-field">
                        <label for="edit_satuan">Satuan *</label>
                        <select id="edit_satuan" name="satuan" required>
                            <?php $editSatuan = (string)($editData['satuan'] ?? 'PCS'); ?>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo h($unit); ?>" <?php echo $editSatuan === $unit ? 'selected' : ''; ?>><?php echo h($unit); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sparepart-form-field">
                        <label for="edit_stok">Stok</label>
                        <input type="number" id="edit_stok" name="stok" min="0" step="0.01" value="<?php echo h($editData['stok'] ?? '0'); ?>">
                    </div>

                    <div class="sparepart-form-field full">
                        <label for="edit_keterangan">Keterangan</label>
                        <textarea id="edit_keterangan" name="keterangan"><?php echo h($editData['keterangan'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="sparepart-modal-footer">
                <button type="button" class="sparepart-btn-secondary" data-close-sparepart-modal>Batal</button>
                <button type="submit" class="sparepart-btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var table = document.getElementById('sparepartTable');
    if (!table) return;

    var tbody = table.querySelector('tbody');
    var globalSearch = document.getElementById('globalSearchSparepart');
    var pageLength = document.getElementById('pageLengthSparepart');
    var pagination = document.getElementById('paginationSparepart');
    var paginationInfo = document.getElementById('paginationInfoSparepart');
    var allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]'));
    var filterInputs = Array.prototype.slice.call(table.querySelectorAll('.column-filter'));
    var currentPage = 1;
    var currentSortColumn = -1;
    var currentSortDirection = 'asc';

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function getCellText(row, column) {
        return row.children[column] ? normalize(row.children[column].innerText) : '';
    }

    function getFilteredRows() {
        var search = normalize(globalSearch.value);
        var filters = {};

        filterInputs.forEach(function (input) {
            filters[input.getAttribute('data-filter-column')] = normalize(input.value);
        });

        return allRows.filter(function (row) {
            if (search && normalize(row.innerText).indexOf(search) === -1) return false;

            for (var column in filters) {
                if (!filters[column]) continue;
                var value = getCellText(row, parseInt(column, 10));
                if (value.indexOf(filters[column]) === -1) return false;
            }

            return true;
        });
    }

    function numericCell(value) {
        var clean = String(value).replace(/[^0-9,-]/g, '').replace(/\./g, '').replace(',', '.');
        return parseFloat(clean) || 0;
    }

    function sortRows(rows) {
        if (currentSortColumn < 0) return rows;

        var column = currentSortColumn;
        var direction = currentSortDirection === 'asc' ? 1 : -1;

        return rows.sort(function (a, b) {
            if (column === 5 || column === 6) {
                return (numericCell(getCellText(a, column)) - numericCell(getCellText(b, column))) * direction;
            }

            return getCellText(a, column).localeCompare(getCellText(b, column), 'id', {
                numeric: true,
                sensitivity: 'base'
            }) * direction;
        });
    }

    function addPageButton(page) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-button' + (page === currentPage ? ' active' : '');
        button.innerText = page;
        button.addEventListener('click', function () {
            currentPage = page;
            render();
        });
        pagination.appendChild(button);
    }

    function addEllipsis() {
        var span = document.createElement('span');
        span.className = 'pagination-ellipsis';
        span.innerText = '...';
        pagination.appendChild(span);
    }

    function renderPagination(totalPages) {
        pagination.innerHTML = '';
        if (totalPages <= 1) return;

        var previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'page-button';
        previous.innerText = '‹ Sebelumnya';
        previous.disabled = currentPage === 1;
        previous.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                render();
            }
        });
        pagination.appendChild(previous);

        var maxButtons = 5;
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, startPage + maxButtons - 1);

        if ((endPage - startPage) < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        if (startPage > 1) {
            addPageButton(1);
            if (startPage > 2) addEllipsis();
        }

        for (var page = startPage; page <= endPage; page++) addPageButton(page);

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) addEllipsis();
            addPageButton(totalPages);
        }

        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'page-button';
        next.innerText = 'Berikutnya ›';
        next.disabled = currentPage === totalPages;
        next.addEventListener('click', function () {
            if (currentPage < totalPages) {
                currentPage++;
                render();
            }
        });
        pagination.appendChild(next);
    }

    function render() {
        var filteredRows = sortRows(getFilteredRows());
        var total = filteredRows.length;
        var perPage = parseInt(pageLength.value, 10) || 10;
        var totalPages = Math.max(1, Math.ceil(total / perPage));

        if (currentPage > totalPages) currentPage = totalPages;

        var start = (currentPage - 1) * perPage;
        var end = Math.min(start + perPage, total);

        allRows.forEach(function (row) { row.style.display = 'none'; });
        for (var i = start; i < end; i++) {
            filteredRows[i].style.display = '';
            tbody.appendChild(filteredRows[i]);
        }

        paginationInfo.innerText = total === 0
            ? 'Tidak ada data yang sesuai'
            : 'Menampilkan ' + (start + 1) + ' sampai ' + end + ' dari ' + total + ' item';

        renderPagination(totalPages);
    }

    globalSearch.addEventListener('input', function () { currentPage = 1; render(); });
    pageLength.addEventListener('change', function () { currentPage = 1; render(); });

    filterInputs.forEach(function (input) {
        input.addEventListener('input', function () { currentPage = 1; render(); });
        input.addEventListener('change', function () { currentPage = 1; render(); });
    });

    table.querySelectorAll('thead tr:first-child th.sortable').forEach(function (header) {
        header.addEventListener('click', function () {
            var column = parseInt(this.getAttribute('data-column'), 10);
            if (currentSortColumn === column) {
                currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortColumn = column;
                currentSortDirection = 'asc';
            }
            currentPage = 1;
            render();
        });
    });

    var modalTambah = document.getElementById('modalTambahSparepart');
    var modalEdit = document.getElementById('modalEditSparepart');

    function closeModals() {
        [modalTambah, modalEdit].forEach(function (modal) {
            if (modal) {
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
            }
        });
        document.body.style.overflow = '';
    }

    function openModal(modal) {
        if (!modal) return;
        closeModals();
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    document.getElementById('btnTambahSparepart').addEventListener('click', function () {
        openModal(modalTambah);
    });

    document.querySelectorAll('[data-close-sparepart-modal]').forEach(function (button) {
        button.addEventListener('click', closeModals);
    });

    [modalTambah, modalEdit].forEach(function (modal) {
        if (!modal) return;
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModals();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModals();
    });

    document.querySelectorAll('.btn-edit-sparepart').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('edit_id').value = this.getAttribute('data-id') || '';
            document.getElementById('edit_kode').value = this.getAttribute('data-kode') || '';
            document.getElementById('edit_nama').value = this.getAttribute('data-nama') || '';
            document.getElementById('edit_part_number').value = this.getAttribute('data-part-number') || '';
            document.getElementById('edit_merk').value = this.getAttribute('data-merk') || '';
            document.getElementById('edit_satuan').value = this.getAttribute('data-satuan') || 'PCS';
            document.getElementById('edit_stok').value = this.getAttribute('data-stok') || '0';
            document.getElementById('edit_keterangan').value = this.getAttribute('data-keterangan') || '';
            openModal(modalEdit);
        });
    });

    render();

    <?php if ($openModal === 'modalTambahSparepart'): ?>
    openModal(modalTambah);
    <?php elseif ($openModal === 'modalEditSparepart'): ?>
    openModal(modalEdit);
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
