<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();
$pageTitle = 'Detail Alat Berat';
$adminBase = '../';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: alat_berat.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    if ($value === null || $value === '') {
        return '0';
    }
    return number_format((float)$value, 0, ',', '.');
}

function dateId($value): string
{
    if (!$value) return '-';
    $t = strtotime((string)$value);
    return $t ? date('d-M-Y', $t) : h($value);
}

function badgeClass($value): string
{
    $v = strtoupper(trim((string)$value));
    if (in_array($v, ['TERSEDIA', 'SIAP JUAL', 'READY'], true)) return 'badge-success';
    if (in_array($v, ['PERBAIKAN', 'REPAIR'], true)) return 'badge-warning';
    if (in_array($v, ['DISEWAKAN', 'DI SEWAKAN', 'RENTAL'], true)) return 'badge-info';
    if (in_array($v, ['TERJUAL', 'SOLD'], true)) return 'badge-danger';
    return 'badge-default';
}

/*
|--------------------------------------------------------------------------
| Ambil kolom tabel alat_berat
|--------------------------------------------------------------------------
| Cara ini membuat halaman tetap aman jika database Anda belum memiliki
| sebagian kolom tambahan harga/operasional. Kolom yang memang tersedia
| akan digunakan saat menyimpan edit.
|--------------------------------------------------------------------------
*/
$columns = [];
try {
    $columnRows = $pdo->query("SHOW COLUMNS FROM alat_berat")->fetchAll();
    foreach ($columnRows as $columnRow) {
        $columns[] = $columnRow['Field'];
    }
} catch (Throwable $e) {
    $columns = [
        'id', 'kode', 'tipe', 'nomor_rangka', 'tahun_pembuatan',
        'kondisi', 'status', 'lokasi'
    ];
}

$hasColumn = function (string $column) use ($columns): bool {
    return in_array($column, $columns, true);
};

