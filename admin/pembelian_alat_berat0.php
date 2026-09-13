<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Pembelian Alat Berat';
$adminBase = '../';

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
    header('Location: pembelian_alat_berat.php?' . http_build_query([
        'msg_type' => $type,
        'msg' => $message
    ]));
    exit;
}

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| CRUD TRANSAKSI
|--------------------------------------------------------------------------
| Satu nomor pembelian adalah HEADER.
| Setiap unit yang dibeli adalah DETAIL tersendiri.
|
| Jika nomor pembelian yang dimasukkan sudah ada, sistem menambahkan
| unit baru ke nomor tersebut. Jadi satu nota dapat memiliki banyak unit.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $nomor = trim((string)($_POST['nomor_pembelian'] ?? ''));
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $supplierId = filter_var(
                $_POST['supplier_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            $alatBeratId = filter_var(
                $_POST['alat_berat_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $hargaBeli = (float)($_POST['harga_beli'] ?? 0);
            $hargaUsd = (float)($_POST['harga_usd'] ?? 0);
            $biayaBeaCukai = (float)($_POST['biaya_bea_cukai'] ?? 0);
            $biayaPengiriman = (float)($_POST['biaya_pengiriman'] ?? 0);
            $biayaLain = (float)($_POST['biaya_lain'] ?? 0);
            $kursPembelian = (float)($_POST['kurs_pembelian'] ?? 0);
            $estimasiKedatangan = trim((string)($_POST['estimasi_kedatangan'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($nomor === '' || $tanggal === '' || !$supplierId || !$alatBeratId) {
                redirectMessage(
                    'error',
                    'Nomor pembelian, tanggal, supplier, dan unit alat berat wajib diisi.'
                );
            }

            if (
                $hargaBeli < 0 ||
                $biayaBeaCukai < 0 ||
                $biayaPengiriman < 0 ||
                $biayaLain < 0 ||
                $hargaUsd < 0 ||
                $kursPembelian < 0
            ) {
                redirectMessage('error', 'Harga dan biaya tidak boleh bernilai negatif.');
            }

            /*
            | Validasi supplier.
            */
            $stmt = $pdo->prepare("
                SELECT id
                FROM supplier
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$supplierId]);

            if (!$stmt->fetch()) {
                redirectMessage('error', 'Supplier tidak ditemukan.');
            }

            /*
            | Validasi unit.
            */
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

            /*
            | Jangan membeli unit yang sama dua kali pada transaksi aktif.
            | Transaksi BATAL tidak dihitung.
            */
            $stmt = $pdo->prepare("
                SELECT d.id, p.nomor_pembelian, p.status
                FROM pembelian_alat_berat_detail d
                INNER JOIN pembelian_alat_berat p
                    ON p.id = d.pembelian_id
                WHERE d.alat_berat_id = ?
                  AND UPPER(p.status) <> 'BATAL'
                LIMIT 1
            ");
            $stmt->execute([$alatBeratId]);

            if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
                redirectMessage(
                    'error',
                    'Unit ' . $existing['nomor_pembelian'] .
                    ' sudah tercatat pada pembelian ' .
                    $existing['nomor_pembelian'] . '.'
                );
            }

            $pdo->beginTransaction();

            /*
            | Cari header berdasarkan nomor pembelian.
            */
            $stmt = $pdo->prepare("
                SELECT id, supplier_id, status
                FROM pembelian_alat_berat
                WHERE nomor_pembelian = ?
                LIMIT 1
            ");
            $stmt->execute([$nomor]);
            $header = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($header) {
                $pembelianId = (int)$header['id'];

                /*
                | Untuk nomor yang sama, supplier dan tanggal harus konsisten.
                */
                if ((int)$header['supplier_id'] !== (int)$supplierId) {
                    throw new RuntimeException(
                        'Nomor pembelian tersebut sudah digunakan oleh supplier lain.'
                    );
                }

                if (strtoupper((string)$header['status']) === 'BATAL') {
                    throw new RuntimeException(
                        'Nomor pembelian tersebut berstatus BATAL dan tidak dapat ditambahkan.'
                    );
                }

                $stmt = $pdo->prepare("
                    UPDATE pembelian_alat_berat
                    SET tanggal = ?,
                        estimasi_kedatangan = ?,
                        keterangan = COALESCE(NULLIF(?, ''), keterangan)
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $tanggal,
                    $estimasiKedatangan !== '' ? $estimasiKedatangan : null,
                    $keterangan,
                    $pembelianId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO pembelian_alat_berat
                        (
                            nomor_pembelian,
                            tanggal,
                            supplier_id,
                            estimasi_kedatangan,
                            kurs_pembelian,
                            biaya_bea_cukai,
                            biaya_pengiriman,
                            biaya_lain,
                            total,
                            status,
                            keterangan,
                            created_by
                        )
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, 0, 'PROSES', ?, ?)
                ");

                $stmt->execute([
                    $nomor,
                    $tanggal,
                    $supplierId,
                    $estimasiKedatangan !== '' ? $estimasiKedatangan : null,
                    $kursPembelian,
                    $biayaBeaCukai,
                    $biayaPengiriman,
                    $biayaLain,
                    $keterangan !== '' ? $keterangan : null,
                    (int)$_SESSION['admin_id']
                ]);

                $pembelianId = (int)$pdo->lastInsertId();
            }

            /*
            | Satu unit = satu detail.
            */
            $stmt = $pdo->prepare("
                INSERT INTO pembelian_alat_berat_detail
                    (
                        pembelian_id,
                        alat_berat_id,
                        harga_beli,
                        harga_usd,
                        keterangan
                    )
                VALUES
                    (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $pembelianId,
                $alatBeratId,
                $hargaBeli,
                $hargaUsd,
                $keterangan !== '' ? $keterangan : null
            ]);

            /*
            | Hitung ulang total HEADER dari semua detail.
            */
            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat
                SET total = (
                    (
                        SELECT COALESCE(SUM(subtotal), 0)
                        FROM pembelian_alat_berat_detail
                        WHERE pembelian_id = ?
                    )
                    + biaya_bea_cukai
                    + biaya_pengiriman
                    + biaya_lain
                )
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$pembelianId, $pembelianId]);

            /*
            | Setelah pembelian dicatat, unit menjadi siap diproses.
            | Hanya ubah status jika sebelumnya masih status awal.
            */
            $stmt = $pdo->prepare("
                UPDATE alat_berat
                SET status = CASE
                    WHEN UPPER(status) IN ('TERSEDIA', 'SIAP JUAL')
                        THEN status
                    ELSE 'Siap Jual'
                END
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$alatBeratId]);

            $pdo->commit();

            redirectMessage(
                'success',
                'Unit ' . $unit['kode'] .
                ' berhasil dimasukkan ke pembelian ' . $nomor . '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE DETAIL
        |--------------------------------------------------------------------------
        */
        if ($action === 'update_detail') {
            $detailId = filter_var(
                $_POST['detail_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $alatBeratId = filter_var(
                $_POST['alat_berat_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $hargaBeli = (float)($_POST['harga_beli'] ?? 0);
            $hargaUsd = (float)($_POST['harga_usd'] ?? 0);
            $biayaBeaCukai = (float)($_POST['biaya_bea_cukai'] ?? 0);
            $biayaPengiriman = (float)($_POST['biaya_pengiriman'] ?? 0);
            $biayaLain = (float)($_POST['biaya_lain'] ?? 0);
            $kursPembelian = (float)($_POST['kurs_pembelian'] ?? 0);
            $estimasiKedatangan = trim((string)($_POST['estimasi_kedatangan'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if (!$detailId || !$alatBeratId) {
                redirectMessage('error', 'Data detail tidak valid.');
            }

            if (
                $hargaBeli < 0 ||
                $biayaBeaCukai < 0 ||
                $biayaPengiriman < 0 ||
                $biayaLain < 0 ||
                $hargaUsd < 0 ||
                $kursPembelian < 0
            ) {
                redirectMessage('error', 'Harga dan biaya tidak boleh bernilai negatif.');
            }

            $stmt = $pdo->prepare("
                SELECT
                    d.id,
                    d.pembelian_id,
                    d.alat_berat_id AS old_alat_berat_id,
                    p.status
                FROM pembelian_alat_berat_detail d
                INNER JOIN pembelian_alat_berat p
                    ON p.id = d.pembelian_id
                WHERE d.id = ?
                LIMIT 1
            ");
            $stmt->execute([$detailId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$detail) {
                redirectMessage('error', 'Detail pembelian tidak ditemukan.');
            }

            if (strtoupper((string)$detail['status']) === 'SELESAI') {
                redirectMessage(
                    'error',
                    'Pembelian berstatus SELESAI. Edit detail tidak diperbolehkan.'
                );
            }

            if (strtoupper((string)$detail['status']) === 'BATAL') {
                redirectMessage(
                    'error',
                    'Pembelian berstatus BATAL. Detail tidak dapat diedit.'
                );
            }

            /*
            | Pastikan unit pengganti tidak sudah digunakan.
            */
            if ((int)$detail['old_alat_berat_id'] !== (int)$alatBeratId) {
                $stmt = $pdo->prepare("
                    SELECT d.id
                    FROM pembelian_alat_berat_detail d
                    INNER JOIN pembelian_alat_berat p
                        ON p.id = d.pembelian_id
                    WHERE d.alat_berat_id = ?
                      AND d.id <> ?
                      AND UPPER(p.status) <> 'BATAL'
                    LIMIT 1
                ");
                $stmt->execute([$alatBeratId, $detailId]);

                if ($stmt->fetch()) {
                    redirectMessage(
                        'error',
                        'Unit tersebut sudah tercatat pada transaksi pembelian lain.'
                    );
                }
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat_detail
                SET
                    alat_berat_id = ?,
                    harga_beli = ?,
                    harga_usd = ?,
                    keterangan = ?
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $alatBeratId,
                $hargaBeli,
                $hargaUsd,
                $keterangan !== '' ? $keterangan : null,
                $detailId
            ]);

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat
                SET total = (
                    (
                        SELECT COALESCE(SUM(subtotal), 0)
                        FROM pembelian_alat_berat_detail
                        WHERE pembelian_id = ?
                    )
                    + biaya_bea_cukai
                    + biaya_pengiriman
                    + biaya_lain
                )
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                (int)$detail['pembelian_id'],
                (int)$detail['pembelian_id']
            ]);

            $pdo->commit();

            redirectMessage('success', 'Detail pembelian berhasil diperbarui.');
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE DETAIL
        |--------------------------------------------------------------------------
        */
        if ($action === 'delete_detail') {
            $detailId = filter_var(
                $_POST['detail_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$detailId) {
                redirectMessage('error', 'ID detail tidak valid.');
            }

            $stmt = $pdo->prepare("
                SELECT
                    d.id,
                    d.pembelian_id,
                    d.alat_berat_id,
                    p.status,
                    p.nomor_pembelian
                FROM pembelian_alat_berat_detail d
                INNER JOIN pembelian_alat_berat p
                    ON p.id = d.pembelian_id
                WHERE d.id = ?
                LIMIT 1
            ");
            $stmt->execute([$detailId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$detail) {
                redirectMessage('error', 'Detail pembelian tidak ditemukan.');
            }

            if (strtoupper((string)$detail['status']) === 'SELESAI') {
                redirectMessage(
                    'error',
                    'Pembelian berstatus SELESAI. Detail tidak dapat dihapus.'
                );
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                DELETE FROM pembelian_alat_berat_detail
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$detailId]);

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat
                SET total = (
                    (
                        SELECT COALESCE(SUM(subtotal), 0)
                        FROM pembelian_alat_berat_detail
                        WHERE pembelian_id = ?
                    )
                    + biaya_bea_cukai
                    + biaya_pengiriman
                    + biaya_lain
                )
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([
                (int)$detail['pembelian_id'],
                (int)$detail['pembelian_id']
            ]);

            /*
            | Jika header sudah tidak memiliki detail, hapus header.
            */
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM pembelian_alat_berat_detail
                WHERE pembelian_id = ?
            ");
            $stmt->execute([(int)$detail['pembelian_id']]);

            if ((int)$stmt->fetchColumn() === 0) {
                $stmt = $pdo->prepare("
                    DELETE FROM pembelian_alat_berat
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->execute([(int)$detail['pembelian_id']]);
            }

            $pdo->commit();

            redirectMessage(
                'success',
                'Detail unit dari pembelian ' .
                $detail['nomor_pembelian'] .
                ' berhasil dihapus.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS HEADER
        |--------------------------------------------------------------------------
        */
        if ($action === 'update_status') {
            $pembelianId = filter_var(
                $_POST['pembelian_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $status = strtoupper(trim((string)($_POST['status'] ?? 'PROSES')));

            $allowedStatus = ['PROSES', 'SELESAI', 'BATAL'];

            if (!$pembelianId || !in_array($status, $allowedStatus, true)) {
                redirectMessage('error', 'Status pembelian tidak valid.');
            }

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$status, $pembelianId]);

            redirectMessage(
                'success',
                'Status pembelian berhasil diubah menjadi ' . $status . '.'
            );
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        redirectMessage(
            'error',
            $e->getMessage() !== ''
                ? $e->getMessage()
                : 'Terjadi kesalahan pada transaksi pembelian.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| PESAN REDIRECT
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
| AJAX DETAIL
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['ajax_detail']) &&
    $_GET['ajax_detail'] === '1'
) {
    header('Content-Type: application/json; charset=utf-8');

    $ajaxId = filter_var(
        $_GET['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$ajaxId) {
        echo json_encode([
            'success' => false,
            'message' => 'ID pembelian tidak valid.'
        ]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                d.id,
                d.pembelian_id,
                d.alat_berat_id,
                a.kode,
                a.tipe,
                a.nomor_rangka,
                d.harga_beli,
                d.harga_usd,
                d.subtotal,
                d.keterangan
            FROM pembelian_alat_berat_detail d
            INNER JOIN alat_berat a
                ON a.id = d.alat_berat_id
            WHERE d.pembelian_id = ?
            ORDER BY d.id ASC
        ");

        $stmt->execute([$ajaxId]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.nomor_pembelian,
                p.tanggal,
                p.supplier_id,
                s.kode AS supplier_kode,
                s.nama AS supplier_nama,
                p.estimasi_kedatangan,
                p.kedatangan_aktual,
                p.kurs_pembelian,
                p.biaya_bea_cukai,
                p.biaya_pengiriman,
                p.biaya_lain,
                p.total,
                p.status,
                p.keterangan
            FROM pembelian_alat_berat p
            INNER JOIN supplier s ON s.id = p.supplier_id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$ajaxId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$purchase) {
            echo json_encode([
                'success' => false,
                'message' => 'Pembelian tidak ditemukan.'
            ]);
            exit;
        }

        $subtotal = 0;
        foreach ($details as $item) {
            $subtotal += (float)$item['subtotal'];
        }

        echo json_encode([
            'success' => true,
            'purchase' => $purchase,
            'details' => $details,
            'subtotal' => $subtotal,
            'total' => (float)$purchase['total']
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengambil detail pembelian.'
        ]);
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| DATA MASTER
|--------------------------------------------------------------------------
*/
$suppliers = $pdo->query("
    SELECT id, kode, nama
    FROM supplier
    ORDER BY nama ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

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
    ORDER BY kode ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| DATA PEMBELIAN
|--------------------------------------------------------------------------
*/
$purchases = $pdo->query("
    SELECT
        p.id,
        p.nomor_pembelian,
        p.tanggal,
        p.supplier_id,
        s.kode AS supplier_kode,
        s.nama AS supplier_nama,
        p.status,
        p.total,
        p.biaya_bea_cukai,
        p.biaya_pengiriman,
        p.biaya_lain,
        p.kurs_pembelian,
        p.estimasi_kedatangan,
        p.kedatangan_aktual,
        p.keterangan,
        COUNT(d.id) AS jumlah_unit
    FROM pembelian_alat_berat p
    INNER JOIN supplier s
        ON s.id = p.supplier_id
    LEFT JOIN pembelian_alat_berat_detail d
        ON d.pembelian_id = p.id
    GROUP BY
        p.id,
        p.nomor_pembelian,
        p.tanggal,
        p.supplier_id,
        s.kode,
        s.nama,
        p.status,
        p.total,
        p.biaya_bea_cukai,
        p.biaya_pengiriman,
        p.biaya_lain,
        p.kurs_pembelian,
        p.estimasi_kedatangan,
        p.kedatangan_aktual,
        p.keterangan
    ORDER BY p.tanggal DESC, p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalPurchase = count($purchases);

require __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <div>
        <h1>Manajemen Pembelian Alat Berat</h1>
        <p>Mencatat pembelian unit alat berat berdasarkan supplier dan nomor nota</p>
    </div>

    <button type="button" class="btn-primary" id="openCreateModal">
        + Tambah Pembelian
    </button>
</section>

<?php if ($message !== ''): ?>
    <div class="page-alert <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
        <?php echo h($message); ?>
    </div>
<?php endif; ?>

<section class="dashboard-panel purchase-panel">

    <div class="panel-heading">
        <div>
            <h2>Daftar Pembelian</h2>
            <span class="panel-subtitle">
                <?php echo number_format($totalPurchase, 0, ',', '.'); ?> transaksi
            </span>
        </div>
    </div>

    <div class="purchase-toolbar">
        <div class="purchase-search">
            <label for="purchaseSearch">Pencarian</label>
            <input
                type="search"
                id="purchaseSearch"
                placeholder="Cari nomor, supplier, status..."
                autocomplete="off"
            >
        </div>

        <div class="purchase-page-size">
            <label for="purchasePageSize">Tampilkan</label>
            <select id="purchasePageSize">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dashboard-table purchase-table" id="purchaseTable">
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Aksi</th>
                </tr>

                <tr class="purchase-filter-row">
                    <th><input type="text" data-filter-column="0" placeholder="Filter nomor"></th>
                    <th><input type="text" data-filter-column="1" placeholder="Filter tanggal"></th>
                    <th><input type="text" data-filter-column="2" placeholder="Filter supplier"></th>
                    <th><input type="text" data-filter-column="3" placeholder="Filter unit"></th>
                    <th><input type="text" data-filter-column="4" placeholder="Filter status"></th>
                    <th><input type="text" data-filter-column="5" placeholder="Filter total"></th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($purchases)): ?>
                <tr>
                    <td colspan="7" class="empty-state">
                        Belum ada transaksi pembelian alat berat.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($purchases as $purchase): ?>
                    <tr
                        data-id="<?php echo (int)$purchase['id']; ?>"
                        data-nomor="<?php echo h($purchase['nomor_pembelian']); ?>"
                        data-tanggal="<?php echo h($purchase['tanggal']); ?>"
                        data-supplier="<?php echo h($purchase['supplier_nama']); ?>"
                        data-unit="<?php echo (int)$purchase['jumlah_unit']; ?>"
                        data-status="<?php echo h($purchase['status']); ?>"
                        data-total="<?php echo h($purchase['total']); ?>"
                        data-beacukai="<?php echo h($purchase['biaya_bea_cukai']); ?>"
                        data-pengiriman="<?php echo h($purchase['biaya_pengiriman']); ?>"
                        data-biayalain="<?php echo h($purchase['biaya_lain']); ?>"
                        data-kurs="<?php echo h($purchase['kurs_pembelian']); ?>"
                    >
                        <td><?php echo h($purchase['nomor_pembelian']); ?></td>

                        <td>
                            <?php
                            $timestamp = strtotime((string)$purchase['tanggal']);
                            echo $timestamp
                                ? h(date('d-M-Y', $timestamp))
                                : h($purchase['tanggal']);
                            ?>
                        </td>

                        <td><?php echo h($purchase['supplier_nama']); ?></td>

                        <td>
                            <span class="unit-count">
                                <?php echo number_format((int)$purchase['jumlah_unit'], 0, ',', '.'); ?>
                                unit
                            </span>
                        </td>

                        <td>
                            <?php
                            $status = strtoupper((string)$purchase['status']);

                            $statusClass = 'badge-info';

                            if ($status === 'SELESAI') {
                                $statusClass = 'badge-success';
                            } elseif ($status === 'BATAL') {
                                $statusClass = 'badge-danger';
                            }
                            ?>
                            <span class="purchase-badge <?php echo $statusClass; ?>">
                                <?php echo h($purchase['status']); ?>
                            </span>
                        </td>

                        <td class="money-cell">
                            <?php echo rupiah($purchase['total']); ?>
                        </td>

                        <td>
                            <button
                                type="button"
                                class="btn-small btn-detail"
                                data-action="detail"
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

    <div class="purchase-table-footer">
        <div id="purchaseInfo">Menampilkan 0 transaksi</div>
        <div class="purchase-pagination" id="purchasePagination"></div>
    </div>

</section>


<!-- ============================================================
     MODAL TAMBAH / TAMBAH UNIT KE NOTA
============================================================ -->
<div class="purchase-modal" id="createModal" aria-hidden="true">
    <div class="purchase-modal-box">

        <div class="purchase-modal-header">
            <div>
                <h3>Tambah Pembelian Alat Berat</h3>
                <p>
                    Satu kali simpan = satu unit.
                    Gunakan nomor nota yang sama untuk menambahkan unit lain.
                </p>
            </div>

            <button
                type="button"
                class="purchase-modal-close"
                data-close-modal="createModal"
            >&times;</button>
        </div>

        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="create">

            <div class="purchase-modal-body">

                <div class="purchase-section-title">
                    Informasi Pembelian
                </div>

                <div class="purchase-form-grid">

                    <div class="purchase-field">
                        <label>Nomor Pembelian <span>*</span></label>
                        <input
                            type="text"
                            name="nomor_pembelian"
                            required
                            maxlength="50"
                            placeholder="Contoh: PO-20260909-0001"
                        >
                        <small>
                            Jika nomor sudah ada, unit baru otomatis masuk ke nota tersebut.
                        </small>
                    </div>

                    <div class="purchase-field">
                        <label>Tanggal <span>*</span></label>
                        <input
                            type="date"
                            name="tanggal"
                            value="<?php echo h(date('Y-m-d')); ?>"
                            required
                        >
                    </div>

                    <div class="purchase-field">
                        <label>Estimasi Kedatangan</label>
                        <input
                            type="date"
                            name="estimasi_kedatangan"
                        >
                    </div>

                    <div class="purchase-field purchase-field-full">
                        <label>Supplier <span>*</span></label>
                        <select name="supplier_id" required>
                            <option value="">-- Pilih Supplier --</option>

                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo (int)$supplier['id']; ?>">
                                    <?php
                                    echo h(
                                        $supplier['kode'] . ' - ' . $supplier['nama']
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>


                <div class="purchase-section-title">
                    Unit Alat Berat
                </div>

                <div class="purchase-form-grid">

                    <div class="purchase-field purchase-field-full">
                        <label>Unit <span>*</span></label>
                        <select
                            name="alat_berat_id"
                            id="create_alat_berat_id"
                            required
                        >
                            <option value="">-- Pilih Unit --</option>

                            <?php foreach ($units as $unit): ?>
                                <option
                                    value="<?php echo (int)$unit['id']; ?>"
                                    data-tipe="<?php echo h($unit['tipe']); ?>"
                                    data-rangka="<?php echo h($unit['nomor_rangka']); ?>"
                                >
                                    <?php
                                    echo h(
                                        $unit['kode'] .
                                        ' - ' .
                                        $unit['tipe'] .
                                        ' - ' .
                                        ($unit['nomor_rangka'] ?: 'Tanpa nomor rangka')
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="purchase-unit-preview" id="unitPreview">
                        Pilih unit untuk melihat informasi unit.
                    </div>

                </div>


                <div class="purchase-section-title">
                    Nilai Pembelian & Biaya
                </div>

                <div class="purchase-form-grid">

                    <div class="purchase-field">
                        <label>Harga Beli (IDR) <span>*</span></label>
                        <input
                            type="number"
                            name="harga_beli"
                            min="0"
                            step="0.01"
                            value="0"
                            required
                        >
                    </div>

                    <div class="purchase-field">
                        <label>Harga Beli (USD)</label>
                        <input
                            type="number"
                            name="harga_usd"
                            min="0"
                            step="0.01"
                            value="0"
                        >
                    </div>

                    <div class="purchase-field">
                        <label>Kurs Pembelian (IDR/USD)</label>
                        <input
                            type="number"
                            name="kurs_pembelian"
                            min="0"
                            step="0.01"
                            value="0"
                        >
                    </div>

                    <div class="purchase-field">
                        <label>Biaya Bea Cukai</label>
                        <input
                            type="number"
                            name="biaya_bea_cukai"
                            min="0"
                            step="0.01"
                            value="0"
                        >
                    </div>

                    <div class="purchase-field">
                        <label>Biaya Pengiriman</label>
                        <input
                            type="number"
                            name="biaya_pengiriman"
                            min="0"
                            step="0.01"
                            value="0"
                        >
                    </div>

                    <div class="purchase-field">
                        <label>Biaya Lain</label>
                        <input
                            type="number"
                            name="biaya_lain"
                            min="0"
                            step="0.01"
                            value="0"
                        >
                    </div>

                    <div class="purchase-field purchase-field-full">
                        <label>Keterangan</label>
                        <textarea
                            name="keterangan"
                            rows="3"
                            placeholder="Keterangan pembelian unit"
                        ></textarea>
                    </div>

                </div>

            </div>

            <div class="purchase-modal-footer">
                <button
                    type="button"
                    class="btn-secondary"
                    data-close-modal="createModal"
                >
                    Batal
                </button>

                <button type="submit" class="btn-primary">
                    Simpan Unit
                </button>
            </div>
        </form>

    </div>
</div>


<!-- ============================================================
     MODAL DETAIL
============================================================ -->
<div class="purchase-modal" id="detailModal" aria-hidden="true">
    <div class="purchase-modal-box purchase-modal-large">

        <div class="purchase-modal-header">
            <div>
                <h3 id="detailTitle">Detail Pembelian</h3>
                <p id="detailSubtitle"></p>
            </div>

            <button
                type="button"
                class="purchase-modal-close"
                data-close-modal="detailModal"
            >&times;</button>
        </div>

        <div class="purchase-modal-body">

            <div class="purchase-detail-summary" id="detailSummary"></div>

            <div class="purchase-detail-toolbar">
                <strong>Daftar Unit</strong>

                <button
                    type="button"
                    class="btn-small btn-detail-add"
                    id="detailAddUnit"
                >
                    + Tambah Unit ke Nota
                </button>
            </div>

            <div class="table-responsive">
                <table class="dashboard-table detail-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Tipe</th>
                            <th>No. Rangka</th>
                            <th>Harga Beli</th>
                            <th>Harga USD</th>
                            <th>Subtotal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody id="detailBody">
                        <tr>
                            <td colspan="8" class="empty-state">
                                Memuat data...
                            </td>
                        </tr>
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="6" style="text-align:right;">
                                Total Pembelian
                            </th>
                            <th id="detailTotal">0</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        <div class="purchase-modal-footer">
            <form method="post" id="statusForm" style="margin-right:auto;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="pembelian_id" id="statusPembelianId">

                <select name="status" id="statusSelect" class="status-select">
                    <option value="PROSES">PROSES</option>
                    <option value="SELESAI">SELESAI</option>
                    <option value="BATAL">BATAL</option>
                </select>

                <button type="submit" class="btn-secondary">
                    Simpan Status
                </button>
            </form>

            <button
                type="button"
                class="btn-secondary"
                data-close-modal="detailModal"
            >
                Tutup
            </button>
        </div>

    </div>
</div>


<!-- ============================================================
     MODAL EDIT DETAIL
============================================================ -->
<div class="purchase-modal" id="editModal" aria-hidden="true">
    <div class="purchase-modal-box">

        <div class="purchase-modal-header">
            <h3>Edit Detail Pembelian</h3>

            <button
                type="button"
                class="purchase-modal-close"
                data-close-modal="editModal"
            >&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="update_detail">
            <input type="hidden" name="detail_id" id="edit_detail_id">

            <div class="purchase-modal-body">

                <div class="purchase-form-grid">

                    <div class="purchase-field purchase-field-full">
                        <label>Unit <span>*</span></label>
                        <select name="alat_berat_id" id="edit_alat_berat_id" required>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo (int)$unit['id']; ?>">
                                    <?php
                                    echo h(
                                        $unit['kode'] .
                                        ' - ' .
                                        $unit['tipe'] .
                                        ' - ' .
                                        ($unit['nomor_rangka'] ?: 'Tanpa nomor rangka')
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="purchase-field">
                        <label>Harga Beli (IDR)</label>
                        <input
                            type="number"
                            name="harga_beli"
                            id="edit_harga_beli"
                            min="0"
                            step="0.01"
                            required
                        >
                    </div>

                    <div class="purchase-field">
                        <label>Harga Beli (USD)</label>
                        <input
                            type="number"
                            name="harga_usd"
                            id="edit_harga_usd"
                            min="0"
                            step="0.01"
                        >
                    </div>

                    <div class="purchase-field purchase-field-full">
                        <label>Keterangan</label>
                        <textarea
                            name="keterangan"
                            id="edit_keterangan"
                            rows="3"
                        ></textarea>
                    </div>

                </div>

            </div>

            <div class="purchase-modal-footer">
                <button
                    type="button"
                    class="btn-secondary"
                    data-close-modal="editModal"
                >
                    Batal
                </button>

                <button type="submit" class="btn-primary">
                    Update Detail
                </button>
            </div>
        </form>

    </div>
</div>


<style>
.purchase-panel {
    overflow: visible;
}

.panel-subtitle {
    display: block;
    margin-top: 3px;
    color: #718096;
    font-size: 12px;
}

.purchase-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    padding: 15px 16px;
    border-bottom: 1px solid #dfe5ed;
}

.purchase-search {
    flex: 1;
    max-width: 480px;
}

.purchase-page-size {
    width: 130px;
}

.purchase-toolbar label {
    display: block;
    margin-bottom: 6px;
    color: #52657f;
    font-size: 11px;
}

.purchase-toolbar input,
.purchase-toolbar select,
.purchase-field input,
.purchase-field select,
.purchase-field textarea,
.status-select {
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

.purchase-toolbar input,
.purchase-toolbar select,
.purchase-field input,
.purchase-field select,
.status-select {
    height: 36px;
}

.purchase-field textarea {
    resize: vertical;
    min-height: 80px;
}

.purchase-toolbar input:focus,
.purchase-toolbar select:focus,
.purchase-field input:focus,
.purchase-field select:focus,
.purchase-field textarea:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13,110,253,.08);
}

.purchase-table {
    min-width: 950px;
}

.purchase-table th,
.purchase-table td,
.detail-table th,
.detail-table td {
    vertical-align: middle;
}

.purchase-filter-row th {
    padding: 8px 7px;
    background: #f7f9fc;
}

.purchase-filter-row input {
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

.purchase-filter-row input:focus {
    border-color: #0d6efd;
}

.money-cell {
    text-align: right;
    white-space: nowrap;
}

.unit-count {
    color: #0d6efd;
    font-size: 12px;
}

.purchase-badge {
    display: inline-block;
    padding: 3px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.badge-info {
    background: #06b6d4;
    color: #fff;
}

.badge-success {
    background: #10b981;
    color: #fff;
}

.badge-danger {
    background: #ef4444;
    color: #fff;
}

.btn-small {
    min-width: 48px;
    height: 30px;
    padding: 0 10px;
    border: 1px solid #bdcbe0;
    border-radius: 4px;
    background: #fff;
    color: #52657f;
    font-size: 11px;
    cursor: pointer;
}

.btn-small:hover {
    border-color: #0d6efd;
    color: #0d6efd;
}

.purchase-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 12px 16px;
    color: #718096;
    font-size: 12px;
}

.purchase-pagination {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.purchase-pagination button {
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

.purchase-pagination button.active {
    border-color: #0d6efd;
    background: #0d6efd;
    color: #fff;
}

.purchase-pagination button:disabled {
    cursor: not-allowed;
    opacity: .5;
}

.purchase-modal {
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

.purchase-modal.show {
    display: flex;
}

.purchase-modal-box {
    width: min(760px, 100%);
    max-height: calc(100vh - 36px);
    overflow-y: auto;
    border-radius: 6px;
    background: #fff;
    box-shadow: 0 15px 45px rgba(0,0,0,.18);
}

.purchase-modal-large {
    width: min(1150px, 100%);
}

.purchase-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    min-height: 58px;
    padding: 14px 16px;
    box-sizing: border-box;
    border-bottom: 1px solid #dfe5ed;
}

.purchase-modal-header h3 {
    margin: 0;
    color: #183052;
    font-size: 15px;
}

.purchase-modal-header p {
    margin: 5px 0 0;
    color: #718096;
    font-size: 11px;
}

.purchase-modal-close {
    width: 34px;
    height: 34px;
    border: 0;
    background: transparent;
    color: #718096;
    font-size: 25px;
    cursor: pointer;
}

.purchase-modal-body {
    padding: 18px 16px;
}

.purchase-section-title {
    margin: 2px 0 14px;
    padding-bottom: 7px;
    border-bottom: 1px solid #dfe5ed;
    color: #183052;
    font-size: 12px;
    font-weight: 600;
}

.purchase-section-title:not(:first-child) {
    margin-top: 20px;
}

.purchase-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: 14px;
}

.purchase-field {
    min-width: 0;
}

.purchase-field-full {
    grid-column: 1 / -1;
}

.purchase-field label {
    display: block;
    margin-bottom: 6px;
    color: #183052;
    font-size: 11px;
}

.purchase-field label span {
    color: #dc3545;
}

.purchase-field small {
    display: block;
    margin-top: 5px;
    color: #718096;
    font-size: 10px;
    line-height: 1.4;
}

.purchase-unit-preview {
    grid-column: 1 / -1;
    padding: 10px 12px;
    border: 1px dashed #bdcbe0;
    border-radius: 4px;
    background: #f8fafc;
    color: #718096;
    font-size: 11px;
}

.purchase-modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #dfe5ed;
}

.btn-primary,
.btn-secondary {
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

.purchase-detail-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 10px;
    margin-bottom: 18px;
}

.purchase-summary-item {
    padding: 12px;
    border: 1px solid #dfe5ed;
    border-radius: 4px;
    background: #f8fafc;
}

.purchase-summary-item span {
    display: block;
    margin-bottom: 4px;
    color: #718096;
    font-size: 10px;
}

.purchase-summary-item strong {
    color: #183052;
    font-size: 12px;
}

.purchase-detail-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
    color: #183052;
    font-size: 12px;
}

.btn-detail-add {
    color: #0d6efd;
}

.detail-table {
    min-width: 900px;
}

.detail-table tfoot th {
    background: #f7f9fc;
}

.status-select {
    width: 140px;
}

.detail-edit,
.detail-delete {
    margin-right: 4px;
}

.detail-delete {
    color: #dc3545;
    border-color: #f0b9c0;
}

@media (max-width: 800px) {
    .purchase-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .purchase-search,
    .purchase-page-size {
        width: 100%;
        max-width: none;
    }

    .purchase-table-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .purchase-pagination {
        justify-content: flex-start;
    }

    .purchase-form-grid {
        grid-template-columns: 1fr;
    }

    .purchase-field-full {
        grid-column: auto;
    }

    .purchase-modal {
        padding: 10px;
    }

    .purchase-modal-box {
        max-height: calc(100vh - 20px);
    }

    .purchase-detail-summary {
        grid-template-columns: repeat(2, minmax(0,1fr));
    }

    .purchase-modal-footer {
        flex-wrap: wrap;
    }

    .status-select {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .purchase-detail-summary {
        grid-template-columns: 1fr;
    }
}
</style>


<script>
(function () {
    'use strict';

    var currentPurchaseId = 0;
    var currentPurchaseNumber = '';

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

        if (!document.querySelector('.purchase-modal.show')) {
            document.body.style.overflow = '';
        }
    }

    document.getElementById('openCreateModal').addEventListener(
        'click',
        function () {
            openModal('createModal');
        }
    );

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(this.getAttribute('data-close-modal'));
        });
    });

    document.querySelectorAll('.purchase-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.purchase-modal.show').forEach(function (modal) {
                closeModal(modal.id);
            });
        }
    });


    /*
    |--------------------------------------------------------------------------
    | PREVIEW UNIT
    |--------------------------------------------------------------------------
    */
    var unitSelect = document.getElementById('create_alat_berat_id');
    var unitPreview = document.getElementById('unitPreview');

    if (unitSelect && unitPreview) {
        unitSelect.addEventListener('change', function () {
            var option = this.options[this.selectedIndex];

            if (!option || !option.value) {
                unitPreview.textContent =
                    'Pilih unit untuk melihat informasi unit.';
                return;
            }

            var tipe = option.getAttribute('data-tipe') || '-';
            var rangka = option.getAttribute('data-rangka') || '-';

            unitPreview.innerHTML =
                '<strong>' + escapeHtml(tipe) + '</strong>' +
                ' &nbsp; | &nbsp; Nomor Rangka: ' +
                escapeHtml(rangka || '-');
        });
    }



    /*
    |--------------------------------------------------------------------------
    | Hitung harga USD dari harga IDR / kurs
    |--------------------------------------------------------------------------
    */
    var hargaBeliInput = document.querySelector('#createModal input[name="harga_beli"]');
    var hargaUsdInput = document.querySelector('#createModal input[name="harga_usd"]');
    var kursInput = document.querySelector('#createModal input[name="kurs_pembelian"]');

    function calculateUsd() {
        if (!hargaBeliInput || !hargaUsdInput || !kursInput) return;

        var idr = parseFloat(hargaBeliInput.value || 0);
        var kurs = parseFloat(kursInput.value || 0);

        if (idr > 0 && kurs > 0) {
            hargaUsdInput.value = (idr / kurs).toFixed(2);
        }
    }

    if (hargaBeliInput) hargaBeliInput.addEventListener('input', calculateUsd);
    if (kursInput) kursInput.addEventListener('input', calculateUsd);

    /*
    |--------------------------------------------------------------------------
    | DETAIL PEMBELIAN
    |--------------------------------------------------------------------------
    */
    document.querySelectorAll('[data-action="detail"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var row = this.closest('tr');

            if (!row) {
                return;
            }

            currentPurchaseId = parseInt(row.dataset.id || '0', 10);
            currentPurchaseNumber = row.dataset.nomor || '';

            loadPurchaseDetail(currentPurchaseId, row);
        });
    });

    function loadPurchaseDetail(id, row) {
        var body = document.getElementById('detailBody');
        var summary = document.getElementById('detailSummary');

        body.innerHTML =
            '<tr><td colspan="8" class="empty-state">Memuat data...</td></tr>';

        document.getElementById('detailTitle').textContent =
            'Detail ' + (row.dataset.nomor || 'Pembelian');

        document.getElementById('detailSubtitle').textContent =
            'Supplier: ' + (row.dataset.supplier || '-');

        summary.innerHTML =
            '<div class="purchase-summary-item">' +
                '<span>Nomor Pembelian</span>' +
                '<strong>' + escapeHtml(row.dataset.nomor || '-') + '</strong>' +
            '</div>' +
            '<div class="purchase-summary-item">' +
                '<span>Tanggal</span>' +
                '<strong>' + escapeHtml(row.dataset.tanggal || '-') + '</strong>' +
            '</div>' +
            '<div class="purchase-summary-item">' +
                '<span>Jumlah Unit</span>' +
                '<strong>' + escapeHtml(row.dataset.unit || '0') + ' unit</strong>' +
            '</div>' +
            '<div class="purchase-summary-item">' +
                '<span>Grand Total</span>' +
                '<strong>Rp ' + formatNumber(row.dataset.total || 0) + '</strong>' +
            '</div>';

        document.getElementById('statusPembelianId').value = id;
        document.getElementById('statusSelect').value =
            (row.dataset.status || 'PROSES').toUpperCase();

        document.getElementById('detailTotal').textContent =
            formatNumber(row.dataset.total || 0);

        document.getElementById('detailAddUnit').onclick = function () {
            closeModal('detailModal');

            var nomorInput =
                document.querySelector('#createModal input[name="nomor_pembelian"]');

            if (nomorInput) {
                nomorInput.value = row.dataset.nomor || '';
            }

            openModal('createModal');
        };

        /*
        | Endpoint AJAX ringan menggunakan file yang sama.
        | Detail dibaca melalui parameter GET ajax_detail.
        */
        fetch(
            'pembelian_alat_berat.php?ajax_detail=1&id=' +
            encodeURIComponent(id)
        )
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data.success) {
                throw new Error(data.message || 'Gagal memuat detail.');
            }

            renderDetailRows(data.details);
            document.getElementById('detailTotal').textContent =
                formatNumber(data.total || 0);

            var purchase = data.purchase || {};
            summary.innerHTML +=
                '<div class="purchase-summary-item">' +
                    '<span>Bea Cukai</span>' +
                    '<strong>Rp ' + formatNumber(purchase.biaya_bea_cukai || 0) + '</strong>' +
                '</div>' +
                '<div class="purchase-summary-item">' +
                    '<span>Pengiriman</span>' +
                    '<strong>Rp ' + formatNumber(purchase.biaya_pengiriman || 0) + '</strong>' +
                '</div>' +
                '<div class="purchase-summary-item">' +
                    '<span>Biaya Lain</span>' +
                    '<strong>Rp ' + formatNumber(purchase.biaya_lain || 0) + '</strong>' +
                '</div>' +
                '<div class="purchase-summary-item">' +
                    '<span>Kurs Pembelian</span>' +
                    '<strong>Rp ' + formatNumber(purchase.kurs_pembelian || 0) + ' / USD</strong>' +
                '</div>';

            openModal('detailModal');
        })
        .catch(function (error) {
            body.innerHTML =
                '<tr><td colspan="8" class="empty-state">' +
                escapeHtml(error.message) +
                '</td></tr>';

            openModal('detailModal');
        });
    }


    function renderDetailRows(details) {
        var body = document.getElementById('detailBody');

        if (!details || details.length === 0) {
            body.innerHTML =
                '<tr><td colspan="8" class="empty-state">' +
                'Belum ada detail unit.' +
                '</td></tr>';
            return;
        }

        body.innerHTML = '';

        details.forEach(function (detail, index) {
            var tr = document.createElement('tr');

            tr.innerHTML =
                '<td>' + (index + 1) + '</td>' +
                '<td>' + escapeHtml(detail.kode || '-') + '</td>' +
                '<td>' + escapeHtml(detail.tipe || '-') + '</td>' +
                '<td>' + escapeHtml(detail.nomor_rangka || '-') + '</td>' +
                '<td class="money-cell">' +
                    formatNumber(detail.harga_beli) +
                '</td>' +
                '<td class="money-cell">' +
                    formatNumber(detail.harga_usd) +
                '</td>' +
                '<td class="money-cell">' +
                    formatNumber(detail.subtotal) +
                '</td>' +
                '<td>' +
                    '<button type="button" class="btn-small detail-edit">Edit</button>' +
                    '<button type="button" class="btn-small detail-delete">Hapus</button>' +
                '</td>';

            tr.querySelector('.detail-edit').addEventListener(
                'click',
                function () {
                    fillEditModal(detail);
                }
            );

            tr.querySelector('.detail-delete').addEventListener(
                'click',
                function () {
                    deleteDetail(detail);
                }
            );

            body.appendChild(tr);
        });
    }


    function fillEditModal(detail) {
        document.getElementById('edit_detail_id').value = detail.id || '';
        document.getElementById('edit_alat_berat_id').value =
            detail.alat_berat_id || '';
        document.getElementById('edit_harga_beli').value =
            detail.harga_beli || 0;
        document.getElementById('edit_harga_usd').value =
            detail.harga_usd || 0;
        document.getElementById('edit_keterangan').value =
            detail.keterangan || '';

        openModal('editModal');
    }


    function deleteDetail(detail) {
        if (!confirm(
            'Hapus unit ' +
            (detail.kode || '') +
            ' dari pembelian ' +
            currentPurchaseNumber +
            '?'
        )) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'pembelian_alat_berat.php';

        var action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'action';
        action.value = 'delete_detail';

        var id = document.createElement('input');
        id.type = 'hidden';
        id.name = 'detail_id';
        id.value = detail.id;

        form.appendChild(action);
        form.appendChild(id);
        document.body.appendChild(form);
        form.submit();
    }


    /*
    |--------------------------------------------------------------------------
    | SEARCH + FILTER + PAGINATION
    |--------------------------------------------------------------------------
    */
    var table = document.getElementById('purchaseTable');
    var tbody = table ? table.querySelector('tbody') : null;
    var searchInput = document.getElementById('purchaseSearch');
    var pageSizeSelect = document.getElementById('purchasePageSize');
    var info = document.getElementById('purchaseInfo');
    var pagination = document.getElementById('purchasePagination');

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
        return String(value || '').toLowerCase().trim();
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
                if (normalize(row.textContent).indexOf(globalSearch) === -1) {
                    return false;
                }
            }

            for (var column in filters) {
                if (!filters[column]) {
                    continue;
                }

                var cell = cells[parseInt(column, 10)];

                if (
                    cell &&
                    normalize(cell.textContent).indexOf(filters[column]) === -1
                ) {
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

        for (var page = start; page <= end; page++) {
            addPageButton(page);
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
                ' transaksi';
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


    function formatNumber(value) {
        var number = parseFloat(value || 0);

        return new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0
        }).format(number);
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

})();
</script>

<?php
/*
|--------------------------------------------------------------------------
| AJAX DETAIL
|--------------------------------------------------------------------------
| Diletakkan setelah HTML supaya request normal tetap menghasilkan halaman.
*/
if (isset($_GET['ajax_detail']) && $_GET['ajax_detail'] === '1') {
    /*
    | Catatan: blok ini secara normal seharusnya diproses sebelum header HTML.
    | Untuk menjaga file satu halaman, request AJAX perlu dihentikan lebih awal.
    */
}
?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
