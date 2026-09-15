<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Pembelian Restorasi';
$adminBase = '../';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function qtyFormat($value): string
{
    $n = (float)$value;
    return floor($n) === $n
        ? number_format($n, 0, ',', '.')
        : number_format($n, 2, ',', '.');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_pembelian_restorasi'])) {
        $_SESSION['csrf_pembelian_restorasi'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_pembelian_restorasi'];
}

function verifyCsrf(): void
{
    if (!hash_equals(
        $_SESSION['csrf_pembelian_restorasi'] ?? '',
        (string)($_POST['csrf_token'] ?? '')
    )) {
        throw new RuntimeException('Token keamanan tidak valid. Silakan coba lagi.');
    }
}

function redirectMsg(string $type, string $message): void
{
    header('Location: pembelian_restorasi.php?' . $type . '=' . urlencode($message));
    exit;
}

/*
|--------------------------------------------------------------------------
| Nomor pembelian
|--------------------------------------------------------------------------
*/
function generatePurchaseNumber(PDO $pdo): string
{
    $prefix = 'PO-RST-' . date('Ymd') . '-';

    $stmt = $pdo->prepare("
        SELECT nomor_pembelian
        FROM pembelian_restorasi
        WHERE nomor_pembelian LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);

    $last = $stmt->fetchColumn();

    $number = 1;

    if ($last && preg_match('/-(\d+)$/', (string)$last, $m)) {
        $number = ((int)$m[1]) + 1;
    }

    return $prefix . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
}

/*
|--------------------------------------------------------------------------
| Hitung ulang total header
|--------------------------------------------------------------------------
*/
function refreshPurchaseTotal(PDO $pdo, int $purchaseId): float
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(subtotal), 0)
        FROM pembelian_restorasi_detail
        WHERE pembelian_id = ?
    ");
    $stmt->execute([$purchaseId]);

    $total = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        UPDATE pembelian_restorasi
        SET total = ?
        WHERE id = ?
    ");
    $stmt->execute([$total, $purchaseId]);

    return $total;
}