/*
|--------------------------------------------------------------------------
| Proses UPDATE
|--------------------------------------------------------------------------
*/
$editError = '';
$editSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_alat_berat') {

    $postId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$postId || $postId !== (int)$id) {
        $editError = 'ID unit tidak valid.';
    } else {

        $data = [
            'kode'              => trim((string)($_POST['kode'] ?? '')),
            'tipe'              => trim((string)($_POST['tipe'] ?? '')),
            'nomor_rangka'      => trim((string)($_POST['nomor_rangka'] ?? '')),
            'tahun_pembuatan'   => trim((string)($_POST['tahun_pembuatan'] ?? '')),
            'kondisi'           => trim((string)($_POST['kondisi'] ?? '')),
            'status'            => trim((string)($_POST['status'] ?? '')),
            'lokasi'            => trim((string)($_POST['lokasi'] ?? '')),
            'deskripsi'         => trim((string)($_POST['deskripsi'] ?? '')),

            'kurs_beli'         => $_POST['kurs_beli'] ?? null,
            'kurs_hpp'         => $_POST['kurs_hpp'] ?? null,
            'harga_beli_idr'   => $_POST['harga_beli_idr'] ?? null,
            'hpp_idr'          => $_POST['hpp_idr'] ?? null,
            'harga_beli_usd'   => $_POST['harga_beli_usd'] ?? null,
            'harga_jual'       => $_POST['harga_jual'] ?? null,
            'harga_sewa_allin' => $_POST['harga_sewa_allin'] ?? null,
            'harga_sewa_kosongan' => $_POST['harga_sewa_kosongan'] ?? null,
            'jam_operasional_terakhir' => $_POST['jam_operasional_terakhir'] ?? null,
            'total_jam_operasional' => $_POST['total_jam_operasional'] ?? null,
        ];

        if ($data['kode'] === '' || $data['tipe'] === '' || $data['nomor_rangka'] === '') {
            $editError = 'Kode, tipe, dan nomor rangka wajib diisi.';
        } else {

            try {

                $set = [];
                $params = [];

                foreach ($data as $column => $value) {

                    if ($column === 'id' || !$hasColumn($column)) {
                        continue;
                    }

                    /*
                     * Nilai angka kosong disimpan sebagai NULL jika kolom
                     * tersebut memang tersedia.
                     */
                    if (is_string($value)) {
                        $value = trim($value);
                    }

                    if (
                        in_array($column, [
                            'kurs_beli',
                            'kurs_hpp',
                            'harga_beli_idr',
                            'hpp_idr',
                            'harga_beli_usd',
                            'harga_jual',
                            'harga_sewa_allin',
                            'harga_sewa_kosongan',
                            'jam_operasional_terakhir',
                            'total_jam_operasional'
                        ], true)
                    ) {
                        $value = ($value === '' || $value === null)
                            ? null
                            : str_replace('.', '', (string)$value);
                        $value = ($value === '' || $value === null)
                            ? null
                            : str_replace(',', '.', (string)$value);
                    }

                    $set[] = "`{$column}` = ?";
                    $params[] = $value;
                }

                if (empty($set)) {
                    throw new RuntimeException('Tidak ada kolom yang dapat diperbarui.');
                }

                if ($hasColumn('updated_at')) {
                    $set[] = "`updated_at` = NOW()";
                }

                $params[] = $id;

                $sql = "UPDATE alat_berat SET " .
                       implode(', ', $set) .
                       " WHERE id = ? LIMIT 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $editSuccess = 'Data alat berat berhasil diperbarui.';

            } catch (Throwable $e) {
                $editError = 'Gagal memperbarui data: ' . $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Data unit
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM alat_berat
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$unit = $stmt->fetch();

if (!$unit) {
    header('Location: alat_berat.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Nilai form.
| Jika kolom harga/operasional belum ada, nilai default 0/-
|--------------------------------------------------------------------------
*/
$form = [
    'kode' => $unit['kode'] ?? '',
    'tipe' => $unit['tipe'] ?? '',
    'nomor_rangka' => $unit['nomor_rangka'] ?? '',
    'tahun_pembuatan' => $unit['tahun_pembuatan'] ?? '',
    'kondisi' => $unit['kondisi'] ?? '',
    'status' => $unit['status'] ?? '',
    'lokasi' => $unit['lokasi'] ?? '',
    'deskripsi' => $unit['deskripsi'] ?? '',

    'kurs_beli' => $unit['kurs_beli'] ?? 0,
    'kurs_hpp' => $unit['kurs_hpp'] ?? 0,
    'harga_beli_idr' => $unit['harga_beli_idr'] ?? 0,
    'hpp_idr' => $unit['hpp_idr'] ?? 0,
    'harga_beli_usd' => $unit['harga_beli_usd'] ?? 0,
    'harga_jual' => $unit['harga_jual'] ?? 0,
    'harga_sewa_allin' => $unit['harga_sewa_allin'] ?? 0,
    'harga_sewa_kosongan' => $unit['harga_sewa_kosongan'] ?? 0,
    'jam_operasional_terakhir' => $unit['jam_operasional_terakhir'] ?? 0,
    'total_jam_operasional' => $unit['total_jam_operasional'] ?? 0,
];

if ($editSuccess !== '') {
    /*
     * Reload agar tampilan detail langsung menggunakan data terbaru.
     */
    $stmt = $pdo->prepare("SELECT * FROM alat_berat WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $unit = $stmt->fetch();

    foreach ($form as $key => $unused) {
        $form[$key] = $unit[$key] ?? $form[$key];
    }
}

/*
|--------------------------------------------------------------------------
| Relasi sparepart
|--------------------------------------------------------------------------
*/
$spareparts = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            abs.*,
            s.kode,
            s.nama
        FROM alat_berat_sparepart abs
        LEFT JOIN sparepart s ON s.id = abs.sparepart_id
        WHERE abs.alat_berat_id = ?
        ORDER BY abs.id ASC
    ");
    $stmt->execute([$id]);
    $spareparts = $stmt->fetchAll();
} catch (Throwable $e) {
    $spareparts = [];
}

/*
|--------------------------------------------------------------------------
| Jasa
|--------------------------------------------------------------------------
*/
$jasas = [];
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM alat_berat_jasa
        WHERE alat_berat_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$id]);
    $jasas = $stmt->fetchAll();
} catch (Throwable $e) {
    $jasas = [];
}

/*
|--------------------------------------------------------------------------
| Perawatan
|--------------------------------------------------------------------------
*/
$perawatan = [];
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM alat_berat_perawatan
        WHERE alat_berat_id = ?
        ORDER BY tanggal DESC, id DESC
    ");
    $stmt->execute([$id]);
    $perawatan = $stmt->fetchAll();
} catch (Throwable $e) {
    $perawatan = [];
}

/*
|--------------------------------------------------------------------------
| Pembelian sparepart yang terkait unit
|--------------------------------------------------------------------------
*/
$pembelian = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            p.id,
            p.no_pembelian,
            p.tanggal,
            p.kurs,
            d.harga_idr AS hrg_idr,
            d.harga_usd AS hrg_usd,
            d.hpp_idr
        FROM pembelian_sparepart p
        INNER JOIN pembelian_sparepart_detail d
            ON d.pembelian_id = p.id
        INNER JOIN alat_berat_sparepart abs
            ON abs.sparepart_id = d.sparepart_id
        WHERE abs.alat_berat_id = ?
        ORDER BY p.tanggal DESC, p.id DESC
    ");
    $stmt->execute([$id]);
    $pembelian = $stmt->fetchAll();
} catch (Throwable $e) {
    $pembelian = [];
}

$totalSparepart = 0;
foreach ($spareparts as $item) {
    $qty = (float)($item['qty'] ?? 0);
    $harga = (float)($item['harga'] ?? 0);
    $total = isset($item['total']) && $item['total'] !== null
        ? (float)$item['total']
        : $qty * $harga;
    $totalSparepart += $total;
}

$totalJasa = 0;
foreach ($jasas as $jasa) {
    $totalJasa += (float)(
        $jasa['total']
        ?? $jasa['biaya']
        ?? $jasa['harga']
        ?? 0
    );
}

