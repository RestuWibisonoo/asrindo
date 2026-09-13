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


function generateNomorPemasukan(PDO $pdo, string $tanggal): string
{
    $prefix = 'PM-' . date('Ymd', strtotime($tanggal)) . '-';

    $stmt = $pdo->prepare("
        SELECT nomor_pemasukan
        FROM pemasukan
        WHERE nomor_pemasukan LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last = (string)$stmt->fetchColumn();

    $next = 1;
    if ($last !== '') {
        $suffix = substr($last, -4);
        if (ctype_digit($suffix)) {
            $next = (int)$suffix + 1;
        }
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function redirectEdit(int $id, string $type, string $message): void
{
    header('Location: penjualan_detail.php?' . http_build_query([
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
                SELECT SUM(qty * harga)
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

            // Jangan izinkan nilai invoice turun di bawah pembayaran yang sudah
            // tercatat atau total jadwal pembayaran yang sudah dibuat.
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM pemasukan
                WHERE sumber = 'PENJUALAN'
                  AND penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $sudahDibayar = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM penjualan_pembayaran
                WHERE penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $sudahDijadwalkan = (float)$stmt->fetchColumn();

            if ($total + 0.00001 < $sudahDibayar) {
                redirectEdit(
                    $id,
                    'error',
                    'Grand total tidak boleh lebih kecil dari total pembayaran yang sudah diterima (' . rupiah($sudahDibayar) . ').'
                );
            }

            if ($total + 0.00001 < $sudahDijadwalkan) {
                redirectEdit(
                    $id,
                    'error',
                    'Grand total tidak boleh lebih kecil dari total jadwal pembayaran (' . rupiah($sudahDijadwalkan) . ').'
                );
            }

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


        if ($action === 'add_payment_plan') {
            $jenis = strtoupper(trim((string)($_POST['jenis_pembayaran'] ?? '')));
            $tanggalJatuhTempo = trim((string)($_POST['tanggal_jatuh_tempo_pembayaran'] ?? ''));
            $nominalRaw = (string)($_POST['nominal_pembayaran'] ?? '0');
            $nominal = (float)str_replace([',', '.', ' '], '', $nominalRaw);

            if (!in_array($jenis, ['DP', 'CICILAN', 'PELUNASAN'], true)) {
                throw new RuntimeException('Jenis pembayaran tidak valid.');
            }
            if ($tanggalJatuhTempo === '') {
                throw new RuntimeException('Tanggal jatuh tempo pembayaran wajib diisi.');
            }
            if ($nominal <= 0) {
                throw new RuntimeException('Nominal pembayaran harus lebih dari 0.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, tanggal, total, status
                FROM penjualan
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $saleLocked = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$saleLocked) {
                throw new RuntimeException('Penjualan tidak ditemukan.');
            }

            if (strtoupper((string)$saleLocked['status']) === 'BATAL') {
                throw new RuntimeException('Penjualan yang sudah BATAL tidak dapat dibuatkan jadwal pembayaran.');
            }

            if ($tanggalJatuhTempo < $saleLocked['tanggal']) {
                throw new RuntimeException('Tanggal jatuh tempo tidak boleh sebelum tanggal penjualan.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM penjualan_pembayaran
                WHERE penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $totalTerjadwal = (float)$stmt->fetchColumn();

            $sisaYangBisaDijadwalkan = max(0, (float)$saleLocked['total'] - $totalTerjadwal);
            if ($nominal > $sisaYangBisaDijadwalkan + 0.00001) {
                throw new RuntimeException(
                    'Nominal melebihi sisa tagihan yang belum dijadwalkan (' .
                    rupiah($sisaYangBisaDijadwalkan) . ').'
                );
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(termin_ke), 0) + 1
                FROM penjualan_pembayaran
                WHERE penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $terminKe = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                INSERT INTO penjualan_pembayaran
                    (penjualan_id, termin_ke, jenis_pembayaran, tanggal_jatuh_tempo, nominal, status)
                VALUES (?, ?, ?, ?, ?, 'BELUM_BAYAR')
            ");
            $stmt->execute([
                $id,
                $terminKe,
                $jenis,
                $tanggalJatuhTempo,
                $nominal
            ]);

            $pdo->commit();

            // Untuk penjualan tempo, tanggal jatuh tempo header mengikuti termin terakhir.
            $stmt = $pdo->prepare("
                UPDATE penjualan
                SET tanggal_jatuh_tempo = (
                    SELECT MAX(tanggal_jatuh_tempo)
                    FROM penjualan_pembayaran
                    WHERE penjualan_id = ?
                )
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $id]);

            redirectEdit($id, 'success', 'Termin pembayaran berhasil ditambahkan.');
        }

        if ($action === 'generate_installments') {
            $jumlahTermin = filter_var($_POST['jumlah_termin'] ?? null, FILTER_VALIDATE_INT);
            $tanggalPertama = trim((string)($_POST['tanggal_termin_pertama'] ?? ''));
            $intervalHari = filter_var($_POST['interval_hari'] ?? null, FILTER_VALIDATE_INT);

            if (!$jumlahTermin || $jumlahTermin < 1 || $jumlahTermin > 60) {
                throw new RuntimeException('Jumlah termin harus antara 1 sampai 60.');
            }
            if ($tanggalPertama === '') {
                throw new RuntimeException('Tanggal termin pertama wajib diisi.');
            }
            if ($intervalHari === false || $intervalHari < 0 || $intervalHari > 3650) {
                throw new RuntimeException('Interval hari tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, tanggal, total, status
                FROM penjualan
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $saleLocked = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$saleLocked) {
                throw new RuntimeException('Penjualan tidak ditemukan.');
            }
            if (strtoupper((string)$saleLocked['status']) === 'BATAL') {
                throw new RuntimeException('Penjualan yang sudah BATAL tidak dapat dibuatkan jadwal pembayaran.');
            }
            if ($tanggalPertama < $saleLocked['tanggal']) {
                throw new RuntimeException('Tanggal termin pertama tidak boleh sebelum tanggal penjualan.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM penjualan_pembayaran
                WHERE penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $totalTerjadwal = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM pemasukan
                WHERE sumber = 'PENJUALAN'
                  AND penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $totalDibayar = (float)$stmt->fetchColumn();

            $sisa = (float)$saleLocked['total'] - max($totalTerjadwal, $totalDibayar);
            if ($sisa <= 0.00001) {
                throw new RuntimeException('Tidak ada sisa tagihan yang dapat dibuatkan jadwal.');
            }

            $nominalDasar = floor(($sisa / $jumlahTermin) * 100) / 100;
            $terminTerakhir = $sisa - ($nominalDasar * ($jumlahTermin - 1));

            if ($nominalDasar <= 0) {
                throw new RuntimeException('Nominal per termin terlalu kecil.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(termin_ke), 0)
                FROM penjualan_pembayaran
                WHERE penjualan_id = ?
            ");
            $stmt->execute([$id]);
            $terminKe = (int)$stmt->fetchColumn();

            $insert = $pdo->prepare("
                INSERT INTO penjualan_pembayaran
                    (penjualan_id, termin_ke, jenis_pembayaran, tanggal_jatuh_tempo, nominal, status)
                VALUES (?, ?, 'CICILAN', ?, ?, 'BELUM_BAYAR')
            ");

            for ($i = 1; $i <= $jumlahTermin; $i++) {
                $date = new DateTime($tanggalPertama);
                if ($intervalHari > 0) {
                    $date->modify('+' . (($i - 1) * $intervalHari) . ' days');
                }

                $nominalTermin = ($i === $jumlahTermin) ? $terminTerakhir : $nominalDasar;
                $insert->execute([
                    $id,
                    ++$terminKe,
                    $date->format('Y-m-d'),
                    $nominalTermin
                ]);
            }

            $pdo->commit();

            $stmt = $pdo->prepare("
                UPDATE penjualan
                SET tanggal_jatuh_tempo = (
                    SELECT MAX(tanggal_jatuh_tempo)
                    FROM penjualan_pembayaran
                    WHERE penjualan_id = ?
                )
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $id]);

            redirectEdit(
                $id,
                'success',
                'Jadwal ' . $jumlahTermin . 'x cicilan berhasil dibuat. Nominal termin terakhir disesuaikan agar total tepat.'
            );
        }

        if ($action === 'mark_payment_paid') {
            $paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT);
            $tanggalBayar = trim((string)($_POST['tanggal_bayar'] ?? date('Y-m-d')));
            $metode = strtoupper(trim((string)($_POST['metode_pembayaran'] ?? '')));
            $referensi = trim((string)($_POST['referensi'] ?? ''));
            $keteranganPembayaran = trim((string)($_POST['keterangan_pembayaran'] ?? ''));

            if (!$paymentId) {
                throw new RuntimeException('Termin pembayaran tidak valid.');
            }
            if ($tanggalBayar === '') {
                throw new RuntimeException('Tanggal pembayaran wajib diisi.');
            }
            if ($metode === '') {
                throw new RuntimeException('Metode pembayaran wajib dipilih.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    pp.*,
                    p.nomor_penjualan,
                    p.tanggal,
                    p.total AS total_penjualan,
                    p.status AS status_penjualan
                FROM penjualan_pembayaran pp
                INNER JOIN penjualan p ON p.id = pp.penjualan_id
                WHERE pp.id = ?
                  AND pp.penjualan_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$paymentId, $id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new RuntimeException('Termin pembayaran tidak ditemukan.');
            }
            if (strtoupper((string)$payment['status_penjualan']) === 'BATAL') {
                throw new RuntimeException('Penjualan BATAL tidak dapat menerima pembayaran.');
            }
            if (strtoupper((string)$payment['status']) === 'PAID') {
                throw new RuntimeException('Termin pembayaran ini sudah PAID.');
            }
            if ($tanggalBayar < $payment['tanggal']) {
                throw new RuntimeException('Tanggal pembayaran tidak boleh sebelum tanggal penjualan.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM pemasukan
                WHERE sumber = 'PENJUALAN'
                  AND penjualan_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $totalDibayar = (float)$stmt->fetchColumn();

            $nominalTermin = (float)$payment['nominal'];
            $totalPenjualan = (float)$payment['total_penjualan'];

            if (($totalDibayar + $nominalTermin) > ($totalPenjualan + 0.00001)) {
                throw new RuntimeException('Pembayaran ini menyebabkan total pembayaran melebihi nilai penjualan.');
            }

            $nomorPemasukan = generateNomorPemasukan($pdo, $tanggalBayar);

            $stmt = $pdo->prepare("
                INSERT INTO pemasukan
                    (
                        nomor_pemasukan,
                        tanggal,
                        sumber,
                        penjualan_id,
                        kategori_id,
                        jenis_pembayaran,
                        nominal,
                        metode_pembayaran,
                        referensi,
                        keterangan,
                        created_by,
                        jadwal_pembayaran_id
                    )
                VALUES
                    (?, ?, 'PENJUALAN', ?, NULL, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nomorPemasukan,
                $tanggalBayar,
                $id,
                $payment['jenis_pembayaran'],
                $nominalTermin,
                $metode,
                $referensi !== '' ? $referensi : null,
                $keteranganPembayaran !== '' ? $keteranganPembayaran : null,
                $_SESSION['admin_id'] ?? null,
                $paymentId
            ]);

            $stmt = $pdo->prepare("
                UPDATE penjualan_pembayaran
                SET
                    status = 'PAID',
                    tanggal_bayar = ?,
                    metode_pembayaran = ?,
                    referensi = ?,
                    keterangan = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $tanggalBayar,
                $metode,
                $referensi !== '' ? $referensi : null,
                $keteranganPembayaran !== '' ? $keteranganPembayaran : null,
                $paymentId
            ]);

            $totalSetelahBayar = $totalDibayar + $nominalTermin;

            // Status transaksi tetap menjadi CONFIRMED sampai seluruh tagihan lunas.
            // Saat seluruh pembayaran lunas, status transaksi otomatis menjadi PAID.
            if ($totalSetelahBayar >= $totalPenjualan - 0.00001) {
                $stmt = $pdo->prepare("
                    UPDATE penjualan
                    SET status = 'PAID'
                    WHERE id = ?
                      AND UPPER(COALESCE(status, '')) <> 'BATAL'
                    LIMIT 1
                ");
                $stmt->execute([$id]);
            }

            $pdo->commit();

            redirectEdit(
                $id,
                'success',
                'Pembayaran ' . $payment['jenis_pembayaran'] . ' sebesar ' .
                rupiah($nominalTermin) . ' berhasil dicatat dan masuk ke Pemasukan.'
            );
        }

        if ($action === 'delete_payment_plan') {
            $paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$paymentId) {
                throw new RuntimeException('Termin pembayaran tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, status, penjualan_id
                FROM penjualan_pembayaran
                WHERE id = ?
                  AND penjualan_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$paymentId, $id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new RuntimeException('Termin pembayaran tidak ditemukan.');
            }
            if (strtoupper((string)$payment['status']) === 'PAID') {
                throw new RuntimeException('Termin yang sudah PAID tidak dapat dihapus dari jadwal.');
            }

            $stmt = $pdo->prepare("DELETE FROM penjualan_pembayaran WHERE id = ? LIMIT 1");
            $stmt->execute([$paymentId]);

            $pdo->commit();

            $stmt = $pdo->prepare("
                UPDATE penjualan
                SET tanggal_jatuh_tempo = (
                    SELECT MAX(tanggal_jatuh_tempo)
                    FROM penjualan_pembayaran
                    WHERE penjualan_id = ?
                )
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $id]);

            redirectEdit($id, 'success', 'Termin pembayaran berhasil dihapus.');
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

            if ($newStatus === 'PAID') {
                $stmt = $pdo->prepare("
                    SELECT
                        p.total,
                        COALESCE((
                            SELECT SUM(pm.nominal)
                            FROM pemasukan pm
                            WHERE pm.sumber = 'PENJUALAN'
                              AND pm.penjualan_id = p.id
                        ), 0) AS total_dibayar
                    FROM penjualan p
                    WHERE p.id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$id]);
                $paymentCheck = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$paymentCheck || (float)$paymentCheck['total_dibayar'] < (float)$paymentCheck['total'] - 0.00001) {
                    throw new RuntimeException(
                        'Status PAID hanya dapat dipilih setelah seluruh tagihan lunas. ' .
                        'Gunakan skenario pembayaran dan tandai setiap termin sebagai PAID.'
                    );
                }
            }

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
| SKENARIO / JADWAL PEMBAYARAN
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        pp.id,
        pp.termin_ke,
        pp.jenis_pembayaran,
        pp.tanggal_jatuh_tempo,
        pp.nominal,
        pp.status,
        pp.tanggal_bayar,
        pp.metode_pembayaran,
        pp.referensi,
        pp.keterangan,
        pm.nomor_pemasukan,
        pm.tanggal AS tanggal_pemasukan
    FROM penjualan_pembayaran pp
    LEFT JOIN pemasukan pm
        ON pm.jadwal_pembayaran_id = pp.id
       AND pm.sumber = 'PENJUALAN'
       AND pm.penjualan_id = pp.penjualan_id
    WHERE pp.penjualan_id = ?
    ORDER BY pp.termin_ke ASC, pp.id ASC
");
$stmt->execute([$id]);
$paymentPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDibayar = 0;
$totalTerjadwal = 0;
foreach ($paymentPlans as $paymentPlan) {
    $totalTerjadwal += (float)$paymentPlan['nominal'];
    if (strtoupper((string)$paymentPlan['status']) === 'PAID') {
        $totalDibayar += (float)$paymentPlan['nominal'];
    }
}

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(nominal), 0)
    FROM pemasukan
    WHERE sumber = 'PENJUALAN'
      AND penjualan_id = ?
");
$stmt->execute([$id]);
$totalDibayarAktual = (float)$stmt->fetchColumn();

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

// Menentukan pembayaran terakhir yang membuat invoice benar-benar lunas.
$paidForPrint = array_values(array_filter($paymentPlans, static function ($row) {
    return strtoupper((string)$row['status']) === 'PAID' && !empty($row['tanggal_bayar']);
}));
usort($paidForPrint, static function ($a, $b) {
    $dateCompare = strcmp((string)$a['tanggal_bayar'], (string)$b['tanggal_bayar']);
    if ($dateCompare !== 0) {
        return $dateCompare;
    }
    return ((int)$a['id']) <=> ((int)$b['id']);
});

$paymentPrintInfo = [];
$cumulativePaid = 0.0;
foreach ($paidForPrint as $paidRow) {
    $cumulativePaid += (float)$paidRow['nominal'];
    $paymentPrintInfo[(int)$paidRow['id']] = [
        'cumulative_paid' => $cumulativePaid,
        'remaining' => max(0, $grandTotal - $cumulativePaid),
        'is_pelunasan' => $cumulativePaid >= ($grandTotal - 0.00001),
    ];
}

$sisaTagihan = max(0, $grandTotal - $totalDibayarAktual);
$sisaBelumDijadwalkan = max(0, $grandTotal - $totalTerjadwal);
$allPaymentPlansPaid = !empty($paymentPlans);
foreach ($paymentPlans as $paymentPlanStatus) {
    if (strtoupper((string)$paymentPlanStatus['status']) !== 'PAID') {
        $allPaymentPlansPaid = false;
        break;
    }
}
$invoiceFinalLunas = $grandTotal > 0
    && $allPaymentPlansPaid
    && $totalDibayarAktual >= ($grandTotal - 0.00001);

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
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
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


    .payment-summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0;
        border-bottom: 1px solid #dce3eb;
    }
    .payment-summary-item {
        padding: 11px 16px;
        border-right: 1px solid #e5eaf0;
    }
    .payment-summary-item:last-child { border-right: 0; }
    .payment-summary-label {
        color: #718096; font-size: 11px; margin-bottom: 5px;
    }
    .payment-summary-value {
        color: #172b4d; font-size: 16px; font-weight: 700;
    }
    .payment-green { color: #198754; }
    .payment-red { color: #dc3545; }
    .payment-orange { color: #b26a00; }
    .payment-badge {
        display: inline-block; padding: 4px 8px; border-radius: 5px;
        font-size: 10px; font-weight: 700; white-space: nowrap;
    }
    .payment-badge-unpaid { background: #fff3cd; color: #856404; }
    .payment-badge-paid { background: #d1e7dd; color: #0f5132; }
    .payment-actions {
        display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;
    }
    .payment-note {
        padding: 9px 16px; background: #f7f9fc;
        border-bottom: 1px solid #dce3eb; color: #718096; font-size: 11px;
    }
    .modal-wide { width: min(760px, 100%); }

    .invoice-print,
    .payment-invoice-print {
        display: none;
    }

    .payment-print-button {
        border-color: #0d6efd;
        color: #0d6efd;
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

        .payment-summary {
            grid-template-columns: repeat(2, 1fr);
        }

        .payment-summary-item:nth-child(2) {
            border-right: 0;
        }

        .payment-summary-item:nth-child(-n+2) {
            border-bottom: 1px solid #e5eaf0;
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

        .payment-summary {
            grid-template-columns: 1fr;
        }

        .payment-summary-item {
            border-right: 0;
            border-bottom: 1px solid #e5eaf0;
        }

        .summary-item:last-child {
            border-bottom: 0;
        }
    }

    .final-invoice-print {
        display: none;
    }

    @media print {
        body * {
            visibility: hidden !important;
        }

        body:not(.printing-payment) .invoice-print,
        body:not(.printing-payment) .invoice-print *,
        body.printing-payment .payment-invoice-print.print-target,
        body.printing-payment .payment-invoice-print.print-target * {
            visibility: visible !important;
        }

        body.printing-payment .invoice-print,
        body.printing-payment .invoice-print * {
            visibility: hidden !important;
            display: none !important;
        }

        body:not(.printing-payment) .payment-invoice-print {
            visibility: hidden !important;
            display: none !important;
        }

        body.printing-payment .payment-invoice-print.print-target {
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

        .payment-invoice-print.print-target {
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

        .invoice-print h1,
        .payment-invoice-print h1 {
            margin: 0 0 5px;
            font-size: 22px;
        }

        .invoice-print table,
        .payment-invoice-print table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        .invoice-print th,
        .invoice-print td,
        .payment-invoice-print th,
        .payment-invoice-print td {
            border: 1px solid #999;
            padding: 7px;
            font-size: 11px;
        }

        .invoice-print th,
        .payment-invoice-print th {
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

        .payment-invoice-title {
            margin-top: 12px;
            font-size: 16px;
            font-weight: 700;
        }

        .payment-invoice-status {
            display: inline-block;
            margin-top: 8px;
            padding: 5px 10px;
            border: 1px solid #198754;
            color: #198754;
            font-weight: 700;
            font-size: 11px;
        }

        .payment-receipt-summary {
            margin-top: 16px;
            margin-left: auto;
            width: min(360px, 100%);
            border-top: 1px solid #999;
        }

        .payment-receipt-summary div {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 7px 0;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }

        .payment-only-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        .payment-only-table th,
        .payment-only-table td {
            border: 1px solid #999;
            padding: 9px;
            font-size: 11px;
        }
        body.printing-final .invoice-print,
        body.printing-final .invoice-print *,
        body.printing-final .payment-invoice-print,
        body.printing-final .payment-invoice-print * {
            visibility: hidden !important;
            display: none !important;
        }

        body.printing-final .final-invoice-print {
            display: block !important;
            visibility: visible !important;
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

        body.printing-final .final-invoice-print * {
            visibility: visible !important;
        }

        .final-invoice-print h1 {
            margin: 0 0 5px;
            font-size: 22px;
        }

        .final-invoice-title {
            margin-top: 10px;
            font-size: 16px;
            font-weight: 700;
        }

        .final-invoice-print table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        .final-invoice-print th,
        .final-invoice-print td {
            border: 1px solid #999;
            padding: 7px;
            font-size: 10px;
            vertical-align: top;
        }

        .final-invoice-print th {
            background: #eee;
        }

        .final-paid-badge {
            display: inline-block;
            margin-top: 14px;
            padding: 6px 12px;
            border: 1px solid #198754;
            color: #198754;
            font-weight: 700;
            font-size: 11px;
        }

        .final-section-title {
            margin: 20px 0 0;
            font-size: 13px;
        }

        .final-invoice-total {
            margin-top: 15px;
            margin-left: auto;
            width: 330px;
        }

        .final-invoice-total div {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 4px 0;
            font-size: 11px;
        }

        .final-invoice-total .final-remaining {
            border-top: 1px solid #999;
            margin-top: 4px;
            padding-top: 7px;
            font-size: 13px;
            font-weight: 700;
        }

        .final-lunas-note {
            margin-top: 22px;
            padding: 10px;
            border: 1px solid #999;
            font-size: 11px;
        }

        .final-signature {
            margin-top: 35px;
            text-align: right;
            font-size: 11px;
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
        <?php if ($invoiceFinalLunas): ?>
            <button type="button" class="btn btn-primary" onclick="printFinalInvoice()">Cetak Invoice Final / Pelunasan</button>
        <?php else: ?>
            <button type="button" class="btn btn-primary" onclick="window.print()">Cetak Invoice</button>
        <?php endif; ?>
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
        <div class="section-toolbar-title">Skenario / Jadwal Pembayaran</div>
        <div class="top-actions">
            <button type="button" class="btn" onclick="openInstallmentModal()">+ Buat Cicilan Otomatis</button>
            <button type="button" class="btn btn-primary" onclick="openPaymentPlanModal()">+ Tambah Termin</button>
        </div>
    </div>

    <div class="payment-summary">
        <div class="payment-summary-item">
            <div class="payment-summary-label">Grand Total Tagihan</div>
            <div class="payment-summary-value"><?php echo rupiah($grandTotal); ?></div>
        </div>
        <div class="payment-summary-item">
            <div class="payment-summary-label">Total Terbayar</div>
            <div class="payment-summary-value payment-green"><?php echo rupiah($totalDibayarAktual); ?></div>
        </div>
        <div class="payment-summary-item">
            <div class="payment-summary-label">Sisa Tagihan</div>
            <div class="payment-summary-value payment-red"><?php echo rupiah($sisaTagihan); ?></div>
        </div>
        <div class="payment-summary-item">
            <div class="payment-summary-label">Belum Dijadwalkan</div>
            <div class="payment-summary-value payment-orange"><?php echo rupiah($sisaBelumDijadwalkan); ?></div>
        </div>
    </div>

    <?php if ($totalTerjadwal > $grandTotal + 0.00001): ?>
        <div class="payment-note" style="color:#b42318;background:#fff5f5;">
            Perhatian: total jadwal pembayaran melebihi grand total. Periksa kembali jadwal pembayaran.
        </div>
    <?php elseif ($totalTerjadwal < $grandTotal - 0.00001): ?>
        <div class="payment-note">
            Jadwal pembayaran baru mencakup <?php echo rupiah($totalTerjadwal); ?>.
            Masih ada <?php echo rupiah($sisaBelumDijadwalkan); ?> yang belum masuk skenario pembayaran.
        </div>
    <?php else: ?>
        <div class="payment-note">
            Seluruh grand total sudah teralokasi ke dalam skenario pembayaran.
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Termin</th>
                    <th>Jenis</th>
                    <th>Jatuh Tempo</th>
                    <th>Nominal</th>
                    <th>Status</th>
                    <th>Tanggal Bayar</th>
                    <th>Metode</th>
                    <th>Referensi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$paymentPlans): ?>
                    <tr>
                        <td colspan="9" class="empty-row">
                            Belum ada skenario pembayaran. Tambahkan DP, cicilan, atau pelunasan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paymentPlans as $paymentPlan): ?>
                        <?php $paymentStatus = strtoupper((string)$paymentPlan['status']); ?>
                        <tr>
                            <td><strong>Termin <?php echo (int)$paymentPlan['termin_ke']; ?></strong></td>
                            <td><?php echo h($paymentPlan['jenis_pembayaran']); ?></td>
                            <td><?php echo h($paymentPlan['tanggal_jatuh_tempo']); ?></td>
                            <td class="money"><?php echo rupiah($paymentPlan['nominal']); ?></td>
                            <td>
                                <span class="payment-badge <?php echo $paymentStatus === 'PAID' ? 'payment-badge-paid' : 'payment-badge-unpaid'; ?>">
                                    <?php echo $paymentStatus === 'PAID' ? 'PAID' : 'NON PAID'; ?>
                                </span>
                            </td>
                            <td><?php echo h($paymentPlan['tanggal_bayar'] ?: '-'); ?></td>
                            <td><?php echo h($paymentPlan['metode_pembayaran'] ?: '-'); ?></td>
                            <td><?php echo h($paymentPlan['referensi'] ?: '-'); ?></td>
                            <td class="action-cell">
                                <?php if ($paymentStatus !== 'PAID'): ?>
                                    <div class="payment-actions">
                                        <button type="button" class="btn btn-success"
                                            onclick='openPayModal(<?php echo json_encode([
                                                'id' => (int)$paymentPlan['id'],
                                                'jenis' => $paymentPlan['jenis_pembayaran'],
                                                'nominal' => (float)$paymentPlan['nominal'],
                                                'tanggal' => date('Y-m-d')
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>
                                            Tandai PAID
                                        </button>
                                        <form method="post" onsubmit="return confirm('Hapus termin pembayaran ini?');">
                                            <input type="hidden" name="action" value="delete_payment_plan">
                                            <input type="hidden" name="sale_id" value="<?php echo $id; ?>">
                                            <input type="hidden" name="payment_id" value="<?php echo (int)$paymentPlan['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Hapus</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="payment-actions">
                                        <span style="font-size:11px;color:#718096;">Sudah masuk Kas</span>
                                        <button type="button" class="btn payment-print-button"
                                            onclick="printPaymentInvoice(<?php echo (int)$paymentPlan['id']; ?>)">
                                            <?php echo !empty($paymentPrintInfo[(int)$paymentPlan['id']]['is_pelunasan']) ? 'Cetak Invoice Pelunasan' : 'Cetak Invoice Termin'; ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($paymentPlan['keterangan'])): ?>
                            <tr>
                                <td></td>
                                <td colspan="8" style="font-size:11px;color:#718096;">
                                    Keterangan: <?php echo h($paymentPlan['keterangan']); ?>
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
    <div class="section-toolbar">
        <div class="section-toolbar-title">
            Item Alat Berat (<?php echo count($details); ?> unit)
        </div>

        <button
            type="button"
            class="btn btn-primary"
            onclick="openUnitModal(); return false;"
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


<!-- Modal Tambah Termin Pembayaran -->
<div class="modal-backdrop" id="paymentPlanModal">
    <div class="modal modal-wide">
        <div class="modal-header">
            <div class="modal-title">Tambah Skenario Pembayaran</div>
            <button type="button" class="modal-close" onclick="closePaymentPlanModal()">×</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="add_payment_plan">
            <input type="hidden" name="sale_id" value="<?php echo $id; ?>">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="field-label">Jenis Pembayaran *</label>
                        <select name="jenis_pembayaran" class="form-control" required>
                            <option value="DP">DP</option>
                            <option value="CICILAN">CICILAN</option>
                            <option value="PELUNASAN">PELUNASAN</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Tanggal Jatuh Tempo *</label>
                        <input type="date" name="tanggal_jatuh_tempo_pembayaran" class="form-control"
                               min="<?php echo h($sale['tanggal']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Nominal *</label>
                        <input type="text" name="nominal_pembayaran" class="form-control"
                               inputmode="numeric" placeholder="Contoh: 100000000" required>
                    </div>
                </div>
                <div class="calc-box">
                    <div class="calc-row"><span>Grand Total</span><strong><?php echo rupiah($grandTotal); ?></strong></div>
                    <div class="calc-row"><span>Sudah Terjadwal</span><strong><?php echo rupiah($totalTerjadwal); ?></strong></div>
                    <div class="calc-row"><span>Sisa yang dapat dijadwalkan</span><strong class="payment-orange"><?php echo rupiah($sisaBelumDijadwalkan); ?></strong></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closePaymentPlanModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Termin</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Buat Cicilan Otomatis -->
<div class="modal-backdrop" id="installmentModal">
    <div class="modal modal-wide">
        <div class="modal-header">
            <div class="modal-title">Buat Cicilan Otomatis</div>
            <button type="button" class="modal-close" onclick="closeInstallmentModal()">×</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="generate_installments">
            <input type="hidden" name="sale_id" value="<?php echo $id; ?>">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="field-label">Jumlah Termin *</label>
                        <input type="number" name="jumlah_termin" class="form-control" min="1" max="60" value="5" required>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Tanggal Termin Pertama *</label>
                        <input type="date" name="tanggal_termin_pertama" class="form-control"
                               min="<?php echo h($sale['tanggal']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Jarak Antar Termin (Hari) *</label>
                        <input type="number" name="interval_hari" class="form-control" min="0" max="3650" value="30" required>
                    </div>
                </div>
                <div class="calc-box">
                    <div class="calc-row"><span>Grand Total</span><strong><?php echo rupiah($grandTotal); ?></strong></div>
                    <div class="calc-row"><span>Sudah Terbayar</span><strong class="payment-green"><?php echo rupiah($totalDibayarAktual); ?></strong></div>
                    <div class="calc-row"><span>Belum Dijadwalkan</span><strong class="payment-orange"><?php echo rupiah($sisaBelumDijadwalkan); ?></strong></div>
                </div>
                <div class="payment-note" style="margin-top:12px;border:1px solid #e2e8f0;border-radius:5px;">
                    Sistem membagi sisa yang belum dijadwalkan secara rata ke jumlah termin.
                    Jika ada selisih pembulatan, selisih dimasukkan ke termin terakhir.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeInstallmentModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Buat Jadwal Cicilan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tandai Pembayaran PAID -->
<div class="modal-backdrop" id="payModal">
    <div class="modal modal-wide">
        <div class="modal-header">
            <div class="modal-title">Konfirmasi Pembayaran</div>
            <button type="button" class="modal-close" onclick="closePayModal()">×</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="mark_payment_paid">
            <input type="hidden" name="sale_id" value="<?php echo $id; ?>">
            <input type="hidden" name="payment_id" id="payPaymentId">
            <div class="modal-body">
                <div class="calc-box" style="margin-top:0;">
                    <div class="calc-row"><span>Jenis</span><strong id="payJenis">-</strong></div>
                    <div class="calc-row"><span>Nominal</span><strong id="payNominal">Rp 0</strong></div>
                </div>
                <div class="form-grid" style="margin-top:14px;">
                    <div class="form-group">
                        <label class="field-label">Tanggal Pembayaran *</label>
                        <input type="date" name="tanggal_bayar" id="payTanggal" class="form-control"
                               min="<?php echo h($sale['tanggal']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Metode Pembayaran *</label>
                        <select name="metode_pembayaran" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            <option value="TRANSFER">TRANSFER</option>
                            <option value="TUNAI">TUNAI</option>
                            <option value="GIRO">GIRO</option>
                            <option value="CEK">CEK</option>
                            <option value="LAINNYA">LAINNYA</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Referensi</label>
                        <input type="text" name="referensi" class="form-control" placeholder="No. bukti transfer / kuitansi">
                    </div>
                    <div class="form-group full">
                        <label class="field-label">Keterangan</label>
                        <textarea name="keterangan_pembayaran" class="form-control" rows="3" placeholder="Catatan pembayaran..."></textarea>
                    </div>
                </div>
                <div class="payment-note" style="margin-top:12px;border:1px solid #cfe2ff;border-radius:5px;background:#f3f8ff;color:#084298;">
                    Setelah disimpan, pembayaran otomatis dicatat ke <strong>Pemasukan</strong> sebagai sumber <strong>PENJUALAN</strong>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closePayModal()">Batal</button>
                <button type="submit" class="btn btn-success"
                        onclick="return confirm('Catat pembayaran ini sebagai PAID dan masukkan ke Pemasukan?');">
                    Simpan Pembayaran
                </button>
            </div>
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

                        <?php if (empty($availableUnits)): ?>
                            <div class="unit-info" style="color:#b42318;">
                                Tidak ada unit alat berat yang tersedia untuk ditambahkan ke penjualan ini.
                            </div>
                        <?php endif; ?>
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

<!-- Tampilan khusus cetak invoice pembayaran per termin -->
<?php foreach ($paymentPlans as $paymentPlan): ?>
    <?php
    $paymentIdPrint = (int)$paymentPlan['id'];
    $paymentStatusPrint = strtoupper((string)$paymentPlan['status']);
    if ($paymentStatusPrint !== 'PAID' || empty($paymentPlan['tanggal_bayar'])) {
        continue;
    }
    $printInfo = $paymentPrintInfo[$paymentIdPrint] ?? [
        'cumulative_paid' => (float)$paymentPlan['nominal'],
        'remaining' => max(0, $grandTotal - (float)$paymentPlan['nominal']),
        'is_pelunasan' => false,
    ];
    $judulPembayaran = $printInfo['is_pelunasan'] ? 'BUKTI PELUNASAN' : 'BUKTI PEMBAYARAN TERMIN';
    ?>
    <div class="payment-invoice-print" id="paymentInvoice-<?php echo $paymentIdPrint; ?>">
        <h1>ASRINDO</h1>
        <div class="payment-invoice-title"><?php echo h($judulPembayaran); ?></div>
        <hr>

        <table style="border:0; margin-top:10px;">
            <tr>
                <td style="border:0;"><strong>No. Penjualan</strong></td>
                <td style="border:0;"><?php echo h($sale['nomor_penjualan']); ?></td>
                <td style="border:0;"><strong>No. Bukti</strong></td>
                <td style="border:0;"><?php echo h($paymentPlan['nomor_pemasukan'] ?: '-'); ?></td>
            </tr>
            <tr>
                <td style="border:0;"><strong>Customer</strong></td>
                <td style="border:0;"><?php echo h($sale['customer_nama']); ?></td>
                <td style="border:0;"><strong>Tanggal Bayar</strong></td>
                <td style="border:0;"><?php echo h($paymentPlan['tanggal_bayar']); ?></td>
            </tr>
            <tr>
                <td style="border:0;"><strong>Termin</strong></td>
                <td style="border:0;">Termin <?php echo (int)$paymentPlan['termin_ke']; ?> - <?php echo h($paymentPlan['jenis_pembayaran']); ?></td>
                <td style="border:0;"><strong>Jatuh Tempo</strong></td>
                <td style="border:0;"><?php echo h($paymentPlan['tanggal_jatuh_tempo']); ?></td>
            </tr>
            <tr>
                <td style="border:0;"><strong>Metode</strong></td>
                <td style="border:0;"><?php echo h($paymentPlan['metode_pembayaran'] ?: '-'); ?></td>
                <td style="border:0;"><strong>Referensi</strong></td>
                <td style="border:0;"><?php echo h($paymentPlan['referensi'] ?: '-'); ?></td>
            </tr>
        </table>

        <div class="payment-invoice-status">PEMBAYARAN TELAH DITERIMA</div>

        <!-- Hanya pembayaran termin yang dipilih, bukan seluruh item penjualan -->
        <table class="payment-only-table">
            <thead>
                <tr>
                    <th>Keterangan Pembayaran</th>
                    <th style="text-align:right;">Nominal</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Termin <?php echo (int)$paymentPlan['termin_ke']; ?> - <?php echo h($paymentPlan['jenis_pembayaran']); ?></strong><br>
                        <span style="font-size:10px;">Pembayaran yang diterima pada <?php echo h($paymentPlan['tanggal_bayar']); ?></span>
                    </td>
                    <td style="text-align:right;font-size:15px;"><strong><?php echo rupiah($paymentPlan['nominal']); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="payment-receipt-summary">
            <div><span>Sisa Tagihan Setelah Pembayaran</span><strong><?php echo rupiah($printInfo['remaining']); ?></strong></div>
            <?php if ($printInfo['is_pelunasan']): ?>
                <div><span>Status Tagihan</span><strong>LUNAS</strong></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($paymentPlan['keterangan'])): ?>
            <p style="margin-top:18px;">Keterangan: <?php echo nl2br(h($paymentPlan['keterangan'])); ?></p>
        <?php endif; ?>

        <div style="margin-top:35px;font-size:11px;">
            Dokumen ini merupakan bukti penerimaan pembayaran untuk transaksi tersebut.
        </div>
        <div style="margin-top:35px;text-align:right;font-size:11px;">
            ASRINDO<br><br><br>________________________
        </div>
    </div>
<?php endforeach; ?>

<!-- Tampilan khusus cetak invoice final / pelunasan -->
<?php if ($invoiceFinalLunas): ?>
<div class="final-invoice-print" id="finalInvoice">
    <h1>ASRINDO</h1>
    <div class="final-invoice-title">INVOICE FINAL / PELUNASAN</div>
    <hr>

    <table style="border:0; margin-top:10px;">
        <tr>
            <td style="border:0;"><strong>No. Penjualan</strong></td>
            <td style="border:0;"><?php echo h($sale['nomor_penjualan']); ?></td>
            <td style="border:0;"><strong>Tanggal Penjualan</strong></td>
            <td style="border:0;"><?php echo h($sale['tanggal']); ?></td>
        </tr>
        <tr>
            <td style="border:0;"><strong>Customer</strong></td>
            <td style="border:0;"><?php echo h($sale['customer_nama']); ?></td>
            <td style="border:0;"><strong>Tanggal Pelunasan</strong></td>
            <td style="border:0;"><?php echo h($paidForPrint ? $paidForPrint[count($paidForPrint)-1]['tanggal_bayar'] : '-'); ?></td>
        </tr>
        <tr>
            <td style="border:0;"><strong>Jatuh Tempo</strong></td>
            <td style="border:0;"><?php echo h($sale['tanggal_jatuh_tempo'] ?: '-'); ?></td>
            <td style="border:0;"><strong>Status</strong></td>
            <td style="border:0;"><strong>LUNAS</strong></td>
        </tr>
        <tr>
            <td style="border:0;"><strong>Alamat</strong></td>
            <td colspan="3" style="border:0;"><?php echo nl2br(h($sale['customer_alamat'] ?: '-')); ?></td>
        </tr>
    </table>

    <div class="final-paid-badge">TAGIHAN TELAH LUNAS</div>

    <h3 class="final-section-title">Rincian Pembayaran</h3>
    <table class="final-payment-table">
        <thead>
            <tr>
                <th>Termin</th>
                <th>Jenis</th>
                <th>Jatuh Tempo</th>
                <th>Tanggal Bayar</th>
                <th>Metode</th>
                <th>Referensi</th>
                <th style="text-align:right;">Nominal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paymentPlans as $paymentPlan): ?>
                <tr>
                    <td>Termin <?php echo (int)$paymentPlan['termin_ke']; ?></td>
                    <td><?php echo h($paymentPlan['jenis_pembayaran']); ?></td>
                    <td><?php echo h($paymentPlan['tanggal_jatuh_tempo']); ?></td>
                    <td><?php echo h($paymentPlan['tanggal_bayar'] ?: '-'); ?></td>
                    <td><?php echo h($paymentPlan['metode_pembayaran'] ?: '-'); ?></td>
                    <td><?php echo h($paymentPlan['referensi'] ?: '-'); ?></td>
                    <td style="text-align:right;"><?php echo rupiah($paymentPlan['nominal']); ?></td>
                </tr>
                <?php if (!empty($paymentPlan['keterangan'])): ?>
                    <tr>
                        <td colspan="7" style="font-size:10px;"><strong>Keterangan Termin <?php echo (int)$paymentPlan['termin_ke']; ?>:</strong> <?php echo nl2br(h($paymentPlan['keterangan'])); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="final-invoice-total">
        <div><span>Subtotal</span><strong><?php echo rupiah($subtotalReal); ?></strong></div>
        <div><span>PPN (<?php echo number_format($ppn, 2, ',', '.'); ?>%)</span><strong><?php echo rupiah($ppnNominal); ?></strong></div>
        <div><span>Grand Total</span><strong><?php echo rupiah($grandTotal); ?></strong></div>
        <div><span>Total Seluruh Pembayaran</span><strong><?php echo rupiah($totalDibayarAktual); ?></strong></div>
        <div class="final-remaining"><span>Sisa Tagihan</span><strong><?php echo rupiah($sisaTagihan); ?></strong></div>
    </div>

    <div class="final-lunas-note">
        Dengan dokumen ini dinyatakan bahwa seluruh kewajiban pembayaran atas transaksi tersebut telah diterima dan tagihan dinyatakan <strong>LUNAS</strong>.
    </div>

    <?php if (!empty($sale['keterangan'])): ?>
        <p style="margin-top:18px;"><strong>Keterangan Penjualan:</strong> <?php echo nl2br(h($sale['keterangan'])); ?></p>
    <?php endif; ?>

    <div class="final-signature">
        ASRINDO<br><br><br>________________________
    </div>
</div>
<?php endif; ?>

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

function printFinalInvoice() {
    var target = document.getElementById('finalInvoice');
    if (!target) {
        alert('Invoice final tidak tersedia. Pastikan seluruh pembayaran sudah PAID.');
        return;
    }

    document.body.classList.add('printing-final');
    window.print();

    setTimeout(function () {
        document.body.classList.remove('printing-final');
    }, 1000);
}

function printPaymentInvoice(paymentId) {
    var target = document.getElementById('paymentInvoice-' + paymentId);
    if (!target) {
        alert('Invoice pembayaran tidak ditemukan.');
        return;
    }

    document.querySelectorAll('.payment-invoice-print').forEach(function (item) {
        item.classList.remove('print-target');
    });

    target.classList.add('print-target');
    document.body.classList.add('printing-payment');
    window.print();

    setTimeout(function () {
        target.classList.remove('print-target');
        document.body.classList.remove('printing-payment');
    }, 1000);
}

function openPaymentPlanModal() {
    var modal = document.getElementById('paymentPlanModal');
    if (!modal) return;
    modal.classList.add('show');
    modal.style.display = 'flex';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
}
function closePaymentPlanModal() {
    var modal = document.getElementById('paymentPlanModal');
    if (!modal) return;
    modal.classList.remove('show');
    modal.style.display = '';
    modal.style.visibility = '';
    modal.style.opacity = '';
}
function openInstallmentModal() {
    var modal = document.getElementById('installmentModal');
    if (!modal) return;
    modal.classList.add('show');
    modal.style.display = 'flex';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
}
function closeInstallmentModal() {
    var modal = document.getElementById('installmentModal');
    if (!modal) return;
    modal.classList.remove('show');
    modal.style.display = '';
    modal.style.visibility = '';
    modal.style.opacity = '';
}
function openPayModal(data) {
    var modal = document.getElementById('payModal');
    if (!modal) return;
    document.getElementById('payPaymentId').value = data.id || '';
    document.getElementById('payJenis').textContent = data.jenis || '-';
    document.getElementById('payNominal').textContent = formatRupiah(data.nominal || 0);
    document.getElementById('payTanggal').value = data.tanggal || '';
    modal.classList.add('show');
    modal.style.display = 'flex';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
}
function closePayModal() {
    var modal = document.getElementById('payModal');
    if (!modal) return;
    modal.classList.remove('show');
    modal.style.display = '';
    modal.style.visibility = '';
    modal.style.opacity = '';
}

function openHeaderModal() {
    document.getElementById('headerModal').classList.add('show');
}

function closeHeaderModal() {
    document.getElementById('headerModal').classList.remove('show');
}

function openUnitModal() {
    var modal = document.getElementById('unitModal');
    if (!modal) {
        alert('Form Tambah Unit tidak ditemukan.');
        return false;
    }

    modal.classList.add('show');
    modal.style.display = 'flex';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';

    updateUnitCalculation();
    return false;
}

function closeUnitModal() {
    var modal = document.getElementById('unitModal');
    if (!modal) return;

    modal.classList.remove('show');
    modal.style.display = '';
    modal.style.visibility = '';
    modal.style.opacity = '';
}

function formatRupiah(value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID', {
        maximumFractionDigits: 0
    });
}

function updateUnitCalculation() {
    var select = document.getElementById('unitSelect');
    if (!select) return;

    var option = select.options[select.selectedIndex];
    var hpp = 0;

    if (option && option.getAttribute('data-hpp')) {
        hpp = Number(option.getAttribute('data-hpp')) || 0;
    }

    var hargaElement = document.getElementById('unitHargaJual');
    var diskonElement = document.getElementById('unitDiskon');

    var harga = hargaElement ? Number(hargaElement.value || 0) : 0;
    var diskon = diskonElement ? Number(diskonElement.value || 0) : 0;
    var subtotal = Math.max(0, harga - diskon);
    var laba = subtotal - hpp;

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