/*
|--------------------------------------------------------------------------
| Ambil pembelian
|--------------------------------------------------------------------------
*/
function getPurchase(PDO $pdo, int $id, bool $lock = false): ?array
{
    $sql = "
        SELECT
            p.*,
            s.nama AS supplier_nama
        FROM pembelian_restorasi p
        LEFT JOIN supplier s ON s.id = p.supplier_id
        WHERE p.id = ?
        LIMIT 1
    ";

    if ($lock) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/*
|--------------------------------------------------------------------------
| Sinkronisasi pengeluaran
|--------------------------------------------------------------------------
|
| Pembelian restorasi dibayar langsung.
| Ketika SELESAI:
|   - stok bertambah
|   - satu pengeluaran dibuat
|
| Ketika status keluar dari SELESAI:
|   - stok dikurangi
|   - pengeluaran terkait dihapus
|
*/
function syncPurchaseExpense(PDO $pdo, int $purchaseId): void
{
    $purchase = getPurchase($pdo, $purchaseId, true);

    if (!$purchase) {
        throw new RuntimeException('Pembelian restorasi tidak ditemukan.');
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            nomor_pengeluaran,
            nominal
        FROM pengeluaran
        WHERE pembelian_restorasi_id = ?
        LIMIT 1
    ");
    $stmt->execute([$purchaseId]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (strtoupper((string)$purchase['status']) !== 'SELESAI') {
        if ($expense) {
            $stmt = $pdo->prepare("
                DELETE FROM pengeluaran
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([(int)$expense['id']]);
        }

        return;
    }

    $total = (float)$purchase['total'];

    if ($total <= 0) {
        throw new RuntimeException(
            'Pembelian restorasi tidak dapat diselesaikan karena total masih Rp 0.'
        );
    }

    if ($expense) {
        $stmt = $pdo->prepare("
            UPDATE pengeluaran
            SET tanggal = ?,
                nominal = ?,
                jenis_pengeluaran = ?,
                referensi = ?,
                keterangan = ?
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $purchase['tanggal'],
            $total,
            'Pembayaran Pembelian Restorasi',
            $purchase['nomor_pembelian'],
            'Pengeluaran pembelian bahan restorasi ' . $purchase['nomor_pembelian'],
            (int)$expense['id']
        ]);

        return;
    }

    $nomor = 'PK-RST-' . date('Ymd', strtotime((string)$purchase['tanggal']))
        . '-' . strtoupper(bin2hex(random_bytes(4)));

    $stmt = $pdo->prepare("
        INSERT INTO pengeluaran (
            nomor_pengeluaran,
            tanggal,
            sumber,
            pembelian_pembayaran_id,
            pembelian_sparepart_id,
            pembelian_restorasi_id,
            kategori_id,
            jenis_pengeluaran,
            nominal,
            metode_pembayaran,
            referensi,
            keterangan,
            created_by
        )
        VALUES (
            ?, ?, 'PEMBELIAN',
            NULL, NULL, ?,
            NULL, ?, ?, NULL, ?, ?, ?
        )
    ");

    $stmt->execute([
        $nomor,
        $purchase['tanggal'],
        $purchaseId,
        'Pembayaran Pembelian Restorasi',
        $total,
        $purchase['nomor_pembelian'],
        'Pengeluaran pembelian bahan restorasi ' . $purchase['nomor_pembelian'],
        $_SESSION['admin_id'] ?? null
    ]);
}

/*
|--------------------------------------------------------------------------
| Perubahan stok
|--------------------------------------------------------------------------
*/
function changeRestorasiStock(
    PDO $pdo,
    int $restorasiId,
    float $delta
): void {
    $stmt = $pdo->prepare("
        SELECT stok
        FROM restorasi
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$restorasiId]);

    $stok = $stmt->fetchColumn();

    if ($stok === false) {
        throw new RuntimeException('Bahan restorasi tidak ditemukan.');
    }

    $newStock = (float)$stok + $delta;

    if ($newStock < -0.000001) {
        throw new RuntimeException(
            'Stok bahan restorasi tidak mencukupi untuk transaksi ini.'
        );
    }

    if ($newStock < 0) {
        $newStock = 0;
    }

    $stmt = $pdo->prepare("
        UPDATE restorasi
        SET stok = ?
        WHERE id = ?
    ");
    $stmt->execute([$newStock, $restorasiId]);
}

function adjustPurchaseStock(
    PDO $pdo,
    int $purchaseId,
    float $direction
): void {
    $stmt = $pdo->prepare("
        SELECT restorasi_id, qty
        FROM pembelian_restorasi_detail
        WHERE pembelian_id = ?
        ORDER BY id
        FOR UPDATE
    ");
    $stmt->execute([$purchaseId]);

    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($details as $detail) {
        changeRestorasiStock(
            $pdo,
            (int)$detail['restorasi_id'],
            ((float)$detail['qty']) * $direction
        );
    }
}

/*
|--------------------------------------------------------------------------
| CSRF + CRUD
|--------------------------------------------------------------------------
*/
$csrf = csrfToken();

$message = (string)($_GET['success'] ?? '');
$error = (string)($_GET['error'] ?? '');

$openModal = '';
$openDetail = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        verifyCsrf();

        /*
         * ---------------------------------------------------------------
         * Tambah pembelian
         * ---------------------------------------------------------------
         */
        if ($action === 'add_purchase') {
            $nomor = trim((string)($_POST['nomor_pembelian'] ?? ''));
            $tanggal = (string)($_POST['tanggal'] ?? date('Y-m-d'));
            $supplierId = (int)($_POST['supplier_id'] ?? 0);
            $restorasiId = (int)($_POST['restorasi_id'] ?? 0);
            $qty = (float)($_POST['qty'] ?? 0);
            $harga = (float)($_POST['harga'] ?? 0);
            $diskon = (float)($_POST['diskon'] ?? 0);
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($nomor === '') {
                $nomor = generatePurchaseNumber($pdo);
            }

            if ($tanggal === '') {
                throw new RuntimeException('Tanggal pembelian wajib diisi.');
            }

            if ($supplierId <= 0) {
                throw new RuntimeException('Supplier wajib dipilih.');
            }

            if ($restorasiId <= 0) {
                throw new RuntimeException('Bahan restorasi wajib dipilih.');
            }

            if ($qty <= 0) {
                throw new RuntimeException('Qty harus lebih besar dari 0.');
            }

            if ($harga < 0) {
                throw new RuntimeException('Harga tidak boleh negatif.');
            }

            if ($diskon < 0) {
                throw new RuntimeException('Diskon tidak boleh negatif.');
            }

            $subtotal = ($qty * $harga) - $diskon;

            if ($subtotal < 0) {
                throw new RuntimeException(
                    'Diskon tidak boleh lebih besar dari nilai pembelian.'
                );
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM pembelian_restorasi
                WHERE nomor_pembelian = ?
                LIMIT 1
            ");
            $stmt->execute([$nomor]);

            if ($stmt->fetch()) {
                throw new RuntimeException('Nomor pembelian sudah digunakan.');
            }

            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO pembelian_restorasi (
                        nomor_pembelian,
                        tanggal,
                        supplier_id,
                        total,
                        status,
                        keterangan,
                        created_by
                    )
                    VALUES (?, ?, ?, ?, 'DRAFT', ?, ?)
                ");

                $stmt->execute([
                    $nomor,
                    $tanggal,
                    $supplierId,
                    $subtotal,
                    $keterangan !== '' ? $keterangan : null,
                    $_SESSION['admin_id'] ?? null
                ]);

                $purchaseId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO pembelian_restorasi_detail (
                        pembelian_id,
                        restorasi_id,
                        qty,
                        harga,
                        diskon,
                        keterangan
                    )
                    VALUES (?, ?, ?, ?, ?, NULL)
                ");

                $stmt->execute([
                    $purchaseId,
                    $restorasiId,
                    $qty,
                    $harga,
                    $diskon
                ]);

                refreshPurchaseTotal($pdo, $purchaseId);

                $pdo->commit();

                redirectMsg(
                    'success',
                    'Pembelian restorasi ' . $nomor . ' berhasil dibuat.'
                );
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        }

        /*
         * ---------------------------------------------------------------
         * Update header / status
         * ---------------------------------------------------------------
         */
        if ($action === 'update_header') {
            $id = (int)($_POST['id'] ?? 0);
            $tanggal = (string)($_POST['tanggal'] ?? '');
            $supplierId = (int)($_POST['supplier_id'] ?? 0);
            $status = strtoupper(trim((string)($_POST['status'] ?? 'DRAFT')));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($id <= 0) {
                throw new RuntimeException('Pembelian tidak valid.');
            }

            if (!in_array($status, ['DRAFT', 'PROSES', 'SELESAI', 'BATAL'], true)) {
                throw new RuntimeException('Status pembelian tidak valid.');
            }

            if ($supplierId <= 0) {
                throw new RuntimeException('Supplier wajib dipilih.');
            }

            $pdo->beginTransaction();

            try {
                $purchase = getPurchase($pdo, $id, true);

                if (!$purchase) {
                    throw new RuntimeException('Pembelian tidak ditemukan.');
                }

                $oldStatus = strtoupper((string)$purchase['status']);

                if ($status === 'SELESAI') {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM pembelian_restorasi_detail
                        WHERE pembelian_id = ?
                    ");
                    $stmt->execute([$id]);

                    if ((int)$stmt->fetchColumn() === 0) {
                        throw new RuntimeException(
                            'Pembelian belum memiliki detail bahan restorasi.'
                        );
                    }

                    $total = refreshPurchaseTotal($pdo, $id);

                    if ($total <= 0) {
                        throw new RuntimeException(
                            'Pembelian tidak dapat diselesaikan karena total masih Rp 0.'
                        );
                    }
                }

                /*
                 * Keluar dari SELESAI:
                 * kembalikan stok terlebih dahulu.
                 */
                if ($oldStatus === 'SELESAI' && $status !== 'SELESAI') {
                    adjustPurchaseStock($pdo, $id, -1);
                }

                /*
                 * Masuk ke SELESAI:
                 * tambahkan stok.
                 */
                if ($oldStatus !== 'SELESAI' && $status === 'SELESAI') {
                    adjustPurchaseStock($pdo, $id, 1);
                }

                $stmt = $pdo->prepare("
                    UPDATE pembelian_restorasi
                    SET tanggal = ?,
                        supplier_id = ?,
                        status = ?,
                        keterangan = ?
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $tanggal,
                    $supplierId,
                    $status,
                    $keterangan !== '' ? $keterangan : null,
                    $id
                ]);

                refreshPurchaseTotal($pdo, $id);
                syncPurchaseExpense($pdo, $id);

                $pdo->commit();

                redirectMsg(
                    'success',
                    'Pembelian restorasi berhasil diperbarui.'
                );
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        }

        /*
         * ---------------------------------------------------------------
         * Tambah detail
         * ---------------------------------------------------------------
         */
        if ($action === 'add_detail') {
            $purchaseId = (int)($_POST['pembelian_id'] ?? 0);
            $restorasiId = (int)($_POST['restorasi_id'] ?? 0);
            $qty = (float)($_POST['qty'] ?? 0);
            $harga = (float)($_POST['harga'] ?? 0);
            $diskon = (float)($_POST['diskon'] ?? 0);
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($purchaseId <= 0 || $restorasiId <= 0) {
                throw new RuntimeException('Data detail tidak valid.');
            }

            if ($qty <= 0) {
                throw new RuntimeException('Qty harus lebih besar dari 0.');
            }

            if ($harga < 0 || $diskon < 0) {
                throw new RuntimeException('Harga dan diskon tidak boleh negatif.');
            }

            if (($qty * $harga) - $diskon < 0) {
                throw new RuntimeException(
                    'Diskon tidak boleh lebih besar dari nilai pembelian.'
                );
            }

            $pdo->beginTransaction();

            try {
                $purchase = getPurchase($pdo, $purchaseId, true);

                if (!$purchase) {
                    throw new RuntimeException('Pembelian tidak ditemukan.');
                }

                if (strtoupper((string)$purchase['status']) === 'BATAL') {
                    throw new RuntimeException(
                        'Pembelian BATAL tidak dapat ditambahkan detail.'
                    );
                }

                $stmt = $pdo->prepare("
                    INSERT INTO pembelian_restorasi_detail (
                        pembelian_id,
                        restorasi_id,
                        qty,
                        harga,
                        diskon,
                        keterangan
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $purchaseId,
                    $restorasiId,
                    $qty,
                    $harga,
                    $diskon,
                    $keterangan !== '' ? $keterangan : null
                ]);

                if (strtoupper((string)$purchase['status']) === 'SELESAI') {
                    changeRestorasiStock($pdo, $restorasiId, $qty);
                }

                refreshPurchaseTotal($pdo, $purchaseId);
                syncPurchaseExpense($pdo, $purchaseId);

                $pdo->commit();

                redirectMsg(
                    'success',
                    'Detail pembelian berhasil ditambahkan.'
                );
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        }

        /*
         * ---------------------------------------------------------------
         * Edit detail
         * ---------------------------------------------------------------
         */
        if ($action === 'update_detail') {
            $detailId = (int)($_POST['id'] ?? 0);
            $purchaseId = (int)($_POST['pembelian_id'] ?? 0);
            $restorasiId = (int)($_POST['restorasi_id'] ?? 0);
            $qty = (float)($_POST['qty'] ?? 0);
            $harga = (float)($_POST['harga'] ?? 0);
            $diskon = (float)($_POST['diskon'] ?? 0);
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($detailId <= 0 || $purchaseId <= 0 || $restorasiId <= 0) {
                throw new RuntimeException('Data detail tidak valid.');
            }

            if ($qty <= 0) {
                throw new RuntimeException('Qty harus lebih besar dari 0.');
            }

            if ($harga < 0 || $diskon < 0) {
                throw new RuntimeException('Harga dan diskon tidak boleh negatif.');
            }

            if (($qty * $harga) - $diskon < 0) {
                throw new RuntimeException(
                    'Diskon tidak boleh lebih besar dari nilai pembelian.'
                );
            }

            $pdo->beginTransaction();

            try {
                $purchase = getPurchase($pdo, $purchaseId, true);

                if (!$purchase) {
                    throw new RuntimeException('Pembelian tidak ditemukan.');
                }

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM pembelian_restorasi_detail
                    WHERE id = ?
                      AND pembelian_id = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmt->execute([$detailId, $purchaseId]);

                $old = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$old) {
                    throw new RuntimeException('Detail pembelian tidak ditemukan.');
                }

                $isComplete =
                    strtoupper((string)$purchase['status']) === 'SELESAI';

                if ($isComplete) {
                    /*
                     * Kembalikan stok lama.
                     */
                    changeRestorasiStock(
                        $pdo,
                        (int)$old['restorasi_id'],
                        -(float)$old['qty']
                    );
                }

                /*
                 * Jika bahan berubah, tambahkan stok ke bahan baru.
                 */
                if ($isComplete) {
                    changeRestorasiStock(
                        $pdo,
                        $restorasiId,
                        $qty
                    );
                }

                $stmt = $pdo->prepare("
                    UPDATE pembelian_restorasi_detail
                    SET restorasi_id = ?,
                        qty = ?,
                        harga = ?,
                        diskon = ?,
                        keterangan = ?
                    WHERE id = ?
                      AND pembelian_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $restorasiId,
                    $qty,
                    $harga,
                    $diskon,
                    $keterangan !== '' ? $keterangan : null,
                    $detailId,
                    $purchaseId
                ]);

                refreshPurchaseTotal($pdo, $purchaseId);
                syncPurchaseExpense($pdo, $purchaseId);

                $pdo->commit();

                redirectMsg(
                    'success',
                    'Detail pembelian berhasil diperbarui.'
                );
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        }

        /*
         * ---------------------------------------------------------------
         * Hapus detail
         * ---------------------------------------------------------------
         */
        if ($action === 'delete_detail') {
            $detailId = (int)($_POST['id'] ?? 0);
            $purchaseId = (int)($_POST['pembelian_id'] ?? 0);

            if ($detailId <= 0 || $purchaseId <= 0) {
                throw new RuntimeException('Data detail tidak valid.');
            }

            $pdo->beginTransaction();

            try {
                $purchase = getPurchase($pdo, $purchaseId, true);

                if (!$purchase) {
                    throw new RuntimeException('Pembelian tidak ditemukan.');
                }

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM pembelian_restorasi_detail
                    WHERE id = ?
                      AND pembelian_id = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmt->execute([$detailId, $purchaseId]);

                $detail = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$detail) {
                    throw new RuntimeException('Detail pembelian tidak ditemukan.');
                }

                if (strtoupper((string)$purchase['status']) === 'SELESAI') {
                    changeRestorasiStock(
                        $pdo,
                        (int)$detail['restorasi_id'],
                        -(float)$detail['qty']
                    );
                }

                $stmt = $pdo->prepare("
                    DELETE FROM pembelian_restorasi_detail
                    WHERE id = ?
                      AND pembelian_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$detailId, $purchaseId]);

                refreshPurchaseTotal($pdo, $purchaseId);
                syncPurchaseExpense($pdo, $purchaseId);

                $pdo->commit();

                redirectMsg(
                    'success',
                    'Detail pembelian berhasil dihapus.'
                );
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        }

        /*
         * ---------------------------------------------------------------
         * Hapus pembelian
         * ---------------------------------------------------------------
         */
        if ($action === 'delete_purchase') {
            $purchaseId = (int)($_POST['id'] ?? 0);

            if ($purchaseId <= 0) {
                throw new RuntimeException('Pembelian tidak valid.');
            }

            $pdo->beginTransaction();

            try {
                $purchase = getPurchase($pdo, $purchaseId, true);

                if (!$purchase) {
                    throw new RuntimeException('Pembelian tidak ditemukan.');
                }

                if (strtoupper((string)$purchase['status']) === 'SELESAI') {
                    adjustPurchaseStock($pdo, $purchaseId, -1);
                }

                /*
                 * Hapus pengeluaran terlebih dahulu karena FK RESTRICT.
                 */
                $stmt = $pdo->prepare("
                    DELETE FROM pengeluaran
                    WHERE pembelian_restorasi_id = ?
                ");
                $stmt->execute([$purchaseId]);

                $stmt = $pdo->prepare("
                    DELETE FROM pembelian_restorasi_detail
                    WHERE pembelian_id = ?
                ");
                $stmt->execute([$purchaseId]);

                $stmt = $pdo->prepare("
                    DELETE FROM pembelian_restorasi
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->execute([$purchaseId]);

                $pdo->commit();

                redirectMsg(
                    'success',
                    'Pembelian restorasi berhasil dihapus.'
                );
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        }

        throw new RuntimeException('Aksi tidak dikenal.');

    } catch (Throwable $e) {
        $error = $e->getMessage();

        if (!empty($_POST['pembelian_id'])) {
            $openDetail = (int)$_POST['pembelian_id'];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Master supplier dan restorasi
|--------------------------------------------------------------------------
*/
$suppliers = [];
$restorasiMaster = [];

try {
    $stmt = $pdo->query("
        SELECT id, nama
        FROM supplier
        ORDER BY nama ASC
    ");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $suppliers = [];
}

try {
    $stmt = $pdo->query("
        SELECT id, kode, nama, jenis, merk, satuan, stok
        FROM restorasi
        ORDER BY nama ASC
    ");
    $restorasiMaster = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $restorasiMaster = [];
}

/*
|--------------------------------------------------------------------------
| Daftar pembelian
|--------------------------------------------------------------------------
*/
$purchases = [];

try {
    $stmt = $pdo->query("
        SELECT
            p.id,
            p.nomor_pembelian,
            p.tanggal,
            p.supplier_id,
            p.total,
            p.status,
            p.keterangan,
            s.nama AS supplier_nama,
            COUNT(d.id) AS jumlah_item
        FROM pembelian_restorasi p
        LEFT JOIN supplier s ON s.id = p.supplier_id
        LEFT JOIN pembelian_restorasi_detail d
            ON d.pembelian_id = p.id
        GROUP BY
            p.id,
            p.nomor_pembelian,
            p.tanggal,
            p.supplier_id,
            p.total,
            p.status,
            p.keterangan,
            s.nama
        ORDER BY p.id DESC
    ");

    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error !== '' ? $error : $e->getMessage();
}

/*
|--------------------------------------------------------------------------
| Detail yang sedang dibuka
|--------------------------------------------------------------------------
*/
$detailPurchase = null;
$details = [];

if ($openDetail > 0) {
    $detailPurchase = getPurchase($pdo, $openDetail);

    if ($detailPurchase) {
        $stmt = $pdo->prepare("
            SELECT
                d.*,
                r.kode,
                r.nama,
                r.jenis,
                r.merk,
                r.satuan
            FROM pembelian_restorasi_detail d
            INNER JOIN restorasi r
                ON r.id = d.restorasi_id
            WHERE d.pembelian_id = ?
            ORDER BY d.id ASC
        ");
        $stmt->execute([$openDetail]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/*
|--------------------------------------------------------------------------
| AJAX DETAIL
|--------------------------------------------------------------------------
*/
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detail') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id) {
            throw new RuntimeException('ID pembelian tidak valid.');
        }

        $purchase = getPurchase($pdo, (int)$id);

        if (!$purchase) {
            throw new RuntimeException('Data pembelian restorasi tidak ditemukan.');
        }

        $stmt = $pdo->prepare("\n            SELECT\n                d.id,\n                d.pembelian_id,\n                d.restorasi_id,\n                r.kode,\n                r.nama,\n                r.jenis,\n                r.merk,\n                r.satuan,\n                d.qty,\n                d.harga,\n                d.diskon,\n                d.subtotal,\n                d.keterangan\n            FROM pembelian_restorasi_detail d\n            INNER JOIN restorasi r ON r.id = d.restorasi_id\n            WHERE d.pembelian_id = ?\n            ORDER BY d.id ASC\n        ");
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

$extraHead = <<<'HTML'
<style>
/*
|--------------------------------------------------------------------------
| Style lokal halaman
|--------------------------------------------------------------------------
| Layout utama tetap mengikuti assets/css yang sudah digunakan aplikasi.
| Style ini hanya untuk komponen khusus transaksi pembelian restorasi.
|--------------------------------------------------------------------------
*/
.page-content { padding: 26px; }
.page-heading { display:flex; justify-content:space-between; align-items:center; gap:20px; margin-bottom:22px; }
.page-heading h1 { margin:0 0 4px; font-size:25px; color:#071b3a; }
.page-heading p { margin:0; color:#71809a; font-size:13px; }
.card { background:#fff; border:1px solid #dbe2ec; border-radius:4px; overflow:hidden; }
.card-header { padding:16px 18px; border-bottom:1px solid #dbe2ec; color:#0b2853; }
.card-header > div { display:flex; align-items:center; gap:10px; }
.muted { color:#8290a7; font-size:12px; }
.table-wrap { width:100%; overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; min-width:950px; }
.data-table th,.data-table td { padding:12px 10px; border-bottom:1px solid #dbe2ec; text-align:left; font-size:13px; color:#0b2853; vertical-align:middle; }
.data-table thead th { background:#f5f7fa; font-weight:600; white-space:nowrap; }
.data-table tbody tr:hover { background:#fafcff; }
.filter-row th { padding:8px; background:#f8f9fb; }
.filter-row input { width:100%; min-width:80px; box-sizing:border-box; padding:8px 9px; border:1px solid #bdcadc; border-radius:4px; outline:none; background:#fff; }
.filter-row input:focus,.form-group input:focus,.form-group select:focus,.form-group textarea:focus { border-color:#1473e6; box-shadow:0 0 0 2px rgba(20,115,230,.08); }
.money-cell { text-align:right !important; white-space:nowrap; }
.empty-cell { text-align:center !important; padding:28px !important; color:#8a96aa !important; }
.status-badge { display:inline-block; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600; }
.status-success { background:#10b95d; color:#fff; }
.status-info { background:#0db6d1; color:#fff; }
.status-warning { background:#f9b700; color:#111; }
.status-danger { background:#e74c3c; color:#fff; }
.table-footer { display:flex; justify-content:space-between; align-items:center; gap:15px; padding:12px 14px; color:#65758f; font-size:13px; }
.pagination { display:flex; align-items:center; gap:7px; }
.page-size { padding:7px 25px 7px 9px; border:1px solid #d1d9e5; border-radius:4px; background:#fff; }
.btn { border:1px solid transparent; border-radius:4px; padding:9px 15px; cursor:pointer; font-size:13px; text-decoration:none; display:inline-flex; justify-content:center; align-items:center; gap:5px; box-sizing:border-box; }
.btn-sm { padding:7px 12px; font-size:12px; }
.btn-xs { padding:5px 8px; font-size:11px; }
.btn-primary { background:#086cff; color:#fff; }
.btn-primary:hover { background:#0058d8; }
.btn-outline { background:#fff; border-color:#7890b1; color:#58708f; }
.btn-danger-outline { background:#fff; border-color:#ff5a64; color:#f04450; }
.btn-light { background:#eef1f5; border-color:#d8dfe8; color:#52647d; }
.btn:disabled { opacity:.45; cursor:not-allowed; }
.alert { padding:12px 14px; border-radius:4px; margin-bottom:18px; font-size:13px; }
.alert-success { background:#ecfbf2; border:1px solid #b7ebca; color:#158044; }
.alert-danger { background:#fff0f1; border:1px solid #ffc5c9; color:#c52f3c; }
.modal-backdrop { position:fixed; inset:0; z-index:9999; background:rgba(8,19,37,.58); display:none; align-items:center; justify-content:center; padding:18px; box-sizing:border-box; }
.modal-backdrop.show { display:flex; touch-action:none; }
.modal { width:min(700px,100%); max-height:calc(100vh - 36px); background:#fff; border-radius:6px; overflow-y:auto; overflow-x:hidden; -webkit-overflow-scrolling:touch; box-shadow:0 20px 60px rgba(0,0,0,.25); display:block; touch-action:pan-y; }
.modal-lg { width:min(850px,100%); }
.modal-xl { width:min(1180px,100%); }
.modal-header { padding:16px 18px; border-bottom:1px solid #dbe2ec; display:flex; justify-content:space-between; align-items:flex-start; gap:15px; }
.modal-header h2 { margin:0 0 4px; color:#0a2347; font-size:18px; }
.modal-header p { margin:0; color:#7c8ba1; font-size:12px; }
.modal-close { border:0; background:transparent; color:#7b899e; font-size:26px; cursor:pointer; line-height:1; }
.modal-body { overflow:visible; padding:20px; }
.modal-footer { border-top:1px solid #dbe2ec; padding:13px 18px; display:flex; justify-content:flex-end; gap:8px; }
.form-section-title { margin:0 0 14px; padding-bottom:8px; border-bottom:1px solid #dbe2ec; color:#17345d; font-weight:600; font-size:12px; text-transform:uppercase; }
.form-section-title.no-margin { margin-bottom:0; border-bottom:0; }
.form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-bottom:20px; }
.form-group { min-width:0; }
.form-group-full { grid-column:1/-1; }
.form-group label { display:block; margin-bottom:6px; color:#263d60; font-size:12px; }
.form-group label span { color:#e53935; }
.form-group input,.form-group select,.form-group textarea { width:100%; box-sizing:border-box; padding:9px 10px; border:1px solid #bdcadc; border-radius:4px; background:#fff; color:#183457; outline:none; font:inherit; font-size:13px; }
.form-group textarea { resize:vertical; }
.form-group input[readonly] { background:#f4f6f9; }
.form-help { display:block; margin-top:5px; color:#73839a; font-size:11px; }
.stock-note { padding:11px 12px; background:#f5f8fd; border:1px solid #d9e3f2; border-radius:4px; color:#5e718e; font-size:11px; line-height:1.5; margin-bottom:14px; }
.detail-summary { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); border:1px solid #dbe2ec; margin-bottom:18px; }
.summary-item { padding:13px; border-right:1px solid #dbe2ec; }
.summary-item:last-child { border-right:0; }
.summary-item span { display:block; color:#7a889d; font-size:11px; margin-bottom:5px; }
.summary-item strong { color:#09264d; font-size:13px; }
.detail-toolbar { display:flex; justify-content:space-between; align-items:center; gap:15px; margin-bottom:12px; }
.toolbar-actions { display:flex; gap:7px; flex-wrap:wrap; }
.detail-table { min-width:1000px; }
.detail-table tfoot th { background:#f8f9fb; }
.table-subtext { display:block; margin-top:3px; color:#8b97a8; font-size:10px; }
.inline-form { display:inline; }
.text-right { text-align:right !important; }
@media (max-width:800px) {
    .page-content { padding:15px; }
    .page-heading { align-items:flex-start; flex-direction:column; }
    .page-heading .btn { width:100%; }
    .form-grid { grid-template-columns:1fr; }
    .form-group-full { grid-column:auto; }
    .detail-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .summary-item:nth-child(2n) { border-right:0; }
    .summary-item { border-bottom:1px solid #dbe2ec; }
    .table-footer { align-items:flex-start; flex-direction:column; }
    .pagination { width:100%; justify-content:flex-end; }
    .modal-backdrop { padding:10px; align-items:center; justify-content:center; }
    .modal { width:100%; max-width:850px; max-height:calc(100vh - 20px); overflow-y:auto; overflow-x:hidden; -webkit-overflow-scrolling:touch; display:block; }
    .detail-toolbar { align-items:flex-start; flex-direction:column; }
    .toolbar-actions { width:100%; }
    .toolbar-actions .btn { flex:1; }
}
@supports (max-height:100dvh) { @media (max-width:800px) { .modal { max-height:calc(100dvh - 20px); } } }
@media (max-width:480px) {
    .detail-summary { grid-template-columns:1fr; }
    .summary-item,.summary-item:nth-child(2n) { border-right:0; }
}
</style>
HTML;

require __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="page-heading">
        <div>
            <h1>Manajemen Pembelian Restorasi</h1>
            <p>Kelola transaksi pembelian bahan restorasi dan penambahan stok.</p>
        </div>

        <button type="button" class="btn btn-primary" id="openCreatePurchaseBtn">
            + Tambah Pembelian
        </button>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo h($message); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div>
                <strong>Daftar Pembelian Restorasi</strong>
                <span class="muted"><?php echo count($purchases); ?> transaksi</span>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table" id="purchaseTable">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Item</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                    <tr class="filter-row">
                        <th><input type="text" placeholder="Cari nomor"></th>
                        <th><input type="text" placeholder="Cari tanggal"></th>
                        <th><input type="text" placeholder="Cari supplier"></th>
                        <th><input type="text" placeholder="Item"></th>
                        <th><input type="text" placeholder="Status"></th>
                        <th><input type="text" placeholder="Total"></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$purchases): ?>
                        <tr>
                            <td colspan="7" class="empty-cell">Belum ada transaksi pembelian restorasi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $row): ?>
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
                                data-nomor="<?php echo h($row['nomor_pembelian']); ?>"
                                data-tanggal="<?php echo h($row['tanggal']); ?>"
                                data-supplier="<?php echo h($row['supplier_nama']); ?>"
                                data-supplier-id="<?php echo (int)$row['supplier_id']; ?>"
                                data-keterangan="<?php echo h($row['keterangan']); ?>"
                                data-item="<?php echo h($row['jumlah_item']); ?>"
                                data-status="<?php echo h($status); ?>"
                                data-total="<?php echo h($row['total']); ?>"
                            >
                                <td><strong><?php echo h($row['nomor_pembelian']); ?></strong></td>
                                <td><?php echo h(date('d-M-Y', strtotime($row['tanggal']))); ?></td>
                                <td><?php echo h($row['supplier_nama']); ?></td>
                                <td><?php echo h($row['jumlah_item']); ?> item</td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo h($status); ?></span></td>
                                <td class="money-cell">Rp <?php echo rupiah($row['total']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-outline btn-sm" data-open-detail="<?php echo (int)$row['id']; ?>">Detail</button>
                                    <button type="button" class="btn btn-outline btn-sm" data-edit-purchase="<?php echo (int)$row['id']; ?>">Edit</button>
                                    <button type="button" class="btn btn-danger-outline btn-sm" data-delete-purchase="<?php echo (int)$row['id']; ?>" data-nomor="<?php echo h($row['nomor_pembelian']); ?>">Hapus</button>
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

<!-- MODAL TAMBAH -->
<div class="modal-backdrop" id="createModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div>
                <h2>Tambah Pembelian Restorasi</h2>
                <p>Input satu item per transaksi detail.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>

        <form method="post" id="createForm">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="add_purchase">

            <div class="modal-body">
                <div class="form-section-title">Informasi Pembelian</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nomor Pembelian <span>*</span></label>
                        <input type="text" name="nomor_pembelian" value="<?php echo h(generatePurchaseNumber($pdo)); ?>" maxlength="50" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal <span>*</span></label>
                        <input type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Supplier <span>*</span></label>
                        <select name="supplier_id" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo (int)$supplier['id']; ?>"><?php echo h($supplier['nama']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section-title">Detail Bahan Restorasi</div>
                <div class="stock-note">
                    <strong>Harga pembelian:</strong> masukkan harga aktual per satuan dari transaksi ini. Harga master restorasi tidak digunakan sebagai harga transaksi.
                </div>

                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label>Bahan Restorasi <span>*</span></label>
                        <select name="restorasi_id" id="createRestorasi" required>
                            <option value="">-- Pilih Bahan Restorasi --</option>
                            <?php foreach ($restorasiMaster as $r): ?>
                                <option value="<?php echo (int)$r['id']; ?>" data-stok="<?php echo h($r['stok']); ?>" data-satuan="<?php echo h($r['satuan']); ?>">
                                    <?php echo h($r['kode'] . ' - ' . $r['nama'] . ($r['jenis'] ? ' (' . $r['jenis'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="createRestorasiInfo" class="form-help"></small>
                    </div>
                    <div class="form-group">
                        <label>Qty <span>*</span></label>
                        <input type="number" name="qty" id="createQty" min="0.01" step="0.01" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>Harga Beli per Satuan <span>*</span></label>
                        <input type="number" name="harga" id="createHarga" min="0" step="0.01" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Diskon</label>
                        <input type="number" name="diskon" id="createDiskon" min="0" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Subtotal</label>
                        <input type="text" id="createSubtotal" value="Rp 0" readonly>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" rows="3" placeholder="Keterangan pembelian..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="closeModal('createModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DETAIL -->
<div class="modal-backdrop" id="detailModal">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div>
                <h2>Detail Pembelian</h2>
                <p id="detailSubtitle">Memuat data...</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('detailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="detail-summary" id="detailSummary"></div>
            <div class="detail-toolbar">
                <div><div class="form-section-title no-margin">Daftar Bahan Restorasi</div></div>
                <div class="toolbar-actions">
                    <button type="button" class="btn btn-outline btn-sm" id="editHeaderBtn">Edit Pembelian</button>
                    <button type="button" class="btn btn-primary btn-sm" id="addDetailBtn">+ Tambah Bahan</button>
                    <button type="button" class="btn btn-danger-outline btn-sm" id="deletePurchaseBtn">Hapus</button>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table detail-table">
                    <thead>
                        <tr>
                            <th>No</th><th>Kode</th><th>Nama Bahan</th><th>Jenis</th><th>Qty</th><th>Harga</th><th>Diskon</th><th>Subtotal</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="detailBody"><tr><td colspan="9" class="empty-cell">Memuat...</td></tr></tbody>
                    <tfoot>
                        <tr><th colspan="7" class="text-right">Total</th><th class="money-cell" id="detailTotal">Rp 0</th><th></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" onclick="closeModal('detailModal')">Tutup</button>
        </div>
    </div>
</div>

<!-- MODAL EDIT HEADER -->
<div class="modal-backdrop" id="editHeaderModal">
    <div class="modal">
        <div class="modal-header">
            <div><h2>Edit Pembelian</h2><p>Perbarui informasi transaksi.</p></div>
            <button type="button" class="modal-close" onclick="closeModal('editHeaderModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="update_header">
            <input type="hidden" name="id" id="editHeaderId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group"><label>Nomor Pembelian</label><input type="text" id="editHeaderNomor" readonly></div>
                    <div class="form-group"><label>Tanggal <span>*</span></label><input type="date" name="tanggal" id="editHeaderTanggal" required></div>
                    <div class="form-group form-group-full"><label>Supplier <span>*</span></label><select name="supplier_id" id="editHeaderSupplier" required><option value="">-- Pilih Supplier --</option><?php foreach ($suppliers as $supplier): ?><option value="<?php echo (int)$supplier['id']; ?>"><?php echo h($supplier['nama']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Status <span>*</span></label><select name="status" id="editHeaderStatus" required><option value="DRAFT">DRAFT</option><option value="PROSES">PROSES</option><option value="SELESAI">SELESAI</option><option value="BATAL">BATAL</option></select></div>
                    <div class="form-group form-group-full"><label>Keterangan</label><textarea name="keterangan" id="editHeaderKeterangan" rows="3"></textarea></div>
                </div>
                <div class="stock-note"><strong>Catatan:</strong> ketika status menjadi <strong>SELESAI</strong>, stok bahan restorasi bertambah dan pengeluaran pembelian otomatis dicatat. Pembelian restorasi tidak menggunakan termin pembayaran.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" onclick="closeModal('editHeaderModal')">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH DETAIL -->
<div class="modal-backdrop" id="addDetailModal">
    <div class="modal">
        <div class="modal-header"><div><h2>Tambah Bahan Restorasi</h2><p id="addDetailSubtitle">Tambahkan bahan ke nota.</p></div><button type="button" class="modal-close" onclick="closeModal('addDetailModal')">&times;</button></div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="add_detail">
            <input type="hidden" name="pembelian_id" id="addDetailPurchaseId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-group-full"><label>Bahan Restorasi <span>*</span></label><select name="restorasi_id" id="addDetailRestorasi" required><option value="">-- Pilih Bahan --</option><?php foreach ($restorasiMaster as $r): ?><option value="<?php echo (int)$r['id']; ?>" data-stok="<?php echo h($r['stok']); ?>" data-satuan="<?php echo h($r['satuan']); ?>"><?php echo h($r['kode'] . ' - ' . $r['nama'] . ($r['jenis'] ? ' (' . $r['jenis'] . ')' : '')); ?></option><?php endforeach; ?></select><small id="addDetailRestorasiInfo" class="form-help"></small></div>
                    <div class="form-group"><label>Qty <span>*</span></label><input type="number" name="qty" id="addDetailQty" min="0.01" step="0.01" value="1" required></div>
                    <div class="form-group"><label>Harga Beli per Satuan <span>*</span></label><input type="number" name="harga" id="addDetailHarga" min="0" step="0.01" value="0" required></div>
                    <div class="form-group"><label>Diskon</label><input type="number" name="diskon" id="addDetailDiskon" min="0" step="0.01" value="0"></div>
                    <div class="form-group"><label>Subtotal</label><input type="text" id="addDetailSubtotal" value="Rp 0" readonly></div>
                    <div class="form-group form-group-full"><label>Keterangan</label><textarea name="keterangan" rows="3"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" onclick="closeModal('addDetailModal')">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
        </form>
    </div>
</div>

<!-- MODAL EDIT DETAIL -->
<div class="modal-backdrop" id="editDetailModal">
    <div class="modal">
        <div class="modal-header"><div><h2>Edit Detail Bahan</h2><p>Perbarui item pembelian.</p></div><button type="button" class="modal-close" onclick="closeModal('editDetailModal')">&times;</button></div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
            <input type="hidden" name="action" value="update_detail">
            <input type="hidden" name="id" id="editDetailId">
            <input type="hidden" name="pembelian_id" id="editDetailPurchaseId">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-group-full"><label>Bahan Restorasi <span>*</span></label><select name="restorasi_id" id="editDetailRestorasi" required><option value="">-- Pilih Bahan --</option><?php foreach ($restorasiMaster as $r): ?><option value="<?php echo (int)$r['id']; ?>"><?php echo h($r['kode'] . ' - ' . $r['nama'] . ($r['jenis'] ? ' (' . $r['jenis'] . ')' : '')); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Qty <span>*</span></label><input type="number" name="qty" id="editDetailQty" min="0.01" step="0.01" required></div>
                    <div class="form-group"><label>Harga Beli per Satuan <span>*</span></label><input type="number" name="harga" id="editDetailHarga" min="0" step="0.01" required></div>
                    <div class="form-group"><label>Diskon</label><input type="number" name="diskon" id="editDetailDiskon" min="0" step="0.01" value="0"></div>
                    <div class="form-group"><label>Subtotal</label><input type="text" id="editDetailSubtotal" value="Rp 0" readonly></div>
                    <div class="form-group form-group-full"><label>Keterangan</label><textarea name="keterangan" id="editDetailKeterangan" rows="3"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" onclick="closeModal('editDetailModal')">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';
    var currentPurchase = null;

    function formatNumber(value) {
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(value || 0));
    }
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    function calculate(qtyId, priceId, discountId, resultId) {
        var qty = Number((document.getElementById(qtyId) || {}).value || 0);
        var price = Number((document.getElementById(priceId) || {}).value || 0);
        var discount = Number((document.getElementById(discountId) || {}).value || 0);
        var result = document.getElementById(resultId);
        if (result) result.value = 'Rp ' + formatNumber(Math.max(0, qty * price - discount));
    }
    function calculateAll() {
        calculate('createQty','createHarga','createDiskon','createSubtotal');
        calculate('addDetailQty','addDetailHarga','addDetailDiskon','addDetailSubtotal');
        calculate('editDetailQty','editDetailHarga','editDetailDiskon','editDetailSubtotal');
    }
    function bindInfo(selectId, infoId) {
        var select = document.getElementById(selectId), info = document.getElementById(infoId);
        if (!select || !info) return;
        select.addEventListener('change', function() {
            var option = select.options[select.selectedIndex];
            if (!option || !option.value) { info.textContent = ''; return; }
            info.textContent = 'Stok saat ini: ' + formatNumber(option.dataset.stok || 0) + (option.dataset.satuan ? ' ' + option.dataset.satuan : '') + '. Harga diisi sesuai transaksi pembelian.';
        });
    }
    window.openModal = function(id) { var el=document.getElementById(id); if(el) el.classList.add('show'); document.body.classList.add('modal-open'); };
    window.closeModal = function(id) { var el=document.getElementById(id); if(el) el.classList.remove('show'); if(!document.querySelector('.modal-backdrop.show')) document.body.classList.remove('modal-open'); };
    window.openCreateModal = function() {
        var form=document.getElementById('createForm'); if(form) form.reset();
        var date=form ? form.querySelector('input[name="tanggal"]') : null;
        if(date) date.value=new Date().toISOString().slice(0,10);
        var nomor=form ? form.querySelector('input[name="nomor_pembelian"]') : null;
        if(nomor) nomor.value='<?php echo h(generatePurchaseNumber($pdo)); ?>';
        document.getElementById('createQty').value='1'; document.getElementById('createHarga').value='0'; document.getElementById('createDiskon').value='0'; document.getElementById('createSubtotal').value='Rp 0'; document.getElementById('createRestorasiInfo').textContent='';
        openModal('createModal');
    };
    function renderDetails(details) {
        var tbody=document.getElementById('detailBody');
        if(!details || !details.length) { tbody.innerHTML='<tr><td colspan="9" class="empty-cell">Belum ada detail bahan restorasi.</td></tr>'; document.getElementById('detailTotal').textContent='Rp 0'; return; }
        var html='', total=0;
        details.forEach(function(d,i){
            total += Number(d.subtotal||0);
            html += '<tr><td>'+(i+1)+'</td><td><strong>'+escapeHtml(d.kode)+'</strong></td><td>'+escapeHtml(d.nama)+(d.merk?'<small class="table-subtext">'+escapeHtml(d.merk)+'</small>':'')+'</td><td>'+escapeHtml(d.jenis||'-')+'</td><td>'+formatNumber(d.qty)+(d.satuan?' '+escapeHtml(d.satuan):'')+'</td><td class="money-cell">Rp '+formatNumber(d.harga)+'</td><td class="money-cell">Rp '+formatNumber(d.diskon)+'</td><td class="money-cell"><strong>Rp '+formatNumber(d.subtotal)+'</strong></td><td><button type="button" class="btn btn-outline btn-xs" data-detail-edit="'+Number(d.id)+'">Edit</button> <button type="button" class="btn btn-danger-outline btn-xs" data-detail-delete="'+Number(d.id)+'" data-purchase="'+Number(d.pembelian_id)+'">Hapus</button></td></tr>';
        });
        tbody.innerHTML=html; document.getElementById('detailTotal').textContent='Rp '+formatNumber(total);
        tbody.querySelectorAll('[data-detail-edit]').forEach(function(btn){ btn.addEventListener('click',function(){ var d=details.find(function(x){return Number(x.id)===Number(btn.dataset.detailEdit);}); if(d) openEditDetail(d); }); });
        tbody.querySelectorAll('[data-detail-delete]').forEach(function(btn){ btn.addEventListener('click',function(){ if(!confirm('Hapus detail ini? Stok akan disesuaikan jika transaksi SELESAI.')) return; submitHidden('delete_detail',{id:btn.dataset.detailDelete,pembelian_id:btn.dataset.purchase}); }); });
    }
    function submitHidden(action, data) {
        var form=document.createElement('form'); form.method='post';
        var csrf=document.createElement('input'); csrf.type='hidden'; csrf.name='csrf_token'; csrf.value='<?php echo h($csrf); ?>'; form.appendChild(csrf);
        var a=document.createElement('input'); a.type='hidden'; a.name='action'; a.value=action; form.appendChild(a);
        Object.keys(data||{}).forEach(function(k){var i=document.createElement('input');i.type='hidden';i.name=k;i.value=data[k];form.appendChild(i);});
        document.body.appendChild(form); form.submit();
    }
    window.openDetailModal = function(id) {
        currentPurchase=null; document.getElementById('detailBody').innerHTML='<tr><td colspan="9" class="empty-cell">Memuat data...</td></tr>'; document.getElementById('detailTotal').textContent='Rp 0'; document.getElementById('detailSubtitle').textContent='Memuat data...'; openModal('detailModal');
        fetch('pembelian_restorasi.php?ajax=detail&id='+encodeURIComponent(id),{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(data){if(!data.success) throw new Error(data.message||'Gagal mengambil data.'); currentPurchase=data.purchase; document.getElementById('detailSubtitle').textContent=data.purchase.nomor_pembelian+' • '+data.purchase.supplier_nama; document.getElementById('detailSummary').innerHTML='<div class="summary-item"><span>Nomor Pembelian</span><strong>'+escapeHtml(data.purchase.nomor_pembelian)+'</strong></div><div class="summary-item"><span>Tanggal</span><strong>'+escapeHtml(data.purchase.tanggal)+'</strong></div><div class="summary-item"><span>Supplier</span><strong>'+escapeHtml(data.purchase.supplier_nama)+'</strong></div><div class="summary-item"><span>Status</span><strong>'+escapeHtml(data.purchase.status)+'</strong></div><div class="summary-item"><span>Total</span><strong>Rp '+formatNumber(data.purchase.total)+'</strong></div>'; renderDetails(data.details);}).catch(function(err){document.getElementById('detailBody').innerHTML='<tr><td colspan="9" class="empty-cell">'+escapeHtml(err.message)+'</td></tr>';});
    };
    function openEditDetail(detail) { document.getElementById('editDetailId').value=detail.id; document.getElementById('editDetailPurchaseId').value=detail.pembelian_id; document.getElementById('editDetailRestorasi').value=detail.restorasi_id; document.getElementById('editDetailQty').value=detail.qty; document.getElementById('editDetailHarga').value=detail.harga; document.getElementById('editDetailDiskon').value=detail.diskon; document.getElementById('editDetailKeterangan').value=detail.keterangan||''; calculateAll(); closeModal('detailModal'); openModal('editDetailModal'); }
    window.openEditPurchase = function(row) { var d=row.dataset; document.getElementById('editHeaderId').value=d.id; document.getElementById('editHeaderNomor').value=d.nomor; document.getElementById('editHeaderTanggal').value=d.tanggal; document.getElementById('editHeaderSupplier').value=d.supplierId || ''; document.getElementById('editHeaderStatus').value=d.status; document.getElementById('editHeaderKeterangan').value=d.keterangan || ''; openModal('editHeaderModal'); };
    document.getElementById('openCreatePurchaseBtn').addEventListener('click',openCreateModal);
    document.querySelectorAll('[data-open-detail]').forEach(function(btn){btn.addEventListener('click',function(){openDetailModal(Number(btn.dataset.openDetail));});});
    document.querySelectorAll('[data-edit-purchase]').forEach(function(btn){btn.addEventListener('click',function(){var row=btn.closest('tr'); openEditPurchase({dataset:{id:row.dataset.id,nomor:row.dataset.nomor,tanggal:row.dataset.tanggal,status:row.dataset.status,supplierId:row.dataset.supplierId||'',keterangan:row.dataset.keterangan||''}});});});
    document.querySelectorAll('[data-delete-purchase]').forEach(function(btn){btn.addEventListener('click',function(){if(confirm('Hapus pembelian '+btn.dataset.nomor+'? Jika status SELESAI, stok akan dikurangi kembali dan pengeluaran terkait akan dihapus.')) submitHidden('delete_purchase',{id:btn.dataset.deletePurchase});});});
    document.getElementById('editHeaderBtn').addEventListener('click',function(){ if(!currentPurchase)return; document.getElementById('editHeaderId').value=currentPurchase.id; document.getElementById('editHeaderNomor').value=currentPurchase.nomor_pembelian; document.getElementById('editHeaderTanggal').value=currentPurchase.tanggal; document.getElementById('editHeaderSupplier').value=currentPurchase.supplier_id; document.getElementById('editHeaderStatus').value=currentPurchase.status; document.getElementById('editHeaderKeterangan').value=currentPurchase.keterangan||''; closeModal('detailModal'); openModal('editHeaderModal'); });
    document.getElementById('addDetailBtn').addEventListener('click',function(){if(!currentPurchase)return;document.getElementById('addDetailPurchaseId').value=currentPurchase.id;document.getElementById('addDetailSubtitle').textContent='Tambahkan bahan ke '+currentPurchase.nomor_pembelian;document.getElementById('addDetailRestorasi').value='';document.getElementById('addDetailQty').value='1';document.getElementById('addDetailHarga').value='0';document.getElementById('addDetailDiskon').value='0';document.getElementById('addDetailSubtotal').value='Rp 0';document.getElementById('addDetailRestorasiInfo').textContent='';closeModal('detailModal');openModal('addDetailModal');});
    document.getElementById('deletePurchaseBtn').addEventListener('click',function(){if(!currentPurchase)return;if(confirm('Hapus transaksi '+currentPurchase.nomor_pembelian+'? Semua detail akan ikut dihapus.')) submitHidden('delete_purchase',{id:currentPurchase.id});});
    ['createQty','createHarga','createDiskon','addDetailQty','addDetailHarga','addDetailDiskon','editDetailQty','editDetailHarga','editDetailDiskon'].forEach(function(id){var el=document.getElementById(id);if(el)el.addEventListener('input',calculateAll);});
    bindInfo('createRestorasi','createRestorasiInfo'); bindInfo('addDetailRestorasi','addDetailRestorasiInfo');
    document.querySelectorAll('.modal-backdrop').forEach(function(bg){bg.addEventListener('click',function(e){if(e.target===bg)closeModal(bg.id);});});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.show').forEach(function(m){closeModal(m.id);});});

    var table=document.getElementById('purchaseTable'), rows=Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-id]')), filterInputs=Array.prototype.slice.call(table.querySelectorAll('thead .filter-row input')), pageSizeSelect=document.getElementById('pageSize'), prevButton=document.getElementById('prevPage'), nextButton=document.getElementById('nextPage'), tableInfo=document.getElementById('tableInfo'), pageNumber=document.getElementById('pageNumber'), currentPage=1;
    function getFilteredRows(){return rows.filter(function(row){return filterInputs.every(function(input,index){var value=input.value.trim().toLowerCase();if(!value)return true;var field=['nomor','tanggal','supplier','item','status','total'][index];return String(row.dataset[field]||'').toLowerCase().includes(value);});});}
    function renderTable(){var filtered=getFilteredRows(),size=Number(pageSizeSelect.value||10),totalPages=Math.max(1,Math.ceil(filtered.length/size));if(currentPage>totalPages)currentPage=totalPages;rows.forEach(function(row){row.style.display='none';});var start=(currentPage-1)*size,end=Math.min(start+size,filtered.length);filtered.slice(start,end).forEach(function(row){row.style.display='';});tableInfo.textContent=filtered.length===0?'Tidak ada data':'Menampilkan '+(start+1)+' sampai '+end+' dari '+filtered.length+' transaksi';pageNumber.textContent=currentPage+' / '+totalPages;prevButton.disabled=currentPage<=1;nextButton.disabled=currentPage>=totalPages;}
    filterInputs.forEach(function(i){i.addEventListener('input',function(){currentPage=1;renderTable();});}); pageSizeSelect.addEventListener('change',function(){currentPage=1;renderTable();}); prevButton.addEventListener('click',function(){if(currentPage>1){currentPage--;renderTable();}}); nextButton.addEventListener('click',function(){var totalPages=Math.max(1,Math.ceil(getFilteredRows().length/Number(pageSizeSelect.value||10)));if(currentPage<totalPages){currentPage++;renderTable();}});
    calculateAll(); renderTable();
})();
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