$hargaBeliDetail = $totalSparepart + $totalJasa;
$hargaBeliForm = (float)($form['harga_beli_idr'] ?? 0);
$hargaBeli = $hargaBeliForm > 0 ? $hargaBeliForm : $hargaBeliDetail;

require __DIR__ . '/../includes/header.php';
?>

<style>
.detail-page {
    padding-bottom: 30px;
}

.detail-breadcrumb {
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:4px;
    font-size:12px;
    color:#0d6efd;
    font-weight:600;
    text-transform:uppercase;
}

.detail-breadcrumb .separator {
    color:#8a98aa;
}

.detail-title-row {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:15px;
    margin-bottom:24px;
}

.detail-title-row h1 {
    margin:0 0 4px;
    font-size:21px;
    color:#17243d;
}

.detail-title-row p {
    margin:0;
    color:#8290a3;
    font-size:12px;
}

.detail-card {
    background:#fff;
    border:1px solid #dfe5ed;
    border-radius:4px;
    margin-bottom:20px;
    overflow:hidden;
}

.detail-card-header {
    min-height:56px;
    padding:0 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    border-bottom:1px solid #dfe5ed;
    color:#13294b;
    font-size:14px;
    font-weight:600;
}

.detail-card-body {
    padding:20px;
}

.detail-actions {
    display:flex;
    gap:6px;
}

.detail-btn {
    height:30px;
    min-width:43px;
    padding:0 12px;
    border:1px solid #0dcaf0;
    border-radius:4px;
    background:#fff;
    color:#0aa4c5;
    text-decoration:none;
    font-size:12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
}

.detail-btn-danger {
    border-color:#dc3545;
    color:#dc3545;
}

.detail-btn-back {
    border-color:#7185a5;
    color:#637a9d;
}

.detail-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    column-gap:80px;
    row-gap:12px;
}

.detail-field {
    display:grid;
    grid-template-columns:135px 1fr;
    align-items:center;
    min-height:18px;
    font-size:13px;
}

.detail-field .label {
    color:#13294b;
}

.detail-field .value {
    color:#13294b;
    font-weight:500;
}

.detail-field .value.rangka {
    color:#ff2f68;
}

.price-header-label {
    width:100%;
    margin-bottom:10px;
    color:#71839d;
    font-size:12px;
}

.price-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    text-align:center;
}

.price-item {
    min-height:65px;
    border-right:1px solid #dfe5ed;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.price-item:last-child {
    border-right:0;
}

.price-item .label {
    color:#71839d;
    font-size:11px;
    margin-bottom:5px;
}

.price-item .value {
    color:#102b55;
    font-size:15px;
    font-weight:600;
}

.price-item .value.green,
.price-sale .value {
    color:#00ae58;
}

.price-sale {
    border-top:1px solid #dfe5ed;
    margin-top:12px;
    padding-top:15px;
    text-align:center;
}

.price-sale .label {
    color:#71839d;
    font-size:11px;
    margin-bottom:5px;
}

.price-sale .value {
    font-size:15px;
    font-weight:600;
}

.detail-table-wrap {
    overflow-x:auto;
}

.detail-table {
    width:100%;
    border-collapse:collapse;
    min-width:700px;
}

.detail-table th {
    padding:10px;
    background:#f8fafc;
    border-bottom:1px solid #dfe5ed;
    color:#152d50;
    text-align:left;
    font-size:12px;
    font-weight:600;
    white-space:nowrap;
}

.detail-table td {
    padding:10px;
    border-bottom:1px solid #e2e7ee;
    color:#183052;
    font-size:12px;
    white-space:nowrap;
}

.detail-table td.number,
.detail-table th.number {
    text-align:right;
}

.detail-table td.center,
.detail-table th.center {
    text-align:center;
}

.detail-empty {
    text-align:center;
    padding:24px!important;
    color:#8390a3!important;
    font-style:italic;
}

.detail-two-column {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}

.rental-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    text-align:center;
}

.rental-item {
    padding:4px 20px;
    border-right:1px solid #dfe5ed;
}

.rental-item:last-child {
    border-right:0;
}

.rental-item .label {
    color:#71839d;
    font-size:11px;
    margin-bottom:6px;
}

.rental-item .value {
    color:#006dff;
    font-size:18px;
    font-weight:600;
}

.rental-item .note {
    color:#71839d;
    font-size:10px;
    margin-top:5px;
}

.maintenance-action {
    border:1px solid #0d6efd;
    color:#0d6efd;
    background:#fff;
    border-radius:4px;
    padding:7px 11px;
    text-decoration:none;
    font-size:12px;
}

.image-section {
    display:grid;
    grid-template-columns:1fr 2fr;
    gap:10px;
}

.upload-box {
    padding:5px 10px 15px;
}

.upload-box strong,
.image-list-title {
    display:block;
    text-align:center;
    color:#13294b;
    font-size:12px;
    margin-bottom:18px;
}

