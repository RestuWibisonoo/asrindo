<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Detail Penjualan';
$adminBase = '../';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)($value ?? 0), 0, ',', '.');
}

function redirectEdit(int $id, string $type, string $message): void
{
    header('Location: penjualan_edit.php?' . http_build_query([
        'id' => $id,
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

$id = filter_var($_GET['id'] ?? $_POST['sale_id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: penjualan.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_header') {
            $nomor = trim((string)($_POST['nomor_penjualan'] ?? ''));
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $jatuhTempo = trim((string)($_POST['tanggal_jatuh_tempo'] ?? ''));
            $customerId = filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT);
            $ppn = (float)($_POST['ppn'] ?? 0);
            $alamatPengiriman = trim((string)($_POST['alamat_pengiriman'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($nomor === '' || $tanggal === '' || !$customerId) {
                redirectEdit($id, 'error', 'Nomor penjualan, tanggal, dan customer wajib diisi.');
            }

            if ($ppn < 0 || $ppn > 100) {
                redirectEdit($id, 'error', 'PPN harus berada di antara 0 sampai 100 persen.');
            }

            if ($jatuhTempo !== '' && $jatuhTempo < $tanggal) {
                redirectEdit($id, 'error', 'Tanggal jatuh tempo tidak boleh sebelum tanggal penjualan.');
            }

            $stmt = $pdo->prepare("SELECT id FROM customer WHERE id = ? LIMIT 1");
            $stmt->execute([$customerId]);
            if (!$stmt->fetch()) {
                redirectEdit($id, 'error', 'Customer tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT id, nomor_penjualan, status
                FROM penjualan
                WHERE nomor_penjualan = ?
                  AND id <> ?
                LIMIT 1
            ");
            $stmt->execute([$nomor, $id]);
            if ($stmt->fetch()) {
                redirectEdit($id, 'error', 'Nomor penjualan sudah digunakan.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(subtotal), 0)
                FROM penjualan_detail
                WHERE penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $subtotal = (float)$stmt->fetchColumn();

            $total = $subtotal + ($subtotal * $ppn / 100);

            $stmt = $pdo->prepare("
                UPDATE penjualan
                SET
                    nomor_penjualan = ?,
                    tanggal = ?,
                    tanggal_jatuh_tempo = ?,
                    customer_id = ?,
                    total = ?,
                    ppn = ?,
                    alamat_pengiriman = ?,
                    keterangan = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $nomor,
                $tanggal,
                $jatuhTempo !== '' ? $jatuhTempo : null,
                $customerId,
                $total,
                $ppn,
                $alamatPengiriman !== '' ? $alamatPengiriman : null,
                $keterangan !== '' ? $keterangan : null,
                $id
            ]);

            redirectEdit($id, 'success', 'Informasi penjualan berhasil diperbarui.');
        }

        if ($action === 'add_unit') {
            $alatBeratId = filter_var(
                $_POST['alat_berat_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $hargaJual = (float)($_POST['harga_jual'] ?? 0);
            $diskon = (float)($_POST['diskon'] ?? 0);
            $detailKeterangan = trim((string)($_POST['detail_keterangan'] ?? ''));

            if (!$alatBeratId) {
                redirectEdit($id, 'error', 'Unit alat berat wajib dipilih.');
            }

            if ($hargaJual < 0 || $diskon < 0 || $diskon > $hargaJual) {
                redirectEdit($id, 'error', 'Harga jual atau diskon tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, kode, tipe, status
                FROM alat_berat
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$alatBeratId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                throw new RuntimeException('Unit alat berat tidak ditemukan.');
            }

            if (strtoupper((string)$unit['status']) === 'TERJUAL') {
                throw new RuntimeException('Unit ' . $unit['kode'] . ' sudah berstatus TERJUAL.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM penjualan_detail
                WHERE penjualan_id = ?
                  AND alat_berat_id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $alatBeratId]);

            if ($stmt->fetch()) {
                throw new RuntimeException('Unit tersebut sudah ada dalam nota ini.');
            }

            $stmt = $pdo->prepare("
                SELECT p.status
                FROM penjualan_detail d
                INNER JOIN penjualan p ON p.id = d.penjualan_id
                WHERE d.alat_berat_id = ?
                  AND d.penjualan_id <> ?
                  AND UPPER(COALESCE(p.status, '')) <> 'BATAL'
                LIMIT 1
            ");
            $stmt->execute([$alatBeratId, $id]);

            if ($otherSale = $stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Unit tersebut sudah digunakan pada penjualan lain.');
            }

            $hpp = getUnitHpp($pdo, $alatBeratId);

            /*
            | subtotal dan laba adalah GENERATED COLUMN.
            */
            $stmt = $pdo->prepare("
                INSERT INTO penjualan_detail
                    (
                        penjualan_id,
                        alat_berat_id,
                        harga_jual,
                        diskon,
                        hpp,
                        keterangan
                    )
                VALUES
                    (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id,
                $alatBeratId,
                $hargaJual,
                $diskon,
                $hpp,
                $detailKeterangan !== '' ? $detailKeterangan : null
            ]);

            $stmt = $pdo->prepare("
                SELECT status
                FROM penjualan
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $saleStatus = strtoupper((string)$stmt->fetchColumn());

            if (in_array($saleStatus, ['CONFIRMED', 'PAID'], true)) {
                $stmt = $pdo->prepare("
                    UPDATE alat_berat
                    SET status = 'TERJUAL'
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->execute([$alatBeratId]);
            }

            $pdo->commit();

            /*
            | Recalculate header total after adding the unit.
            */
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(subtotal), 0)
                FROM penjualan_detail
                WHERE penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $subtotal = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COALESCE(ppn, 0) FROM penjualan WHERE id = ?");
            $stmt->execute([$id]);
            $ppn = (float)$stmt->fetchColumn();

            $total = $subtotal + ($subtotal * $ppn / 100);

            $stmt = $pdo->prepare("UPDATE penjualan SET total = ? WHERE id = ? LIMIT 1");
            $stmt->execute([$total, $id]);

            redirectEdit($id, 'success', 'Unit ' . $unit['kode'] . ' berhasil ditambahkan ke penjualan.');
        }

        if ($action === 'delete_unit') {
            $detailId = filter_var($_POST['detail_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$detailId) {
                redirectEdit($id, 'error', 'Detail unit tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    d.id,
                    d.alat_berat_id,
                    a.kode,
                    p.status AS sale_status
                FROM penjualan_detail d
                INNER JOIN alat_berat a ON a.id = d.alat_berat_id
                INNER JOIN penjualan p ON p.id = d.penjualan_id
                WHERE d.id = ?
                  AND d.penjualan_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$detailId, $id]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$detail) {
                throw new RuntimeException('Detail unit tidak ditemukan.');
            }

            $stmt = $pdo->prepare("DELETE FROM penjualan_detail WHERE id = ? LIMIT 1");
            $stmt->execute([$detailId]);

            /*
            | Jika transaksi aktif sudah menandai unit sebagai TERJUAL,
            | menghapus item mengembalikannya menjadi TERSEDIA.
            */
            if (in_array(strtoupper((string)$detail['sale_status']), ['CONFIRMED', 'PAID'], true)) {
                $stmt = $pdo->prepare("
                    UPDATE alat_berat
                    SET status = 'TERSEDIA'
                    WHERE id = ?
                      AND status = 'TERJUAL'
                    LIMIT 1
                ");
                $stmt->execute([(int)$detail['alat_berat_id']]);
            }

            $pdo->commit();

            /*
            | Recalculate total header.
            */
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(subtotal), 0)
                FROM penjualan_detail
                WHERE penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $subtotal = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COALESCE(ppn, 0) FROM penjualan WHERE id = ?");
            $stmt->execute([$id]);
            $ppn = (float)$stmt->fetchColumn();

            $total = $subtotal + ($subtotal * $ppn / 100);

            $stmt = $pdo->prepare("UPDATE penjualan SET total = ? WHERE id = ? LIMIT 1");
            $stmt->execute([$total, $id]);

            redirectEdit($id, 'success', 'Unit ' . $detail['kode'] . ' berhasil dihapus dari penjualan.');
        }

        if ($action === 'change_status') {
            $newStatus = strtoupper(trim((string)($_POST['new_status'] ?? '')));

            if (!in_array($newStatus, ['DRAFT', 'CONFIRMED', 'PAID', 'BATAL'], true)) {
                redirectEdit($id, 'error', 'Status tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, status
                FROM penjualan
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                throw new RuntimeException('Penjualan tidak ditemukan.');
            }

            $oldStatus = strtoupper((string)$sale['status']);

            $stmt = $pdo->prepare("
                SELECT alat_berat_id
                FROM penjualan_detail
                WHERE penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $detailUnits = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare("
                UPDATE penjualan
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$newStatus, $id]);

            if (in_array($newStatus, ['CONFIRMED', 'PAID'], true)) {
                $stmt = $pdo->prepare("
                    UPDATE alat_berat
                    SET status = 'TERJUAL'
                    WHERE id = ?
                    LIMIT 1
                ");

                foreach ($detailUnits as $unitId) {
                    $stmt->execute([(int)$unitId]);
                }
            } elseif ($newStatus === 'BATAL' && $oldStatus !== 'BATAL') {
                $stmt = $pdo->prepare("
                    UPDATE alat_berat
                    SET status = 'TERSEDIA'
                    WHERE id = ?
                      AND status = 'TERJUAL'
                    LIMIT 1
                ");

                foreach ($detailUnits as $unitId) {
                    $stmt->execute([(int)$unitId]);
                }
            }

            $pdo->commit();

            redirectEdit($id, 'success', 'Status penjualan berhasil diubah menjadi ' . $newStatus . '.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        redirectEdit($id, 'error', $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| HEADER PENJUALAN
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        p.*,
        c.nama AS customer_nama,
        c.telepon AS customer_telepon,
        c.email AS customer_email,
        c.alamat AS customer_alamat
    FROM penjualan p
    INNER JOIN customer c ON c.id = p.customer_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    header('Location: penjualan.php');
    exit;
}

$customers = $pdo->query("
    SELECT id, nama
    FROM customer
    ORDER BY nama ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| DETAIL UNIT
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        d.id,
        d.penjualan_id,
        d.alat_berat_id,
        d.harga_jual,
        d.diskon,
        d.subtotal,
        d.hpp,
        d.laba,
        d.keterangan,
        a.kode,
        a.tipe,
        a.nomor_rangka
    FROM penjualan_detail d
    INNER JOIN alat_berat a ON a.id = d.alat_berat_id
    WHERE d.penjualan_id = ?
    ORDER BY d.id ASC
");
$stmt->execute([$id]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| UNIT YANG BISA DITAMBAHKAN
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.kode,
        a.tipe,
        a.nomor_rangka,
        a.status
    FROM alat_berat a
    WHERE UPPER(COALESCE(a.status, '')) <> 'TERJUAL'
      AND NOT EXISTS (
          SELECT 1
          FROM penjualan_detail d
          WHERE d.penjualan_id = ?
            AND d.alat_berat_id = a.id
      )
      AND NOT EXISTS (
          SELECT 1
          FROM penjualan_detail d2
          INNER JOIN penjualan p2 ON p2.id = d2.penjualan_id
          WHERE d2.alat_berat_id = a.id
            AND d2.penjualan_id <> ?
            AND UPPER(COALESCE(p2.status, '')) <> 'BATAL'
      )
    ORDER BY a.kode ASC
");
$stmt->execute([$id, $id]);
$availableUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$availableHpp = [];
foreach ($availableUnits as $unit) {
    $availableHpp[(int)$unit['id']] = getUnitHpp($pdo, (int)$unit['id']);
}

$subtotalReal = 0;
$totalHpp = 0;
$totalLaba = 0;

foreach ($details as $detail) {
    $subtotalReal += (float)$detail['subtotal'];
    $totalHpp += (float)$detail['hpp'];
    $totalLaba += (float)$detail['laba'];
}

$ppn = (float)($sale['ppn'] ?? 0);
$ppnNominal = $subtotalReal * ($ppn / 100);
$grandTotal = $subtotalReal + $ppnNominal;

$msg = trim((string)($_GET['msg'] ?? ''));
$msgType = $_GET['msg_type'] ?? 'success';

require __DIR__ . '/../includes/header.php';
?>

<style>
    .page-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 18px;
    }

    .page-title {
        margin: 0;
        font-size: 21px;
        color: #172b4d;
    }

    .page-subtitle {
        margin: 5px 0 0;
        color: #718096;
        font-size: 12px;
    }

    .top-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-block;
        border: 1px solid #b9c7d8;
        background: #fff;
        color: #315a8c;
        border-radius: 5px;
        padding: 9px 13px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
    }

    .btn:hover {
        background: #f3f6fa;
    }

    .btn-primary {
        border-color: #0d6efd;
        background: #0d6efd;
        color: #fff;
    }

    .btn-primary:hover {
        background: #0b5ed7;
    }

    .btn-danger {
        border-color: #dc3545;
        color: #dc3545;
    }

    .btn-danger:hover {
        background: #fff5f5;
    }

    .btn-success {
        border-color: #198754;
        color: #198754;
    }

    .btn-success:hover {
        background: #f1fbf5;
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

    .panel {
        background: #fff;
        border: 1px solid #dce3eb;
        border-radius: 6px;
        margin-bottom: 18px;
        overflow: hidden;
    }

    .panel-header {
        padding: 13px 16px;
        border-bottom: 1px solid #dce3eb;
        font-weight: 700;
        font-size: 13px;
        color: #172b4d;
    }

    .panel-body {
        padding: 16px;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }

    .info-item {
        min-width: 0;
    }

    .info-label {
        color: #718096;
        font-size: 11px;
        margin-bottom: 5px;
    }

    .info-value {
        color: #172b4d;
        font-size: 13px;
        font-weight: 600;
        word-break: break-word;
    }

    .info-item.full {
        grid-column: 1 / -1;
    }

    .customer-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
    }

    .customer-address {
        margin-top: 16px;
    }

    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 5px;
        font-size: 10px;
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

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0;
    }

    .summary-item {
        padding: 10px 16px;
        border-right: 1px solid #e5eaf0;
    }

    .summary-item:last-child {
        border-right: 0;
    }

    .summary-label {
        color: #718096;
        font-size: 11px;
        margin-bottom: 6px;
    }

    .summary-value {
        color: #172b4d;
        font-size: 17px;
        font-weight: 700;
    }

    .summary-green {
        color: #00a65a;
    }

    .summary-blue {
        color: #0d6efd;
    }

    .section-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid #dce3eb;
    }

    .section-toolbar-title {
        font-weight: 700;
        font-size: 13px;
        color: #172b4d;
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
        font-size: 11px;
        text-align: left;
        padding: 10px;
        border-bottom: 1px solid #dce3eb;
        white-space: nowrap;
    }

    .data-table td {
        padding: 10px;
        border-bottom: 1px solid #e5eaf0;
        font-size: 12px;
        vertical-align: middle;
    }

    .money {
        text-align: right;
        white-space: nowrap;
    }

    .action-cell {
        white-space: nowrap;
        text-align: center;
    }

    .empty-row {
        text-align: center;
        color: #718096;
        padding: 28px !important;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
    }

    .form-group.full {
        grid-column: 1 / -1;
    }

    .field-label {
        display: block;
        font-size: 12px;
        color: #53657d;
        margin-bottom: 6px;
    }

    .form-control {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #b9c7d8;
        border-radius: 5px;
        padding: 9px 10px;
        font-size: 13px;
        background: #fff;
    }

    textarea.form-control {
        resize: vertical;
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
        width: min(700px, 100%);
        max-height: 90vh;
        overflow-y: auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 15px 45px rgba(0,0,0,.18);
    }

    .modal-header,
    .modal-footer {
        padding: 14px 18px;
        border-bottom: 1px solid #e5eaf0;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-footer {
        border-top: 1px solid #e5eaf0;
        border-bottom: 0;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .modal-title {
        font-weight: 700;
        color: #172b4d;
    }

    .modal-close {
        border: 0;
        background: transparent;
        font-size: 23px;
        color: #718096;
        cursor: pointer;
    }

    .modal-body {
        padding: 18px;
    }

    .calc-box {
        margin-top: 14px;
        padding: 12px;
        background: #f7f9fc;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
    }

    .calc-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 5px 0;
        font-size: 12px;
    }

    .calc-row strong {
        color: #172b4d;
    }

    .invoice-print {
        display: none;
    }

    @media (max-width: 900px) {
        .info-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .customer-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .summary-item:nth-child(2) {
            border-right: 0;
        }

        .summary-item:nth-child(-n+2) {
            border-bottom: 1px solid #e5eaf0;
        }

        .form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .page-top {
            align-items: flex-start;
            flex-direction: column;
        }

        .info-grid,
        .customer-grid,
        .summary-grid,
        .form-grid {
            grid-template-columns: 1fr;
        }

        .info-item.full,
        .form-group.full {
            grid-column: auto;
        }

        .summary-item {
            border-right: 0;
            border-bottom: 1px solid #e5eaf0;
        }

        .summary-item:last-child {
            border-bottom: 0;
        }
    }

    @media print {
        body * {
            visibility: hidden !important;
        }

        .invoice-print,
        .invoice-print * {
            visibility: visible !important;
        }

        .invoice-print {
            display: block !important;
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 20px;
            box-sizing: border-box;
            color: #000;
            background: #fff;
            font-family: Arial, sans-serif;
        }

        .invoice-print h1 {
            margin: 0 0 5px;
            font-size: 22px;
        }

        .invoice-print table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        .invoice-print th,
        .invoice-print td {
            border: 1px solid #999;
            padding: 7px;
            font-size: 11px;
        }

        .invoice-print th {
            background: #eee;
        }

        .invoice-total {
            margin-top: 15px;
            margin-left: auto;
            width: 300px;
        }

        .invoice-total div {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
    }
</style>

<?php if ($msg !== ''): ?>
    <div class="alert <?php echo $msgType === 'error' ? 'alert-error' : 'alert-success'; ?>">
        <?php echo h($msg); ?>
    </div>
<?php endif; ?>

<div class="page-top">
    <div>
        <h1 class="page-title">Detail Penjualan</h1>
        <p class="page-subtitle">
            <?php echo h($sale['nomor_penjualan']); ?>
        </p>
    </div>

    <div class="top-actions">
        <button type="button" class="btn" onclick="openHeaderModal()">Edit Informasi</button>
        <button type="button" class="btn btn-primary" onclick="window.print()">Cetak Invoice</button>
        <a href="penjualan.php" class="btn">Kembali</a>
    </div>
</div>

<div class="panel">
    <div class="panel-header">Informasi Penjualan</div>
    <div class="panel-body">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Nomor Penjualan</div>
                <div class="info-value"><?php echo h($sale['nomor_penjualan']); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Status Transaksi</div>
                <div class="info-value">
                    <?php
                    $status = strtoupper((string)$sale['status']);
                    $badgeClass = 'badge-draft';

                    if ($status === 'CONFIRMED') {
                        $badgeClass = 'badge-confirmed';
                    } elseif ($status === 'PAID') {
                        $badgeClass = 'badge-paid';
                    } elseif ($status === 'BATAL') {
                        $badgeClass = 'badge-batal';
                    }
                    ?>
                    <span class="badge <?php echo $badgeClass; ?>"><?php echo h($status); ?></span>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Tanggal Penjualan</div>
                <div class="info-value"><?php echo h($sale['tanggal']); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Tanggal Jatuh Tempo</div>
                <div class="info-value"><?php echo h($sale['tanggal_jatuh_tempo'] ?: '-'); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">PPN</div>
                <div class="info-value"><?php echo number_format($ppn, 2, ',', '.'); ?>%</div>
            </div>

            <div class="info-item full">
                <div class="info-label">Alamat Pengiriman</div>
                <div class="info-value"><?php echo nl2br(h($sale['alamat_pengiriman'] ?: '-')); ?></div>
            </div>

            <div class="info-item full">
                <div class="info-label">Keterangan</div>
                <div class="info-value"><?php echo nl2br(h($sale['keterangan'] ?: '-')); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">Informasi Pelanggan</div>
    <div class="panel-body">
        <div class="customer-grid">
            <div class="info-item">
                <div class="info-label">Nama Pelanggan</div>
                <div class="info-value"><?php echo h($sale['customer_nama']); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Telepon</div>
                <div class="info-value"><?php echo h($sale['customer_telepon'] ?: '-'); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo h($sale['customer_email'] ?: '-'); ?></div>
            </div>
        </div>

        <div class="customer-address">
            <div class="info-label">Alamat</div>
            <div class="info-value"><?php echo nl2br(h($sale['customer_alamat'] ?: '-')); ?></div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">Ringkasan Total Harga Akhir</div>
    <div class="summary-grid">
        <div class="summary-item">
            <div class="summary-label">Subtotal Real</div>
            <div class="summary-value"><?php echo rupiah($subtotalReal); ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">PPN (<?php echo number_format($ppn, 2, ',', '.'); ?>%)</div>
            <div class="summary-value summary-green"><?php echo rupiah($ppnNominal); ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Total HPP</div>
            <div class="summary-value"><?php echo rupiah($totalHpp); ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Grand Total</div>
            <div class="summary-value summary-blue"><?php echo rupiah($grandTotal); ?></div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="section-toolbar">
        <div class="section-toolbar-title">
            Item Alat Berat (<?php echo count($details); ?> unit)
        </div>

        <button
            type="button"
            class="btn btn-primary"
            onclick="openUnitModal()"
            <?php echo empty($availableUnits) ? 'disabled' : ''; ?>
        >
            + Tambah Unit Alat Berat
        </button>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Kode</th>
                    <th>Tipe</th>
                    <th>Nomor Rangka</th>
                    <th>Harga Jual</th>
                    <th>Diskon</th>
                    <th>Subtotal</th>
                    <th>HPP</th>
                    <th>Laba</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$details): ?>
                    <tr>
                        <td colspan="10" class="empty-row">Belum ada unit dalam penjualan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($details as $index => $detail): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo h($detail['kode']); ?></strong></td>
                            <td><?php echo h($detail['tipe']); ?></td>
                            <td><?php echo h($detail['nomor_rangka'] ?: '-'); ?></td>
                            <td class="money"><?php echo rupiah($detail['harga_jual']); ?></td>
                            <td class="money"><?php echo rupiah($detail['diskon']); ?></td>
                            <td class="money"><?php echo rupiah($detail['subtotal']); ?></td>
                            <td class="money"><?php echo rupiah($detail['hpp']); ?></td>
                            <td class="money"><?php echo rupiah($detail['laba']); ?></td>
                            <td class="action-cell">
                                <form method="post" onsubmit="return confirm('Hapus unit ini dari penjualan?');">
                                    <input type="hidden" name="action" value="delete_unit">
                                    <input type="hidden" name="sale_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="detail_id" value="<?php echo (int)$detail['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php if (!empty($detail['keterangan'])): ?>
                            <tr>
                                <td></td>
                                <td colspan="9" style="font-size:11px;color:#718096;">
                                    Keterangan: <?php echo h($detail['keterangan']); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-header">Status Penjualan</div>
    <div class="panel-body">
        <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="sale_id" value="<?php echo $id; ?>">

            <select name="new_status" class="form-control" style="width:180px;">
                <?php foreach (['DRAFT', 'CONFIRMED', 'PAID', 'BATAL'] as $option): ?>
                    <option value="<?php echo $option; ?>" <?php echo $status === $option ? 'selected' : ''; ?>>
                        <?php echo $option; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Simpan perubahan status penjualan?');">
                Update Status
            </button>
        </form>
    </div>
</div>

<!-- Modal Edit Informasi Penjualan -->
<div class="modal-backdrop" id="headerModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Informasi Penjualan</div>
            <button type="button" class="modal-close" onclick="closeHeaderModal()">×</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="update_header">
            <input type="hidden" name="sale_id" value="<?php echo $id; ?>">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="field-label">Nomor Penjualan *</label>
                        <input type="text" name="nomor_penjualan" class="form-control"
                               value="<?php echo h($sale['nomor_penjualan']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Tanggal Penjualan *</label>
                        <input type="date" name="tanggal" class="form-control"
                               value="<?php echo h($sale['tanggal']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Tanggal Jatuh Tempo</label>
                        <input type="date" name="tanggal_jatuh_tempo" class="form-control"
                               value="<?php echo h($sale['tanggal_jatuh_tempo'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="field-label">Customer *</label>
                        <select name="customer_id" class="form-control" required>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo (int)$customer['id']; ?>"
                                    <?php echo (int)$customer['id'] === (int)$sale['customer_id'] ? 'selected' : ''; ?>>
                                    <?php echo h($customer['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">PPN (%) *</label>
                        <input type="number" name="ppn" class="form-control"
                               min="0" max="100" step="0.01"
                               value="<?php echo h($ppn); ?>" required>
                    </div>

                    <div class="form-group full">
                        <label class="field-label">Alamat Pengiriman</label>
                        <textarea name="alamat_pengiriman" class="form-control" rows="3"
                                  placeholder="Alamat lengkap pengiriman..."><?php echo h($sale['alamat_pengiriman'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group full">
                        <label class="field-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3"
                                  placeholder="Catatan tambahan..."><?php echo h($sale['keterangan'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeHeaderModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tambah Unit -->
<div class="modal-backdrop" id="unitModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Tambah Unit Alat Berat</div>
            <button type="button" class="modal-close" onclick="closeUnitModal()">×</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="add_unit">
            <input type="hidden" name="sale_id" value="<?php echo $id; ?>">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="field-label">Unit Alat Berat *</label>
                        <select name="alat_berat_id" id="unitSelect" class="form-control" required
                                onchange="updateUnitCalculation()">
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach ($availableUnits as $unit): ?>
                                <option
                                    value="<?php echo (int)$unit['id']; ?>"
                                    data-hpp="<?php echo h($availableHpp[(int)$unit['id']] ?? 0); ?>"
                                >
                                    <?php
                                    echo h(
                                        $unit['kode'] . ' - ' .
                                        $unit['tipe'] .
                                        ' | Rangka: ' .
                                        ($unit['nomor_rangka'] ?: '-')
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="field-label">Harga Jual *</label>
                        <input type="number" name="harga_jual" id="unitHargaJual"
                               class="form-control" min="0" step="0.01"
                               value="0" required oninput="updateUnitCalculation()">
                    </div>

                    <div class="form-group">
                        <label class="field-label">Diskon</label>
                        <input type="number" name="diskon" id="unitDiskon"
                               class="form-control" min="0" step="0.01"
                               value="0" oninput="updateUnitCalculation()">
                    </div>

                    <div class="form-group full">
                        <label class="field-label">Keterangan Unit</label>
                        <input type="text" name="detail_keterangan" class="form-control"
                               placeholder="Keterangan item">
                    </div>
                </div>

                <div class="calc-box">
                    <div class="calc-row">
                        <span>HPP Unit</span>
                        <strong id="unitCalcHpp">Rp 0</strong>
                    </div>
                    <div class="calc-row">
                        <span>Subtotal</span>
                        <strong id="unitCalcSubtotal">Rp 0</strong>
                    </div>
                    <div class="calc-row">
                        <span>Estimasi Laba</span>
                        <strong id="unitCalcLaba">Rp 0</strong>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeUnitModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Tambah Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- Tampilan khusus cetak invoice -->
<div class="invoice-print">
    <h1>ASRINDO</h1>
    <div>Invoice Penjualan</div>
    <hr>

    <table style="border:0;">
        <tr>
            <td style="border:0;"><strong>No. Penjualan</strong></td>
            <td style="border:0;"><?php echo h($sale['nomor_penjualan']); ?></td>
            <td style="border:0;"><strong>Tanggal</strong></td>
            <td style="border:0;"><?php echo h($sale['tanggal']); ?></td>
        </tr>
        <tr>
            <td style="border:0;"><strong>Customer</strong></td>
            <td style="border:0;"><?php echo h($sale['customer_nama']); ?></td>
            <td style="border:0;"><strong>Jatuh Tempo</strong></td>
            <td style="border:0;"><?php echo h($sale['tanggal_jatuh_tempo'] ?: '-'); ?></td>
        </tr>
        <tr>
            <td style="border:0;"><strong>Alamat</strong></td>
            <td colspan="3" style="border:0;"><?php echo nl2br(h($sale['customer_alamat'] ?: '-')); ?></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Kode</th>
                <th>Tipe</th>
                <th>Nomor Rangka</th>
                <th>Harga Jual</th>
                <th>Diskon</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $index => $detail): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo h($detail['kode']); ?></td>
                    <td><?php echo h($detail['tipe']); ?></td>
                    <td><?php echo h($detail['nomor_rangka'] ?: '-'); ?></td>
                    <td style="text-align:right;"><?php echo rupiah($detail['harga_jual']); ?></td>
                    <td style="text-align:right;"><?php echo rupiah($detail['diskon']); ?></td>
                    <td style="text-align:right;"><?php echo rupiah($detail['subtotal']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="invoice-total">
        <div>
            <span>Subtotal</span>
            <strong><?php echo rupiah($subtotalReal); ?></strong>
        </div>
        <div>
            <span>PPN (<?php echo number_format($ppn, 2, ',', '.'); ?>%)</span>
            <strong><?php echo rupiah($ppnNominal); ?></strong>
        </div>
        <div>
            <span><strong>Grand Total</strong></span>
            <strong><?php echo rupiah($grandTotal); ?></strong>
        </div>
    </div>

    <p style="margin-top:25px;">
        Alamat Pengiriman: <?php echo nl2br(h($sale['alamat_pengiriman'] ?: '-')); ?>
    </p>
</div>

<script>
function openHeaderModal() {
    document.getElementById('headerModal').classList.add('show');
}

function closeHeaderModal() {
    document.getElementById('headerModal').classList.remove('show');
}

function openUnitModal() {
    const modal = document.getElementById('unitModal');
    if (!modal) return;
    modal.classList.add('show');
    updateUnitCalculation();
}

function closeUnitModal() {
    document.getElementById('unitModal').classList.remove('show');
}

function formatRupiah(value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID', {
        maximumFractionDigits: 0
    });
}

function updateUnitCalculation() {
    const select = document.getElementById('unitSelect');
    if (!select) return;

    const option = select.options[select.selectedIndex];
    const hpp = Number(option?.dataset.hpp || 0);
    const harga = Number(document.getElementById('unitHargaJual').value || 0);
    const diskon = Number(document.getElementById('unitDiskon').value || 0);
    const subtotal = Math.max(0, harga - diskon);
    const laba = subtotal - hpp;

    document.getElementById('unitCalcHpp').textContent = formatRupiah(hpp);
    document.getElementById('unitCalcSubtotal').textContent = formatRupiah(subtotal);
    document.getElementById('unitCalcLaba').textContent = formatRupiah(laba);
}

document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
    backdrop.addEventListener('click', function(event) {
        if (event.target === backdrop) {
            backdrop.classList.remove('show');
        }
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
