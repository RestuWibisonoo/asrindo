<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Penjualan';
$adminBase = '../';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)($value ?? 0), 0, ',', '.');
}

function redirectMessage(string $type, string $message): void
{
    header('Location: penjualan.php?' . http_build_query([
        'msg_type' => $type,
        'msg' => $message
    ]));
    exit;
}

function getUnitHpp(PDO $pdo, int $alatBeratId): float
{
    $sql = "
        SELECT
            COALESCE((
                SELECT
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
                FROM pembelian_alat_berat_detail d
                INNER JOIN pembelian_alat_berat p
                    ON p.id = d.pembelian_id
                WHERE d.alat_berat_id = ?
                  AND UPPER(COALESCE(p.status, '')) <> 'BATAL'
                ORDER BY p.tanggal DESC, p.id DESC
                LIMIT 1
            ), 0)
            +
            COALESCE((
                SELECT SUM(qty * harga)
                FROM alat_berat_sparepart
                WHERE alat_berat_id = ?
            ), 0)
            +
            COALESCE((
                SELECT SUM(qty * biaya)
                FROM alat_berat_jasa
                WHERE alat_berat_id = ?
            ), 0)
            +
            COALESCE((
                SELECT SUM(finishing + suku_cadang + jasa)
                FROM alat_berat_perawatan
                WHERE alat_berat_id = ?
            ), 0)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$alatBeratId, $alatBeratId, $alatBeratId, $alatBeratId]);

    return (float)$stmt->fetchColumn();
}

function refreshSaleTotal(PDO $pdo, int $saleId): void
{
    $stmt = $pdo->prepare("
        UPDATE penjualan p
        SET p.total = (
            SELECT COALESCE(SUM(d.subtotal), 0)
            FROM penjualan_detail d
            WHERE d.penjualan_id = p.id
        )
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$saleId]);
}