.upload-help {
    color:#13294b;
    font-size:12px;
    margin-bottom:8px;
}

.upload-row {
    display:flex;
    height:36px;
}

.upload-row input[type="file"] {
    min-width:0;
    flex:1;
    border:1px solid #cbd5e1;
    border-radius:4px 0 0 4px;
    font-size:11px;
    padding:6px;
}

.upload-row button {
    width:68px;
    border:0;
    background:#0d6efd;
    color:#fff;
    border-radius:0 4px 4px 0;
    cursor:pointer;
    font-size:11px;
}

/* MODAL EDIT */
.edit-modal {
    position:fixed;
    inset:0;
    z-index:9999;
    background:rgba(15,23,42,.55);
    display:none;
    align-items:center;
    justify-content:center;
    padding:14px;
}

.edit-modal.show {
    display:flex;
}

.edit-modal-dialog {
    width:min(940px,100%);
    max-height:94vh;
    overflow:auto;
    background:#fff;
    border-radius:5px;
    box-shadow:0 18px 55px rgba(0,0,0,.22);
}

.edit-modal-header {
    height:48px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 14px;
    border-bottom:1px solid #dfe5ed;
}

.edit-modal-header strong {
    color:#17243d;
    font-size:13px;
}

.edit-close {
    border:0;
    background:transparent;
    color:#8b98a9;
    font-size:22px;
    cursor:pointer;
    line-height:1;
}

.edit-modal-body {
    padding:10px;
}

.edit-section-title {
    position:relative;
    text-align:center;
    margin:0 10px 14px;
    padding:0 0 7px;
    color:#1a2c4a;
    font-size:10px;
    font-weight:700;
    text-transform:uppercase;
}

.edit-section-title:after {
    content:"";
    position:absolute;
    left:0;
    right:0;
    bottom:0;
    height:1px;
    background:#dfe5ed;
}

.edit-grid-3 {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:10px 8px;
    margin:0 10px 16px;
}

.edit-grid-2 {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:10px 8px;
    margin:0 10px 16px;
}

.edit-field {
    min-width:0;
}

.edit-field label {
    display:block;
    margin-bottom:5px;
    color:#183052;
    font-size:10px;
}

.edit-field label .required {
    color:#dc3545;
}

.edit-field input,
.edit-field select,
.edit-field textarea {
    width:100%;
    height:35px;
    box-sizing:border-box;
    border:1px solid #bdcbe0;
    border-radius:4px;
    background:#fff;
    color:#172d4e;
    font-size:11px;
    padding:7px 9px;
    outline:none;
}

.edit-field textarea {
    height:70px;
    resize:vertical;
}

.edit-field input:focus,
.edit-field select:focus,
.edit-field textarea:focus {
    border-color:#0d6efd;
    box-shadow:0 0 0 2px rgba(13,110,253,.08);
}

.input-group {
    display:flex;
}

.input-group input {
    border-radius:4px 0 0 4px;
}

.input-prefix {
    height:35px;
    min-width:36px;
    display:flex;
    align-items:center;
    justify-content:center;
    box-sizing:border-box;
    border:1px solid #bdcbe0;
    border-right:0;
    background:#f5f7fa;
    color:#52657f;
    font-size:10px;
    border-radius:4px 0 0 4px;
}

.input-prefix + input {
    border-radius:0 4px 4px 0;
}

.edit-alert {
    margin:0 10px 12px;
    padding:9px 11px;
    border-radius:4px;
    font-size:11px;
}

.edit-alert.error {
    color:#9b1c31;
    background:#fff0f2;
    border:1px solid #ffc8d0;
}

.edit-alert.success {
    color:#087443;
    background:#effcf5;
    border:1px solid #bcebd2;
}

.edit-modal-footer {
    display:flex;
    justify-content:flex-end;
    gap:7px;
    padding:12px 14px;
    border-top:1px solid #dfe5ed;
}

.btn-update {
    height:35px;
    padding:0 15px;
    border:0;
    border-radius:4px;
    background:#0d6efd;
    color:#fff;
    font-size:11px;
    cursor:pointer;
}

.btn-cancel {
    height:35px;
    padding:0 15px;
    border:0;
    border-radius:4px;
    background:#718096;
    color:#fff;
    font-size:11px;
    cursor:pointer;
}

@media(max-width:800px) {
    .detail-grid,
    .detail-two-column,
    .image-section,
    .edit-grid-3,
    .edit-grid-2 {
        grid-template-columns:1fr;
    }

    .detail-title-row {
        flex-direction:column;
    }

    .price-grid {
        grid-template-columns:1fr;
    }

    .price-item {
        border-right:0;
        border-bottom:1px solid #dfe5ed;
        padding:10px 0;
    }

    .price-item:last-child {
        border-bottom:0;
    }
}

@media(max-width:500px) {
    .detail-field {
        grid-template-columns:120px 1fr;
        font-size:12px;
    }

    .detail-card-body {
        padding:14px;
    }

    .detail-card-header {
        padding:0 14px;
    }

    .edit-modal {
        padding:5px;
    }

    .edit-modal-dialog {
        max-height:98vh;
    }
}
</style>

