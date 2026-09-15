<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Penjualan Sparepart';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return number_format((float)($value ?? 0), 0, ',', '.');
}

function redirectMessage(string $type, string $message): void
{
    header('Location: penjualan_sparepart.php?' . http_build_query([
        'msg_type' => $type,
        'msg' => $message
    ]));
    exit;
}

function refreshPurchaseTotal(PDO $pdo, int $purchaseId): void
{
    $stmt = $pdo->prepare("
        UPDATE penjualan_sparepart p
        SET p.total = (
            SELECT COALESCE(SUM(d.subtotal), 0)
            FROM penjualan_sparepart_detail d
            WHERE d.penjualan_id = p.id
        )
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$purchaseId]);
}

function getPurchase(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.nomor_penjualan,
            p.tanggal,
            p.customer_id,
            s.nama AS customer_nama,
            p.total,
            p.status,
            p.keterangan,
            p.created_at,
            p.updated_at
        FROM penjualan_sparepart p
        INNER JOIN customer s ON s.id = p.customer_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function stockDelta(PDO $pdo, int $sparepartId, float $delta): void
{
    // Ambil stok terkini dan kunci baris selama transaksi.
    $stmt = $pdo->prepare("
        SELECT stok
        FROM sparepart
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$sparepartId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Sparepart tidak ditemukan saat memperbarui stok.');
    }

    $currentStock = (float)$row['stok'];
    $newStock = $currentStock - $delta;

    if ($newStock < 0) {
        throw new RuntimeException(
            'Stok sparepart tidak mencukupi untuk koreksi transaksi. Stok saat ini: ' .
            rupiah($currentStock) . '.'
        );
    }

    $stmt = $pdo->prepare("
        UPDATE sparepart
        SET stok = ?
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$newStock, $sparepartId]);
}

/*
|--------------------------------------------------------------------------
| AJAX DETAIL
|--------------------------------------------------------------------------
*/
/**
 * Sinkronisasi pemasukan penjualan sparepart.
 *
 * Karena penjualan sparepart tidak menggunakan termin pembayaran,
 * saat status SELESAI transaksi dianggap sudah dibayar dan dicatat
 * langsung sebagai pemasukan.
 */
