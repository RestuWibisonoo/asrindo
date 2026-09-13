<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Bahan Restorasi';
$adminBase = '../';
$pdo = getPDO();

function h($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function rupiah($v): string {
    return 'Rp ' . number_format((float)$v, 0, ',', '.');
}
function stockFormat($v): string {
    $n = (float)$v;
    return floor($n) === $n
        ? number_format($n, 0, ',', '.')
        : number_format($n, 2, ',', '.');
}
function csrfToken(): string {
    if (empty($_SESSION['csrf_restorasi'])) {
        $_SESSION['csrf_restorasi'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_restorasi'];
}
function verifyCsrf(): void {
    if (!hash_equals($_SESSION['csrf_restorasi'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('Token keamanan tidak valid. Silakan coba lagi.');
    }
}
function redirectMsg(string $type, string $msg): void {
    header('Location: restorasi.php?' . $type . '=' . urlencode($msg));
    exit;
}

$csrf = csrfToken();
$message = (string)($_GET['success'] ?? '');
$error = (string)($_GET['error'] ?? '');
$openModal = '';
$editData = null;

/*
 * MASTER BAHAN RESTORASI
 * Stok tidak dapat diedit dari halaman ini.
 * Stok bertambah melalui pembelian_restorasi berstatus SELESAI.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        verifyCsrf();

        if ($action === 'add_restorasi' || $action === 'edit_restorasi') {
            $id = (int)($_POST['id'] ?? 0);
            $kode = trim((string)($_POST['kode'] ?? ''));
            $nama = trim((string)($_POST['nama'] ?? ''));
            $jenis = trim((string)($_POST['jenis'] ?? ''));
            $jenisLainnya = trim((string)($_POST['jenis_lainnya'] ?? ''));
            $merk = trim((string)($_POST['merk'] ?? ''));
            $satuan = trim((string)($_POST['satuan'] ?? 'PCS'));
            $satuanLainnya = trim((string)($_POST['satuan_lainnya'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            $jenisLainnyaSelected = strcasecmp($jenis, 'LAINNYA') === 0;
            $satuanLainnyaSelected = strcasecmp($satuan, 'LAINNYA') === 0;

            if ($jenisLainnyaSelected) {
                if ($jenisLainnya === '') {
                    throw new RuntimeException('Jenis bahan lainnya wajib diisi.');
                }
                $jenis = $jenisLainnya;
            }

            if ($satuanLainnyaSelected) {
                if ($satuanLainnya === '') {
                    throw new RuntimeException('Satuan lainnya wajib diisi.');
                }
                $satuan = $satuanLainnya;
            }

            if ($kode === '' || $nama === '') {
                throw new RuntimeException('Kode dan nama bahan restorasi wajib diisi.');
            }
            if ($satuan === '') {
                $satuan = 'PCS';
            }

            $stmt = $pdo->prepare(
                'SELECT id FROM restorasi WHERE kode = ? AND id <> ? LIMIT 1'
            );
            $stmt->execute([$kode, $id]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Kode bahan restorasi sudah digunakan.');
            }

            if ($action === 'add_restorasi') {
                $stmt = $pdo->prepare('
                    INSERT INTO restorasi
                    (kode, nama, jenis, merk, satuan, stok, keterangan)
                    VALUES (?, ?, ?, ?, ?, 0, ?)
                ');
                $stmt->execute([
                    $kode, $nama,
                    $jenis !== '' ? $jenis : null,
                    $merk !== '' ? $merk : null,
                    $satuan,
                    $keterangan !== '' ? $keterangan : null
                ]);
                redirectMsg('success', 'Bahan restorasi ' . $kode . ' berhasil ditambahkan.');
            }

            if ($id <= 0) {
                throw new RuntimeException('Data bahan restorasi tidak valid.');
            }

            $stmt = $pdo->prepare('
                UPDATE restorasi
                SET kode = ?, nama = ?, jenis = ?, merk = ?, satuan = ?, keterangan = ?
                WHERE id = ?
                LIMIT 1
            ');
            $stmt->execute([
                $kode, $nama,
                $jenis !== '' ? $jenis : null,
                $merk !== '' ? $merk : null,
                $satuan,
                $keterangan !== '' ? $keterangan : null,
                $id
            ]);
            redirectMsg('success', 'Bahan restorasi ' . $kode . ' berhasil diperbarui.');
        }

        if ($action === 'delete_restorasi') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Data bahan restorasi tidak valid.');
            }

            $stmt = $pdo->prepare('SELECT kode FROM restorasi WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new RuntimeException('Bahan restorasi tidak ditemukan.');
            }

            $stmt = $pdo->prepare('DELETE FROM restorasi WHERE id = ?');
            $stmt->execute([$id]);

            redirectMsg('success', 'Bahan restorasi ' . $row['kode'] . ' berhasil dihapus.');
        }

        throw new RuntimeException('Aksi tidak dikenal.');
    } catch (Throwable $e) {
        $error = $e->getMessage();

        if ($action === 'add_restorasi') {
            $openModal = 'modalTambahRestorasi';
        } elseif ($action === 'edit_restorasi') {
            $openModal = 'modalEditRestorasi';
            $editData = [
                'id' => (int)($_POST['id'] ?? 0),
                'kode' => (string)($_POST['kode'] ?? ''),
                'nama' => (string)($_POST['nama'] ?? ''),
                'jenis' => (string)($_POST['jenis'] ?? ''),
                'merk' => (string)($_POST['merk'] ?? ''),
                'satuan' => (string)($_POST['satuan'] ?? 'PCS'),
                'keterangan' => (string)($_POST['keterangan'] ?? '')
            ];
        }
    }
}

/* Data master + harga pembelian terakhir */
$items = [];
try {
    $stmt = $pdo->query("
        SELECT
            r.id, r.kode, r.nama, r.jenis, r.merk, r.satuan,
            r.stok, r.keterangan,
            COALESCE(lp.harga_terakhir, 0) AS harga_terakhir
        FROM restorasi r
        LEFT JOIN (
            SELECT d.restorasi_id, d.harga AS harga_terakhir
            FROM pembelian_restorasi_detail d
            INNER JOIN pembelian_restorasi p ON p.id = d.pembelian_id
            INNER JOIN (
                SELECT d2.restorasi_id,
                       MAX(CONCAT(DATE_FORMAT(p2.tanggal,'%Y%m%d'), LPAD(d2.id,10,'0'))) AS last_key
                FROM pembelian_restorasi_detail d2
                INNER JOIN pembelian_restorasi p2 ON p2.id = d2.pembelian_id
                WHERE UPPER(p2.status) = 'SELESAI'
                GROUP BY d2.restorasi_id
            ) x ON x.restorasi_id = d.restorasi_id
               AND x.last_key = CONCAT(DATE_FORMAT(p.tanggal,'%Y%m%d'), LPAD(d.id,10,'0'))
            WHERE UPPER(p.status) = 'SELESAI'
        ) lp ON lp.restorasi_id = r.id
        ORDER BY r.id DESC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $stmt = $pdo->query('
        SELECT id, kode, nama, jenis, merk, satuan, stok, keterangan, 0 AS harga_terakhir
        FROM restorasi ORDER BY id DESC
    ');
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalStok = 0.0;
$lowStock = 0;
$totalValue = 0.0;

foreach ($items as $item) {
    $stok = (float)$item['stok'];
    $totalStok += $stok;
    if ($stok < 10) $lowStock++;
    $totalValue += $stok * (float)$item['harga_terakhir'];
}

$units = ['PCS','SET','UNIT','LITER','KG','METER','ROLL','BOX'];

require __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================================
   HALAMAN BAHAN RESTORASI - SELARAS DENGAN SPAREPART
   ========================================================= */
.restorasi-panel { overflow: visible; }

.restorasi-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    padding: 15px 16px;
    border-bottom: 1px solid #dfe5ed;
}

.restorasi-search { flex: 1; max-width: 480px; }
.restorasi-page-size { width: 130px; }

.restorasi-toolbar label {
    display: block;
    margin-bottom: 6px;
    color: #52657f;
    font-size: 11px;
}

.restorasi-toolbar input,
.restorasi-toolbar select,
.restorasi-filter-row input,
.restorasi-filter-row select {
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

.restorasi-toolbar input,
.restorasi-toolbar select { height: 36px; }

.restorasi-toolbar input:focus,
.restorasi-toolbar select:focus,
.restorasi-filter-row input:focus,
.restorasi-filter-row select:focus,
.restorasi-form-field input:focus,
.restorasi-form-field select:focus,
.restorasi-form-field textarea:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13,110,253,.08);
}

.restorasi-table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

.restorasi-table th,
.restorasi-table td { vertical-align: middle; }

.restorasi-table thead > tr:first-child th {
    padding: 10px 8px;
    background: #fff;
    border-bottom: 1px solid #dfe5ed;
    color: #183052;
    font-size: 11px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.restorasi-filter-row th {
    padding: 8px 7px;
    background: #f7f9fc;
    border-bottom: 1px solid #dfe5ed;
}

.restorasi-filter-row input {
    height: 32px;
    padding: 5px 7px;
    font-size: 11px;
}

.restorasi-table tbody td {
    padding: 11px 9px;
    border-bottom: 1px solid #e2e7ee;
    color: #183052;
    font-size: 11px;
    white-space: nowrap;
}

.restorasi-table tbody tr:hover { background: #fbfdff; }

.restorasi-code {
    color: #0d6efd;
    font-weight: 600;
}

.restorasi-stock {
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

.restorasi-price {
    color: #008f4c;
    font-weight: 600;
    text-align: right;
}

.restorasi-action {
    width: 135px;
    text-align: center;
}

.btn-restorasi-action {
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

.btn-restorasi-action:hover {
    border-color: #0d6efd;
    color: #0d6efd;
}

.btn-restorasi-delete:hover {
    border-color: #dc3545;
    color: #dc3545;
}

.restorasi-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 13px 16px;
    color: #6d7e96;
    font-size: 11px;
}

.restorasi-pagination {
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
.page-button.active {
    border-color: #0d6efd;
    color: #0d6efd;
}

.page-button.active {
    background: #eef5ff;
    font-weight: 600;
}

.page-button:disabled { opacity: .45; cursor: default; }
.pagination-ellipsis { padding: 0 3px; color: #8795a8; }
.empty-table { padding: 35px !important; text-align: center; color: #8190a5 !important; }

.restorasi-stat-panel {
    margin-bottom: 18px;
    background: #fff;
    border: 1px solid #dfe5ed;
    border-radius: 4px;
    overflow: hidden;
}

.restorasi-stat-title {
    padding: 15px 20px;
    border-bottom: 1px solid #dfe5ed;
    color: #183052;
    font-size: 13px;
    font-weight: 500;
}

.restorasi-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
}

.restorasi-stat-item {
    min-height: 85px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px 10px;
    text-align: center;
}

.restorasi-stat-item strong {
    color: #006cff;
    font-size: 19px;
    font-weight: 500;
}

.restorasi-stat-item span {
    margin-top: 7px;
    color: #71839b;
    font-size: 11px;
}

.text-green { color: #00b85a !important; }
.text-orange { color: #f0a000 !important; }

.restorasi-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 30, 50, .42);
}

.restorasi-modal.show { display: flex; }

.restorasi-modal-box {
    width: min(680px, 100%);
    max-height: calc(100vh - 40px);
    overflow: auto;
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 15px 45px rgba(0,0,0,.18);
}

.restorasi-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 18px 20px;
    border-bottom: 1px solid #e1e6ee;
}

.restorasi-modal-header h3 {
    margin: 0;
    color: #183052;
    font-size: 16px;
}

.restorasi-modal-header p {
    margin: 5px 0 0;
    color: #71839b;
    font-size: 11px;
}

.restorasi-modal-close {
    border: 0;
    background: transparent;
    color: #71839b;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}

.restorasi-modal-body { padding: 20px; }

.restorasi-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.restorasi-form-field.full { grid-column: 1 / -1; }

.restorasi-form-field label {
    display: block;
    margin-bottom: 6px;
    color: #52657f;
    font-size: 11px;
    font-weight: 600;
}

.restorasi-form-field input,
.restorasi-form-field select,
.restorasi-form-field textarea {
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

.restorasi-form-field textarea {
    min-height: 80px;
    resize: vertical;
}

.restorasi-lainnya {
    display: none;
    margin-top: 7px;
}

.restorasi-lainnya.show { display: block; }

.restorasi-stock-info {
    padding: 10px 12px;
    border: 1px solid #e1e7ef;
    border-radius: 4px;
    background: #f8fafc;
    color: #52657f;
    font-size: 11px;
    line-height: 1.5;
}

.restorasi-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 20px;
    border-top: 1px solid #e1e6ee;
}

.restorasi-btn-primary,
.restorasi-btn-secondary {
    border: 0;
    border-radius: 4px;
    padding: 9px 14px;
    font-size: 11px;
    cursor: pointer;
}

.restorasi-btn-primary { background: #0d6efd; color: #fff; }
.restorasi-btn-secondary { background: #718096; color: #fff; }

.restorasi-alert {
    padding: 10px 13px;
    margin: 0 0 16px;
    border-radius: 4px;
    font-size: 12px;
}

.restorasi-alert.success {
    background: #effcf5;
    border: 1px solid #bcebd2;
    color: #087443;
}

.restorasi-alert.error {
    background: #fff0f2;
    border: 1px solid #ffc8d0;
    color: #9b1c31;
}

@media (max-width: 800px) {
    .restorasi-toolbar { align-items: stretch; flex-direction: column; }
    .restorasi-search, .restorasi-page-size { width: 100%; max-width: none; }
    .restorasi-table-footer { align-items: flex-start; flex-direction: column; }
    .restorasi-pagination { justify-content: flex-start; }
    .restorasi-stat-grid { grid-template-columns: repeat(2, 1fr); }
    .restorasi-form-grid { grid-template-columns: 1fr; }
    .restorasi-form-field.full { grid-column: auto; }
    .restorasi-modal { padding: 10px; }
    .restorasi-modal-box { max-height: calc(100vh - 20px); }
}

@media (max-width: 480px) {
    .restorasi-stat-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<section class="page-heading">
    <div>
        <h1>Manajemen Bahan Restorasi</h1>
        <p>Mengelola data inventori bahan restorasi ASRINDO</p>
    </div>
    <button type="button" class="btn-primary" id="btnTambahRestorasi">
        <span>+</span> Tambah Bahan
    </button>
</section>

<?php if ($message): ?>
    <div class="restorasi-alert success"><?php echo h($message); ?></div>
<?php endif; ?>

<?php if ($error && !$openModal): ?>
    <div class="restorasi-alert error"><?php echo h($error); ?></div>
<?php endif; ?>

<section class="restorasi-stat-panel">
    <div class="restorasi-stat-title">Statistik Bahan Restorasi</div>

    <div class="restorasi-stat-grid">
        <div class="restorasi-stat-item">
            <strong><?php echo number_format(count($items), 0, ',', '.'); ?></strong>
            <span>Total Item</span>
        </div>

        <div class="restorasi-stat-item">
            <strong class="text-green"><?php echo rupiah($totalValue); ?></strong>
            <span>Total Nilai Inventori</span>
        </div>

        <div class="restorasi-stat-item">
            <strong class="text-orange"><?php echo number_format($lowStock, 0, ',', '.'); ?></strong>
            <span>Item Stok Rendah (&lt;10)</span>
        </div>

        <div class="restorasi-stat-item">
            <strong><?php echo stockFormat($totalStok); ?></strong>
            <span>Total Stok</span>
        </div>
    </div>
</section>

<section class="dashboard-panel restorasi-panel">
    <div class="panel-heading">
        <div>
            <h2>Daftar Bahan Restorasi</h2>
            <span class="panel-subtitle">
                <?php echo number_format(count($items), 0, ',', '.'); ?> item
            </span>
        </div>
    </div>

    <div class="restorasi-toolbar">
        <div class="restorasi-search">
            <label for="globalSearchRestorasi">Pencarian</label>
            <input
                type="search"
                id="globalSearchRestorasi"
                placeholder="Cari kode, nama, jenis, merk..."
                autocomplete="off"
            >
        </div>

        <div class="restorasi-page-size">
            <label for="pageLengthRestorasi">Tampilkan</label>
            <select id="pageLengthRestorasi">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="restorasi-note" style="margin: 0 16px 12px; padding: 10px 13px; border: 1px solid #dfe5ed; border-radius: 4px; background: #f8fafc; color: #64748b; font-size: 11px; line-height: 1.5;">
        <strong>Catatan stok:</strong>
        stok tidak dapat ditambah atau dikurangi secara manual pada master.
        Stok bertambah melalui <strong>Pembelian Restorasi</strong> setelah status
        <strong>SELESAI</strong>.
    </div>

    <div class="table-responsive">
        <table class="dashboard-table restorasi-table" id="restorasiTable">
            <thead>
                <tr>
                    <th class="sortable" data-column="0">No</th>
                    <th class="sortable" data-column="1">Kode</th>
                    <th class="sortable" data-column="2">Nama Bahan</th>
                    <th class="sortable" data-column="3">Jenis</th>
                    <th class="sortable" data-column="4">Merk</th>
                    <th class="sortable" data-column="5">Satuan</th>
                    <th class="sortable" data-column="6">Stok</th>
                    <th class="sortable" data-column="7">Hrg Beli Terakhir</th>
                    <th class="sortable" data-column="8">Keterangan</th>
                    <th class="restorasi-action">Aksi</th>
                </tr>

                <tr class="restorasi-filter-row">
                    <th><input type="text" class="column-filter" data-filter-column="0" placeholder="Filter no"></th>
                    <th><input type="text" class="column-filter" data-filter-column="1" placeholder="Filter kode"></th>
                    <th><input type="text" class="column-filter" data-filter-column="2" placeholder="Filter nama"></th>
                    <th><input type="text" class="column-filter" data-filter-column="3" placeholder="Filter jenis"></th>
                    <th><input type="text" class="column-filter" data-filter-column="4" placeholder="Filter merk"></th>
                    <th><input type="text" class="column-filter" data-filter-column="5" placeholder="Filter satuan"></th>
                    <th><input type="text" class="column-filter" data-filter-column="6" placeholder="Filter stok"></th>
                    <th><input type="text" class="column-filter" data-filter-column="7" placeholder="Filter harga"></th>
                    <th><input type="text" class="column-filter" data-filter-column="8" placeholder="Filter keterangan"></th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="10" class="empty-table">Belum ada data bahan restorasi.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $i => $item): ?>
                    <tr data-id="<?php echo (int)$item['id']; ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td><strong class="restorasi-code"><?php echo h($item['kode']); ?></strong></td>
                        <td><?php echo h($item['nama']); ?></td>
                        <td><?php echo h($item['jenis']); ?></td>
                        <td><?php echo h($item['merk']); ?></td>
                        <td><?php echo h($item['satuan']); ?></td>
                        <td class="restorasi-stock">
                            <span class="stock-badge <?php echo (float)$item['stok'] < 10 ? 'stock-low' : 'stock-ok'; ?>">
                                <?php echo stockFormat($item['stok']); ?>
                            </span>
                        </td>
                        <td class="restorasi-price"><?php echo rupiah($item['harga_terakhir']); ?></td>
                        <td title="<?php echo h($item['keterangan']); ?>"><?php echo h($item['keterangan']); ?></td>
                        <td class="restorasi-action">
                            <button
                                type="button"
                                class="btn-restorasi-action btn-edit-restorasi"
                                data-id="<?php echo (int)$item['id']; ?>"
                                data-kode="<?php echo h($item['kode']); ?>"
                                data-nama="<?php echo h($item['nama']); ?>"
                                data-jenis="<?php echo h($item['jenis']); ?>"
                                data-merk="<?php echo h($item['merk']); ?>"
                                data-satuan="<?php echo h($item['satuan']); ?>"
                                data-keterangan="<?php echo h($item['keterangan']); ?>"
                            >Edit</button>

                            <form method="post" style="display:inline" onsubmit="return confirm('Hapus bahan restorasi <?php echo h($item['kode']); ?>? Data yang masih digunakan transaksi tidak dapat dihapus.');">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                                <input type="hidden" name="action" value="delete_restorasi">
                                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                <button type="submit" class="btn-restorasi-action btn-restorasi-delete">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="restorasi-table-footer">
        <div id="paginationInfoRestorasi">Menampilkan 0 item</div>
        <div class="restorasi-pagination" id="paginationRestorasi"></div>
    </div>
</section>

<!-- MODAL TAMBAH -->
<div class="restorasi-modal" id="modalTambahRestorasi" aria-hidden="true">
    <div class="restorasi-modal-box">
        <div class="restorasi-modal-header">
            <div>
                <h3>Tambah Bahan Restorasi</h3>
                <p>Masukkan data master bahan restorasi.</p>
            </div>
            <button type="button" class="restorasi-modal-close" data-close-restorasi-modal>&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="add_restorasi">

            <div class="restorasi-modal-body">
                <?php if ($openModal === 'modalTambahRestorasi' && $error): ?>
                    <div class="restorasi-alert error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="restorasi-form-grid">

                    <div class="restorasi-form-field">
                        <label for="tambah_kode">Kode *</label>
                        <input
                            type="text"
                            id="tambah_kode"
                            name="kode"
                            required
                            maxlength="50"
                            value="<?php echo h($_POST['kode'] ?? ''); ?>"
                            placeholder="Contoh: RST-CAT-001"
                        >
                    </div>

                    <div class="restorasi-form-field">
                        <label for="tambah_nama">Nama Bahan *</label>
                        <input
                            type="text"
                            id="tambah_nama"
                            name="nama"
                            required
                            maxlength="200"
                            value="<?php echo h($_POST['nama'] ?? ''); ?>"
                            placeholder="Contoh: Cat Hitam"
                        >
                    </div>

                    <div class="restorasi-form-field">
                        <label for="tambah_jenis">Jenis Bahan</label>
                        <?php $jenisTambah = (string)($_POST['jenis'] ?? ''); ?>
                        <select id="tambah_jenis" name="jenis">
                            <option value="">-- Pilih Jenis --</option>
                            <option value="CAT" <?php echo $jenisTambah === 'CAT' ? 'selected' : ''; ?>>CAT</option>
                            <option value="DEMPUL" <?php echo $jenisTambah === 'DEMPUL' ? 'selected' : ''; ?>>DEMPUL</option>
                            <option value="TINER" <?php echo $jenisTambah === 'TINER' ? 'selected' : ''; ?>>TINER</option>
                            <option value="LAINNYA" <?php echo $jenisTambah === 'LAINNYA' ? 'selected' : ''; ?>>LAINNYA</option>
                        </select>
                        <input
                            type="text"
                            class="restorasi-lainnya <?php echo $jenisTambah === 'LAINNYA' ? 'show' : ''; ?>"
                            id="tambah_jenis_lainnya"
                            name="jenis_lainnya"
                            maxlength="100"
                            value="<?php echo h($_POST['jenis_lainnya'] ?? ''); ?>"
                            placeholder="Tulis jenis bahan lainnya..."
                        >
                    </div>

                    <div class="restorasi-form-field">
                        <label for="tambah_merk">Merk</label>
                        <input
                            type="text"
                            id="tambah_merk"
                            name="merk"
                            maxlength="100"
                            value="<?php echo h($_POST['merk'] ?? ''); ?>"
                            placeholder="Merk bahan"
                        >
                    </div>

                    <div class="restorasi-form-field">
                        <label for="tambah_satuan">Satuan *</label>
                        <?php $satuanTambah = (string)($_POST['satuan'] ?? 'PCS'); ?>
                        <select id="tambah_satuan" name="satuan" required>
                            <?php foreach ($units as $u): ?>
                                <option value="<?php echo h($u); ?>" <?php echo $satuanTambah === $u ? 'selected' : ''; ?>>
                                    <?php echo h($u); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="LAINNYA" <?php echo $satuanTambah === 'LAINNYA' ? 'selected' : ''; ?>>LAINNYA</option>
                        </select>
                        <input
                            type="text"
                            class="restorasi-lainnya <?php echo $satuanTambah === 'LAINNYA' ? 'show' : ''; ?>"
                            id="tambah_satuan_lainnya"
                            name="satuan_lainnya"
                            maxlength="30"
                            value="<?php echo h($_POST['satuan_lainnya'] ?? ''); ?>"
                            placeholder="Tulis satuan lainnya..."
                        >
                    </div>

                    <div class="restorasi-form-field full">
                        <div class="restorasi-stock-info">
                            <strong>Stok awal: 0</strong><br>
                            Stok tidak dapat dimasukkan secara manual. Stok hanya bertambah
                            melalui Pembelian Restorasi setelah transaksi berstatus SELESAI.
                        </div>
                    </div>

                    <div class="restorasi-form-field full">
                        <label for="tambah_keterangan">Keterangan</label>
                        <textarea id="tambah_keterangan" name="keterangan" maxlength="1000" placeholder="Keterangan tambahan..."><?php echo h($_POST['keterangan'] ?? ''); ?></textarea>
                    </div>

                </div>
            </div>

            <div class="restorasi-modal-footer">
                <button type="button" class="restorasi-btn-secondary" data-close-restorasi-modal>Batal</button>
                <button type="submit" class="restorasi-btn-primary">Simpan Bahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="restorasi-modal" id="modalEditRestorasi" aria-hidden="true">
    <div class="restorasi-modal-box">
        <div class="restorasi-modal-header">
            <div>
                <h3>Edit Bahan Restorasi</h3>
                <p>Perbarui data master bahan restorasi.</p>
            </div>
            <button type="button" class="restorasi-modal-close" data-close-restorasi-modal>&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="edit_restorasi">
            <input type="hidden" name="id" id="editRestId" value="<?php echo (int)($editData['id'] ?? 0); ?>">

            <div class="restorasi-modal-body">
                <?php if ($openModal === 'modalEditRestorasi' && $error): ?>
                    <div class="restorasi-alert error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="restorasi-form-grid">

                    <div class="restorasi-form-field">
                        <label for="editRestKode">Kode *</label>
                        <input id="editRestKode" name="kode" required maxlength="50" value="<?php echo h($editData['kode'] ?? ''); ?>">
                    </div>

                    <div class="restorasi-form-field">
                        <label for="editRestNama">Nama Bahan *</label>
                        <input id="editRestNama" name="nama" required maxlength="200" value="<?php echo h($editData['nama'] ?? ''); ?>">
                    </div>

                    <div class="restorasi-form-field">
                        <label for="editRestJenis">Jenis Bahan</label>
                        <select id="editRestJenis" name="jenis">
                            <option value="">-- Pilih Jenis --</option>
                            <option value="CAT">CAT</option>
                            <option value="DEMPUL">DEMPUL</option>
                            <option value="TINER">TINER</option>
                            <option value="LAINNYA">LAINNYA</option>
                        </select>
                        <input type="text" class="restorasi-lainnya" id="editRestJenisLainnya" name="jenis_lainnya" maxlength="100" placeholder="Tulis jenis bahan lainnya...">
                    </div>

                    <div class="restorasi-form-field">
                        <label for="editRestMerk">Merk</label>
                        <input id="editRestMerk" name="merk" maxlength="100" value="<?php echo h($editData['merk'] ?? ''); ?>">
                    </div>

                    <div class="restorasi-form-field">
                        <label for="editRestSatuan">Satuan *</label>
                        <select id="editRestSatuan" name="satuan" required>
                            <?php foreach ($units as $u): ?>
                                <option value="<?php echo h($u); ?>"><?php echo h($u); ?></option>
                            <?php endforeach; ?>
                            <option value="LAINNYA">LAINNYA</option>
                        </select>
                        <input type="text" class="restorasi-lainnya" id="editRestSatuanLainnya" name="satuan_lainnya" maxlength="30" placeholder="Tulis satuan lainnya...">
                    </div>

                    <div class="restorasi-form-field full">
                        <div class="restorasi-stock-info">
                            <strong>Stok tidak dapat diubah di sini.</strong><br>
                            Perubahan stok dilakukan melalui transaksi Pembelian Restorasi
                            dan penggunaan bahan pada unit.
                        </div>
                    </div>

                    <div class="restorasi-form-field full">
                        <label for="editRestKet">Keterangan</label>
                        <textarea id="editRestKet" name="keterangan" maxlength="1000"><?php echo h($editData['keterangan'] ?? ''); ?></textarea>
                    </div>

                </div>
            </div>

            <div class="restorasi-modal-footer">
                <button type="button" class="restorasi-btn-secondary" data-close-restorasi-modal>Batal</button>
                <button type="submit" class="restorasi-btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var table = document.getElementById('restorasiTable');
    if (!table) return;

    var tbody = table.querySelector('tbody');
    var globalSearch = document.getElementById('globalSearchRestorasi');
    var pageLength = document.getElementById('pageLengthRestorasi');
    var pagination = document.getElementById('paginationRestorasi');
    var paginationInfo = document.getElementById('paginationInfoRestorasi');
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
            if (column === 0 || column === 6 || column === 7) {
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

        for (var page = startPage; page <= endPage; page++) {
            addPageButton(page);
        }

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

        allRows.forEach(function (row) {
            row.style.display = 'none';
        });

        for (var i = start; i < end; i++) {
            filteredRows[i].style.display = '';
            filteredRows[i].children[0].innerText = i + 1;
            tbody.appendChild(filteredRows[i]);
        }

        paginationInfo.innerText = total === 0
            ? 'Tidak ada data yang sesuai'
            : 'Menampilkan ' + (start + 1) + ' sampai ' + end + ' dari ' + total + ' item';

        renderPagination(totalPages);
    }

    globalSearch.addEventListener('input', function () {
        currentPage = 1;
        render();
    });

    pageLength.addEventListener('change', function () {
        currentPage = 1;
        render();
    });

    filterInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            currentPage = 1;
            render();
        });
        input.addEventListener('change', function () {
            currentPage = 1;
            render();
        });
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

    render();
})();

/* =========================================================
   MODAL BAHAN RESTORASI
   ========================================================= */
(function () {
    var modalTambah = document.getElementById('modalTambahRestorasi');
    var modalEdit = document.getElementById('modalEditRestorasi');

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

    function setupLainnya(selectId, inputId) {
        var select = document.getElementById(selectId);
        var input = document.getElementById(inputId);
        if (!select || !input) return;

        function toggle() {
            var show = select.value === 'LAINNYA';
            input.classList.toggle('show', show);
            input.required = show;
            if (!show) input.value = '';
        }

        select.addEventListener('change', toggle);
        toggle();
    }

    document.getElementById('btnTambahRestorasi').addEventListener('click', function () {
        openModal(modalTambah);
    });

    document.querySelectorAll('[data-close-restorasi-modal]').forEach(function (button) {
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

    setupLainnya('tambah_jenis', 'tambah_jenis_lainnya');
    setupLainnya('tambah_satuan', 'tambah_satuan_lainnya');
    setupLainnya('editRestJenis', 'editRestJenisLainnya');
    setupLainnya('editRestSatuan', 'editRestSatuanLainnya');

    document.querySelectorAll('.btn-edit-restorasi').forEach(function (button) {
        button.addEventListener('click', function () {
            var jenis = this.getAttribute('data-jenis') || '';
            var satuan = this.getAttribute('data-satuan') || '';

            document.getElementById('editRestId').value = this.getAttribute('data-id') || '';
            document.getElementById('editRestKode').value = this.getAttribute('data-kode') || '';
            document.getElementById('editRestNama').value = this.getAttribute('data-nama') || '';
            document.getElementById('editRestMerk').value = this.getAttribute('data-merk') || '';
            document.getElementById('editRestKet').value = this.getAttribute('data-keterangan') || '';

            var jenisSelect = document.getElementById('editRestJenis');
            var jenisLainnya = document.getElementById('editRestJenisLainnya');
            var jenisKnown = ['', 'CAT', 'DEMPUL', 'TINER'].indexOf(jenis) >= 0;

            jenisSelect.value = jenisKnown ? jenis : 'LAINNYA';
            jenisLainnya.value = jenisKnown ? '' : jenis;
            jenisLainnya.classList.toggle('show', !jenisKnown);
            jenisLainnya.required = !jenisKnown;

            var satuanSelect = document.getElementById('editRestSatuan');
            var satuanLainnya = document.getElementById('editRestSatuanLainnya');
            var satuanKnown = ['PCS','SET','UNIT','LITER','KG','METER','ROLL','BOX'].indexOf(satuan) >= 0;

            satuanSelect.value = satuanKnown ? satuan : 'LAINNYA';
            satuanLainnya.value = satuanKnown ? '' : satuan;
            satuanLainnya.classList.toggle('show', !satuanKnown);
            satuanLainnya.required = !satuanKnown;

            openModal(modalEdit);
        });
    });

    <?php if ($openModal === 'modalTambahRestorasi'): ?>
    openModal(modalTambah);
    <?php elseif ($openModal === 'modalEditRestorasi'): ?>
    openModal(modalEdit);
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