<div class="detail-page">

    <div class="detail-breadcrumb">
        <span>Alat Berat</span>
        <span class="separator">/</span>
        <span>Detail</span>
    </div>

    <div class="detail-title-row">
        <div>
            <h1>Detail Alat Berat</h1>
            <p>Informasi lengkap tentang unit alat berat</p>
        </div>

        <a href="alat_berat.php" class="detail-btn detail-btn-back">
            ← Back
        </a>
    </div>

    <?php if ($editSuccess !== ''): ?>
        <div class="edit-alert success" style="margin:0 0 18px;">
            <?php echo h($editSuccess); ?>
        </div>
    <?php endif; ?>

    <!-- DATA UMUM -->
    <section class="detail-card">
        <div class="detail-card-header">
            <span>Data Umum</span>

            <div class="detail-actions">
                <button type="button" class="detail-btn" id="openEditModal">
                    Edit
                </button>

                <a
                    href="alat_berat_hapus.php?id=<?php echo (int)$unit['id']; ?>"
                    class="detail-btn detail-btn-danger"
                    onclick="return confirm('Hapus unit ini?');"
                    title="Hapus"
                >
                    🗑
                </a>
            </div>
        </div>

        <div class="detail-card-body">
            <div class="detail-grid">

                <div class="detail-field">
                    <span class="label">Kode</span>
                    <span class="value"><?php echo h($unit['kode']); ?></span>
                </div>

                <div class="detail-field">
                    <span class="label">Tahun Pembuatan</span>
                    <span class="value"><?php echo h($unit['tahun_pembuatan']); ?></span>
                </div>

                <div class="detail-field">
                    <span class="label">Tipe</span>
                    <span class="value"><?php echo h($unit['tipe']); ?></span>
                </div>

                <div class="detail-field">
                    <span class="label">Kondisi Unit</span>
                    <span class="value">
                        <span class="unit-badge badge-default">
                            <?php echo h($unit['kondisi']); ?>
                        </span>
                    </span>
                </div>

                <div class="detail-field">
                    <span class="label">Nomor Rangka</span>
                    <span class="value rangka"><?php echo h($unit['nomor_rangka']); ?></span>
                </div>

                <div class="detail-field">
                    <span class="label">Status Unit</span>
                    <span class="value">
                        <span class="unit-badge <?php echo badgeClass($unit['status']); ?>">
                            <?php echo h($unit['status']); ?>
                        </span>
                    </span>
                </div>

                <div class="detail-field"></div>

                <div class="detail-field">
                    <span class="label">Lokasi</span>
                    <span class="value"><?php echo h($unit['lokasi']); ?></span>
                </div>

            </div>
        </div>
    </section>

    <!-- INFORMASI HARGA -->
    <section class="detail-card">
        <div class="detail-card-header">Informasi Harga</div>

        <div class="detail-card-body">
            <div class="price-header-label">Harga Beli</div>

            <div class="price-grid">
                <div class="price-item">
                    <span class="label">IDR</span>
                    <span class="value"><?php echo rupiah($hargaBeli); ?></span>
                </div>

                <div class="price-item">
                    <span class="label">Kurs (IDR/USD)</span>
                    <span class="value">
                        <?php echo rupiah($form['kurs_beli']); ?>
                    </span>
                </div>

                <div class="price-item">
                    <span class="label">USD</span>
                    <span class="value">
                        <?php echo rupiah($form['harga_beli_usd']); ?>
                    </span>
                </div>
            </div>

            <div class="price-sale">
                <div class="label">Harga Jual</div>
                <div class="value">
                    <?php echo rupiah($form['harga_jual']); ?>
                </div>
            </div>
        </div>
    </section>

    <!-- RIWAYAT PEMBELIAN -->
    <section class="detail-card">
        <div class="detail-card-header">Riwayat Pembelian</div>

        <div class="detail-table-wrap">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th class="center">No</th>
                        <th>Tanggal</th>
                        <th>No Pembelian</th>
                        <th class="number">Kurs</th>
                        <th class="number">Hrg IDR</th>
                        <th class="number">Hrg USD</th>
                        <th class="number">HPP IDR</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pembelian)): ?>
                    <tr>
                        <td colspan="7" class="detail-empty">
                            Belum ada riwayat pembelian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pembelian as $no => $item): ?>
                        <tr>
                            <td class="center"><?php echo $no + 1; ?></td>
                            <td><?php echo dateId($item['tanggal'] ?? null); ?></td>
                            <td style="color:#006dff;">
                                <?php echo h($item['no_pembelian'] ?? '-'); ?>
                            </td>
                            <td class="number"><?php echo rupiah($item['kurs'] ?? 0); ?></td>
                            <td class="number"><?php echo rupiah($item['hrg_idr'] ?? 0); ?></td>
                            <td class="number"><?php echo rupiah($item['hrg_usd'] ?? 0); ?></td>
                            <td class="number"><?php echo rupiah($item['hpp_idr'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- HARGA SEWA + OPERASIONAL -->
    <div class="detail-two-column">

        <section class="detail-card">
            <div class="detail-card-header">Harga Sewa</div>

            <div class="detail-card-body">
                <div class="rental-grid">

                    <div class="rental-item">
                        <div class="label">Per Jam - All In</div>
                        <div class="value">
                            <?php echo (float)$form['harga_sewa_allin'] > 0
                                ? rupiah($form['harga_sewa_allin'])
                                : '-'; ?>
                        </div>
                        <div class="note">Termasuk operator &amp; BBM</div>
                    </div>

                    <div class="rental-item">
                        <div class="label">Per Jam - Kosongan</div>
                        <div class="value">
                            <?php echo (float)$form['harga_sewa_kosongan'] > 0
                                ? rupiah($form['harga_sewa_kosongan'])
                                : '-'; ?>
                        </div>
                        <div class="note">Unit saja (tanpa operator)</div>
                    </div>

                </div>
            </div>
        </section>

        <section class="detail-card">
            <div class="detail-card-header">Jam Operasional</div>

            <div class="detail-card-body">
                <div class="rental-grid">

                    <div class="rental-item">
                        <div class="label">Jam Operasional Terakhir</div>
                        <div class="value" style="color:#102b55;">
                            <?php echo rupiah($form['jam_operasional_terakhir']); ?> Jam
                        </div>
                    </div>

                    <div class="rental-item">
                        <div class="label">Total Jam Operasional</div>
                        <div class="value">
                            <?php echo rupiah($form['total_jam_operasional']); ?> Jam
                        </div>
                    </div>

                </div>
            </div>
        </section>

    </div>

    <!-- SPAREPART -->
    <section class="detail-card">
        <div class="detail-card-header">
            <span>Sparepart Pembentuk Unit</span>
        </div>

        <div class="detail-table-wrap">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Nama Sparepart</th>
                        <th class="number">Qty</th>
                        <th class="number">Harga</th>
                        <th class="number">Total</th>
                    </tr>
                </thead>
                <tbody>

                <?php if (empty($spareparts)): ?>
                    <tr>
                        <td colspan="6" class="detail-empty">
                            Belum ada sparepart pembentuk unit.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($spareparts as $no => $item): ?>
                        <?php
                        $qty = (float)($item['qty'] ?? 0);
                        $harga = (float)($item['harga'] ?? 0);
                        $total = isset($item['total'])
                            ? (float)$item['total']
                            : $qty * $harga;
                        ?>
                        <tr>
                            <td><?php echo $no + 1; ?></td>
                            <td><?php echo h($item['kode'] ?? '-'); ?></td>
                            <td><?php echo h($item['nama'] ?? '-'); ?></td>
                            <td class="number"><?php echo rupiah($qty); ?></td>
                            <td class="number"><?php echo rupiah($harga); ?></td>
                            <td class="number"><?php echo rupiah($total); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <tr>
                        <td colspan="5" class="number"><strong>Total Sparepart</strong></td>
                        <td class="number"><strong><?php echo rupiah($totalSparepart); ?></strong></td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>

    <!-- JASA -->
    <section class="detail-card">
        <div class="detail-card-header">Jasa Pembentuk Unit</div>

        <div class="detail-table-wrap">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Jasa</th>
                        <th class="number">Biaya</th>
                    </tr>
                </thead>
                <tbody>

                <?php if (empty($jasas)): ?>
                    <tr>
                        <td colspan="3" class="detail-empty">Belum ada data jasa.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($jasas as $no => $jasa): ?>
                        <?php
                        $namaJasa = $jasa['nama_jasa']
                            ?? $jasa['jasa']
                            ?? $jasa['nama']
                            ?? 'Jasa';

                        $biaya = $jasa['total']
                            ?? $jasa['biaya']
                            ?? $jasa['harga']
                            ?? 0;
                        ?>
                        <tr>
                            <td><?php echo $no + 1; ?></td>
                            <td><?php echo h($namaJasa); ?></td>
                            <td class="number"><?php echo rupiah($biaya); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <tr>
                        <td colspan="2" class="number"><strong>Total Jasa</strong></td>
                        <td class="number"><strong><?php echo rupiah($totalJasa); ?></strong></td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>

    <!-- PERAWATAN -->
    <section class="detail-card">
        <div class="detail-card-header">
            <span>Riwayat Perawatan</span>

            <a
                href="alat_berat_perawatan_tambah.php?alat_berat_id=<?php echo (int)$unit['id']; ?>"
                class="maintenance-action"
            >
                + Perawatan Baru
            </a>
        </div>

        <div class="detail-table-wrap">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="number">Finishing</th>
                        <th class="number">Suku Cadang</th>
                        <th class="number">Total</th>
                        <th class="center">Aksi</th>
                    </tr>
                </thead>
                <tbody>

                <?php if (empty($perawatan)): ?>
                    <tr>
                        <td colspan="5" class="detail-empty">
                            Belum ada data perawatan!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($perawatan as $item): ?>
                        <?php
                        $finishing = (float)($item['finishing'] ?? 0);
                        $sukuCadang = (float)($item['suku_cadang'] ?? ($item['sparepart'] ?? 0));
                        $total = isset($item['total'])
                            ? (float)$item['total']
                            : $finishing + $sukuCadang;
                        ?>
                        <tr>
                            <td><?php echo dateId($item['tanggal'] ?? null); ?></td>
                            <td class="number"><?php echo rupiah($finishing); ?></td>
                            <td class="number"><?php echo rupiah($sukuCadang); ?></td>
                            <td class="number"><?php echo rupiah($total); ?></td>
                            <td class="center">
                                <a
                                    href="alat_berat_perawatan_detail.php?id=<?php echo (int)$item['id']; ?>"
                                    class="detail-btn"
                                >
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>

    <!-- IMAGE -->
    <section class="detail-card">
        <div class="detail-card-header">Upload Image</div>

        <div class="detail-card-body">
            <div class="image-section">

                <div class="upload-box">
                    <strong>Upload Image</strong>

                    <div class="upload-help">Image: JPG/PNG, Max: 3mb</div>

                    <form
                        action="alat_berat_image_upload.php"
                        method="post"
                        enctype="multipart/form-data"
                    >
                        <input
                            type="hidden"
                            name="alat_berat_id"
                            value="<?php echo (int)$unit['id']; ?>"
                        >

                        <div class="upload-row">
                            <input
                                type="file"
                                name="image"
                                accept=".jpg,.jpeg,.png"
                            >
                            <button type="submit">Upload</button>
                        </div>
                    </form>
                </div>

                <div>
                    <strong class="image-list-title">Daftar Image</strong>

                    <div class="image-list">
                        <div class="image-list-head">Link</div>
                        <div class="detail-empty" style="border:1px solid #dfe5ed;">
                            No data available in table
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

</div>

<!-- =========================================================
     MODAL EDIT - TETAP DI HALAMAN YANG SAMA
     ========================================================= -->
<div
    class="edit-modal <?php echo $editError !== '' ? 'show' : ''; ?>"
    id="editModal"
    aria-hidden="<?php echo $editError !== '' ? 'false' : 'true'; ?>"
>
    <div class="edit-modal-dialog">

        <div class="edit-modal-header">
            <strong>Edit Alat Berat</strong>

            <button type="button" class="edit-close" id="closeEditModal">
                ×
            </button>
        </div>

        <form method="post" action="alat_berat_detail.php?id=<?php echo (int)$id; ?>">

            <input type="hidden" name="action" value="update_alat_berat">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

            <div class="edit-modal-body">

                <?php if ($editError !== ''): ?>
                    <div class="edit-alert error">
                        <?php echo h($editError); ?>
                    </div>
                <?php endif; ?>

                <div class="edit-section-title">Data Umum</div>

                <div class="edit-grid-3">

                    <div class="edit-field">
                        <label>Kode Alat Berat <span class="required">*</span></label>
                        <input
                            type="text"
                            name="kode"
                            value="<?php echo h($form['kode']); ?>"
                            required
                        >
                    </div>

                    <div class="edit-field">
                        <label>Tipe Alat Berat <span class="required">*</span></label>
                        <input
                            type="text"
                            name="tipe"
                            value="<?php echo h($form['tipe']); ?>"
                            required
                        >
                    </div>

                    <div class="edit-field">
                        <label>Nomor Rangka <span class="required">*</span></label>
                        <input
                            type="text"
                            name="nomor_rangka"
                            value="<?php echo h($form['nomor_rangka']); ?>"
                            required
                        >
                    </div>

                    <div class="edit-field">
                        <label>Tahun Pembuatan <span class="required">*</span></label>
                        <input
                            type="number"
                            name="tahun_pembuatan"
                            value="<?php echo h($form['tahun_pembuatan']); ?>"
                            min="1900"
                            max="2100"
                            required
                        >
                    </div>

                    <div class="edit-field">
                        <label>Kondisi</label>
                        <select name="kondisi">
                            <option value="Bekas" <?php echo $form['kondisi'] === 'Bekas' ? 'selected' : ''; ?>>Bekas</option>
                            <option value="Baru" <?php echo $form['kondisi'] === 'Baru' ? 'selected' : ''; ?>>Baru</option>
                        </select>
                    </div>

                    <div class="edit-field">
                        <label>Status</label>
                        <select name="status">
                            <?php
                            $statusOptions = [
                                'Tersedia',
                                'Siap Jual',
                                'Perbaikan',
                                'Disewakan',
                                'Terjual'
                            ];
                            ?>
                            <?php foreach ($statusOptions as $status): ?>
                                <option
                                    value="<?php echo h($status); ?>"
                                    <?php echo strcasecmp((string)$form['status'], $status) === 0 ? 'selected' : ''; ?>
                                >
                                    <?php echo h($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="edit-field" style="grid-column:1 / -1;">
                        <label>Lokasi</label>
                        <input
                            type="text"
                            name="lokasi"
                            value="<?php echo h($form['lokasi']); ?>"
                        >
                    </div>

                </div>

                <div class="edit-section-title">Riwayat Pembelian (Terakhir)</div>

                <?php
                $lastPurchase = $pembelian[0] ?? null;
                ?>

                <div style="text-align:center;margin:0 10px 14px;color:#1b3154;font-size:10px;">
                    <?php if ($lastPurchase): ?>
                        <strong><?php echo h($lastPurchase['no_pembelian']); ?></strong><br>
                        <em><?php echo dateId($lastPurchase['tanggal'] ?? null); ?></em>
                    <?php else: ?>
                        Belum ada transaksi pembelian.
                    <?php endif; ?>
                </div>

                <div class="edit-grid-2">

                    <div class="edit-field">
                        <label>Kurs Beli</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input
                                type="text"
                                name="kurs_beli"
                                value="<?php echo h(rupiah($form['kurs_beli'])); ?>"
                            >
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>Kurs HPP</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input
                                type="text"
                                name="kurs_hpp"
                                value="<?php echo h(rupiah($form['kurs_hpp'])); ?>"
                            >
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>Harga Beli (IDR)</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input
                                type="text"
                                name="harga_beli_idr"
                                value="<?php echo h(rupiah($form['harga_beli_idr'])); ?>"
                            >
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>HPP (IDR)</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input
                                type="text"
                                name="hpp_idr"
                                value="<?php echo h(rupiah($form['hpp_idr'])); ?>"
                            >
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>Harga Beli (USD)</label>
                        <div class="input-group">
                            <span class="input-prefix">USD</span>
                            <input
                                type="text"
                                name="harga_beli_usd"
                                value="<?php echo h(rupiah($form['harga_beli_usd'])); ?>"
                            >
                        </div>
                    </div>

                </div>

                <div class="edit-section-title">Data Harga</div>

                <div class="edit-grid-2">

                    <div class="edit-field">
                        <label>Kurs Beli</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input
                                type="text"
                                value="<?php echo h(rupiah($form['kurs_beli'])); ?>"
                                readonly
                            >
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>Harga Jual</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input
                                type="text"
                                name="harga_jual"
                                value="<?php echo h(rupiah($form['harga_jual'])); ?>"
                            >
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>Harga Beli (IDR)</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input
                                type="text"
                                value="<?php echo h(rupiah($form['harga_beli_idr'])); ?>"
                                readonly
                            >
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>Harga Beli (USD)</label>
                        <div class="input-group">
                            <span class="input-prefix">USD</span>
                            <input
                                type="text"
                                value="<?php echo h(rupiah($form['harga_beli_usd'])); ?>"
                                readonly
                            >
                        </div>
                    </div>

                </div>

                <div class="edit-section-title">Harga Sewa &amp; Data Operasional</div>

                <div class="edit-grid-2">

                    <div class="edit-field">
                        <label>Per Jam - All In</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input
                                type="text"
                                name="harga_sewa_allin"
                                value="<?php echo h(rupiah($form['harga_sewa_allin'])); ?>"
                            >
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>Jam Operasional Terakhir</label>
                        <div class="input-group">
                            <input
                                type="text"
                                name="jam_operasional_terakhir"
                                value="<?php echo h(rupiah($form['jam_operasional_terakhir'])); ?>"
                            >
                            <span class="input-prefix" style="border-left:0;border-right:1px solid #bdcbe0;border-radius:0 4px 4px 0;">
                                Jam
                            </span>
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>Per Jam - Kosongan</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input
                                type="text"
                                name="harga_sewa_kosongan"
                                value="<?php echo h(rupiah($form['harga_sewa_kosongan'])); ?>"
                            >
                        </div>
                    </div>

                    <div class="edit-field">
                        <label>Total Jam Operasional</label>
                        <div class="input-group">
                            <input
                                type="text"
                                name="total_jam_operasional"
                                value="<?php echo h(rupiah($form['total_jam_operasional'])); ?>"
                            >
                            <span class="input-prefix" style="border-left:0;border-right:1px solid #bdcbe0;border-radius:0 4px 4px 0;">
                                Jam
                            </span>
                        </div>
                    </div>

                </div>

                <div class="edit-grid-2">

                    <div class="edit-field" style="grid-column:1 / -1;">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi"><?php echo h($form['deskripsi']); ?></textarea>
                    </div>

                </div>

            </div>

            <div class="edit-modal-footer">
                <button type="button" class="btn-cancel" id="cancelEditModal">
                    Batal
                </button>

                <button type="submit" class="btn-update">
                    Update Data
                </button>
            </div>

        </form>

    </div>
</div>

<script>
(function () {

    var modal = document.getElementById('editModal');
    var openButton = document.getElementById('openEditModal');
    var closeButton = document.getElementById('closeEditModal');
    var cancelButton = document.getElementById('cancelEditModal');

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

    if (openButton) {
        openButton.addEventListener('click', openModal);
    }

    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', closeModal);
    }

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

})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
