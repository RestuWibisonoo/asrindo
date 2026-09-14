<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Alat Berat';
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

function statusClass($status): string
{
    $status = strtoupper(trim((string)$status));

    if (in_array($status, ['TERSEDIA', 'SIAP JUAL', 'READY'], true)) {
        return 'badge-success';
    }

    if (in_array($status, ['PERBAIKAN', 'REPAIR'], true)) {
        return 'badge-warning';
    }

    if (in_array($status, ['DISEWAKAN', 'DI SEWAKAN', 'RENTAL'], true)) {
        return 'badge-info';
    }

    if (in_array($status, ['TERJUAL', 'SOLD'], true)) {
        return 'badge-danger';
    }

    return 'badge-default';
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_alat_berat'])) {
        $_SESSION['csrf_alat_berat'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_alat_berat'];
}

function verifyCsrf(): void
{
    if (!hash_equals(
        $_SESSION['csrf_alat_berat'] ?? '',
        (string)($_POST['csrf_token'] ?? '')
    )) {
        throw new RuntimeException('Token keamanan tidak valid. Silakan coba lagi.');
    }
}

$csrf = csrfToken();
$message = '';
$error = '';
$openModal = '';

/* =========================================================
   TAMBAH ALAT BERAT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'add_unit') {
            $kode = trim((string)($_POST['kode'] ?? ''));
            $tipe = trim((string)($_POST['tipe'] ?? ''));
            $nomorRangka = trim((string)($_POST['nomor_rangka'] ?? ''));
            $tahun = trim((string)($_POST['tahun_pembuatan'] ?? ''));
            $kondisi = trim((string)($_POST['kondisi'] ?? ''));
            $status = trim((string)($_POST['status'] ?? ''));
            $lokasi = trim((string)($_POST['lokasi'] ?? ''));

            if ($kode === '' || $tipe === '' || $nomorRangka === '') {
                throw new RuntimeException('Kode, tipe, dan nomor rangka wajib diisi.');
            }

            if ($tahun !== '' && !preg_match('/^\d{4}$/', $tahun)) {
                throw new RuntimeException('Tahun pembuatan harus berupa 4 digit.');
            }

            $allowedKondisi = ['Baru', 'Bekas'];
            $allowedStatus = ['Tersedia', 'Siap Jual', 'Perbaikan', 'Disewakan', 'Terjual'];

            if (!in_array($kondisi, $allowedKondisi, true)) {
                throw new RuntimeException('Kondisi unit tidak valid.');
            }

            if (!in_array($status, $allowedStatus, true)) {
                throw new RuntimeException('Status unit tidak valid.');
            }

            $stmt = $pdo->prepare('SELECT id FROM alat_berat WHERE kode = ? OR nomor_rangka = ? LIMIT 1');
            $stmt->execute([$kode, $nomorRangka]);
            $duplicate = $stmt->fetch();

            if ($duplicate) {
                throw new RuntimeException('Kode atau nomor rangka sudah digunakan oleh unit lain.');
            }

            $stmt = $pdo->prepare(''
                . 'INSERT INTO alat_berat '
                . '(kode, tipe, nomor_rangka, tahun_pembuatan, kondisi, status, lokasi) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $kode,
                $tipe,
                $nomorRangka,
                $tahun === '' ? null : $tahun,
                $kondisi,
                $status,
                $lokasi === '' ? null : $lokasi
            ]);

            $newId = (int)$pdo->lastInsertId();

            $message = 'Alat berat ' . $kode . ' berhasil ditambahkan.';
            $openModal = '';
        } else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $openModal = 'modalTambahAlat';
    }
}

/* =========================================================
   DATA STATISTIK
   ========================================================= */
$totalUnit = (int)$pdo->query('SELECT COUNT(*) FROM alat_berat')->fetchColumn();

$unitTersedia = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alat_berat
    WHERE UPPER(status) IN ('TERSEDIA', 'SIAP JUAL', 'READY')
")->fetchColumn();

/* Seluruh data untuk pagination/filter di browser */
$units = $pdo->query("
    SELECT
        id,
        kode,
        tipe,
        nomor_rangka,
        tahun_pembuatan,
        kondisi,
        status,
        lokasi
    FROM alat_berat
    ORDER BY id DESC
")->fetchAll();

/* =========================================================
   HPP PER UNIT
   HPP unit = harga pembelian unit terbaru + biaya pembentuk
   (sparepart + jasa) + riwayat perawatan.
   Biaya header pembelian (bea cukai, pengiriman, biaya lain)
   dialokasikan rata ke setiap unit dalam transaksi pembelian.
   ========================================================= */
$hppByUnit = [];
try {
    $stmt = $pdo->query("
        SELECT
            d.alat_berat_id,
            (
                d.harga_beli
                + (
                    COALESCE(p.biaya_bea_cukai, 0)
                    + COALESCE(p.biaya_pengiriman, 0)
                    + COALESCE(p.biaya_lain, 0)
                ) / NULLIF((
                    SELECT COUNT(*)
                    FROM pembelian_alat_berat_detail d2
                    WHERE d2.pembelian_id = p.id
                ), 0)
            ) AS hpp_pembelian
        FROM pembelian_alat_berat_detail d
        INNER JOIN pembelian_alat_berat p ON p.id = d.pembelian_id
        INNER JOIN (
            SELECT d3.alat_berat_id, MAX(CONCAT(LPAD(p3.tanggal, 10, '0'), LPAD(p3.id, 10, '0'))) AS latest_key
            FROM pembelian_alat_berat_detail d3
            INNER JOIN pembelian_alat_berat p3 ON p3.id = d3.pembelian_id
            WHERE UPPER(COALESCE(p3.status, '')) <> 'BATAL'
            GROUP BY d3.alat_berat_id
        ) latest
            ON latest.alat_berat_id = d.alat_berat_id
           AND latest.latest_key = CONCAT(LPAD(p.tanggal, 10, '0'), LPAD(p.id, 10, '0'))
        WHERE UPPER(COALESCE(p.status, '')) <> 'BATAL'
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stmt as $row) {
        $hppByUnit[(int)$row['alat_berat_id']] = (float)$row['hpp_pembelian'];
    }

    $stmt = $pdo->query("
        SELECT alat_berat_id, COALESCE(SUM(qty * harga), 0) AS total
        FROM alat_berat_sparepart
        GROUP BY alat_berat_id
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stmt as $row) {
        $uid = (int)$row['alat_berat_id'];
        $hppByUnit[$uid] = ($hppByUnit[$uid] ?? 0) + (float)$row['total'];
    }

    $stmt = $pdo->query("
        SELECT alat_berat_id, COALESCE(SUM(qty * biaya), 0) AS total
        FROM alat_berat_jasa
        GROUP BY alat_berat_id
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stmt as $row) {
        $uid = (int)$row['alat_berat_id'];
        $hppByUnit[$uid] = ($hppByUnit[$uid] ?? 0) + (float)$row['total'];
    }

    $stmt = $pdo->query("
        SELECT alat_berat_id, COALESCE(SUM(finishing + suku_cadang + jasa), 0) AS total
        FROM alat_berat_perawatan
        GROUP BY alat_berat_id
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stmt as $row) {
        $uid = (int)$row['alat_berat_id'];
        $hppByUnit[$uid] = ($hppByUnit[$uid] ?? 0) + (float)$row['total'];
    }
} catch (Throwable $e) {
    $hppByUnit = [];
}

/* Total Nilai Inventori = total seluruh HPP Unit yang ditampilkan. */
$totalNilaiInventori = array_sum($hppByUnit);

$statuses = [];
foreach ($units as $unit) {
    $status = trim((string)$unit['status']);
    if ($status !== '' && !in_array($status, $statuses, true)) {
        $statuses[] = $status;
    }
}
sort($statuses);

require __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================================
   HALAMAN ALAT BERAT - DISELARASKAN DENGAN MODUL PEMBELIAN
   ========================================================= */
.alat-panel {
    overflow: visible;
}

.alat-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    padding: 15px 16px;
    border-bottom: 1px solid #dfe5ed;
}

.alat-search {
    flex: 1;
    max-width: 480px;
}

.alat-page-size {
    width: 130px;
}

.alat-toolbar label {
    display: block;
    margin-bottom: 6px;
    color: #52657f;
    font-size: 11px;
}

.alat-toolbar input,
.alat-toolbar select,
.alat-filter-row input,
.alat-filter-row select {
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

.alat-toolbar input,
.alat-toolbar select {
    height: 36px;
}

.alat-toolbar input:focus,
.alat-toolbar select:focus,
.alat-filter-row input:focus,
.alat-filter-row select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13,110,253,.08);
}

