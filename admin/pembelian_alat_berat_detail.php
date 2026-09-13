<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Detail Pembelian Alat Berat';
$adminBase = '../';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)($value ?? 0), 0, ',', '.');
}

function redirectDetail(int $id, string $type, string $message): void
{
    header('Location: pembelian_alat_berat_detail.php?' . http_build_query([
        'id' => $id,
        'msg_type' => $type,
        'msg' => $message
    ]));
    exit;
}

function generateNomorPengeluaran(PDO $pdo, string $tanggal): string
{
    $prefix = 'PK-' . date('Ymd', strtotime($tanggal)) . '-';

    $stmt = $pdo->prepare("
        SELECT nomor_pengeluaran
        FROM pengeluaran
        WHERE nomor_pengeluaran LIKE ?
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

$id = filter_var($_GET['id'] ?? $_POST['pembelian_id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: pembelian_alat_berat.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_header') {
            $nomor = trim((string)($_POST['nomor_pembelian'] ?? ''));
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $supplierId = filter_var($_POST['supplier_id'] ?? null, FILTER_VALIDATE_INT);
            $estimasi = trim((string)($_POST['estimasi_kedatangan'] ?? ''));
            $kedatangan = trim((string)($_POST['kedatangan_aktual'] ?? ''));
            $kurs = (float)($_POST['kurs_pembelian'] ?? 0);
            $bea = (float)($_POST['biaya_bea_cukai'] ?? 0);
            $pengiriman = (float)($_POST['biaya_pengiriman'] ?? 0);
            $lain = (float)($_POST['biaya_lain'] ?? 0);
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($nomor === '' || $tanggal === '' || !$supplierId) {
                throw new RuntimeException('Nomor pembelian, tanggal, dan supplier wajib diisi.');
            }
            if ($kurs < 0 || $bea < 0 || $pengiriman < 0 || $lain < 0) {
                throw new RuntimeException('Kurs dan biaya tidak boleh negatif.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, total, status
                FROM pembelian_alat_berat
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$purchase) {
                throw new RuntimeException('Pembelian tidak ditemukan.');
            }
            if (strtoupper((string)$purchase['status']) === 'BATAL') {
                throw new RuntimeException('Pembelian BATAL tidak dapat diedit.');
            }

            $stmt = $pdo->prepare("
                SELECT id FROM supplier WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$supplierId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Supplier tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT id FROM pembelian_alat_berat
                WHERE nomor_pembelian = ? AND id <> ? LIMIT 1
            ");
            $stmt->execute([$nomor, $id]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Nomor pembelian sudah digunakan.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(subtotal), 0)
                FROM pembelian_alat_berat_detail
                WHERE pembelian_id = ?
            ");
            $stmt->execute([$id]);
            $detailSubtotal = (float)$stmt->fetchColumn();

            $newTotal = $detailSubtotal + $bea + $pengiriman + $lain;

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM pembelian_pembayaran
                WHERE pembelian_alat_berat_id = ? AND status <> 'BATAL'
            ");
            $stmt->execute([$id]);
            $scheduled = (float)$stmt->fetchColumn();

            if ($newTotal + 0.00001 < $scheduled) {
                throw new RuntimeException(
                    'Total pembelian tidak boleh lebih kecil dari total termin yang sudah dibuat (' .
                    rupiah($scheduled) . ').'
                );
            }

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat
                SET nomor_pembelian = ?,
                    tanggal = ?,
                    supplier_id = ?,
                    estimasi_kedatangan = ?,
                    kedatangan_aktual = ?,
                    kurs_pembelian = ?,
                    biaya_bea_cukai = ?,
                    biaya_pengiriman = ?,
                    biaya_lain = ?,
                    total = ?,
                    keterangan = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $nomor,
                $tanggal,
                $supplierId,
                $estimasi !== '' ? $estimasi : null,
                $kedatangan !== '' ? $kedatangan : null,
                $kurs,
                $bea,
                $pengiriman,
                $lain,
                $newTotal,
                $keterangan !== '' ? $keterangan : null,
                $id
            ]);

            $pdo->commit();
            redirectDetail($id, 'success', 'Informasi pembelian berhasil diperbarui.');
        }

        if ($action === 'add_unit') {
            $alatId = filter_var($_POST['alat_berat_id'] ?? null, FILTER_VALIDATE_INT);
            $hargaBeli = (float)($_POST['harga_beli'] ?? 0);
            $hargaUsd = (float)($_POST['harga_usd'] ?? 0);
            $keterangan = trim((string)($_POST['detail_keterangan'] ?? ''));

            if (!$alatId) {
                throw new RuntimeException('Unit alat berat wajib dipilih.');
            }
            if ($hargaBeli < 0 || $hargaUsd < 0) {
                throw new RuntimeException('Harga tidak boleh negatif.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, kode, status
                FROM alat_berat
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$alatId]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$unit) {
                throw new RuntimeException('Unit alat berat tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT p.status
                FROM pembelian_alat_berat_detail d
                INNER JOIN pembelian_alat_berat p ON p.id = d.pembelian_id
                WHERE d.alat_berat_id = ?
                  AND d.pembelian_id <> ?
                  AND p.status <> 'BATAL'
                LIMIT 1
            ");
            $stmt->execute([$alatId, $id]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Unit tersebut sudah tercatat pada pembelian aktif lain.');
            }

            $stmt = $pdo->prepare("
                SELECT status, total
                FROM pembelian_alat_berat
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$purchase) {
                throw new RuntimeException('Pembelian tidak ditemukan.');
            }
            if (strtoupper((string)$purchase['status']) === 'BATAL') {
                throw new RuntimeException('Pembelian BATAL tidak dapat ditambah unit.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO pembelian_alat_berat_detail
                    (pembelian_id, alat_berat_id, harga_beli, harga_usd, keterangan)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id,
                $alatId,
                $hargaBeli,
                $hargaUsd,
                $keterangan !== '' ? $keterangan : null
            ]);

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat
                SET total = (
                    SELECT COALESCE(SUM(subtotal), 0)
                    FROM pembelian_alat_berat_detail
                    WHERE pembelian_id = ?
                ) + biaya_bea_cukai + biaya_pengiriman + biaya_lain
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $id]);

            $pdo->commit();
            redirectDetail($id, 'success', 'Unit ' . $unit['kode'] . ' berhasil ditambahkan.');
        }

        if ($action === 'update_unit') {
            $detailId = filter_var($_POST['detail_id'] ?? null, FILTER_VALIDATE_INT);
            $alatId = filter_var($_POST['alat_berat_id'] ?? null, FILTER_VALIDATE_INT);
            $hargaBeli = (float)($_POST['harga_beli'] ?? 0);
            $hargaUsd = (float)($_POST['harga_usd'] ?? 0);
            $keterangan = trim((string)($_POST['detail_keterangan'] ?? ''));

            if (!$detailId || !$alatId) {
                throw new RuntimeException('Detail unit tidak valid.');
            }
            if ($hargaBeli < 0 || $hargaUsd < 0) {
                throw new RuntimeException('Harga tidak boleh negatif.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT d.id, d.alat_berat_id AS old_alat_id, p.status
                FROM pembelian_alat_berat_detail d
                INNER JOIN pembelian_alat_berat p ON p.id = d.pembelian_id
                WHERE d.id = ? AND d.pembelian_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$detailId, $id]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$detail) {
                throw new RuntimeException('Detail unit tidak ditemukan.');
            }
            if (strtoupper((string)$detail['status']) === 'BATAL') {
                throw new RuntimeException('Pembelian BATAL tidak dapat diedit.');
            }

            if ((int)$detail['old_alat_id'] !== $alatId) {
                $stmt = $pdo->prepare("
                    SELECT d.id
                    FROM pembelian_alat_berat_detail d
                    INNER JOIN pembelian_alat_berat p ON p.id = d.pembelian_id
                    WHERE d.alat_berat_id = ?
                      AND d.id <> ?
                      AND p.status <> 'BATAL'
                    LIMIT 1
                ");
                $stmt->execute([$alatId, $detailId]);
                if ($stmt->fetch()) {
                    throw new RuntimeException('Unit pengganti sudah digunakan pada pembelian aktif lain.');
                }
            }

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat_detail
                SET alat_berat_id = ?, harga_beli = ?, harga_usd = ?, keterangan = ?
                WHERE id = ? AND pembelian_id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $alatId,
                $hargaBeli,
                $hargaUsd,
                $keterangan !== '' ? $keterangan : null,
                $detailId,
                $id
            ]);

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat
                SET total = (
                    SELECT COALESCE(SUM(subtotal), 0)
                    FROM pembelian_alat_berat_detail
                    WHERE pembelian_id = ?
                ) + biaya_bea_cukai + biaya_pengiriman + biaya_lain
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $id]);

            $stmt = $pdo->prepare("
                SELECT total FROM pembelian_alat_berat WHERE id = ? FOR UPDATE
            ");
            $stmt->execute([$id]);
            $newTotal = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM pembelian_pembayaran
                WHERE pembelian_alat_berat_id = ? AND status <> 'BATAL'
            ");
            $stmt->execute([$id]);
            $scheduled = (float)$stmt->fetchColumn();

            if ($newTotal + 0.00001 < $scheduled) {
                throw new RuntimeException('Perubahan unit membuat total pembelian lebih kecil dari total termin yang sudah dibuat.');
            }

            $pdo->commit();
            redirectDetail($id, 'success', 'Detail unit berhasil diperbarui.');
        }

        if ($action === 'delete_unit') {
            $detailId = filter_var($_POST['detail_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$detailId) {
                throw new RuntimeException('ID detail tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT d.id, p.status, p.total
                FROM pembelian_alat_berat_detail d
                INNER JOIN pembelian_alat_berat p ON p.id = d.pembelian_id
                WHERE d.id = ? AND d.pembelian_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$detailId, $id]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$detail) {
                throw new RuntimeException('Detail unit tidak ditemukan.');
            }
            if (strtoupper((string)$detail['status']) === 'BATAL') {
                throw new RuntimeException('Pembelian BATAL tidak dapat diubah.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM pembelian_pembayaran
                WHERE pembelian_alat_berat_id = ? AND status <> 'BATAL'
            ");
            $stmt->execute([$id]);
            $scheduled = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(subtotal), 0)
                FROM pembelian_alat_berat_detail
                WHERE pembelian_id = ? AND id <> ?
            ");
            $stmt->execute([$id, $detailId]);
            $detailSubtotal = (float)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT biaya_bea_cukai, biaya_pengiriman, biaya_lain
                FROM pembelian_alat_berat WHERE id = ? FOR UPDATE
            ");
            $stmt->execute([$id]);
            $cost = $stmt->fetch(PDO::FETCH_ASSOC);

            $newTotal = $detailSubtotal
                + (float)$cost['biaya_bea_cukai']
                + (float)$cost['biaya_pengiriman']
                + (float)$cost['biaya_lain'];

            if ($newTotal + 0.00001 < $scheduled) {
                throw new RuntimeException('Unit tidak dapat dihapus karena total pembelian akan lebih kecil dari termin yang sudah dibuat.');
            }

            $stmt = $pdo->prepare("
                DELETE FROM pembelian_alat_berat_detail
                WHERE id = ? AND pembelian_id = ?
                LIMIT 1
            ");
            $stmt->execute([$detailId, $id]);

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat
                SET total = ?
                WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$newTotal, $id]);

            $pdo->commit();
            redirectDetail($id, 'success', 'Unit berhasil dihapus dari pembelian.');
        }

        if ($action === 'add_payment_plan') {
            $jenis = strtoupper(trim((string)($_POST['jenis_pembayaran'] ?? '')));
            $tanggalJatuhTempo = trim((string)($_POST['tanggal_jatuh_tempo'] ?? ''));
            $nominal = (float)($_POST['nominal'] ?? 0);

            if (!in_array($jenis, ['DP', 'CICILAN', 'PELUNASAN', 'LAINNYA'], true)) {
                throw new RuntimeException('Jenis pembayaran tidak valid.');
            }
            if ($tanggalJatuhTempo === '') {
                throw new RuntimeException('Tanggal jatuh tempo wajib diisi.');
            }
            if ($nominal <= 0) {
                throw new RuntimeException('Nominal pembayaran harus lebih dari 0.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, tanggal, total, status
                FROM pembelian_alat_berat
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$purchase) {
                throw new RuntimeException('Pembelian tidak ditemukan.');
            }
            if (strtoupper((string)$purchase['status']) === 'BATAL') {
                throw new RuntimeException('Pembelian BATAL tidak dapat dibuatkan termin.');
            }
            if ($tanggalJatuhTempo < $purchase['tanggal']) {
                throw new RuntimeException('Jatuh tempo tidak boleh sebelum tanggal pembelian.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM pembelian_pembayaran
                WHERE pembelian_alat_berat_id = ? AND status <> 'BATAL'
            ");
            $stmt->execute([$id]);
            $scheduled = (float)$stmt->fetchColumn();

            $remaining = max(0, (float)$purchase['total'] - $scheduled);
            if ($nominal > $remaining + 0.00001) {
                throw new RuntimeException('Nominal melebihi sisa tagihan yang belum dijadwalkan (' . rupiah($remaining) . ').');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(termin_ke), 0) + 1
                FROM pembelian_pembayaran
                WHERE pembelian_alat_berat_id = ?
            ");
            $stmt->execute([$id]);
            $termin = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                INSERT INTO pembelian_pembayaran
                    (nomor_pembayaran, sumber, pembelian_alat_berat_id,
                     termin_ke, jenis_pembayaran, tanggal_jatuh_tempo, nominal, status)
                VALUES (?, 'ALAT_BERAT', ?, ?, ?, ?, ?, 'BELUM_BAYAR')
            ");

            $stmt->execute([
                'JAD-' . date('YmdHis') . '-' . random_int(10, 99),
                $id,
                $termin,
                $jenis,
                $tanggalJatuhTempo,
                $nominal
            ]);

            $pdo->commit();
            redirectDetail($id, 'success', 'Termin pembayaran berhasil ditambahkan.');
        }

        if ($action === 'generate_installments') {
            $jumlah = filter_var($_POST['jumlah_termin'] ?? null, FILTER_VALIDATE_INT);
            $tanggalPertama = trim((string)($_POST['tanggal_termin_pertama'] ?? ''));
            $interval = filter_var($_POST['interval_hari'] ?? null, FILTER_VALIDATE_INT);

            if (!$jumlah || $jumlah < 1 || $jumlah > 60) {
                throw new RuntimeException('Jumlah termin harus antara 1 sampai 60.');
            }
            if ($tanggalPertama === '') {
                throw new RuntimeException('Tanggal termin pertama wajib diisi.');
            }
            if ($interval === false || $interval < 0 || $interval > 3650) {
                throw new RuntimeException('Interval hari tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, tanggal, total, status
                FROM pembelian_alat_berat
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$purchase) {
                throw new RuntimeException('Pembelian tidak ditemukan.');
            }
            if (strtoupper((string)$purchase['status']) === 'BATAL') {
                throw new RuntimeException('Pembelian BATAL tidak dapat dibuatkan termin.');
            }
            if ($tanggalPertama < $purchase['tanggal']) {
                throw new RuntimeException('Tanggal termin pertama tidak boleh sebelum tanggal pembelian.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM pembelian_pembayaran
                WHERE pembelian_alat_berat_id = ? AND status <> 'BATAL'
            ");
            $stmt->execute([$id]);
            $scheduled = (float)$stmt->fetchColumn();

            $remaining = (float)$purchase['total'] - $scheduled;
            if ($remaining <= 0.00001) {
                throw new RuntimeException('Tidak ada sisa tagihan untuk dibuatkan jadwal.');
            }

            $base = floor(($remaining / $jumlah) * 100) / 100;
            $lastAmount = $remaining - ($base * ($jumlah - 1));

            if ($base <= 0) {
                throw new RuntimeException('Nominal per termin terlalu kecil.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(termin_ke), 0)
                FROM pembelian_pembayaran
                WHERE pembelian_alat_berat_id = ?
            ");
            $stmt->execute([$id]);
            $termin = (int)$stmt->fetchColumn();

            $insert = $pdo->prepare("
                INSERT INTO pembelian_pembayaran
                    (nomor_pembayaran, sumber, pembelian_alat_berat_id,
                     termin_ke, jenis_pembayaran, tanggal_jatuh_tempo, nominal, status)
                VALUES (?, 'ALAT_BERAT', ?, ?, 'CICILAN', ?, ?, 'BELUM_BAYAR')
            ");

            for ($i = 1; $i <= $jumlah; $i++) {
                $date = new DateTime($tanggalPertama);
                if ($interval > 0) {
                    $date->modify('+' . (($i - 1) * $interval) . ' days');
                }

                $amount = $i === $jumlah ? $lastAmount : $base;
                $nomorJadwal = 'JAD-' . date('YmdHis') . '-' . $i . random_int(10, 99);

                $insert->execute([
                    $nomorJadwal,
                    $id,
                    ++$termin,
                    $date->format('Y-m-d'),
                    $amount
                ]);
            }

            $pdo->commit();

            redirectDetail(
                $id,
                'success',
                'Jadwal ' . $jumlah . ' termin berhasil dibuat. Termin terakhir disesuaikan agar total tepat.'
            );
        }

        if ($action === 'mark_payment_paid') {
            $paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT);
            $tanggalBayar = trim((string)($_POST['tanggal_bayar'] ?? date('Y-m-d')));
            $metode = strtoupper(trim((string)($_POST['metode_pembayaran'] ?? '')));
            $referensi = trim((string)($_POST['referensi'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan_pembayaran'] ?? ''));

            if (!$paymentId) {
                throw new RuntimeException('Termin pembayaran tidak valid.');
            }
            if ($tanggalBayar === '' || $metode === '') {
                throw new RuntimeException('Tanggal pembayaran dan metode pembayaran wajib diisi.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    pp.*,
                    p.nomor_pembelian,
                    p.tanggal,
                    p.total AS total_pembelian,
                    p.status AS status_pembelian
                FROM pembelian_pembayaran pp
                INNER JOIN pembelian_alat_berat p ON p.id = pp.pembelian_alat_berat_id
                WHERE pp.id = ?
                  AND pp.pembelian_alat_berat_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$paymentId, $id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new RuntimeException('Termin pembayaran tidak ditemukan.');
            }
            if (strtoupper((string)$payment['status_pembelian']) === 'BATAL') {
                throw new RuntimeException('Pembelian BATAL tidak dapat menerima pembayaran.');
            }
            if (strtoupper((string)$payment['status']) === 'PAID') {
                throw new RuntimeException('Termin ini sudah PAID.');
            }
            if ($tanggalBayar < $payment['tanggal']) {
                throw new RuntimeException('Tanggal pembayaran tidak boleh sebelum tanggal pembelian.');
            }

            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM pembelian_pembayaran
                WHERE pembelian_alat_berat_id = ?
                  AND status = 'PAID'
                  AND id <> ?
                FOR UPDATE
            ");
            $stmt->execute([$id, $paymentId]);
            $alreadyPaid = (float)$stmt->fetchColumn();

            $amount = (float)$payment['nominal'];
            $totalPurchase = (float)$payment['total_pembelian'];

            if ($alreadyPaid + $amount > $totalPurchase + 0.00001) {
                throw new RuntimeException('Pembayaran menyebabkan total pembayaran melebihi nilai pembelian.');
            }

            $nomorPengeluaran = generateNomorPengeluaran($pdo, $tanggalBayar);

            $stmt = $pdo->prepare("
                SELECT id
                FROM pengeluaran
                WHERE pembelian_pembayaran_id = ?
                LIMIT 1
            ");
            $stmt->execute([$paymentId]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Termin ini sudah memiliki transaksi pengeluaran.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM kategori_keuangan
                WHERE kode = 'PEMBELIAN'
                  AND tipe = 'PENGELUARAN'
                LIMIT 1
            ");
            $stmt->execute();
            $kategoriId = $stmt->fetchColumn();
            $kategoriId = $kategoriId !== false ? (int)$kategoriId : null;

            $stmt = $pdo->prepare("
                INSERT INTO pengeluaran
                    (nomor_pengeluaran, tanggal, sumber, pembelian_pembayaran_id,
                     kategori_id, jenis_pengeluaran, nominal, metode_pembayaran,
                     referensi, keterangan, created_by)
                VALUES (?, ?, 'PEMBELIAN', ?, ?, 'Pembayaran Pembelian Alat Berat',
                        ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nomorPengeluaran,
                $tanggalBayar,
                $paymentId,
                $kategoriId,
                $amount,
                $metode,
                $referensi !== '' ? $referensi : null,
                $keterangan !== '' ? $keterangan : null,
                (int)$_SESSION['admin_id']
            ]);

            $stmt = $pdo->prepare("
                UPDATE pembelian_pembayaran
                SET status = 'PAID',
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
                $keterangan !== '' ? $keterangan : null,
                $paymentId
            ]);

            $pdo->commit();

            redirectDetail(
                $id,
                'success',
                'Termin ' . (int)$payment['termin_ke'] . ' sebesar ' .
                rupiah($amount) . ' berhasil PAID dan otomatis dicatat sebagai Pengeluaran.'
            );
        }

        if ($action === 'delete_payment_plan') {
            $paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$paymentId) {
                throw new RuntimeException('Termin pembayaran tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT pp.id, pp.status
                FROM pembelian_pembayaran pp
                WHERE pp.id = ?
                  AND pp.pembelian_alat_berat_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$paymentId, $id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new RuntimeException('Termin pembayaran tidak ditemukan.');
            }
            if (strtoupper((string)$payment['status']) === 'PAID') {
                throw new RuntimeException('Termin yang sudah PAID tidak dapat dihapus.');
            }

            $stmt = $pdo->prepare("DELETE FROM pembelian_pembayaran WHERE id = ? LIMIT 1");
            $stmt->execute([$paymentId]);

            $pdo->commit();
            redirectDetail($id, 'success', 'Termin pembayaran berhasil dihapus.');
        }

        if ($action === 'update_status') {
            $status = strtoupper(trim((string)($_POST['status'] ?? 'PROSES')));

            if (!in_array($status, ['PROSES', 'SELESAI', 'BATAL'], true)) {
                throw new RuntimeException('Status pembelian tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, status
                FROM pembelian_alat_berat
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$purchase) {
                throw new RuntimeException('Pembelian tidak ditemukan.');
            }

            if ($status === 'BATAL') {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(nominal), 0)
                    FROM pembelian_pembayaran
                    WHERE pembelian_alat_berat_id = ? AND status = 'PAID'
                ");
                $stmt->execute([$id]);
                if ((float)$stmt->fetchColumn() > 0) {
                    throw new RuntimeException('Pembelian yang sudah memiliki pembayaran PAID tidak dapat dibatalkan.');
                }

                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM pembelian_pembayaran
                    WHERE pembelian_alat_berat_id = ? AND status <> 'BATAL'
                ");
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() > 0) {
                    throw new RuntimeException('Hapus atau batalkan seluruh termin pembayaran sebelum membatalkan pembelian.');
                }
            }

            $stmt = $pdo->prepare("
                UPDATE pembelian_alat_berat
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$status, $id]);

            $pdo->commit();
            redirectDetail($id, 'success', 'Status pembelian berhasil diubah menjadi ' . $status . '.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirectDetail($id, 'error', $e->getMessage() !== '' ? $e->getMessage() : 'Terjadi kesalahan.');
    }
}

$stmt = $pdo->prepare("
    SELECT
        p.*,
        s.kode AS supplier_kode,
        s.nama AS supplier_nama,
        s.telepon AS supplier_telepon,
        s.email AS supplier_email,
        s.alamat AS supplier_alamat
    FROM pembelian_alat_berat p
    INNER JOIN supplier s ON s.id = p.supplier_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    header('Location: pembelian_alat_berat.php');
    exit;
}

$suppliers = $pdo->query("
    SELECT id, kode, nama
    FROM supplier
    ORDER BY nama ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$units = $pdo->query("
    SELECT id, kode, tipe, nomor_rangka, tahun_pembuatan, kondisi, status, lokasi
    FROM alat_berat
    ORDER BY kode ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        d.id, d.alat_berat_id, d.harga_beli, d.harga_usd, d.subtotal, d.keterangan,
        a.kode, a.tipe, a.nomor_rangka, a.tahun_pembuatan, a.kondisi, a.status AS status_unit
    FROM pembelian_alat_berat_detail d
    INNER JOIN alat_berat a ON a.id = d.alat_berat_id
    WHERE d.pembelian_id = ?
    ORDER BY d.id ASC
");
$stmt->execute([$id]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        pp.*,
        CASE
            WHEN pp.status = 'PAID' THEN 'PAID'
            WHEN pp.tanggal_jatuh_tempo < CURDATE() THEN 'JATUH TEMPO'
            ELSE 'BELUM BAYAR'
        END AS status_tampilan,
        pe.nomor_pengeluaran
    FROM pembelian_pembayaran pp
    LEFT JOIN pengeluaran pe ON pe.pembelian_pembayaran_id = pp.id
    WHERE pp.pembelian_alat_berat_id = ?
    ORDER BY pp.termin_ke ASC, pp.id ASC
");
$stmt->execute([$id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUnit = 0;
$subtotalUnit = 0;
foreach ($details as $d) {
    $totalUnit++;
    $subtotalUnit += (float)$d['subtotal'];
}

$totalScheduled = 0;
$totalPaid = 0;
foreach ($payments as $p) {
    if (strtoupper((string)$p['status']) !== 'BATAL') {
        $totalScheduled += (float)$p['nominal'];
    }
    if (strtoupper((string)$p['status']) === 'PAID') {
        $totalPaid += (float)$p['nominal'];
    }
}
$outstanding = max(0, (float)$purchase['total'] - $totalPaid);
$unscheduled = max(0, (float)$purchase['total'] - $totalScheduled);

$message = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$messageType = ($_GET['msg_type'] ?? '') === 'success' ? 'success' : 'error';

require __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <div>
        <h1>Detail Pembelian Alat Berat</h1>
        <p><?php echo h($purchase['nomor_pembelian']); ?> · <?php echo h($purchase['supplier_nama']); ?></p>
    </div>
    <div class="detail-heading-actions">
        <a href="pembelian_alat_berat.php" class="btn-secondary link-button">← Kembali</a>
        <button type="button" class="btn-primary" id="openHeaderModal">Edit Pembelian</button>
    </div>
</section>

<?php if ($message !== ''): ?>
    <div class="page-alert <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
        <?php echo h($message); ?>
    </div>
<?php endif; ?>

<section class="purchase-detail-summary">
    <div class="summary-card">
        <span>Total Pembelian</span>
        <strong><?php echo rupiah($purchase['total']); ?></strong>
    </div>
    <div class="summary-card">
        <span>Sudah Dibayar</span>
        <strong><?php echo rupiah($totalPaid); ?></strong>
    </div>
    <div class="summary-card">
        <span>Sisa Kewajiban</span>
        <strong><?php echo rupiah($outstanding); ?></strong>
    </div>
    <div class="summary-card">
        <span>Belum Terjadwal</span>
        <strong><?php echo rupiah($unscheduled); ?></strong>
    </div>
</section>

<section class="dashboard-panel detail-panel">
    <div class="panel-heading">
        <div>
            <h2>Informasi Pembelian</h2>
            <span class="panel-subtitle">Header transaksi dan informasi supplier.</span>
        </div>
        <?php
        $status = strtoupper((string)$purchase['status']);
        $statusClass = $status === 'SELESAI' ? 'badge-success' : ($status === 'BATAL' ? 'badge-danger' : 'badge-info');
        ?>
        <span class="purchase-badge large-badge <?php echo $statusClass; ?>"><?php echo h($purchase['status']); ?></span>
    </div>

    <div class="info-grid">
        <div><span>Nomor Pembelian</span><strong><?php echo h($purchase['nomor_pembelian']); ?></strong></div>
        <div><span>Tanggal</span><strong><?php echo h(date('d-m-Y', strtotime($purchase['tanggal']))); ?></strong></div>
        <div><span>Supplier</span><strong><?php echo h($purchase['supplier_kode'] . ' - ' . $purchase['supplier_nama']); ?></strong></div>
        <div><span>Telepon</span><strong><?php echo h($purchase['supplier_telepon'] ?: '-'); ?></strong></div>
        <div><span>Estimasi Kedatangan</span><strong><?php echo $purchase['estimasi_kedatangan'] ? h(date('d-m-Y', strtotime($purchase['estimasi_kedatangan']))) : '-'; ?></strong></div>
        <div><span>Kedatangan Aktual</span><strong><?php echo $purchase['kedatangan_aktual'] ? h(date('d-m-Y', strtotime($purchase['kedatangan_aktual']))) : '-'; ?></strong></div>
        <div><span>Kurs Pembelian</span><strong><?php echo rupiah($purchase['kurs_pembelian']); ?> / USD</strong></div>
        <div><span>Bea Cukai</span><strong><?php echo rupiah($purchase['biaya_bea_cukai']); ?></strong></div>
        <div><span>Pengiriman</span><strong><?php echo rupiah($purchase['biaya_pengiriman']); ?></strong></div>
        <div><span>Biaya Lain</span><strong><?php echo rupiah($purchase['biaya_lain']); ?></strong></div>
        <div class="info-full"><span>Keterangan</span><strong><?php echo nl2br(h($purchase['keterangan'] ?: '-')); ?></strong></div>
    </div>
</section>

<section class="dashboard-panel detail-panel">
    <div class="panel-heading">
        <div>
            <h2>Unit Alat Berat</h2>
            <span class="panel-subtitle"><?php echo number_format($totalUnit, 0, ',', '.'); ?> unit · Subtotal unit <?php echo rupiah($subtotalUnit); ?></span>
        </div>
        <button type="button" class="btn-primary" id="openUnitModal">+ Tambah Unit</button>
    </div>

    <div class="table-responsive">
        <table class="dashboard-table detail-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Tipe</th>
                    <th>No. Rangka</th>
                    <th>Tahun</th>
                    <th>Harga Beli</th>
                    <th>USD</th>
                    <th>Subtotal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($details)): ?>
                <tr><td colspan="9" class="empty-state">Belum ada unit pada pembelian ini.</td></tr>
            <?php else: ?>
                <?php foreach ($details as $i => $detail): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo h($detail['kode']); ?></strong></td>
                        <td><?php echo h($detail['tipe']); ?></td>
                        <td><?php echo h($detail['nomor_rangka'] ?: '-'); ?></td>
                        <td><?php echo h($detail['tahun_pembuatan'] ?: '-'); ?></td>
                        <td class="money-cell"><?php echo rupiah($detail['harga_beli']); ?></td>
                        <td class="money-cell"><?php echo number_format((float)$detail['harga_usd'], 2, ',', '.'); ?></td>
                        <td class="money-cell"><?php echo rupiah($detail['subtotal']); ?></td>
                        <td class="action-cell">
                            <button type="button" class="btn-small edit-unit"
                                data-id="<?php echo (int)$detail['id']; ?>"
                                data-unit="<?php echo (int)$detail['alat_berat_id']; ?>"
                                data-harga="<?php echo h($detail['harga_beli']); ?>"
                                data-usd="<?php echo h($detail['harga_usd']); ?>"
                                data-keterangan="<?php echo h($detail['keterangan']); ?>">Edit</button>
                            <form method="post" class="inline-form" onsubmit="return confirm('Hapus unit <?php echo h($detail['kode']); ?> dari pembelian ini?');">
                                <input type="hidden" name="action" value="delete_unit">
                                <input type="hidden" name="pembelian_id" value="<?php echo (int)$id; ?>">
                                <input type="hidden" name="detail_id" value="<?php echo (int)$detail['id']; ?>">
                                <button type="submit" class="btn-small danger-button">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="7" class="text-right">Total Pembelian</th>
                    <th class="money-cell"><?php echo rupiah($purchase['total']); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<section class="dashboard-panel detail-panel">
    <div class="panel-heading">
        <div>
            <h2>Rencana Pembayaran Supplier</h2>
            <span class="panel-subtitle">Termin PAID otomatis dicatat ke Pengeluaran.</span>
        </div>
        <div class="payment-toolbar">
            <button type="button" class="btn-secondary" id="openGenerateModal">Generate Cicilan</button>
            <button type="button" class="btn-primary" id="openPaymentModal">+ Tambah Termin</button>
        </div>
    </div>

    <div class="payment-overview">
        <div><span>Total Tagihan</span><strong><?php echo rupiah($purchase['total']); ?></strong></div>
        <div><span>Total Terjadwal</span><strong><?php echo rupiah($totalScheduled); ?></strong></div>
        <div><span>Total PAID</span><strong><?php echo rupiah($totalPaid); ?></strong></div>
        <div><span>Sisa Kewajiban</span><strong><?php echo rupiah($outstanding); ?></strong></div>
    </div>

    <div class="table-responsive">
        <table class="dashboard-table payment-table">
            <thead>
                <tr>
                    <th>Termin</th>
                    <th>Jenis</th>
                    <th>Jatuh Tempo</th>
                    <th>Nominal</th>
                    <th>Status</th>
                    <th>Tgl Bayar</th>
                    <th>Metode</th>
                    <th>Pengeluaran</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($payments)): ?>
                <tr><td colspan="9" class="empty-state">Belum ada rencana pembayaran.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <?php
                    $ps = strtoupper((string)$payment['status']);
                    $displayStatus = strtoupper((string)$payment['status_tampilan']);
                    $pc = $ps === 'PAID' ? 'badge-success' : ($displayStatus === 'JATUH TEMPO' ? 'badge-warning' : 'badge-info');
                    ?>
                    <tr>
                        <td><strong>Termin <?php echo (int)$payment['termin_ke']; ?></strong></td>
                        <td><?php echo h($payment['jenis_pembayaran']); ?></td>
                        <td><?php echo h(date('d-m-Y', strtotime($payment['tanggal_jatuh_tempo']))); ?></td>
                        <td class="money-cell"><?php echo rupiah($payment['nominal']); ?></td>
                        <td><span class="purchase-badge <?php echo $pc; ?>"><?php echo h($displayStatus); ?></span></td>
                        <td><?php echo $payment['tanggal_bayar'] ? h(date('d-m-Y', strtotime($payment['tanggal_bayar']))) : '-'; ?></td>
                        <td><?php echo h($payment['metode_pembayaran'] ?: '-'); ?></td>
                        <td><?php echo h($payment['nomor_pengeluaran'] ?: '-'); ?></td>
                        <td class="action-cell">
                            <?php if ($ps !== 'PAID'): ?>
                                <button type="button" class="btn-small paid-button"
                                    data-id="<?php echo (int)$payment['id']; ?>"
                                    data-termin="<?php echo (int)$payment['termin_ke']; ?>"
                                    data-nominal="<?php echo h($payment['nominal']); ?>">Tandai PAID</button>
                                <form method="post" class="inline-form" onsubmit="return confirm('Hapus termin ini?');">
                                    <input type="hidden" name="action" value="delete_payment_plan">
                                    <input type="hidden" name="pembelian_id" value="<?php echo (int)$id; ?>">
                                    <input type="hidden" name="payment_id" value="<?php echo (int)$payment['id']; ?>">
                                    <button type="submit" class="btn-small danger-button">Hapus</button>
                                </form>
                            <?php else: ?>
                                <span class="paid-note">Sudah dicatat</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="dashboard-panel detail-panel">
    <div class="panel-heading">
        <div>
            <h2>Status Transaksi</h2>
            <span class="panel-subtitle">Status pembelian terpisah dari status pembayaran supplier.</span>
        </div>
    </div>
    <div class="status-area">
        <form method="post" class="status-form">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="pembelian_id" value="<?php echo (int)$id; ?>">
            <select name="status" class="status-select">
                <option value="PROSES" <?php echo $status === 'PROSES' ? 'selected' : ''; ?>>PROSES</option>
                <option value="SELESAI" <?php echo $status === 'SELESAI' ? 'selected' : ''; ?>>SELESAI</option>
                <option value="BATAL" <?php echo $status === 'BATAL' ? 'selected' : ''; ?>>BATAL</option>
            </select>
            <button type="submit" class="btn-secondary">Simpan Status</button>
        </form>
        <div class="status-help">Pembelian yang sudah memiliki pembayaran PAID tidak dapat dibatalkan.</div>
    </div>
</section>

<div class="purchase-modal" id="headerModal" aria-hidden="true">
    <div class="purchase-modal-box purchase-modal-large">
        <div class="purchase-modal-header">
            <div><h3>Edit Informasi Pembelian</h3><p><?php echo h($purchase['nomor_pembelian']); ?></p></div>
            <button type="button" class="purchase-modal-close" data-close-modal="headerModal">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="update_header">
            <input type="hidden" name="pembelian_id" value="<?php echo (int)$id; ?>">
            <div class="purchase-modal-body">
                <div class="purchase-form-grid">
                    <div class="purchase-field"><label>Nomor Pembelian <span>*</span></label><input type="text" name="nomor_pembelian" maxlength="50" value="<?php echo h($purchase['nomor_pembelian']); ?>" required></div>
                    <div class="purchase-field"><label>Tanggal <span>*</span></label><input type="date" name="tanggal" value="<?php echo h($purchase['tanggal']); ?>" required></div>
                    <div class="purchase-field purchase-field-full"><label>Supplier <span>*</span></label><select name="supplier_id" required>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo (int)$supplier['id']; ?>" <?php echo (int)$supplier['id'] === (int)$purchase['supplier_id'] ? 'selected' : ''; ?>><?php echo h($supplier['kode'] . ' - ' . $supplier['nama']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div class="purchase-field"><label>Estimasi Kedatangan</label><input type="date" name="estimasi_kedatangan" value="<?php echo h($purchase['estimasi_kedatangan']); ?>"></div>
                    <div class="purchase-field"><label>Kedatangan Aktual</label><input type="date" name="kedatangan_aktual" value="<?php echo h($purchase['kedatangan_aktual']); ?>"></div>
                    <div class="purchase-field"><label>Kurs Pembelian</label><input type="number" name="kurs_pembelian" min="0" step="0.01" value="<?php echo h($purchase['kurs_pembelian']); ?>"></div>
                    <div class="purchase-field"><label>Bea Cukai</label><input type="number" name="biaya_bea_cukai" min="0" step="0.01" value="<?php echo h($purchase['biaya_bea_cukai']); ?>"></div>
                    <div class="purchase-field"><label>Biaya Pengiriman</label><input type="number" name="biaya_pengiriman" min="0" step="0.01" value="<?php echo h($purchase['biaya_pengiriman']); ?>"></div>
                    <div class="purchase-field"><label>Biaya Lain</label><input type="number" name="biaya_lain" min="0" step="0.01" value="<?php echo h($purchase['biaya_lain']); ?>"></div>
                    <div class="purchase-field purchase-field-full"><label>Keterangan</label><textarea name="keterangan" rows="3"><?php echo h($purchase['keterangan']); ?></textarea></div>
                </div>
            </div>
            <div class="purchase-modal-footer"><button type="button" class="btn-secondary" data-close-modal="headerModal">Batal</button><button type="submit" class="btn-primary">Simpan Perubahan</button></div>
        </form>
    </div>
</div>

<div class="purchase-modal" id="unitModal" aria-hidden="true">
    <div class="purchase-modal-box">
        <div class="purchase-modal-header"><div><h3 id="unitModalTitle">Tambah Unit</h3><p>Harga unit menjadi bagian dari total pembelian.</p></div><button type="button" class="purchase-modal-close" data-close-modal="unitModal">&times;</button></div>
        <form method="post" id="unitForm">
            <input type="hidden" name="action" id="unitAction" value="add_unit">
            <input type="hidden" name="pembelian_id" value="<?php echo (int)$id; ?>">
            <input type="hidden" name="detail_id" id="unitDetailId" value="">
            <div class="purchase-modal-body">
                <div class="purchase-form-grid">
                    <div class="purchase-field purchase-field-full"><label>Unit Alat Berat <span>*</span></label><select name="alat_berat_id" id="unitSelect" required>
                        <option value="">-- Pilih Unit --</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?php echo (int)$unit['id']; ?>"><?php echo h($unit['kode'] . ' - ' . $unit['tipe'] . ' - ' . ($unit['nomor_rangka'] ?: 'Tanpa nomor rangka')); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div class="purchase-field"><label>Harga Beli (IDR) <span>*</span></label><input type="number" name="harga_beli" id="unitHarga" min="0" step="0.01" value="0" required></div>
                    <div class="purchase-field"><label>Harga Beli (USD)</label><input type="number" name="harga_usd" id="unitUsd" min="0" step="0.01" value="0"></div>
                    <div class="purchase-field purchase-field-full"><label>Keterangan</label><textarea name="detail_keterangan" id="unitKeterangan" rows="3"></textarea></div>
                </div>
            </div>
            <div class="purchase-modal-footer"><button type="button" class="btn-secondary" data-close-modal="unitModal">Batal</button><button type="submit" class="btn-primary" id="unitSubmit">Simpan Unit</button></div>
        </form>
    </div>
</div>

<div class="purchase-modal" id="paymentModal" aria-hidden="true">
    <div class="purchase-modal-box">
        <div class="purchase-modal-header"><div><h3>Tambah Termin Pembayaran</h3><p>Sisa yang belum dijadwalkan: <?php echo rupiah($unscheduled); ?></p></div><button type="button" class="purchase-modal-close" data-close-modal="paymentModal">&times;</button></div>
        <form method="post">
            <input type="hidden" name="action" value="add_payment_plan"><input type="hidden" name="pembelian_id" value="<?php echo (int)$id; ?>">
            <div class="purchase-modal-body">
                <div class="purchase-form-grid">
                    <div class="purchase-field"><label>Jenis Pembayaran <span>*</span></label><select name="jenis_pembayaran" required><option value="DP">DP</option><option value="CICILAN">CICILAN</option><option value="PELUNASAN">PELUNASAN</option><option value="LAINNYA">LAINNYA</option></select></div>
                    <div class="purchase-field"><label>Jatuh Tempo <span>*</span></label><input type="date" name="tanggal_jatuh_tempo" value="<?php echo h(date('Y-m-d')); ?>" min="<?php echo h($purchase['tanggal']); ?>" required></div>
                    <div class="purchase-field purchase-field-full"><label>Nominal <span>*</span></label><input type="number" name="nominal" min="0.01" step="0.01" max="<?php echo h($unscheduled); ?>" value="<?php echo h($unscheduled); ?>" required></div>
                </div>
            </div>
            <div class="purchase-modal-footer"><button type="button" class="btn-secondary" data-close-modal="paymentModal">Batal</button><button type="submit" class="btn-primary">Tambah Termin</button></div>
        </form>
    </div>
</div>

<div class="purchase-modal" id="generateModal" aria-hidden="true">
    <div class="purchase-modal-box">
        <div class="purchase-modal-header"><div><h3>Generate Cicilan</h3><p>Sisa yang belum dijadwalkan: <?php echo rupiah($unscheduled); ?></p></div><button type="button" class="purchase-modal-close" data-close-modal="generateModal">&times;</button></div>
        <form method="post">
            <input type="hidden" name="action" value="generate_installments"><input type="hidden" name="pembelian_id" value="<?php echo (int)$id; ?>">
            <div class="purchase-modal-body">
                <div class="purchase-form-grid">
                    <div class="purchase-field"><label>Jumlah Termin <span>*</span></label><input type="number" name="jumlah_termin" min="1" max="60" value="3" required></div>
                    <div class="purchase-field"><label>Termin Pertama <span>*</span></label><input type="date" name="tanggal_termin_pertama" value="<?php echo h(date('Y-m-d')); ?>" min="<?php echo h($purchase['tanggal']); ?>" required></div>
                    <div class="purchase-field purchase-field-full"><label>Interval Antar Termin (hari) <span>*</span></label><input type="number" name="interval_hari" min="0" max="3650" value="30" required><small class="form-help">Contoh 30 = setiap 30 hari. Nominal termin terakhir disesuaikan agar total tepat.</small></div>
                </div>
            </div>
            <div class="purchase-modal-footer"><button type="button" class="btn-secondary" data-close-modal="generateModal">Batal</button><button type="submit" class="btn-primary">Generate Jadwal</button></div>
        </form>
    </div>
</div>

<div class="purchase-modal" id="paidModal" aria-hidden="true">
    <div class="purchase-modal-box">
        <div class="purchase-modal-header"><div><h3>Tandai Pembayaran PAID</h3><p id="paidSubtitle"></p></div><button type="button" class="purchase-modal-close" data-close-modal="paidModal">&times;</button></div>
        <form method="post">
            <input type="hidden" name="action" value="mark_payment_paid"><input type="hidden" name="pembelian_id" value="<?php echo (int)$id; ?>"><input type="hidden" name="payment_id" id="paidPaymentId">
            <div class="purchase-modal-body">
                <div class="payment-confirm-box"><span>Nominal dibayar</span><strong id="paidNominal">Rp 0</strong></div>
                <div class="purchase-form-grid">
                    <div class="purchase-field"><label>Tanggal Pembayaran <span>*</span></label><input type="date" name="tanggal_bayar" value="<?php echo h(date('Y-m-d')); ?>" required></div>
                    <div class="purchase-field"><label>Metode Pembayaran <span>*</span></label><select name="metode_pembayaran" required><option value="">-- Pilih --</option><option value="TRANSFER">TRANSFER</option><option value="CASH">CASH</option><option value="GIRO">GIRO</option><option value="CEK">CEK</option><option value="LAINNYA">LAINNYA</option></select></div>
                    <div class="purchase-field purchase-field-full"><label>Referensi</label><input type="text" name="referensi" maxlength="100" placeholder="Nomor transfer / bukti pembayaran"></div>
                    <div class="purchase-field purchase-field-full"><label>Keterangan</label><textarea name="keterangan_pembayaran" rows="3"></textarea></div>
                </div>
            </div>
            <div class="purchase-modal-footer"><button type="button" class="btn-secondary" data-close-modal="paidModal">Batal</button><button type="submit" class="btn-primary">Simpan PAID</button></div>
        </form>
    </div>
</div>

<style>
.detail-heading-actions{display:flex;gap:8px;align-items:center}.link-button{display:inline-flex;align-items:center;text-decoration:none}
.purchase-detail-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}.summary-card{padding:15px 16px;border:1px solid #dfe5ed;border-radius:6px;background:#fff}.summary-card span{display:block;color:#718096;font-size:11px;margin-bottom:7px}.summary-card strong{display:block;color:#183052;font-size:17px}
.detail-panel{margin-bottom:16px;overflow:visible}.panel-subtitle{display:block;margin-top:3px;color:#718096;font-size:12px}.large-badge{margin-left:auto}
.info-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;background:#dfe5ed;border:1px solid #dfe5ed}.info-grid>div{padding:12px;background:#fff}.info-grid span{display:block;color:#718096;font-size:10px;margin-bottom:5px}.info-grid strong{display:block;color:#183052;font-size:12px;line-height:1.45}.info-full{grid-column:1/-1}
.detail-table{min-width:1100px}.detail-table th,.detail-table td,.payment-table th,.payment-table td{vertical-align:middle}.detail-table tfoot th{background:#f7f9fc}.text-right{text-align:right}.money-cell{text-align:right;white-space:nowrap}.action-cell{white-space:nowrap}.inline-form{display:inline}.danger-button{color:#dc3545;border-color:#f0b9c0}.danger-button:hover{border-color:#dc3545;color:#dc3545}
.payment-toolbar{display:flex;gap:7px}.payment-overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.payment-overview>div{padding:11px 12px;border:1px solid #dfe5ed;background:#f8fafc;border-radius:4px}.payment-overview span{display:block;color:#718096;font-size:10px;margin-bottom:4px}.payment-overview strong{display:block;color:#183052;font-size:12px}.payment-table{min-width:1150px}.payment-table .paid-note{color:#10a66a;font-size:10px;font-weight:600}
.badge-warning{background:#f59e0b;color:#fff}.status-area{display:flex;align-items:center;gap:12px}.status-form{display:flex;gap:8px;align-items:center}.status-select{height:36px;min-width:150px;padding:0 10px;border:1px solid #bdcbe0;border-radius:4px;background:#fff;color:#172d4e}.status-help{color:#718096;font-size:11px}
.purchase-modal{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:18px;box-sizing:border-box;background:rgba(23,45,78,.55)}.purchase-modal.show{display:flex}.purchase-modal-box{width:min(760px,100%);max-height:calc(100vh - 36px);overflow-y:auto;border-radius:6px;background:#fff;box-shadow:0 15px 45px rgba(0,0,0,.18)}.purchase-modal-large{width:min(1050px,100%)}
.purchase-modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding:14px 16px;border-bottom:1px solid #dfe5ed}.purchase-modal-header h3{margin:0;color:#183052;font-size:15px}.purchase-modal-header p{margin:5px 0 0;color:#718096;font-size:11px}.purchase-modal-close{width:34px;height:34px;border:0;background:transparent;color:#718096;font-size:25px;cursor:pointer}.purchase-modal-body{padding:18px 16px}.purchase-modal-footer{display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid #dfe5ed}
.purchase-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.purchase-field{min-width:0}.purchase-field-full{grid-column:1/-1}.purchase-field label{display:block;margin-bottom:6px;color:#183052;font-size:11px}.purchase-field label span{color:#dc3545}.purchase-field input,.purchase-field select,.purchase-field textarea{width:100%;box-sizing:border-box;border:1px solid #bdcbe0;border-radius:4px;background:#fff;color:#172d4e;padding:8px 10px;outline:none;font-size:12px}.purchase-field input,.purchase-field select{height:36px}.purchase-field textarea{resize:vertical;min-height:80px}.form-help{display:block;margin-top:5px;color:#718096;font-size:10px}.payment-confirm-box{padding:12px;margin-bottom:15px;border:1px solid #dfe5ed;border-radius:5px;background:#f8fafc}.payment-confirm-box span{display:block;color:#718096;font-size:10px}.payment-confirm-box strong{display:block;margin-top:3px;color:#183052;font-size:18px}
.btn-primary,.btn-secondary{min-height:36px;padding:0 15px;border-radius:4px;font-size:11px;cursor:pointer}.btn-primary{border:0;background:#0d6efd;color:#fff}.btn-secondary{border:0;background:#718096;color:#fff}.btn-primary:hover{background:#0b5ed7}
@media(max-width:1000px){.purchase-detail-summary,.payment-overview{grid-template-columns:repeat(2,minmax(0,1fr))}.info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.info-full{grid-column:1/-1}}
@media(max-width:800px){.detail-heading-actions,.payment-toolbar,.status-area{align-items:stretch;flex-direction:column}.purchase-detail-summary,.payment-overview,.info-grid{grid-template-columns:1fr}.info-full{grid-column:auto}.purchase-form-grid{grid-template-columns:1fr}.purchase-field-full{grid-column:auto}.purchase-modal{padding:10px}.purchase-modal-box{max-height:calc(100vh - 20px)}.status-form{flex-direction:column;align-items:stretch}.status-select{width:100%}}
</style>

<script>
(function(){
'use strict';
function openModal(id){var m=document.getElementById(id);if(m){m.classList.add('show');m.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';}}
function closeModal(id){var m=document.getElementById(id);if(m){m.classList.remove('show');m.setAttribute('aria-hidden','true');if(!document.querySelector('.purchase-modal.show'))document.body.style.overflow='';}}
document.querySelectorAll('[data-close-modal]').forEach(function(b){b.addEventListener('click',function(){closeModal(this.getAttribute('data-close-modal'));});});
document.querySelectorAll('.purchase-modal').forEach(function(m){m.addEventListener('click',function(e){if(e.target===m)closeModal(m.id);});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.purchase-modal.show').forEach(function(m){closeModal(m.id);});});

var headerBtn=document.getElementById('openHeaderModal');if(headerBtn)headerBtn.addEventListener('click',function(){openModal('headerModal');});
var unitBtn=document.getElementById('openUnitModal');if(unitBtn)unitBtn.addEventListener('click',function(){
 document.getElementById('unitAction').value='add_unit';document.getElementById('unitDetailId').value='';document.getElementById('unitModalTitle').textContent='Tambah Unit';document.getElementById('unitSubmit').textContent='Simpan Unit';document.getElementById('unitSelect').value='';document.getElementById('unitHarga').value='0';document.getElementById('unitUsd').value='0';document.getElementById('unitKeterangan').value='';openModal('unitModal');
});
document.querySelectorAll('.edit-unit').forEach(function(b){b.addEventListener('click',function(){
 document.getElementById('unitAction').value='update_unit';document.getElementById('unitDetailId').value=this.dataset.id||'';document.getElementById('unitModalTitle').textContent='Edit Unit';document.getElementById('unitSubmit').textContent='Simpan Perubahan';document.getElementById('unitSelect').value=this.dataset.unit||'';document.getElementById('unitHarga').value=this.dataset.harga||'0';document.getElementById('unitUsd').value=this.dataset.usd||'0';document.getElementById('unitKeterangan').value=this.dataset.keterangan||'';openModal('unitModal');
});});
var paymentBtn=document.getElementById('openPaymentModal');if(paymentBtn)paymentBtn.addEventListener('click',function(){openModal('paymentModal');});
var genBtn=document.getElementById('openGenerateModal');if(genBtn)genBtn.addEventListener('click',function(){openModal('generateModal');});

document.querySelectorAll('.paid-button').forEach(function(b){b.addEventListener('click',function(){
 document.getElementById('paidPaymentId').value=this.dataset.id||'';document.getElementById('paidSubtitle').textContent='Termin '+(this.dataset.termin||'')+' · Pembayaran akan otomatis masuk ke Pengeluaran.';document.getElementById('paidNominal').textContent='Rp '+new Intl.NumberFormat('id-ID',{maximumFractionDigits:0}).format(parseFloat(this.dataset.nominal||0));openModal('paidModal');
});});
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