/*
|--------------------------------------------------------------------------
| CRUD PENJUALAN
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $nomor = trim((string)($_POST['nomor_penjualan'] ?? ''));
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $customerId = filter_var(
                $_POST['customer_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $status = strtoupper(trim((string)($_POST['status'] ?? 'DRAFT')));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            $alatBeratId = filter_var(
                $_POST['alat_berat_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $hargaJual = (float)($_POST['harga_jual'] ?? 0);
            $diskon = (float)($_POST['diskon'] ?? 0);
            $detailKeterangan = trim((string)($_POST['detail_keterangan'] ?? ''));

            if ($nomor === '' || $tanggal === '' || !$customerId || !$alatBeratId) {
                redirectMessage(
                    'error',
                    'Nomor penjualan, tanggal, customer, dan unit alat berat wajib diisi.'
                );
            }

            if ($hargaJual < 0 || $diskon < 0) {
                redirectMessage('error', 'Harga jual dan diskon tidak boleh negatif.');
            }

            if ($diskon > $hargaJual) {
                redirectMessage('error', 'Diskon tidak boleh lebih besar dari harga jual.');
            }

            $allowedStatus = ['DRAFT', 'CONFIRMED', 'PAID', 'BATAL'];
            if (!in_array($status, $allowedStatus, true)) {
                $status = 'DRAFT';
            }

            $stmt = $pdo->prepare("SELECT id FROM customer WHERE id = ? LIMIT 1");
            $stmt->execute([$customerId]);
            if (!$stmt->fetch()) {
                redirectMessage('error', 'Customer tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT id, kode, tipe, status
                FROM alat_berat
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$alatBeratId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                redirectMessage('error', 'Unit alat berat tidak ditemukan.');
            }

            if (strtoupper((string)$unit['status']) === 'TERJUAL') {
                redirectMessage('error', 'Unit tersebut sudah berstatus TERJUAL.');
            }

            /*
            | Satu unit hanya boleh memiliki satu transaksi penjualan aktif.
            */
            $stmt = $pdo->prepare("
                SELECT d.id, p.nomor_penjualan, p.status
                FROM penjualan_detail d
                INNER JOIN penjualan p ON p.id = d.penjualan_id
                WHERE d.alat_berat_id = ?
                  AND UPPER(COALESCE(p.status, '')) <> 'BATAL'
                LIMIT 1
            ");
            $stmt->execute([$alatBeratId]);

            if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
                redirectMessage(
                    'error',
                    'Unit ' . $unit['kode'] .
                    ' sudah tercatat pada penjualan ' .
                    $existing['nomor_penjualan'] . '.'
                );
            }

            $hpp = getUnitHpp($pdo, $alatBeratId);
            $subtotal = $hargaJual - $diskon;
            $laba = $subtotal - $hpp;

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO penjualan
                    (
                        nomor_penjualan,
                        tanggal,
                        customer_id,
                        total,
                        status,
                        keterangan,
                        created_by
                    )
                VALUES
                    (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nomor,
                $tanggal,
                $customerId,
                $subtotal,
                $status,
                $keterangan !== '' ? $keterangan : null,
                (int)$_SESSION['admin_id']
            ]);

            $saleId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO penjualan_detail
                    (
                        penjualan_id,
                        alat_berat_id,
                        harga_jual,
                        diskon,
                        subtotal,
                        hpp,
                        laba,
                        keterangan
                    )
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $saleId,
                $alatBeratId,
                $hargaJual,
                $diskon,
                $subtotal,
                $hpp,
                $laba,
                $detailKeterangan !== '' ? $detailKeterangan : null
            ]);

            /*
            | Unit menjadi TERJUAL ketika transaksi CONFIRMED/PAID.
            | DRAFT belum mengubah status unit.
            */
            if (in_array($status, ['CONFIRMED', 'PAID'], true)) {
                $stmt = $pdo->prepare("
                    UPDATE alat_berat
                    SET status = 'TERJUAL'
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->execute([$alatBeratId]);
            }

            $pdo->commit();

            redirectMessage(
                'success',
                'Penjualan ' . $nomor . ' berhasil ditambahkan.'
            );
        }

        if ($action === 'change_status') {
            $saleId = filter_var($_POST['sale_id'] ?? null, FILTER_VALIDATE_INT);
            $newStatus = strtoupper(trim((string)($_POST['new_status'] ?? '')));

            if (!$saleId || !in_array($newStatus, ['DRAFT', 'CONFIRMED', 'PAID', 'BATAL'], true)) {
                redirectMessage('error', 'Data perubahan status tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    p.id,
                    p.status,
                    d.alat_berat_id
                FROM penjualan p
                INNER JOIN penjualan_detail d ON d.penjualan_id = p.id
                WHERE p.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                throw new RuntimeException('Transaksi penjualan tidak ditemukan.');
            }

            $oldStatus = strtoupper((string)$sale['status']);

            if ($oldStatus === $newStatus) {
                $pdo->commit();
                redirectMessage('success', 'Status penjualan tidak berubah.');
            }

            $stmt = $pdo->prepare("
                UPDATE penjualan
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$newStatus, $saleId]);

            $unitId = (int)$sale['alat_berat_id'];

            if (in_array($newStatus, ['CONFIRMED', 'PAID'], true)) {
                $stmt = $pdo->prepare("
                    UPDATE alat_berat
                    SET status = 'TERJUAL'
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->execute([$unitId]);
            } elseif ($newStatus === 'BATAL' && $oldStatus !== 'BATAL') {
                $stmt = $pdo->prepare("
                    UPDATE alat_berat
                    SET status = 'TERSEDIA'
                    WHERE id = ?
                      AND status = 'TERJUAL'
                    LIMIT 1
                ");
                $stmt->execute([$unitId]);
            }

            $pdo->commit();

            redirectMessage(
                'success',
                'Status penjualan berhasil diubah menjadi ' . $newStatus . '.'
            );
        }

        if ($action === 'delete') {
            $saleId = filter_var($_POST['sale_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$saleId) {
                redirectMessage('error', 'ID penjualan tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    p.id,
                    p.status,
                    p.nomor_penjualan,
                    d.alat_berat_id
                FROM penjualan p
                LEFT JOIN penjualan_detail d ON d.penjualan_id = p.id
                WHERE p.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                throw new RuntimeException('Penjualan tidak ditemukan.');
            }

            if (strtoupper((string)$sale['status']) !== 'BATAL') {
                throw new RuntimeException(
                    'Penjualan harus berstatus BATAL sebelum dihapus.'
                );
            }

            if (!empty($sale['alat_berat_id'])) {
                $stmt = $pdo->prepare("
                    UPDATE alat_berat
                    SET status = 'TERSEDIA'
                    WHERE id = ?
                      AND status = 'TERJUAL'
                    LIMIT 1
                ");
                $stmt->execute([(int)$sale['alat_berat_id']]);
            }

            $stmt = $pdo->prepare("DELETE FROM penjualan WHERE id = ? LIMIT 1");
            $stmt->execute([$saleId]);

            $pdo->commit();

            redirectMessage(
                'success',
                'Penjualan ' . $sale['nomor_penjualan'] . ' berhasil dihapus.'
            );
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        redirectMessage('error', $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| DATA MASTER
|--------------------------------------------------------------------------
*/
$customers = $pdo->query("
    SELECT id, nama
    FROM customer
    ORDER BY nama ASC
")->fetchAll(PDO::FETCH_ASSOC);

$units = $pdo->query("
    SELECT
        a.id,
        a.kode,
        a.tipe,
        a.nomor_rangka,
        a.status
    FROM alat_berat a
    WHERE UPPER(COALESCE(a.status, '')) <> 'TERJUAL'
    ORDER BY a.kode ASC
")->fetchAll(PDO::FETCH_ASSOC);

$unitHpp = [];
foreach ($units as $unit) {
    $unitHpp[(int)$unit['id']] = getUnitHpp($pdo, (int)$unit['id']);
}

/*
|--------------------------------------------------------------------------
| DAFTAR PENJUALAN
|--------------------------------------------------------------------------
*/
$stmt = $pdo->query("
    SELECT
        p.id,
        p.nomor_penjualan,
        p.tanggal,
        p.customer_id,
        c.nama AS customer_nama,
        p.total,
        p.status,
        p.keterangan,
        p.created_by,
        p.created_at,
        p.updated_at,
        COUNT(d.id) AS jumlah_unit
    FROM penjualan p
    INNER JOIN customer c ON c.id = p.customer_id
    LEFT JOIN penjualan_detail d ON d.penjualan_id = p.id
    GROUP BY
        p.id,
        p.nomor_penjualan,
        p.tanggal,
        p.customer_id,
        c.nama,
        p.total,
        p.status,
        p.keterangan,
        p.created_by,
        p.created_at,
        p.updated_at
    ORDER BY p.tanggal DESC, p.id DESC
");
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPenjualan = count($sales);
$totalNilaiPenjualan = 0;
$totalLaba = 0;
$totalDraft = 0;

foreach ($sales as $sale) {
    $totalNilaiPenjualan += (float)$sale['total'];

    if (strtoupper((string)$sale['status']) !== 'BATAL') {
        $stmtLaba = $pdo->prepare("
            SELECT COALESCE(SUM(laba), 0)
            FROM penjualan_detail
            WHERE penjualan_id = ?
        ");
        $stmtLaba->execute([(int)$sale['id']]);
        $totalLaba += (float)$stmtLaba->fetchColumn();
    }

    if (strtoupper((string)$sale['status']) === 'DRAFT') {
        $totalDraft++;
    }
}

$msg = trim((string)($_GET['msg'] ?? ''));
$msgType = $_GET['msg_type'] ?? 'success';

require __DIR__ . '/../includes/header.php';
?>

<style>
    .page-actions {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 18px;
    }

    .btn-primary {
        border: 0;
        background: #0d6efd;
        color: #fff;
        border-radius: 6px;
        padding: 10px 16px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-primary:hover {
        background: #0b5ed7;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0;
        border: 1px solid #dce3eb;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 22px;
    }

    .stat-card {
        background: #fff;
        text-align: center;
        padding: 20px 12px;
        border-right: 1px solid #e5eaf0;
    }

    .stat-card:last-child {
        border-right: 0;
    }

    .stat-value {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .stat-label {
        font-size: 12px;
        color: #718096;
    }

    .stat-blue { color: #0d6efd; }
    .stat-green { color: #00a65a; }
    .stat-orange { color: #f0a000; }

    .content-card {
        background: #fff;
        border: 1px solid #dce3eb;
        border-radius: 6px;
        overflow: hidden;
    }

    .content-card-header {
        padding: 14px 20px;
        border-bottom: 1px solid #dce3eb;
        font-weight: 600;
    }

    .table-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: end;
        gap: 20px;
        padding: 14px 20px;
        border-bottom: 1px solid #dce3eb;
    }

    .search-box {
        flex: 1;
        max-width: 480px;
    }

    .page-size-box {
        width: 100px;
    }

    .field-label {
        display: block;
        font-size: 12px;
        color: #718096;
        margin-bottom: 6px;
    }

    .form-control,
    .filter-input {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #b9c7d8;
        border-radius: 5px;
        padding: 9px 10px;
        font-size: 13px;
        background: #fff;
    }

    .table-wrap {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px;
    }

    .data-table th {
        background: #f3f6fa;
        color: #172b4d;
        font-size: 12px;
        text-align: left;
        padding: 11px 10px;
        border-bottom: 1px solid #dce3eb;
        white-space: nowrap;
    }

    .data-table td {
        padding: 11px 10px;
        border-bottom: 1px solid #e5eaf0;
        font-size: 13px;
        vertical-align: middle;
    }

    .filter-row th {
        background: #f8fafc;
        padding: 8px 10px;
    }

    .filter-row input {
        font-size: 12px;
        padding: 7px 8px;
    }

    .sort-btn {
        border: 0;
        background: transparent;
        padding: 0;
        font: inherit;
        color: inherit;
        cursor: pointer;
    }

    .sort-btn::after {
        content: ' ↕';
        color: #7b8794;
    }

    .sort-btn.active.asc::after {
        content: ' ↑';
    }

    .sort-btn.active.desc::after {
        content: ' ↓';
    }

    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 5px;
        font-size: 11px;
        font-weight: 700;
    }

    .badge-draft {
        background: #fff3cd;
        color: #856404;
    }

    .badge-confirmed {
        background: #cfe2ff;
        color: #084298;
    }

    .badge-paid {
        background: #d1e7dd;
        color: #0f5132;
    }

    .badge-batal {
        background: #f8d7da;
        color: #842029;
    }

    .money {
        text-align: right;
        white-space: nowrap;
    }

    .action-cell {
        white-space: nowrap;
    }

    .btn-small {
        border: 1px solid #b8c7dc;
        background: #fff;
        color: #315a8c;
        border-radius: 5px;
        padding: 7px 10px;
        cursor: pointer;
        font-size: 12px;
        margin-right: 4px;
    }

    .btn-small:hover {
        background: #f3f6fa;
    }

    .pagination-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        padding: 12px 20px;
        font-size: 12px;
        color: #718096;
    }

    .pagination {
        display: flex;
        gap: 5px;
    }

    .pagination button {
        border: 1px solid #c9d4e3;
        background: #fff;
        padding: 6px 10px;
        border-radius: 4px;
        cursor: pointer;
    }

    .pagination button.active {
        background: #0d6efd;
        color: #fff;
        border-color: #0d6efd;
    }

    .alert {
        padding: 11px 14px;
        border-radius: 5px;
        margin-bottom: 16px;
        font-size: 13px;
    }

    .alert-success {
        background: #d1e7dd;
        color: #0f5132;
    }

    .alert-error {
        background: #f8d7da;
        color: #842029;
    }

    .modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .45);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-backdrop.show {
        display: flex;
    }

    .modal {
        width: min(720px, 100%);
        max-height: 90vh;
        overflow-y: auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 15px 45px rgba(0,0,0,.18);
    }

    .modal-header,
    .modal-footer {
        padding: 15px 18px;
        border-bottom: 1px solid #e5eaf0;
    }

    .modal-footer {
        border-top: 1px solid #e5eaf0;
        border-bottom: 0;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-weight: 700;
        color: #172b4d;
    }

    .modal-close {
        border: 0;
        background: transparent;
        font-size: 24px;
        cursor: pointer;
        color: #718096;
    }

    .modal-body {
        padding: 18px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
    }

    .form-group.full {
        grid-column: 1 / -1;
    }

    .unit-info {
        margin-top: 7px;
        font-size: 12px;
        color: #718096;
    }

    .calc-box {
        margin-top: 14px;
        padding: 12px;
        background: #f7f9fc;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }

    .calc-item span {
        display: block;
        font-size: 11px;
        color: #718096;
        margin-bottom: 4px;
    }

    .calc-item strong {
        font-size: 14px;
        color: #172b4d;
    }

    .btn-secondary {
        border: 1px solid #b8c7dc;
        background: #fff;
        color: #315a8c;
        border-radius: 5px;
        padding: 9px 14px;
        cursor: pointer;
    }

    .danger {
        color: #b42318;
    }

    @media (max-width: 900px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .stat-card:nth-child(2) {
            border-right: 0;
        }

        .stat-card:nth-child(-n+2) {
            border-bottom: 1px solid #e5eaf0;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-group.full {
            grid-column: auto;
        }
    }

    @media (max-width: 640px) {
        .table-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .search-box,
        .page-size-box {
            max-width: none;
            width: 100%;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-card {
            border-right: 0;
            border-bottom: 1px solid #e5eaf0;
        }

        .stat-card:last-child {
            border-bottom: 0;
        }

        .pagination-bar {
            flex-direction: column;
            align-items: flex-start;
        }

        .calc-box {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if ($msg !== ''): ?>
    <div class="alert <?php echo $msgType === 'error' ? 'alert-error' : 'alert-success'; ?>">
        <?php echo h($msg); ?>
    </div>
<?php endif; ?>

<div class="page-actions">
    <button type="button" class="btn-primary" onclick="openSaleModal()">
        + Tambah Penjualan
    </button>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value stat-blue"><?php echo number_format($totalPenjualan, 0, ',', '.'); ?></div>
        <div class="stat-label">Total Penjualan</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-green"><?php echo rupiah($totalNilaiPenjualan); ?></div>
        <div class="stat-label">Total Nilai Penjualan</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-green"><?php echo rupiah($totalLaba); ?></div>
        <div class="stat-label">Total Laba</div>
    </div>
    <div class="stat-card">
        <div class="stat-value stat-orange"><?php echo number_format($totalDraft, 0, ',', '.'); ?></div>
        <div class="stat-label">Penjualan Draft</div>
    </div>
</div>

<div class="content-card">
    <div class="content-card-header">Daftar Penjualan</div>

    <div class="table-toolbar">
        <div class="search-box">
            <label class="field-label">Pencarian</label>
            <input
                type="text"
                id="globalSearch"
                class="form-control"
                placeholder="Cari nomor, customer, status..."
                oninput="renderTable()"
            >
        </div>

        <div class="page-size-box">
            <label class="field-label">Tampilkan</label>
            <select id="pageSize" class="form-control" onchange="currentPage=1;renderTable()">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table" id="salesTable">
            <thead>
                <tr>
                    <th><button class="sort-btn" data-sort="nomor_penjualan" onclick="sortTable('nomor_penjualan')">No. Penjualan</button></th>
                    <th><button class="sort-btn" data-sort="tanggal" onclick="sortTable('tanggal')">Tanggal</button></th>
                    <th><button class="sort-btn" data-sort="customer_nama" onclick="sortTable('customer_nama')">Customer</button></th>
                    <th><button class="sort-btn" data-sort="jumlah_unit" onclick="sortTable('jumlah_unit')">Unit</button></th>
                    <th><button class="sort-btn" data-sort="total" onclick="sortTable('total')">Total</button></th>
                    <th><button class="sort-btn" data-sort="status" onclick="sortTable('status')">Status</button></th>
                    <th>Keterangan</th>
                    <th>Aksi</th>
                </tr>
                <tr class="filter-row">
                    <th><input class="filter-input" data-filter="nomor_penjualan" placeholder="Filter nomor"></th>
                    <th><input class="filter-input" data-filter="tanggal" placeholder="Filter tanggal"></th>
                    <th><input class="filter-input" data-filter="customer_nama" placeholder="Filter customer"></th>
                    <th><input class="filter-input" data-filter="jumlah_unit" placeholder="Filter unit"></th>
                    <th><input class="filter-input" data-filter="total" placeholder="Filter total"></th>
                    <th><input class="filter-input" data-filter="status" placeholder="Filter status"></th>
                    <th><input class="filter-input" data-filter="keterangan" placeholder="Filter keterangan"></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="salesBody"></tbody>
        </table>
    </div>

    <div class="pagination-bar">
        <div id="tableInfo"></div>
        <div class="pagination" id="pagination"></div>
    </div>
</div>

<div class="modal-backdrop" id="saleModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Tambah Penjualan</div>
            <button type="button" class="modal-close" onclick="closeSaleModal()">×</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="create">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="field-label">Nomor Penjualan *</label>
                        <input type="text" name="nomor_penjualan" class="form-control" required
                               placeholder="PJ-20260910-0004">
                    </div>

                    <div class="form-group">
                        <label class="field-label">Tanggal *</label>
                        <input type="date" name="tanggal" class="form-control"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Customer *</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">-- Pilih Customer --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo (int)$customer['id']; ?>">
                                    <?php echo h($customer['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Status *</label>
                        <select name="status" class="form-control">
                            <option value="DRAFT">DRAFT</option>
                            <option value="CONFIRMED">CONFIRMED</option>
                            <option value="PAID">PAID</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label class="field-label">Unit Alat Berat *</label>
                        <select name="alat_berat_id" id="alatBeratSelect" class="form-control" required onchange="updateSaleCalculation()">
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach ($units as $unit): ?>
                                <option
                                    value="<?php echo (int)$unit['id']; ?>"
                                    data-hpp="<?php echo h($unitHpp[(int)$unit['id']] ?? 0); ?>"
                                >
                                    <?php echo h($unit['kode'] . ' - ' . $unit['tipe'] . ' | ' . ($unit['nomor_rangka'] ?: '-')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="unit-info" id="unitInfo">
                            Pilih unit untuk melihat HPP unit.
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Harga Jual *</label>
                        <input type="number" name="harga_jual" id="hargaJual"
                               class="form-control" min="0" step="0.01"
                               value="0" oninput="updateSaleCalculation()" required>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Diskon</label>
                        <input type="number" name="diskon" id="diskon"
                               class="form-control" min="0" step="0.01"
                               value="0" oninput="updateSaleCalculation()">
                    </div>

                    <div class="form-group full">
                        <label class="field-label">Keterangan Detail</label>
                        <input type="text" name="detail_keterangan" class="form-control"
                               placeholder="Keterangan unit yang dijual">
                    </div>

                    <div class="form-group full">
                        <label class="field-label">Keterangan Penjualan</label>
                        <textarea name="keterangan" class="form-control" rows="3"
                                  placeholder="Keterangan transaksi"></textarea>
                    </div>
                </div>

                <div class="calc-box">
                    <div class="calc-item">
                        <span>HPP Unit</span>
                        <strong id="calcHpp">Rp 0</strong>
                    </div>
                    <div class="calc-item">
                        <span>Subtotal</span>
                        <strong id="calcSubtotal">Rp 0</strong>
                    </div>
                    <div class="calc-item">
                        <span>Estimasi Laba</span>
                        <strong id="calcLaba">Rp 0</strong>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeSaleModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Penjualan</button>
            </div>
        </form>
    </div>
</div>

<script>
const salesData = <?php echo json_encode($sales, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

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

function getStatusBadge(status) {
    const value = String(status || '').toUpperCase();
    const map = {
        DRAFT: 'badge-draft',
        CONFIRMED: 'badge-confirmed',
        PAID: 'badge-paid',
        BATAL: 'badge-batal'
    };

    return '<span class="badge ' + (map[value] || 'badge-draft') + '">' +
        escapeHtml(value) + '</span>';
}

function getFilters() {
    const filters = {};
    document.querySelectorAll('.filter-input').forEach(input => {
        filters[input.dataset.filter] = input.value.toLowerCase().trim();
    });
    return filters;
}

function renderTable() {
    const search = document.getElementById('globalSearch').value.toLowerCase().trim();
    const pageSize = parseInt(document.getElementById('pageSize').value, 10);
    const filters = getFilters();

    let rows = salesData.filter(row => {
        const haystack = [
            row.nomor_penjualan,
            row.tanggal,
            row.customer_nama,
            row.jumlah_unit,
            row.total,
            row.status,
            row.keterangan
        ].join(' ').toLowerCase();

        if (search && !haystack.includes(search)) {
            return false;
        }

        for (const key in filters) {
            if (filters[key] && !String(row[key] ?? '').toLowerCase().includes(filters[key])) {
                return false;
            }
        }

        return true;
    });

    rows.sort((a, b) => {
        let av = a[sortKey] ?? '';
        let bv = b[sortKey] ?? '';

        if (sortKey === 'total' || sortKey === 'jumlah_unit') {
            av = Number(av);
            bv = Number(bv);
        } else {
            av = String(av).toLowerCase();
            bv = String(bv).toLowerCase();
        }

        if (av < bv) return sortDirection === 'asc' ? -1 : 1;
        if (av > bv) return sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    const totalRows = rows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    const start = (currentPage - 1) * pageSize;
    const pageRows = rows.slice(start, start + pageSize);

    const tbody = document.getElementById('salesBody');

    if (!pageRows.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:28px;color:#718096;">Tidak ada data.</td></tr>';
    } else {
        tbody.innerHTML = pageRows.map(row => `
            <tr>
                <td><strong>${escapeHtml(row.nomor_penjualan)}</strong></td>
                <td>${escapeHtml(row.tanggal)}</td>
                <td>${escapeHtml(row.customer_nama)}</td>
                <td>${escapeHtml(row.jumlah_unit)}</td>
                <td class="money">${formatRupiah(row.total)}</td>
                <td>${getStatusBadge(row.status)}</td>
                <td>${escapeHtml(row.keterangan || '-')}</td>
                <td class="action-cell">
                    ${String(row.status).toUpperCase() !== 'BATAL'
                        ? `<form method="post" style="display:inline;" onsubmit="return confirm('Ubah penjualan menjadi BATAL?');">
                            <input type="hidden" name="action" value="change_status">
                            <input type="hidden" name="sale_id" value="${Number(row.id)}">
                            <input type="hidden" name="new_status" value="BATAL">
                            <button type="submit" class="btn-small">Batal</button>
                           </form>`
                        : `<form method="post" style="display:inline;" onsubmit="return confirm('Hapus penjualan ini?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="sale_id" value="${Number(row.id)}">
                            <button type="submit" class="btn-small danger">Hapus</button>
                           </form>`
                    }
                </td>
            </tr>
        `).join('');
    }

    const first = totalRows ? start + 1 : 0;
    const last = Math.min(start + pageSize, totalRows);
    document.getElementById('tableInfo').textContent =
        `Menampilkan ${first} sampai ${last} dari ${totalRows} penjualan`;

    renderPagination(totalPages);
    updateSortButtons();
}

function renderPagination(totalPages) {
    const pagination = document.getElementById('pagination');
    let html = '';

    if (totalPages > 1) {
        html += `<button type="button" onclick="goPage(${Math.max(1, currentPage - 1)})">‹</button>`;

        for (let i = 1; i <= totalPages; i++) {
            if (
                i === 1 ||
                i === totalPages ||
                Math.abs(i - currentPage) <= 2
            ) {
                html += `<button type="button" class="${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
            }
        }

        html += `<button type="button" onclick="goPage(${Math.min(totalPages, currentPage + 1)})">›</button>`;
    }

    pagination.innerHTML = html;
}

function goPage(page) {
    currentPage = page;
    renderTable();
}

function sortTable(key) {
    if (sortKey === key) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey = key;
        sortDirection = 'asc';
    }

    currentPage = 1;
    renderTable();
}

function updateSortButtons() {
    document.querySelectorAll('.sort-btn').forEach(btn => {
        btn.classList.remove('active', 'asc', 'desc');

        if (btn.dataset.sort === sortKey) {
            btn.classList.add('active', sortDirection);
        }
    });
}

function openSaleModal() {
    document.getElementById('saleModal').classList.add('show');
}

function closeSaleModal() {
    document.getElementById('saleModal').classList.remove('show');
}

function updateSaleCalculation() {
    const select = document.getElementById('alatBeratSelect');
    const selected = select.options[select.selectedIndex];

    const hpp = Number(selected?.dataset.hpp || 0);
    const hargaJual = Number(document.getElementById('hargaJual').value || 0);
    const diskon = Number(document.getElementById('diskon').value || 0);
    const subtotal = Math.max(0, hargaJual - diskon);
    const laba = subtotal - hpp;

    document.getElementById('calcHpp').textContent = formatRupiah(hpp);
    document.getElementById('calcSubtotal').textContent = formatRupiah(subtotal);
    document.getElementById('calcLaba').textContent = formatRupiah(laba);

    document.getElementById('unitInfo').textContent =
        select.value
            ? 'HPP unit: ' + formatRupiah(hpp)
            : 'Pilih unit untuk melihat HPP unit.';
}

document.querySelectorAll('.filter-input').forEach(input => {
    input.addEventListener('input', () => {
        currentPage = 1;
        renderTable();
    });
});

document.getElementById('saleModal').addEventListener('click', function (event) {
    if (event.target === this) {
        closeSaleModal();
    }
});

renderTable();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