.alat-table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

.alat-table th,
.alat-table td {
    vertical-align: middle;
}

.alat-table thead > tr:first-child th {
    padding: 10px 8px;
    background: #fff;
    border-bottom: 1px solid #dfe5ed;
    color: #183052;
    font-size: 11px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.alat-table thead > tr:first-child th.sortable {
    cursor: pointer;
}

.alat-table thead > tr:first-child th.sortable:hover {
    color: #0d6efd;
}

.alat-filter-row th {
    padding: 8px 7px;
    background: #f7f9fc;
    border-bottom: 1px solid #dfe5ed;
}

.alat-filter-row input,
.alat-filter-row select {
    height: 32px;
    padding: 5px 7px;
    font-size: 11px;
}

.alat-filter-row th:last-child {
    background: #f7f9fc;
}

.alat-table tbody td {
    padding: 11px 9px;
    border-bottom: 1px solid #e2e7ee;
    color: #183052;
    font-size: 11px;
    white-space: nowrap;
}
.alat-table tbody td.hpp-cell {
    font-weight: 600;
    color: #008f4c;
    text-align: right;
}

.alat-table tbody tr:hover {
    background: #fbfdff;
}

.unit-code {
    color: #0d6efd;
    font-weight: 600;
}

.alat-table .col-action {
    width: 78px;
    text-align: center;
}

.btn-detail {
    min-width: 50px;
    height: 30px;
    padding: 0 10px;
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

.btn-detail:hover {
    border-color: #0d6efd;
    color: #0d6efd;
}

.unit-badge {
    display: inline-block;
    padding: 3px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.badge-success { background: #10b981; color: #fff; }
.badge-warning { background: #ffb400; color: #17243d; }
.badge-info { background: #06b6d4; color: #fff; }
.badge-danger { background: #ef4444; color: #fff; }
.badge-default { background: #718096; color: #fff; }

.empty-table {
    text-align: center !important;
    padding: 28px 12px !important;
    color: #8390a3 !important;
    font-style: italic;
}

.alat-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 12px 16px;
    color: #718096;
    font-size: 12px;
}

.alat-pagination {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.alat-pagination button {
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

.alat-pagination button.active {
    border-color: #0d6efd;
    background: #0d6efd;
    color: #fff;
}

.alat-pagination button:disabled {
    cursor: not-allowed;
    opacity: .5;
}

/* Modal tambah */
.alat-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    box-sizing: border-box;
    background: rgba(23,45,78,.55);
}

.alat-modal.show {
    display: flex;
}

.alat-modal-box {
    width: min(720px, 100%);
    max-height: calc(100vh - 36px);
    overflow: auto;
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
}

.alat-modal-header {
    min-height: 52px;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    border-bottom: 1px solid #dfe5ed;
}

.alat-modal-header h3 {
    margin: 0 0 3px;
    color: #17243d;
    font-size: 14px;
}

.alat-modal-header p {
    margin: 0;
    color: #718096;
    font-size: 10px;
}

.alat-modal-close {
    border: 0;
    background: transparent;
    color: #7d8da4;
    font-size: 22px;
    cursor: pointer;
}

.alat-modal-body {
    padding: 16px;
}

.alat-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.alat-form-field.full {
    grid-column: 1 / -1;
}

.alat-form-field label {
    display: block;
    margin-bottom: 5px;
    color: #183052;
    font-size: 10px;
    font-weight: 500;
}

.alat-form-field input,
.alat-form-field select {
    width: 100%;
    height: 36px;
    box-sizing: border-box;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    background: #fff;
    color: #172d4e;
    padding: 8px 10px;
    outline: none;
    font-size: 12px;
}

.alat-form-field input:focus,
.alat-form-field select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13,110,253,.08);
}

.alat-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 7px;
    padding: 11px 16px;
    border-top: 1px solid #dfe5ed;
}

.alat-btn-primary,
.alat-btn-secondary {
    height: 34px;
    padding: 0 14px;
    border: 0;
    border-radius: 4px;
    font-size: 10px;
    cursor: pointer;
}

.alat-btn-primary {
    background: #0d6efd;
    color: #fff;
}

.alat-btn-secondary {
    background: #718096;
    color: #fff;
}

.alat-alert {
    padding: 10px 13px;
    margin: 0 0 16px;
    border-radius: 4px;
    font-size: 12px;
}

.alat-alert.success {
    background: #effcf5;
    border: 1px solid #bcebd2;
    color: #087443;
}

.alat-alert.error {
    background: #fff0f2;
    border: 1px solid #ffc8d0;
    color: #9b1c31;
}

@media (max-width: 800px) {
    .alat-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .alat-search,
    .alat-page-size {
        width: 100%;
        max-width: none;
    }

    .alat-table-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .alat-pagination {
        justify-content: flex-start;
    }

    .alat-form-grid {
        grid-template-columns: 1fr;
    }

    .alat-form-field.full {
        grid-column: auto;
    }

    .alat-modal {
        padding: 10px;
    }

    .alat-modal-box {
        max-height: calc(100vh - 20px);
    }
}
</style>

<section class="page-heading alat-heading">
    <div>
        <h1>Manajemen Alat Berat</h1>
        <p>Mengelola data inventori alat berat ASRINDO</p>
    </div>

    <button type="button" class="btn-primary" id="btnTambahAlat">
        <span>+</span> Tambah Alat Berat
    </button>
</section>

<?php if ($message): ?>
    <div class="alat-alert success"><?php echo h($message); ?></div>
<?php endif; ?>

<?php if ($error && !$openModal): ?>
    <div class="alat-alert error"><?php echo h($error); ?></div>
<?php endif; ?>

<section class="alat-stat-panel">
    <div class="panel-title">Statistik Alat Berat</div>

    <div class="alat-stat-grid">
        <div class="alat-stat-item">
            <strong><?php echo number_format($totalUnit, 0, ',', '.'); ?></strong>
            <span>Total Unit</span>
        </div>

        <div class="alat-stat-item">
            <strong class="text-green"><?php echo rupiah($totalNilaiInventori); ?></strong>
            <span>Total Nilai Inventori</span>
        </div>

        <div class="alat-stat-item">
            <strong class="text-blue">0 Jam</strong>
            <span>Rerata Jam Operasional</span>
        </div>

        <div class="alat-stat-item">
            <strong class="text-orange"><?php echo number_format($unitTersedia, 0, ',', '.'); ?></strong>
            <span>Unit Tersedia</span>
        </div>
    </div>
</section>

<section class="dashboard-panel alat-panel">

    <div class="panel-heading">
        <div>
            <h2>Daftar Unit Alat Berat</h2>
            <span class="panel-subtitle">
                <?php echo number_format($totalUnit, 0, ',', '.'); ?> unit
            </span>
        </div>
    </div>

    <div class="alat-toolbar">
        <div class="alat-search">
            <label for="globalSearch">Pencarian</label>
            <input
                type="search"
                id="globalSearch"
                placeholder="Cari kode, tipe, nomor rangka, lokasi..."
                autocomplete="off"
            >
        </div>

        <div class="alat-page-size">
            <label for="pageLength">Tampilkan</label>
            <select id="pageLength">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dashboard-table alat-table" id="alatBeratTable">
            <thead>
                <tr>
                    <th class="sortable" data-column="0">Kode</th>
                    <th class="sortable" data-column="1">Tipe</th>
                    <th class="sortable" data-column="2">Nomor Rangka</th>
                    <th class="sortable" data-column="3">HPP Unit</th>
                    <th class="sortable" data-column="4">Tahun Pembuatan</th>
                    <th class="sortable" data-column="5">Kondisi</th>
                    <th class="sortable" data-column="6">Status</th>
                    <th class="sortable" data-column="7">Lokasi</th>
                    <th class="col-action">Aksi</th>
                </tr>

                <tr class="alat-filter-row">
                    <th><input type="text" class="column-filter" data-filter-column="0" placeholder="Filter kode"></th>
                    <th><input type="text" class="column-filter" data-filter-column="1" placeholder="Filter tipe"></th>
                    <th><input type="text" class="column-filter" data-filter-column="2" placeholder="Filter nomor rangka"></th>
                    <th><input type="text" class="column-filter" data-filter-column="3" placeholder="Filter HPP"></th>
                    <th><input type="text" class="column-filter" data-filter-column="4" placeholder="Filter tahun"></th>
                    <th><input type="text" class="column-filter" data-filter-column="5" placeholder="Filter kondisi"></th>
                    <th>
                        <select class="column-filter" data-filter-column="6">
                            <option value="">Semua status</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo h($status); ?>"><?php echo h($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </th>
                    <th><input type="text" class="column-filter" data-filter-column="7" placeholder="Filter lokasi"></th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($units)): ?>
                <tr>
                    <td colspan="9" class="empty-table">Belum ada data alat berat.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($units as $unit): ?>
                    <tr data-id="<?php echo (int)$unit['id']; ?>">
                        <td>
                            <strong class="unit-code"><?php echo h($unit['kode']); ?></strong>
                        </td>
                        <td><?php echo h($unit['tipe']); ?></td>
                        <td><?php echo h($unit['nomor_rangka']); ?></td>
                        <td class="hpp-cell"><?php echo rupiah($hppByUnit[(int)$unit['id']] ?? 0); ?></td>
                        <td><?php echo h($unit['tahun_pembuatan']); ?></td>
                        <td><?php echo h($unit['kondisi']); ?></td>
                        <td>
                            <span class="unit-badge <?php echo h(statusClass($unit['status'])); ?>">
                                <?php echo h($unit['status']); ?>
                            </span>
                        </td>
                        <td><?php echo h($unit['lokasi']); ?></td>
                        <td class="col-action">
                            <a
                                href="alat_berat_detail.php?id=<?php echo (int)$unit['id']; ?>"
                                class="btn-detail"
                            >Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="alat-table-footer">
        <div id="paginationInfo">Menampilkan 0 unit</div>
        <div class="alat-pagination" id="pagination"></div>
    </div>

</section>

<!-- =========================================================
     MODAL TAMBAH ALAT BERAT
     ========================================================= -->
<div class="alat-modal" id="modalTambahAlat" aria-hidden="true">
    <div class="alat-modal-box">
        <div class="alat-modal-header">
            <div>
                <h3>Tambah Alat Berat</h3>
                <p>Masukkan data master unit alat berat.</p>
            </div>
            <button type="button" class="alat-modal-close" data-close-modal>&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="add_unit">

            <div class="alat-modal-body">
                <?php if ($openModal === 'modalTambahAlat' && $error): ?>
                    <div class="alat-alert error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="alat-form-grid">
                    <div class="alat-form-field">
                        <label for="kode">Kode *</label>
                        <input type="text" id="kode" name="kode" required value="<?php echo h($_POST['kode'] ?? ''); ?>" placeholder="Contoh: AGM 101">
                    </div>

                    <div class="alat-form-field">
                        <label for="tipe">Tipe *</label>
                        <input type="text" id="tipe" name="tipe" required value="<?php echo h($_POST['tipe'] ?? ''); ?>" placeholder="Contoh: Komatsu PC200">
                    </div>

                    <div class="alat-form-field">
                        <label for="nomor_rangka">Nomor Rangka *</label>
                        <input type="text" id="nomor_rangka" name="nomor_rangka" required value="<?php echo h($_POST['nomor_rangka'] ?? ''); ?>">
                    </div>

                    <div class="alat-form-field">
                        <label for="tahun_pembuatan">Tahun Pembuatan</label>
                        <input type="number" id="tahun_pembuatan" name="tahun_pembuatan" min="1900" max="2100" value="<?php echo h($_POST['tahun_pembuatan'] ?? ''); ?>" placeholder="Contoh: 2021">
                    </div>

                    <div class="alat-form-field">
                        <label for="kondisi">Kondisi *</label>
                        <select id="kondisi" name="kondisi" required>
                            <?php $selectedKondisi = (string)($_POST['kondisi'] ?? 'Baru'); ?>
                            <option value="Baru" <?php echo $selectedKondisi === 'Baru' ? 'selected' : ''; ?>>Baru</option>
                            <option value="Bekas" <?php echo $selectedKondisi === 'Bekas' ? 'selected' : ''; ?>>Bekas</option>
                        </select>
                    </div>

                    <div class="alat-form-field">
                        <label for="status">Status *</label>
                        <?php $selectedStatus = (string)($_POST['status'] ?? 'Tersedia'); ?>
                        <select id="status" name="status" required>
                            <?php foreach (['Tersedia', 'Siap Jual', 'Perbaikan', 'Disewakan', 'Terjual'] as $statusOption): ?>
                                <option value="<?php echo h($statusOption); ?>" <?php echo $selectedStatus === $statusOption ? 'selected' : ''; ?>>
                                    <?php echo h($statusOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alat-form-field full">
                        <label for="lokasi">Lokasi</label>
                        <input type="text" id="lokasi" name="lokasi" value="<?php echo h($_POST['lokasi'] ?? ''); ?>" placeholder="Contoh: Workshop AGMQ">
                    </div>
                </div>
            </div>

            <div class="alat-modal-footer">
                <button type="button" class="alat-btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="alat-btn-primary">Simpan Alat Berat</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var table = document.getElementById('alatBeratTable');
    var globalSearch = document.getElementById('globalSearch');
    var pageLength = document.getElementById('pageLength');
    var pagination = document.getElementById('pagination');
    var paginationInfo = document.getElementById('paginationInfo');
    var modal = document.getElementById('modalTambahAlat');
    var btnTambah = document.getElementById('btnTambahAlat');

    function openModal() {
        if (!modal) return;
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (btnTambah) {
        btnTambah.addEventListener('click', openModal);
    }

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && modal.classList.contains('show')) {
            closeModal();
        }
    });

    if (!table) return;

    var tbody = table.querySelector('tbody');
    var allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]'));
    var filterInputs = Array.prototype.slice.call(document.querySelectorAll('.column-filter'));

    var currentPage = 1;
    var currentSortColumn = -1;
    var currentSortDirection = 'asc';

    function normalize(value) {
        return String(value || '')
            .trim()
            .replace(/\s+/g, ' ')
            .toLowerCase();
    }

    function getCellText(row, column) {
        return row.children[column]
            ? normalize(row.children[column].innerText)
            : '';
    }

    function getFilters() {
        var filters = {};
        filterInputs.forEach(function (input) {
            filters[input.getAttribute('data-filter-column')] = normalize(input.value);
        });
        return filters;
    }

    function getFilteredRows() {
        var search = normalize(globalSearch.value);
        var filters = getFilters();

        return allRows.filter(function (row) {
            if (search && normalize(row.innerText).indexOf(search) === -1) {
                return false;
            }

            for (var column in filters) {
                if (!filters[column]) continue;

                var value = getCellText(row, parseInt(column, 10));

                if (column === '6') {
                    if (value !== filters[column]) return false;
                } else if (value.indexOf(filters[column]) === -1) {
                    return false;
                }
            }

            return true;
        });
    }

    function sortRows(rows) {
        if (currentSortColumn < 0) return rows;

        var column = currentSortColumn;
        var direction = currentSortDirection === 'asc' ? 1 : -1;

        return rows.sort(function (a, b) {
            var aText = getCellText(a, column);
            var bText = getCellText(b, column);

            if (column === 3) {
                var aHpp = parseFloat(aText.replace(/[^0-9,-]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
                var bHpp = parseFloat(bText.replace(/[^0-9,-]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
                return (aHpp - bHpp) * direction;
            }

            if (column === 4) {
                return ((parseInt(aText, 10) || 0) - (parseInt(bText, 10) || 0)) * direction;
            }

            return aText.localeCompare(bText, 'id', {
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
            tbody.appendChild(filteredRows[i]);
        }

        if (total === 0) {
            paginationInfo.innerText = 'Tidak ada data yang sesuai';
        } else {
            paginationInfo.innerText =
                'Menampilkan ' + (start + 1) + ' sampai ' + end + ' dari ' + total + ' unit';
        }

        renderPagination(totalPages);
    }

    if (globalSearch) {
        globalSearch.addEventListener('input', function () {
            currentPage = 1;
            render();
        });
    }

    if (pageLength) {
        pageLength.addEventListener('change', function () {
            currentPage = 1;
            render();
        });
    }

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

    <?php if ($openModal === 'modalTambahAlat'): ?>
    openModal();
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