function syncPurchaseExpense(PDO $pdo, int $purchaseId): void
{
    $stmt = $pdo->prepare("
        SELECT id, nomor_penjualan, tanggal, total, status
        FROM penjualan_sparepart
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$purchaseId]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$purchase) {
        throw new RuntimeException('Penjualan sparepart tidak ditemukan saat sinkronisasi pemasukan.');
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM pemasukan
        WHERE penjualan_sparepart_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$purchaseId]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (strtoupper((string)$purchase['status']) === 'SELESAI') {
        $nominal = (float)$purchase['total'];

        if ($nominal <= 0) {
            throw new RuntimeException(
                'Penjualan berstatus SELESAI harus memiliki total penjualan lebih dari 0.'
            );
        }

        if ($expense) {
            $stmt = $pdo->prepare("
                UPDATE pemasukan
                SET tanggal = ?,
                    nominal = ?,
                    keterangan = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $purchase['tanggal'],
                $nominal,
                'Pembayaran penjualan sparepart ' . $purchase['nomor_penjualan'],
                (int)$expense['id']
            ]);
        } else {
            $nomorPemasukan = 'PM-SP-' .
                date('Ymd', strtotime((string)$purchase['tanggal'])) . '-' .
                str_pad((string)$purchaseId, 8, '0', STR_PAD_LEFT);

            // Kategori PENJUALAN bersifat opsional. Jika tersedia, gunakan.
            $stmt = $pdo->prepare("
                SELECT id
                FROM kategori_keuangan
                WHERE kode = 'PENJUALAN'
                  AND tipe = 'PENDAPATAN'
                LIMIT 1
            ");
            $stmt->execute();
            $kategoriId = $stmt->fetchColumn();
            $kategoriId = $kategoriId !== false ? (int)$kategoriId : null;

            $stmt = $pdo->prepare("
                INSERT INTO pemasukan
                    (
                        nomor_pemasukan,
                        tanggal,
                        sumber,
                        penjualan_sparepart_id,
                        kategori_id,
                        jenis_pembayaran,
                        nominal,
                        metode_pembayaran,
                        referensi,
                        keterangan,
                        created_by
                    )
                VALUES
                    (?, ?, 'PENJUALAN', ?, ?, ?, ?, NULL, ?, ?, ?)
            ");
            $stmt->execute([
                $nomorPemasukan,
                $purchase['tanggal'],
                $purchaseId,
                $kategoriId,
                'Pembayaran Penjualan Sparepart',
                $nominal,
                $purchase['nomor_penjualan'],
                'Pembayaran penjualan sparepart ' . $purchase['nomor_penjualan'],
                isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null
            ]);
        }

        return;
    }

    // Jika transaksi tidak lagi SELESAI, pemasukan otomatis dibatalkan
    // dengan menghapus catatan kas keluar yang dibuat sistem.
    if ($expense) {
        $stmt = $pdo->prepare("
            DELETE FROM pemasukan
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$expense['id']]);
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'detail') {
    header('Content-Type: application/json; charset=utf-8');

    try {

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id) {
            throw new RuntimeException('ID penjualan tidak valid.');
        }

        $purchase = getPurchase($pdo, (int)$id);

        if (!$purchase) {
            throw new RuntimeException('Data penjualan tidak ditemukan.');
        }

        $stmt = $pdo->prepare("
            SELECT
                d.id,
                d.penjualan_id,
                d.sparepart_id,
                s.kode,
                s.nama,
                s.part_number,
                s.merk,
                s.satuan,
                d.qty,
                d.harga_jual,
                d.hpp,
                d.diskon,
                d.subtotal,
                d.laba,
                d.keterangan
            FROM penjualan_sparepart_detail d
            INNER JOIN sparepart s ON s.id = d.sparepart_id
            WHERE d.penjualan_id = ?
            ORDER BY d.id ASC
        ");
        $stmt->execute([(int)$id]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $subtotal = 0;
        foreach ($details as $detail) {
            $subtotal += (float)$detail['subtotal'];
        }

        echo json_encode([
            'success' => true,
            'purchase' => $purchase,
            'details' => $details,
            'subtotal' => $subtotal
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| CRUD PEMBELIAN SPAREPART
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        /*
        |--------------------------------------------------------------
        | TAMBAH PEMBELIAN + DETAIL
        |--------------------------------------------------------------
        */
        if ($action === 'create') {
            $nomor = trim((string)($_POST['nomor_penjualan'] ?? ''));
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $customerId = filter_var(
                $_POST['customer_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $sparepartId = filter_var(
                $_POST['sparepart_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $qty = (float)($_POST['qty'] ?? 0);
            $harga_jual = (float)($_POST['harga'] ?? 0);
            $hpp = (float)($_POST['hpp'] ?? 0);
            $diskon = (float)($_POST['diskon'] ?? 0);
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($nomor === '' || $tanggal === '' || !$customerId || !$sparepartId) {
                redirectMessage(
                    'error',
                    'Nomor penjualan, tanggal, customer, dan sparepart wajib diisi.'
                );
            }

            if ($qty <= 0 || $harga_jual < 0 || $hpp < 0 || $diskon < 0) {
                redirectMessage(
                    'error',
                    'Qty harus lebih dari 0 dan nilai harga/hpp/diskon tidak boleh negatif.'
                );
            }

            if ($diskon > ($qty * $harga_jual)) {
                redirectMessage('error', 'Diskon tidak boleh lebih besar dari nilai barang.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM customer
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$customerId]);

            if (!$stmt->fetch()) {
                redirectMessage('error', 'Customer tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT id, kode, nama, stok, satuan
                FROM sparepart
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$sparepartId]);
            $sparepart = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sparepart) {
                redirectMessage('error', 'Sparepart tidak ditemukan.');
            }

            $pdo->beginTransaction();

            /*
            | Jika nomor penjualan sudah ada, detail baru masuk
            | ke nomor tersebut.
            */
            $stmt = $pdo->prepare("
                SELECT id, customer_id, status
                FROM penjualan_sparepart
                WHERE nomor_penjualan = ?
                LIMIT 1
            ");
            $stmt->execute([$nomor]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($purchase) {
                $purchaseId = (int)$purchase['id'];

                if ((int)$purchase['customer_id'] !== (int)$customerId) {
                    throw new RuntimeException(
                        'Nomor penjualan tersebut sudah digunakan oleh customer lain.'
                    );
                }

                if (strtoupper((string)$purchase['status']) === 'BATAL') {
                    throw new RuntimeException(
                        'Penjualan berstatus BATAL tidak dapat ditambahkan.'
                    );
                }
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO penjualan_sparepart
                        (
                            nomor_penjualan,
                            tanggal,
                            customer_id,
                            total,
                            status,
                            keterangan
                        )
                    VALUES
                        (?, ?, ?, 0, 'DRAFT', ?)
                ");

                $stmt->execute([
                    $nomor,
                    $tanggal,
                    $customerId,
                    $keterangan !== '' ? $keterangan : null
                ]);

                $purchaseId = (int)$pdo->lastInsertId();
            }

            $subtotal = ($qty * $harga_jual) - $diskon;
            $laba = $subtotal - ($qty * $hpp);

            $stmt = $pdo->prepare("
                INSERT INTO penjualan_sparepart_detail
                    (
                        penjualan_id,
                        sparepart_id,
                        qty,
                        harga_jual,
                        diskon,
                        subtotal,
                        hpp,
                        laba,
                        keterangan
                    )
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $purchaseId,
                $sparepartId,
                $qty,
                $harga_jual,
                $diskon,
                $subtotal,
                $hpp,
                $laba,
                $keterangan !== '' ? $keterangan : null
            ]);

            refreshPurchaseTotal($pdo, $purchaseId);
            syncPurchaseExpense($pdo, $purchaseId);

            $pdo->commit();

            redirectMessage('success', 'Penjualan sparepart berhasil ditambahkan.');
        }

        /*
        |--------------------------------------------------------------
        | EDIT HEADER
        |--------------------------------------------------------------
        */
        if ($action === 'update_header') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $nomor = trim((string)($_POST['nomor_penjualan'] ?? ''));
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $customerId = filter_var(
                $_POST['customer_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $status = strtoupper(trim((string)($_POST['status'] ?? 'DRAFT')));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            $allowedStatus = ['DRAFT', 'PROSES', 'SELESAI', 'BATAL'];

            if (!$id || $nomor === '' || $tanggal === '' || !$customerId) {
                redirectMessage('error', 'Data penjualan belum lengkap.');
            }

            if (!in_array($status, $allowedStatus, true)) {
                redirectMessage('error', 'Status penjualan tidak valid.');
            }

            $purchase = getPurchase($pdo, (int)$id);

            if (!$purchase) {
                redirectMessage('error', 'Penjualan tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM customer
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$customerId]);

            if (!$stmt->fetch()) {
                redirectMessage('error', 'Customer tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM penjualan_sparepart
                WHERE nomor_penjualan = ?
                  AND id <> ?
                LIMIT 1
            ");
            $stmt->execute([$nomor, $id]);

            if ($stmt->fetch()) {
                redirectMessage('error', 'Nomor penjualan sudah digunakan.');
            }

            $pdo->beginTransaction();

            $oldStatus = strtoupper((string)$purchase['status']);

            /*
            | Stok bertambah ketika status menjadi SELESAI.
            | Jika status SELESAI dibatalkan, stok dikembalikan.
            */
            if ($oldStatus !== 'SELESAI' && $status === 'SELESAI') {
                $stmt = $pdo->prepare("
                    SELECT sparepart_id, qty
                    FROM penjualan_sparepart_detail
                    WHERE penjualan_id = ?
                ");
                $stmt->execute([(int)$id]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $detail) {
                    stockDelta(
                        $pdo,
                        (int)$detail['sparepart_id'],
                        (float)$detail['qty']
                    );
                }
            } elseif ($oldStatus === 'SELESAI' && $status !== 'SELESAI') {
                $stmt = $pdo->prepare("
                    SELECT sparepart_id, qty
                    FROM penjualan_sparepart_detail
                    WHERE penjualan_id = ?
                ");
                $stmt->execute([(int)$id]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $detail) {
                    stockDelta(
                        $pdo,
                        (int)$detail['sparepart_id'],
                        -(float)$detail['qty']
                    );
                }
            }

            $stmt = $pdo->prepare("
                UPDATE penjualan_sparepart
                SET nomor_penjualan = ?,
                    tanggal = ?,
                    customer_id = ?,
                    status = ?,
                    keterangan = ?
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $nomor,
                $tanggal,
                $customerId,
                $status,
                $keterangan !== '' ? $keterangan : null,
                $id
            ]);

            syncPurchaseExpense($pdo, (int)$id);

            $pdo->commit();

            redirectMessage('success', 'Data penjualan berhasil diperbarui.');
        }

        /*
        |--------------------------------------------------------------
        | TAMBAH DETAIL KE NOTA YANG SUDAH ADA
        |--------------------------------------------------------------
        */
        if ($action === 'add_detail') {
            $purchaseId = filter_var(
                $_POST['penjualan_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $sparepartId = filter_var(
                $_POST['sparepart_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $qty = (float)($_POST['qty'] ?? 0);
            $harga_jual = (float)($_POST['harga'] ?? 0);
            $hpp = (float)($_POST['hpp'] ?? 0);
            $diskon = (float)($_POST['diskon'] ?? 0);
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if (!$purchaseId || !$sparepartId || $qty <= 0 || $harga_jual < 0 || $hpp < 0 || $diskon < 0) {
                redirectMessage('error', 'Data detail penjualan tidak valid.');
            }

            if ($diskon > ($qty * $harga_jual)) {
                redirectMessage('error', 'Diskon tidak boleh lebih besar dari nilai barang.');
            }

            $purchase = getPurchase($pdo, (int)$purchaseId);

            if (!$purchase) {
                redirectMessage('error', 'Penjualan tidak ditemukan.');
            }

            if (strtoupper((string)$purchase['status']) === 'BATAL') {
                redirectMessage('error', 'Penjualan BATAL tidak dapat ditambah.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM sparepart
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$sparepartId]);

            if (!$stmt->fetch()) {
                redirectMessage('error', 'Sparepart tidak ditemukan.');
            }

            $subtotal = ($qty * $harga_jual) - $diskon;
            $laba = $subtotal - ($qty * $hpp);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO penjualan_sparepart_detail
                    (
                        penjualan_id,
                        sparepart_id,
                        qty,
                        harga_jual,
                        diskon,
                        subtotal,
                        hpp,
                        laba,
                        keterangan
                    )
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $purchaseId,
                $sparepartId,
                $qty,
                $harga_jual,
                $diskon,
                $subtotal,
                $hpp,
                $laba,
                $keterangan !== '' ? $keterangan : null
            ]);

            /*
            | Jika nota sudah SELESAI, detail baru langsung masuk stok.
            */
            if (strtoupper((string)$purchase['status']) === 'SELESAI') {
                stockDelta($pdo, $sparepartId, $qty);
            }

            refreshPurchaseTotal($pdo, $purchaseId);
            syncPurchaseExpense($pdo, $purchaseId);

            $pdo->commit();

            redirectMessage('success', 'Detail sparepart berhasil ditambahkan.');
        }

        /*
        |--------------------------------------------------------------
        | EDIT DETAIL
        |--------------------------------------------------------------
        */
        if ($action === 'update_detail') {
            $detailId = filter_var(
                $_POST['detail_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $sparepartId = filter_var(
                $_POST['sparepart_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $qty = (float)($_POST['qty'] ?? 0);
            $harga_jual = (float)($_POST['harga'] ?? 0);
            $hpp = (float)($_POST['hpp'] ?? 0);
            $diskon = (float)($_POST['diskon'] ?? 0);
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if (!$detailId || !$sparepartId || $qty <= 0 || $harga_jual < 0 || $hpp < 0 || $diskon < 0) {
                redirectMessage('error', 'Data detail tidak valid.');
            }

            if ($diskon > ($qty * $harga_jual)) {
                redirectMessage('error', 'Diskon tidak boleh lebih besar dari nilai barang.');
            }

            $stmt = $pdo->prepare("
                SELECT
                    d.*,
                    p.status
                FROM penjualan_sparepart_detail d
                INNER JOIN penjualan_sparepart p
                    ON p.id = d.penjualan_id
                WHERE d.id = ?
                LIMIT 1
            ");
            $stmt->execute([$detailId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                redirectMessage('error', 'Detail penjualan tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM sparepart
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$sparepartId]);

            if (!$stmt->fetch()) {
                redirectMessage('error', 'Sparepart tidak ditemukan.');
            }

            $pdo->beginTransaction();

            /*
            | Bila nota SELESAI, koreksi stok:
            | keluarkan qty lama, masukkan qty baru.
            */
            if (strtoupper((string)$old['status']) === 'SELESAI') {
                stockDelta(
                    $pdo,
                    (int)$old['sparepart_id'],
                    -(float)$old['qty']
                );

                stockDelta(
                    $pdo,
                    $sparepartId,
                    $qty
                );
            }

            $subtotal = ($qty * $harga_jual) - $diskon;
            $laba = $subtotal - ($qty * $hpp);

            $stmt = $pdo->prepare("
                UPDATE penjualan_sparepart_detail
                SET sparepart_id = ?,
                    qty = ?,
                    harga_jual = ?,
                    diskon = ?,
                    subtotal = ?,
                    hpp = ?,
                    laba = ?,
                    keterangan = ?
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $sparepartId,
                $qty,
                $harga_jual,
                $diskon,
                $subtotal,
                $hpp,
                $laba,
                $keterangan !== '' ? $keterangan : null,
                $detailId
            ]);

            refreshPurchaseTotal($pdo, (int)$old['penjualan_id']);
            syncPurchaseExpense($pdo, (int)$old['penjualan_id']);

            $pdo->commit();

            redirectMessage('success', 'Detail penjualan berhasil diperbarui.');
        }

        /*
        |--------------------------------------------------------------
        | HAPUS DETAIL
        |--------------------------------------------------------------
        */
        if ($action === 'delete_detail') {
            $detailId = filter_var(
                $_POST['detail_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$detailId) {
                redirectMessage('error', 'Detail tidak valid.');
            }

            $stmt = $pdo->prepare("
                SELECT
                    d.id,
                    d.penjualan_id,
                    d.sparepart_id,
                    d.qty,
                    p.status
                FROM penjualan_sparepart_detail d
                INNER JOIN penjualan_sparepart p
                    ON p.id = d.penjualan_id
                WHERE d.id = ?
                LIMIT 1
            ");
            $stmt->execute([$detailId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$detail) {
                redirectMessage('error', 'Detail tidak ditemukan.');
            }

            $pdo->beginTransaction();

            if (strtoupper((string)$detail['status']) === 'SELESAI') {
                stockDelta(
                    $pdo,
                    (int)$detail['sparepart_id'],
                    -(float)$detail['qty']
                );
            }

            $stmt = $pdo->prepare("
                DELETE FROM penjualan_sparepart_detail
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$detailId]);

            refreshPurchaseTotal($pdo, (int)$detail['penjualan_id']);
            syncPurchaseExpense($pdo, (int)$detail['penjualan_id']);

            $pdo->commit();

            redirectMessage('success', 'Detail penjualan berhasil dihapus.');
        }

        /*
        |--------------------------------------------------------------
        | HAPUS HEADER / NOTA
        |--------------------------------------------------------------
        */
        if ($action === 'delete_purchase') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id) {
                redirectMessage('error', 'ID penjualan tidak valid.');
            }

            $purchase = getPurchase($pdo, (int)$id);

            if (!$purchase) {
                redirectMessage('error', 'Penjualan tidak ditemukan.');
            }

            $pdo->beginTransaction();

            if (strtoupper((string)$purchase['status']) === 'SELESAI') {
                $stmt = $pdo->prepare("
                    SELECT sparepart_id, qty
                    FROM penjualan_sparepart_detail
                    WHERE penjualan_id = ?
                ");
                $stmt->execute([(int)$id]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $detail) {
                    stockDelta(
                        $pdo,
                        (int)$detail['sparepart_id'],
                        -(float)$detail['qty']
                    );
                }
            }

            // Pemasukan penjualan sparepart memiliki FK RESTRICT,
            // sehingga harus dihapus lebih dahulu jika transaksi SELESAI.
            $stmt = $pdo->prepare("
                DELETE FROM pemasukan
                WHERE penjualan_sparepart_id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("
                DELETE FROM penjualan_sparepart
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);

            $pdo->commit();

            redirectMessage('success', 'Transaksi penjualan berhasil dihapus.');
        }

        redirectMessage('error', 'Aksi tidak dikenali.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        redirectMessage('error', $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
| DATA MASTER
|--------------------------------------------------------------------------
*/
// Generate Default Nomor Penjualan
$prefix = 'PSJ-' . date('Ymd') . '-';
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM penjualan_sparepart WHERE tanggal = CURDATE()");
$stmtCount->execute();
$countToday = (int)$stmtCount->fetchColumn() + 1;
$defaultNomorPenjualan = $prefix . str_pad((string)$countToday, 3, '0', STR_PAD_LEFT);
$customers = $pdo->query("
    SELECT id, nama
    FROM customer
    ORDER BY nama ASC
")->fetchAll(PDO::FETCH_ASSOC);

$spareparts = $pdo->query("
    SELECT
        s.id,
        s.kode,
        s.nama,
        s.part_number,
        s.merk,
        s.satuan,
        s.stok,
        p.harga AS harga_beli_terakhir
    FROM sparepart s
    LEFT JOIN (
        SELECT sparepart_id, MAX(id) as max_id
        FROM pembelian_sparepart_detail
        GROUP BY sparepart_id
    ) latest ON latest.sparepart_id = s.id
    LEFT JOIN pembelian_sparepart_detail p ON p.id = latest.max_id
    ORDER BY s.nama ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| DATA PEMBELIAN
|--------------------------------------------------------------------------
*/
$rows = $pdo->query("
    SELECT
        p.id,
        p.nomor_penjualan,
        p.tanggal,
        p.customer_id,
        s.nama AS customer_nama,
        p.status,
        p.total,
        p.keterangan,
        COUNT(d.id) AS jumlah_item
    FROM penjualan_sparepart p
    INNER JOIN customer s
        ON s.id = p.customer_id
    LEFT JOIN penjualan_sparepart_detail d
        ON d.penjualan_id = p.id
    GROUP BY
        p.id,
        p.nomor_penjualan,
        p.tanggal,
        p.customer_id,
        s.nama,
        p.status,
        p.total,
        p.keterangan
    ORDER BY p.tanggal DESC, p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$msg = trim((string)($_GET['msg'] ?? ''));
$msgType = trim((string)($_GET['msg_type'] ?? ''));

$extraHead = <<<'HTML'
<style>
/*
|--------------------------------------------------------------------------
| Style lokal halaman
|--------------------------------------------------------------------------
| Layout utama tetap mengikuti assets/css yang sudah digunakan aplikasi.
| Style ini hanya untuk komponen khusus transaksi penjualan sparepart.
|--------------------------------------------------------------------------
*/
.page-content {
    padding: 26px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 22px;
}

.page-heading h1 {
    margin: 0 0 4px;
    font-size: 25px;
    color: #071b3a;
}

.page-heading p {
    margin: 0;
    color: #71809a;
    font-size: 13px;
}

.card {
    background: #fff;
    border: 1px solid #dbe2ec;
    border-radius: 4px;
    overflow: hidden;
}

.card-header {
    padding: 16px 18px;
    border-bottom: 1px solid #dbe2ec;
    color: #0b2853;
}

.card-header > div {
    display: flex;
    align-items: center;
    gap: 10px;
}

.muted {
    color: #8290a7;
    font-size: 12px;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.data-table th,
.data-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #dbe2ec;
    text-align: left;
    font-size: 13px;
    color: #0b2853;
    vertical-align: middle;
}

.data-table thead th {
    background: #f5f7fa;
    font-weight: 600;
    white-space: nowrap;
}

.data-table tbody tr:hover {
    background: #fafcff;
}

.filter-row th {
    padding: 8px;
    background: #f8f9fb;
}

.filter-row input {
    width: 100%;
    min-width: 80px;
    box-sizing: border-box;
    padding: 8px 9px;
    border: 1px solid #bdcadc;
    border-radius: 4px;
    outline: none;
    background: #fff;
}

.filter-row input:focus,
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #1473e6;
    box-shadow: 0 0 0 2px rgba(20, 115, 230, .08);
}

.money-cell {
    text-align: right !important;
    white-space: nowrap;
}

.empty-cell {
    text-align: center !important;
    padding: 28px !important;
    color: #8a96aa !important;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.status-success {
    background: #10b95d;
    color: #fff;
}

.status-info {
    background: #0db6d1;
    color: #fff;
}

.status-warning {
    background: #f9b700;
    color: #111;
}

.status-danger {
    background: #e74c3c;
    color: #fff;
}

.table-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 12px 14px;
    color: #65758f;
    font-size: 13px;
}

.pagination {
    display: flex;
    align-items: center;
    gap: 7px;
}

.page-size {
    padding: 7px 25px 7px 9px;
    border: 1px solid #d1d9e5;
    border-radius: 4px;
    background: #fff;
}

.btn {
    border: 1px solid transparent;
    border-radius: 4px;
    padding: 9px 15px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    box-sizing: border-box;
}

.btn-sm {
    padding: 7px 12px;
    font-size: 12px;
}

.btn-xs {
    padding: 5px 8px;
    font-size: 11px;
}

.btn-primary {
    background: #086cff;
    color: #fff;
}

.btn-primary:hover {
    background: #0058d8;
}

.btn-outline {
    background: #fff;
    border-color: #7890b1;
    color: #58708f;
}

.btn-danger-outline {
    background: #fff;
    border-color: #ff5a64;
    color: #f04450;
}

.btn-light {
    background: #eef1f5;
    border-color: #d8dfe8;
    color: #52647d;
}

.btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.alert {
    padding: 12px 14px;
    border-radius: 4px;
    margin-bottom: 18px;
    font-size: 13px;
}

.alert-success {
    background: #ecfbf2;
    border: 1px solid #b7ebca;
    color: #158044;
}

.alert-danger {
    background: #fff0f1;
    border: 1px solid #ffc5c9;
    color: #c52f3c;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(8, 19, 37, .58);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    box-sizing: border-box;
}

.modal-backdrop.show {
    display: flex;
}

.modal-backdrop.show {
    touch-action: none;
}

.modal {
    touch-action: pan-y;
}

.modal {
    width: min(700px, 100%);
    max-height: calc(100vh - 36px);
    background: #fff;
    border-radius: 6px;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    display: block;
}

.modal-lg {
    width: min(850px, 100%);
}

.modal-xl {
    width: min(1180px, 100%);
}

.modal-header {
    padding: 16px 18px;
    border-bottom: 1px solid #dbe2ec;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.modal-header h2 {
    margin: 0 0 4px;
    color: #0a2347;
    font-size: 18px;
}

.modal-header p {
    margin: 0;
    color: #7c8ba1;
    font-size: 12px;
}

.modal-close {
    border: 0;
    background: transparent;
    color: #7b899e;
    font-size: 26px;
    cursor: pointer;
    line-height: 1;
}

.modal-body {
    overflow: visible;
    padding: 20px;
}

.modal-footer {
    border-top: 1px solid #dbe2ec;
    padding: 13px 18px;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.form-section-title {
    margin: 0 0 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid #dbe2ec;
    color: #17345d;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
}

.form-section-title.no-margin {
    margin-bottom: 0;
    border-bottom: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.form-group {
    min-width: 0;
}

.form-group-full {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    color: #263d60;
    font-size: 12px;
}

.form-group label span {
    color: #e53935;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 9px 10px;
    border: 1px solid #bdcadc;
    border-radius: 4px;
    background: #fff;
    color: #183457;
    outline: none;
    font: inherit;
    font-size: 13px;
}

.form-group textarea {
    resize: vertical;
}

.form-group input[readonly] {
    background: #f4f6f9;
}

.form-help {
    display: block;
    margin-top: 5px;
    color: #73839a;
    font-size: 11px;
}

.detail-summary {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    border: 1px solid #dbe2ec;
    margin-bottom: 18px;
}

.summary-item {
    padding: 13px;
    border-right: 1px solid #dbe2ec;
}

.summary-item:last-child {
    border-right: 0;
}

.summary-item span {
    display: block;
    color: #7a889d;
    font-size: 11px;
    margin-bottom: 5px;
}

.summary-item strong {
    color: #09264d;
    font-size: 13px;
}

.detail-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 12px;
}

.toolbar-actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.detail-table {
    min-width: 950px;
}

.detail-table tfoot th {
    background: #f8f9fb;
}

.table-subtext {
    display: block;
    margin-top: 3px;
    color: #8b97a8;
    font-size: 10px;
}

.inline-form {
    display: inline;
}

.stock-note {
    padding: 11px 12px;
    background: #f5f8fd;
    border: 1px solid #d9e3f2;
    border-radius: 4px;
    color: #5e718e;
    font-size: 11px;
    line-height: 1.5;
}

@media (max-width: 800px) {
    .page-content {
        padding: 15px;
    }

    .page-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .page-heading .btn {
        width: 100%;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group-full {
        grid-column: auto;
    }

    .detail-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .summary-item:nth-child(2n) {
        border-right: 0;
    }

    .summary-item {
        border-bottom: 1px solid #dbe2ec;
    }

    .table-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .pagination {
        width: 100%;
        justify-content: flex-end;
    }

    .modal-backdrop {
        padding: 10px;
        align-items: center;
        justify-content: center;
    }

    .modal {
        width: 100%;
        max-width: 850px;
        max-height: calc(100vh - 20px);
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        display: block;
    }

    @supports (max-height: 100dvh) {
        .modal {
            max-height: calc(100dvh - 20px);
        }
    }
}

@media (max-width: 480px) {
    .detail-summary {
        grid-template-columns: 1fr;
    }

    .summary-item,
    .summary-item:nth-child(2n) {
        border-right: 0;
    }

    .detail-toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .toolbar-actions {
        width: 100%;
    }

    .toolbar-actions .btn {
        flex: 1;
    }
}
</style>
HTML;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="page-heading">
        <div>
            <h1>Manajemen Penjualan Sparepart</h1>
            <p>Kelola transaksi penjualan sparepart dan penambahan stok.</p>
        </div>

        <button type="button" class="btn btn-primary" id="openCreatePurchaseBtn">
            + Tambah Penjualan
        </button>
    </div>

    <?php if ($msg !== ''): ?>
        <div class="alert <?php echo $msgType === 'success' ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo h($msg); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div>
                <strong>Daftar Penjualan Sparepart</strong>
                <span class="muted">
                    <?php echo count($rows); ?> transaksi
                </span>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table" id="purchaseTable">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th>Item</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                    <tr class="filter-row">
                        <th><input type="text" placeholder="Cari nomor"></th>
                        <th><input type="text" placeholder="Cari tanggal"></th>
                        <th><input type="text" placeholder="Cari customer"></th>
                        <th><input type="text" placeholder="Item"></th>
                        <th><input type="text" placeholder="Status"></th>
                        <th><input type="text" placeholder="Total"></th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="7" class="empty-cell">
                                Belum ada transaksi penjualan sparepart.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $status = strtoupper((string)$row['status']);
                            $statusClass = 'status-info';

                            if ($status === 'SELESAI') {
                                $statusClass = 'status-success';
                            } elseif ($status === 'BATAL') {
                                $statusClass = 'status-danger';
                            } elseif ($status === 'DRAFT') {
                                $statusClass = 'status-warning';
                            }
                            ?>
                            <tr
                                data-id="<?php echo h($row['id']); ?>"
                                data-nomor="<?php echo h($row['nomor_penjualan']); ?>"
                                data-tanggal="<?php echo h($row['tanggal']); ?>"
                                data-customer="<?php echo h($row['customer_nama']); ?>"
                                data-item="<?php echo h($row['jumlah_item']); ?>"
                                data-status="<?php echo h($status); ?>"
                                data-total="<?php echo h($row['total']); ?>"
                            >
                                <td>
                                    <strong><?php echo h($row['nomor_penjualan']); ?></strong>
                                </td>
                                <td><?php echo h(date('d-M-Y', strtotime($row['tanggal']))); ?></td>
                                <td><?php echo h($row['customer_nama']); ?></td>
                                <td><?php echo h($row['jumlah_item']); ?> item</td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo h($status); ?>
                                    </span>
                                </td>
                                <td class="money-cell">
                                    Rp <?php echo rupiah($row['total']); ?>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm"
                                        data-open-detail="<?php echo (int)$row['id']; ?>"
                                    >
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span id="tableInfo"></span>

            <div class="pagination">
                <select id="pageSize" class="page-size">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <button type="button" id="prevPage" class="btn btn-light btn-sm">Previous</button>
                <span id="pageNumber"></span>
                <button type="button" id="nextPage" class="btn btn-light btn-sm">Next</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL TAMBAH PEMBELIAN
============================================================ -->
<div class="modal-backdrop" id="createModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div>
                <h2>Tambah Penjualan Sparepart</h2>
                <p>Input satu item per transaksi detail.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>

        <form method="post" id="createForm">
            <input type="hidden" name="action" value="create">

            <div class="modal-body">
                <div class="form-section-title">Informasi Penjualan</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nomor Penjualan <span>*</span></label>
                        <input
                            type="text"
                            name="nomor_penjualan"
                            value="<?php echo h($defaultNomorPenjualan); ?>"
                            placeholder="PSJ-20260909-001"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Tanggal <span>*</span></label>
                        <input
                            type="date"
                            name="tanggal"
                            value="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                    </div>

                    <div class="form-group form-group-full">
                        <label>Customer <span>*</span></label>
                        <select name="customer_id" required>
                            <option value="">-- Pilih Customer --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo (int)$customer['id']; ?>">
                                    <?php echo h($customer['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section-title">Detail Sparepart</div>

                <div class="stock-note">
                    <strong>Harga penjualan:</strong> masukkan harga aktual per satuan dari transaksi ini.
                    Sistem tidak mengambil harga dari master <code>sparepart</code>.
                </div>

                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Sparepart <span>*</span></label>
                        <select name="sparepart_id" id="createSparepart" required>
                            <option value="">-- Pilih Sparepart --</option>
                            <?php foreach ($spareparts as $sparepart): ?>
                                <option
                                    value="<?php echo (int)$sparepart['id']; ?>"
                                    data-stok="<?php echo h($sparepart['stok']); ?>"
                                    data-satuan="<?php echo h($sparepart['satuan']); ?>"
                                    data-hpp="<?php echo (float)$sparepart['harga_beli_terakhir']; ?>"
                                >
                                    <?php
                                    echo h(
                                        $sparepart['kode'] . ' - ' .
                                        $sparepart['nama'] . ' | Stok: ' . rupiah($sparepart['stok'])
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="createSparepartInfo" class="form-help"></small>
                    </div>

                    <div class="form-group">
                        <label>Qty <span>*</span></label>
                        <input
                            type="number"
                            name="qty"
                            id="createQty"
                            min="0.01"
                            step="0.01"
                            value="1"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Harga Jual per Satuan <span>*</span></label>
                        <input
                            type="number"
                            name="harga"
                            id="createHarga"
                            min="0"
                            step="0.01"
                            value="0"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Harga Beli (HPP) per Satuan <span>*</span></label>
                        <input
                            type="number"
                            name="hpp"
                            id="createHpp"
                            min="0"
                            step="0.01"
                            value="0"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Diskon</label>
                        <input
                            type="number"
                            name="diskon"
                            id="createDiskon"
                            min="0"
                            step="0.01"
                            value="0"
                        >
                    </div>

                    <div class="form-group">
                        <label>Subtotal</label>
                        <input
                            type="text"
                            id="createSubtotal"
                            value="Rp 0"
                            readonly
                        >
                    </div>

                    <div class="form-group form-group-full">
                        <label>Keterangan</label>
                        <textarea
                            name="keterangan"
                            rows="3"
                            placeholder="Keterangan penjualan..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('createModal')">
                    Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL DETAIL
============================================================ -->
<div class="modal-backdrop" id="detailModal">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div>
                <h2>Detail Penjualan</h2>
                <p id="detailSubtitle">Memuat data...</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('detailModal')">&times;</button>
        </div>

        <div class="modal-body">
            <div class="detail-summary" id="detailSummary"></div>

            <div class="detail-toolbar">
                <div>
                    <div class="form-section-title no-margin">Daftar Sparepart</div>
                </div>

                <div class="toolbar-actions">
                    <button type="button" class="btn btn-outline btn-sm" id="editHeaderBtn">
                        Edit Penjualan
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="addDetailBtn">
                        + Tambah Sparepart
                    </button>
                    <button type="button" class="btn btn-danger-outline btn-sm" id="deletePurchaseBtn">
                        Hapus
                    </button>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table detail-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Nama Sparepart</th>
                            <th>Qty</th>
                            <th>Harga Jual</th>
                            <th>HPP</th>
                            <th>Diskon</th>
                            <th>Subtotal</th>
                            <th>Laba</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="detailBody">
                        <tr>
                            <td colspan="10" class="empty-cell">Memuat...</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="8" class="text-right">Total</th>
                            <th class="money-cell" id="detailTotal">Rp 0</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-light" onclick="closeModal('detailModal')">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL EDIT HEADER
============================================================ -->
<div class="modal-backdrop" id="editHeaderModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <h2>Edit Penjualan</h2>
                <p>Perbarui informasi transaksi.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('editHeaderModal')">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="update_header">
            <input type="hidden" name="id" id="editHeaderId">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nomor Penjualan <span>*</span></label>
                        <input type="text" name="nomor_penjualan" id="editHeaderNomor" required>
                    </div>

                    <div class="form-group">
                        <label>Tanggal <span>*</span></label>
                        <input type="date" name="tanggal" id="editHeaderTanggal" required>
                    </div>

                    <div class="form-group form-group-full">
                        <label>Customer <span>*</span></label>
                        <select name="customer_id" id="editHeaderCustomer" required>
                            <option value="">-- Pilih Customer --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo (int)$customer['id']; ?>">
                                    <?php echo h($customer['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status <span>*</span></label>
                        <select name="status" id="editHeaderStatus" required>
                            <option value="DRAFT">DRAFT</option>
                            <option value="PROSES">PROSES</option>
                            <option value="SELESAI">SELESAI</option>
                            <option value="BATAL">BATAL</option>
                        </select>
                    </div>

                    <div class="form-group form-group-full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="editHeaderKeterangan" rows="3"></textarea>
                    </div>
                </div>

                <div class="stock-note">
                    <strong>Catatan stok:</strong>
                    stok sparepart otomatis bertambah ketika transaksi berubah menjadi
                    <strong>SELESAI</strong>. Jika transaksi SELESAI diubah ke status lain,
                    stok akan dikurangi kembali. Harga penjualan disimpan pada detail transaksi,
                    bukan pada master sparepart.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('editHeaderModal')">
                    Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL TAMBAH DETAIL
============================================================ -->
<div class="modal-backdrop" id="addDetailModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <h2>Tambah Sparepart</h2>
                <p id="addDetailSubtitle">Tambahkan sparepart ke nota.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('addDetailModal')">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="add_detail">
            <input type="hidden" name="penjualan_id" id="addDetailPurchaseId">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Sparepart <span>*</span></label>
                        <select name="sparepart_id" id="addDetailSparepart" required>
                            <option value="">-- Pilih Sparepart --</option>
                            <?php foreach ($spareparts as $sparepart): ?>
                                <option
                                    value="<?php echo (int)$sparepart['id']; ?>"
                                    data-stok="<?php echo h($sparepart['stok']); ?>"
                                    data-satuan="<?php echo h($sparepart['satuan']); ?>"
                                    data-hpp="<?php echo (float)$sparepart['harga_beli_terakhir']; ?>"
                                >
                                    <?php
                                    echo h(
                                        $sparepart['kode'] . ' - ' .
                                        $sparepart['nama'] . ' | Stok: ' . rupiah($sparepart['stok'])
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="addDetailSparepartInfo" class="form-help"></small>
                    </div>

                    <div class="form-group">
                        <label>Qty <span>*</span></label>
                        <input type="number" name="qty" id="addDetailQty" min="0.01" step="0.01" value="1" required>
                    </div>

                    <div class="form-group">
                        <label>Harga Jual per Satuan <span>*</span></label>
                        <input type="number" name="harga" id="addDetailHarga" min="0" step="0.01" value="0" required>
                    </div>

                    <div class="form-group">
                        <label>Harga Beli (HPP) per Satuan <span>*</span></label>
                        <input type="number" name="hpp" id="addDetailHpp" min="0" step="0.01" value="0" required>
                    </div>

                    <div class="form-group">
                        <label>Diskon</label>
                        <input type="number" name="diskon" id="addDetailDiskon" min="0" step="0.01" value="0">
                    </div>

                    <div class="form-group">
                        <label>Subtotal</label>
                        <input type="text" id="addDetailSubtotal" value="Rp 0" readonly>
                    </div>

                    <div class="form-group form-group-full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" rows="3"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('addDetailModal')">
                    Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     MODAL EDIT DETAIL
============================================================ -->
<div class="modal-backdrop" id="editDetailModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <h2>Edit Detail Sparepart</h2>
                <p>Perbarui item penjualan.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('editDetailModal')">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="update_detail">
            <input type="hidden" name="detail_id" id="editDetailId">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Sparepart <span>*</span></label>
                        <select name="sparepart_id" id="editDetailSparepart" required>
                            <option value="">-- Pilih Sparepart --</option>
                            <?php foreach ($spareparts as $sparepart): ?>
                                <option
                                    value="<?php echo (int)$sparepart['id']; ?>"
                                    data-stok="<?php echo h($sparepart['stok']); ?>"
                                    data-satuan="<?php echo h($sparepart['satuan']); ?>"
                                    data-hpp="<?php echo (float)$sparepart['harga_beli_terakhir']; ?>"
                                >
                                    <?php
                                    echo h(
                                        $sparepart['kode'] . ' - ' .
                                        $sparepart['nama'] . ' | Stok: ' . rupiah($sparepart['stok'])
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Qty <span>*</span></label>
                        <input type="number" name="qty" id="editDetailQty" min="0.01" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Harga Jual per Satuan <span>*</span></label>
                        <input type="number" name="harga" id="editDetailHarga" min="0" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Harga Beli (HPP) per Satuan <span>*</span></label>
                        <input type="number" name="hpp" id="editDetailHpp" min="0" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Diskon</label>
                        <input type="number" name="diskon" id="editDetailDiskon" min="0" step="0.01">
                    </div>

                    <div class="form-group">
                        <label>Subtotal</label>
                        <input type="text" id="editDetailSubtotal" value="Rp 0" readonly>
                    </div>

                    <div class="form-group form-group-full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="editDetailKeterangan" rows="3"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('editDetailModal')">
                    Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var currentPurchase = null;

    function formatNumber(value) {
        var number = Number(value || 0);
        return new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 2
        }).format(number);
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.openModal = function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('show');
        document.body.classList.add('modal-open');
    };

    window.closeModal = function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('show');

        if (!document.querySelector('.modal-backdrop.show')) {
            document.body.classList.remove('modal-open');
        }
    };

    window.openCreateModal = function () {
        var form = document.getElementById('createForm');
        if (form) form.reset();

        var dateInput = document.querySelector('#createForm input[name="tanggal"]');
        if (dateInput) {
            dateInput.value = new Date().toISOString().slice(0, 10);
        }

        document.getElementById('createHarga').value = '0';
        document.getElementById('createHpp').value = '0';
        document.getElementById('createQty').value = '1';
        document.getElementById('createDiskon').value = '0';
        document.getElementById('createSubtotal').value = 'Rp 0';
        document.getElementById('createSparepartInfo').textContent = '';

        openModal('createModal');
    };

    function bindSparepartInfo(selectId, priceId, hppId, infoId) {
        var select = document.getElementById(selectId);
        var price = document.getElementById(priceId);
        var hpp = document.getElementById(hppId);
        var info = document.getElementById(infoId);

        if (!select) return;

        select.addEventListener('change', function () {
            var option = select.options[select.selectedIndex];

            if (!option || !option.value) {
                if (info) info.textContent = '';
                if (hpp) hpp.value = '0';
                return;
            }

            var stok = Number(option.dataset.stok || 0);
            var satuan = option.dataset.satuan || '';
            var hppVal = option.dataset.hpp || '0';

            if (info) {
                info.textContent = 'Stok saat ini: ' + formatNumber(stok) + (satuan ? ' ' + satuan : '') + '. Harga diisi sesuai harga transaksi penjualan.';
            }

            // Harga tidak lagi diambil dari tabel sparepart.
            // Harga transaksi wajib berasal dari input pengguna dan disimpan
            // pada penjualan_sparepart_detail.harga.
            if (price && (!price.value || price.value === '0')) price.value = '0';
            if (hpp) hpp.value = hppVal;
        });
    }

    function calculate(qtyId, priceId, discountId, resultId) {
        var qty = Number((document.getElementById(qtyId) ? document.getElementById(qtyId).value : 0) || 0);
        var price = Number((document.getElementById(priceId) ? document.getElementById(priceId).value : 0) || 0);
        var discount = Number((document.getElementById(discountId) ? document.getElementById(discountId).value : 0) || 0);

        var subtotal = Math.max(0, (qty * price) - discount);
        var result = document.getElementById(resultId);

        if (result) {
            result.value = 'Rp ' + formatNumber(subtotal);
        }
    }

    function calculateAll() {
        calculate('createQty', 'createHarga', 'createDiskon', 'createSubtotal');
        calculate('addDetailQty', 'addDetailHarga', 'addDetailDiskon', 'addDetailSubtotal');
        calculate('editDetailQty', 'editDetailHarga', 'editDetailDiskon', 'editDetailSubtotal');
    }

    ['createQty', 'createHarga', 'createDiskon',
     'addDetailQty', 'addDetailHarga', 'addDetailDiskon',
     'editDetailQty', 'editDetailHarga', 'editDetailDiskon'
    ].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', calculateAll);
    });

    bindSparepartInfo('createSparepart', 'createHarga', 'createHpp', 'createSparepartInfo');
    bindSparepartInfo('addDetailSparepart', 'addDetailHarga', 'addDetailHpp', 'addDetailSparepartInfo');

    var editSelect = document.getElementById('editDetailSparepart');
    if (editSelect) {
        editSelect.addEventListener('change', function () {
            // Jangan mengubah harga otomatis saat sparepart diganti.
            // Harga adalah histori transaksi dan harus diisi/ditinjau manual.
            calculateAll();
        });
    }

    window.openDetailModal = function (id) {
        currentPurchase = null;

        document.getElementById('detailBody').innerHTML =
            '<tr><td colspan="8" class="empty-cell">Memuat data...</td></tr>';

        document.getElementById('detailTotal').textContent = 'Rp 0';
        document.getElementById('detailSubtitle').textContent = 'Memuat data...';

        openModal('detailModal');

        fetch('penjualan_sparepart.php?ajax=detail&id=' + encodeURIComponent(id), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data.success) {
                throw new Error(data.message || 'Gagal mengambil data.');
            }

            currentPurchase = data.purchase;

            document.getElementById('detailSubtitle').textContent =
                data.purchase.nomor_penjualan +
                ' • ' +
                data.purchase.customer_nama;

            document.getElementById('detailSummary').innerHTML =
                '<div class="summary-item">' +
                    '<span>Nomor Penjualan</span>' +
                    '<strong>' + escapeHtml(data.purchase.nomor_penjualan) + '</strong>' +
                '</div>' +
                '<div class="summary-item">' +
                    '<span>Tanggal</span>' +
                    '<strong>' + escapeHtml(data.purchase.tanggal) + '</strong>' +
                '</div>' +
                '<div class="summary-item">' +
                    '<span>Customer</span>' +
                    '<strong>' + escapeHtml(data.purchase.customer_nama) + '</strong>' +
                '</div>' +
                '<div class="summary-item">' +
                    '<span>Status</span>' +
                    '<strong>' + escapeHtml(data.purchase.status) + '</strong>' +
                '</div>' +
                '<div class="summary-item">' +
                    '<span>Total</span>' +
                    '<strong>Rp ' + formatNumber(data.purchase.total) + '</strong>' +
                '</div>';

            renderDetails(data.details);
        })
        .catch(function (error) {
            document.getElementById('detailBody').innerHTML =
                '<tr><td colspan="8" class="empty-cell">' +
                escapeHtml(error.message) +
                '</td></tr>';
        });
    };

    function renderDetails(details) {
        var tbody = document.getElementById('detailBody');

        if (!details || details.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="8" class="empty-cell">' +
                'Belum ada detail sparepart.' +
                '</td></tr>';

            document.getElementById('detailTotal').textContent = 'Rp 0';
            return;
        }

        var html = '';
        var total = 0;

        details.forEach(function (detail, index) {
            total += Number(detail.subtotal || 0);

            html +=
                '<tr>' +
                    '<td>' + (index + 1) + '</td>' +
                    '<td><strong>' + escapeHtml(detail.kode) + '</strong></td>' +
                    '<td>' +
                        escapeHtml(detail.nama) +
                        (detail.part_number ?
                            '<small class="table-subtext">' +
                            escapeHtml(detail.part_number) +
                            '</small>' : '') +
                    '</td>' +
                    '<td>' +
                        formatNumber(detail.qty) +
                        (detail.satuan ? ' ' + escapeHtml(detail.satuan) : '') +
                    '</td>' +
                    '<td class="money-cell">Rp ' + formatNumber(detail.harga_jual) + '</td>' +
                    '<td class="money-cell">Rp ' + formatNumber(detail.hpp) + '</td>' +
                    '<td class="money-cell">Rp ' + formatNumber(detail.diskon) + '</td>' +
                    '<td class="money-cell"><strong>Rp ' + formatNumber(detail.subtotal) + '</strong></td>' +
                    '<td class="money-cell" style="' + (detail.laba < 0 ? 'color:red' : 'color:green') + '">Rp ' + formatNumber(detail.laba) + '</td>' +
                    '<td>' +
                        '<button type="button" class="btn btn-outline btn-xs" ' +
                            'onclick=\'openEditDetail(' + JSON.stringify(detail).replace(/'/g, '&#039;') + ')\'>' +
                            'Edit' +
                        '</button> ' +
                        '<form method="post" class="inline-form" onsubmit="return confirm(&#39;Hapus detail ini? Stok akan disesuaikan jika transaksi SELESAI.&#39;)">' +
                            '<input type="hidden" name="action" value="delete_detail">' +
                            '<input type="hidden" name="detail_id" value="' + Number(detail.id) + '">' +
                            '<button type="submit" class="btn btn-danger-outline btn-xs">Hapus</button>' +
                        '</form>' +
                    '</td>' +
                '</tr>';
        });

        tbody.innerHTML = html;
        document.getElementById('detailTotal').textContent =
            'Rp ' + formatNumber(total);
    }

    window.openEditDetail = function (detail) {
        document.getElementById('editDetailId').value = detail.id;
        document.getElementById('editDetailSparepart').value = detail.sparepart_id;
        document.getElementById('editDetailQty').value = detail.qty;
        document.getElementById('editDetailHarga').value = detail.harga_jual;
        document.getElementById('editDetailHpp').value = detail.hpp;
        document.getElementById('editDetailDiskon').value = detail.diskon;
        document.getElementById('editDetailKeterangan').value = detail.keterangan || '';

        calculateAll();
        openModal('editDetailModal');
    };

    var openCreatePurchaseBtn = document.getElementById('openCreatePurchaseBtn');
    if (openCreatePurchaseBtn) {
        openCreatePurchaseBtn.addEventListener('click', function () {
            openCreateModal();
        });
    }

    document.querySelectorAll('[data-open-detail]').forEach(function (button) {
        button.addEventListener('click', function () {
            var id = Number(button.getAttribute('data-open-detail'));
            if (id > 0) {
                openDetailModal(id);
            }
        });
    });

    document.getElementById('editHeaderBtn').addEventListener('click', function () {
        if (!currentPurchase) return;

        document.getElementById('editHeaderId').value = currentPurchase.id;
        document.getElementById('editHeaderNomor').value = currentPurchase.nomor_penjualan;
        document.getElementById('editHeaderTanggal').value = currentPurchase.tanggal;
        document.getElementById('editHeaderCustomer').value = currentPurchase.customer_id;
        document.getElementById('editHeaderStatus').value = currentPurchase.status;
        document.getElementById('editHeaderKeterangan').value = currentPurchase.keterangan || '';

        closeModal('detailModal');
        openModal('editHeaderModal');
    });

    document.getElementById('addDetailBtn').addEventListener('click', function () {
        if (!currentPurchase) return;

        document.getElementById('addDetailPurchaseId').value = currentPurchase.id;
        document.getElementById('addDetailSubtitle').textContent =
            'Tambahkan item ke ' + currentPurchase.nomor_penjualan;

        document.getElementById('addDetailSparepart').value = '';
        document.getElementById('addDetailQty').value = '1';
        document.getElementById('addDetailHarga').value = '0';
        document.getElementById('addDetailDiskon').value = '0';
        document.getElementById('addDetailSubtotal').value = 'Rp 0';
        document.getElementById('addDetailSparepartInfo').textContent = '';

        closeModal('detailModal');
        openModal('addDetailModal');
    });

    document.getElementById('deletePurchaseBtn').addEventListener('click', function () {
        if (!currentPurchase) return;

        if (!confirm(
            'Hapus transaksi ' +
            currentPurchase.nomor_penjualan +
            '? Semua detail akan ikut dihapus.'
        )) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'post';
        form.innerHTML =
            '<input type="hidden" name="action" value="delete_purchase">' +
            '<input type="hidden" name="id" value="' + Number(currentPurchase.id) + '">';

        document.body.appendChild(form);
        form.submit();
    });

    /*
    |--------------------------------------------------------------------------
    | FILTER + PAGINATION
    |--------------------------------------------------------------------------
    */
    var table = document.getElementById('purchaseTable');
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-id]'));
    var filterInputs = Array.prototype.slice.call(
        table.querySelectorAll('thead .filter-row input')
    );
    var pageSizeSelect = document.getElementById('pageSize');
    var prevButton = document.getElementById('prevPage');
    var nextButton = document.getElementById('nextPage');
    var tableInfo = document.getElementById('tableInfo');
    var pageNumber = document.getElementById('pageNumber');

    var currentPage = 1;

    function getFilteredRows() {
        return rows.filter(function (row) {
            return filterInputs.every(function (input, index) {
                var value = input.value.trim().toLowerCase();

                if (!value) return true;

                var field = ['nomor', 'tanggal', 'customer', 'item', 'status', 'total'][index];
                return String(row.dataset[field] || '').toLowerCase().includes(value);
            });
        });
    }

    function renderTable() {
        var filtered = getFilteredRows();
        var pageSize = Number(pageSizeSelect.value || 10);
        var totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        rows.forEach(function (row) {
            row.style.display = 'none';
        });

        var start = (currentPage - 1) * pageSize;
        var end = Math.min(start + pageSize, filtered.length);

        filtered.slice(start, end).forEach(function (row) {
            row.style.display = '';
        });

        tableInfo.textContent =
            filtered.length === 0
                ? 'Tidak ada data'
                : 'Menampilkan ' + (start + 1) + ' sampai ' + end +
                  ' dari ' + filtered.length + ' transaksi';

        pageNumber.textContent = currentPage + ' / ' + totalPages;

        prevButton.disabled = currentPage <= 1;
        nextButton.disabled = currentPage >= totalPages;
    }

    filterInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            currentPage = 1;
            renderTable();
        });
    });

    pageSizeSelect.addEventListener('change', function () {
        currentPage = 1;
        renderTable();
    });

    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    });

    nextButton.addEventListener('click', function () {
        var filtered = getFilteredRows();
        var totalPages = Math.max(
            1,
            Math.ceil(filtered.length / Number(pageSizeSelect.value || 10))
        );

        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    });

    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function (event) {
            if (event.target === backdrop) {
                closeModal(backdrop.id);
            }
        });
    });

    calculateAll();
    renderTable();
})();
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
