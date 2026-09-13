<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: alat_berat.php');
    exit;
}

function h($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($v): string {
    return number_format((float)($v ?? 0), 0, ',', '.');
}

function dateId($v): string {
    if (!$v) return '-';
    $t = strtotime((string)$v);
    return $t ? date('d-M-Y', $t) : h($v);
}

function badgeClass($v): string {
    $v = strtoupper(trim((string)$v));
    if (in_array($v, ['TERSEDIA', 'SIAP JUAL'], true)) return 'success';
    if ($v === 'PERBAIKAN') return 'warning';
    if ($v === 'DISEWAKAN') return 'info';
    if ($v === 'TERJUAL') return 'danger';
    return 'default';
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_alat_berat_detail'])) {
        $_SESSION['csrf_alat_berat_detail'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_alat_berat_detail'];
}

function verifyCsrf(): void {
    if (!hash_equals(
        $_SESSION['csrf_alat_berat_detail'] ?? '',
        (string)($_POST['csrf_token'] ?? '')
    )) {
        throw new RuntimeException('Token keamanan tidak valid.');
    }
}

function money($v): float {
    $v = trim((string)($v ?? ''));
    if ($v === '') return 0;
    $v = str_replace(' ', '', $v);
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
    return (float)$v;
}

function dateForInput($v): string {
    if (!$v) return '';
    $t = strtotime((string)$v);
    return $t ? date('Y-m-d', $t) : '';
}

$csrf = csrfToken();
$message = '';
$error = '';
$openModal = '';

/* =========================================================
   IMAGE TABLE
   ========================================================= */
$imageTableReady = false;
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alat_berat_image (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            alat_berat_id INT UNSIGNED NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_abi_alat_berat (alat_berat_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $imageTableReady = true;
} catch (Throwable $e) {
    $imageTableReady = false;
}

/* =========================================================
   POST / CRUD
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $action = (string)($_POST['action'] ?? '');

        /* UPDATE UNIT */
        if ($action === 'update_unit') {
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

            $stmt = $pdo->prepare("
                UPDATE alat_berat
                SET kode=?, tipe=?, nomor_rangka=?, tahun_pembuatan=?,
                    kondisi=?, status=?, lokasi=?
                WHERE id=? LIMIT 1
            ");
            $stmt->execute([
                $kode, $tipe, $nomorRangka,
                $tahun === '' ? null : $tahun,
                $kondisi, $status, $lokasi, $id
            ]);

            $message = 'Data alat berat berhasil diperbarui.';
        }

        /* ADD SPAREPART + KURANGI STOK */
        elseif ($action === 'add_sparepart') {
            $sparepartId = filter_input(INPUT_POST, 'sparepart_id', FILTER_VALIDATE_INT);
            $qty = money($_POST['qty'] ?? 0);

            if (!$sparepartId || $qty <= 0) {
                throw new RuntimeException('Sparepart dan qty wajib diisi dengan benar.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, nama, harga_beli, stok
                FROM sparepart
                WHERE id=? FOR UPDATE
            ");
            $stmt->execute([$sparepartId]);
            $sp = $stmt->fetch();

            if (!$sp) {
                throw new RuntimeException('Sparepart tidak ditemukan.');
            }

            $stok = (float)$sp['stok'];
            if ($qty > $stok) {
                throw new RuntimeException(
                    'Stok '.$sp['nama'].' tidak mencukupi. Stok tersedia: '.rupiah($stok)
                );
            }

            /* Harga selalu mengambil harga_beli terbaru dari master sparepart. */
            $harga = (float)$sp['harga_beli'];
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            $stmt = $pdo->prepare("
                INSERT INTO alat_berat_sparepart
                    (alat_berat_id, sparepart_id, qty, harga, keterangan)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id, $sparepartId, $qty, $harga, $keterangan]);

            $stmt = $pdo->prepare("
                UPDATE sparepart
                SET stok = stok - ?
                WHERE id=? LIMIT 1
            ");
            $stmt->execute([$qty, $sparepartId]);

            $pdo->commit();
            $message = 'Sparepart berhasil ditambahkan dan stok telah dikurangi.';
        }

        /* EDIT SPAREPART + KOREKSI STOK */
        elseif ($action === 'edit_sparepart') {
            $relId = filter_input(INPUT_POST, 'rel_id', FILTER_VALIDATE_INT);
            $newSparepartId = filter_input(INPUT_POST, 'sparepart_id', FILTER_VALIDATE_INT);
            $newQty = money($_POST['qty'] ?? 0);
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if (!$relId || !$newSparepartId || $newQty <= 0) {
                throw new RuntimeException('Data sparepart tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT *
                FROM alat_berat_sparepart
                WHERE id=? AND alat_berat_id=?
                FOR UPDATE
            ");
            $stmt->execute([$relId, $id]);
            $old = $stmt->fetch();

            if (!$old) throw new RuntimeException('Detail sparepart tidak ditemukan.');

            $oldSparepartId = (int)$old['sparepart_id'];
            $oldQty = (float)$old['qty'];

            /* Kunci master lama dan master baru. */
            $ids = array_values(array_unique([$oldSparepartId, (int)$newSparepartId]));
            sort($ids);

            foreach ($ids as $sid) {
                $lock = $pdo->prepare("
                    SELECT id, nama, harga_beli, stok
                    FROM sparepart
                    WHERE id=? FOR UPDATE
                ");
                $lock->execute([$sid]);
                if (!$lock->fetch()) {
                    throw new RuntimeException('Master sparepart tidak ditemukan.');
                }
            }

            if ($oldSparepartId === (int)$newSparepartId) {
                $lock = $pdo->prepare("
                    SELECT id, nama, harga_beli, stok
                    FROM sparepart
                    WHERE id=? FOR UPDATE
                ");
                $lock->execute([$newSparepartId]);
                $newSp = $lock->fetch();

                $selisih = $newQty - $oldQty;
                if ($selisih > 0 && $selisih > (float)$newSp['stok']) {
                    throw new RuntimeException(
                        'Stok '.$newSp['nama'].' tidak mencukupi untuk penambahan qty.'
                    );
                }

                $harga = (float)$newSp['harga_beli'];

                $stmt = $pdo->prepare("
                    UPDATE alat_berat_sparepart
                    SET qty=?, harga=?, keterangan=?
                    WHERE id=? AND alat_berat_id=? LIMIT 1
                ");
                $stmt->execute([$newQty, $harga, $keterangan, $relId, $id]);

                $stmt = $pdo->prepare("
                    UPDATE sparepart
                    SET stok = stok - ?
                    WHERE id=? LIMIT 1
                ");
                $stmt->execute([$selisih, $newSparepartId]);
            } else {
                /* Kembalikan stok sparepart lama. */
                $stmt = $pdo->prepare("
                    UPDATE sparepart SET stok=stok+?
                    WHERE id=? LIMIT 1
                ");
                $stmt->execute([$oldQty, $oldSparepartId]);

                /* Ambil master baru setelah stok lama dikembalikan. */
                $stmt = $pdo->prepare("
                    SELECT id, nama, harga_beli, stok
                    FROM sparepart
                    WHERE id=? FOR UPDATE
                ");
                $stmt->execute([$newSparepartId]);
                $newSp = $stmt->fetch();

                if ($newQty > (float)$newSp['stok']) {
                    throw new RuntimeException(
                        'Stok '.$newSp['nama'].' tidak mencukupi. Stok tersedia: '.rupiah($newSp['stok'])
                    );
                }

                $harga = (float)$newSp['harga_beli'];

                $stmt = $pdo->prepare("
                    UPDATE alat_berat_sparepart
                    SET sparepart_id=?, qty=?, harga=?, keterangan=?
                    WHERE id=? AND alat_berat_id=? LIMIT 1
                ");
                $stmt->execute([
                    $newSparepartId, $newQty, $harga, $keterangan, $relId, $id
                ]);

                $stmt = $pdo->prepare("
                    UPDATE sparepart SET stok=stok-?
                    WHERE id=? LIMIT 1
                ");
                $stmt->execute([$newQty, $newSparepartId]);
            }

            $pdo->commit();
            $message = 'Sparepart berhasil diperbarui dan stok telah disesuaikan.';
        }

        /* DELETE SPAREPART + KEMBALIKAN STOK */
        elseif ($action === 'delete_sparepart') {
            $relId = filter_input(INPUT_POST, 'rel_id', FILTER_VALIDATE_INT);
            if (!$relId) throw new RuntimeException('Detail sparepart tidak valid.');

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT sparepart_id, qty
                FROM alat_berat_sparepart
                WHERE id=? AND alat_berat_id=?
                FOR UPDATE
            ");
            $stmt->execute([$relId, $id]);
            $old = $stmt->fetch();

            if ($old) {
                $stmt = $pdo->prepare("
                    UPDATE sparepart SET stok=stok+?
                    WHERE id=? LIMIT 1
                ");
                $stmt->execute([(float)$old['qty'],(int)$old['sparepart_id']]);

                $stmt = $pdo->prepare("
                    DELETE FROM alat_berat_sparepart
                    WHERE id=? AND alat_berat_id=? LIMIT 1
                ");
                $stmt->execute([$relId, $id]);
            }

            $pdo->commit();
            $message = 'Sparepart dihapus dan stok telah dikembalikan.';
        }

        /* ADD JASA */
        elseif ($action === 'add_jasa') {
            $jenis = trim((string)($_POST['jenis_jasa'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));
            $qty = money($_POST['qty'] ?? 1);
            $biaya = money($_POST['biaya'] ?? 0);
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));

            if ($jenis === '') throw new RuntimeException('Jenis jasa wajib diisi.');
            if ($qty <= 0) $qty = 1;

            $stmt = $pdo->prepare("
                INSERT INTO alat_berat_jasa
                    (alat_berat_id, jenis_jasa, keterangan, qty, biaya, tanggal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id, $jenis, $keterangan, $qty, $biaya,
                $tanggal === '' ? null : $tanggal
            ]);

            $message = 'Jasa pembentuk unit berhasil ditambahkan.';
        }

        /* EDIT JASA */
        elseif ($action === 'edit_jasa') {
            $jasaId = filter_input(INPUT_POST, 'jasa_id', FILTER_VALIDATE_INT);
            $jenis = trim((string)($_POST['jenis_jasa'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));
            $qty = money($_POST['qty'] ?? 1);
            $biaya = money($_POST['biaya'] ?? 0);
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));

            if (!$jasaId || $jenis === '') throw new RuntimeException('Data jasa tidak valid.');
            if ($qty <= 0) $qty = 1;

            $stmt = $pdo->prepare("
                UPDATE alat_berat_jasa
                SET jenis_jasa=?, keterangan=?, qty=?, biaya=?, tanggal=?
                WHERE id=? AND alat_berat_id=? LIMIT 1
            ");
            $stmt->execute([
                $jenis, $keterangan, $qty, $biaya,
                $tanggal === '' ? null : $tanggal,
                $jasaId, $id
            ]);

            $message = 'Jasa berhasil diperbarui.';
        }

        /* DELETE JASA */
        elseif ($action === 'delete_jasa') {
            $jasaId = filter_input(INPUT_POST, 'jasa_id', FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare("
                DELETE FROM alat_berat_jasa
                WHERE id=? AND alat_berat_id=? LIMIT 1
            ");
            $stmt->execute([$jasaId, $id]);
            $message = 'Jasa berhasil dihapus.';
        }

        /* ADD PERAWATAN */
        elseif ($action === 'add_perawatan') {
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));
            $finishing = money($_POST['finishing'] ?? 0);
            $sukuCadang = money($_POST['suku_cadang'] ?? 0);
            $jasa = money($_POST['jasa'] ?? 0);

            if ($tanggal === '') throw new RuntimeException('Tanggal perawatan wajib diisi.');

            $stmt = $pdo->prepare("
                INSERT INTO alat_berat_perawatan
                    (alat_berat_id, tanggal, keterangan, finishing, suku_cadang, jasa)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id, $tanggal, $keterangan, $finishing, $sukuCadang, $jasa
            ]);

            $message = 'Riwayat perawatan berhasil ditambahkan.';
        }

        /* EDIT PERAWATAN */
        elseif ($action === 'edit_perawatan') {
            $perawatanId = filter_input(INPUT_POST, 'perawatan_id', FILTER_VALIDATE_INT);
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));
            $finishing = money($_POST['finishing'] ?? 0);
            $sukuCadang = money($_POST['suku_cadang'] ?? 0);
            $jasa = money($_POST['jasa'] ?? 0);

            if (!$perawatanId || $tanggal === '') {
                throw new RuntimeException('Data perawatan tidak valid.');
            }

            $stmt = $pdo->prepare("
                UPDATE alat_berat_perawatan
                SET tanggal=?, keterangan=?, finishing=?, suku_cadang=?, jasa=?
                WHERE id=? AND alat_berat_id=? LIMIT 1
            ");
            $stmt->execute([
                $tanggal, $keterangan, $finishing, $sukuCadang, $jasa,
                $perawatanId, $id
            ]);

            $message = 'Riwayat perawatan berhasil diperbarui.';
        }

        /* DELETE PERAWATAN */
        elseif ($action === 'delete_perawatan') {
            $perawatanId = filter_input(INPUT_POST, 'perawatan_id', FILTER_VALIDATE_INT);

            $stmt = $pdo->prepare("
                DELETE FROM alat_berat_perawatan
                WHERE id=? AND alat_berat_id=? LIMIT 1
            ");
            $stmt->execute([$perawatanId, $id]);

            $message = 'Riwayat perawatan berhasil dihapus.';
        }

        /* UPLOAD IMAGE */
        elseif ($action === 'upload_image') {
            if (!$imageTableReady) {
                throw new RuntimeException('Tabel image belum tersedia.');
            }

            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Pilih file image terlebih dahulu.');
            }

            $file = $_FILES['image'];

            if ((int)$file['size'] > 3 * 1024 * 1024) {
                throw new RuntimeException('Ukuran image maksimal 3 MB.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];

            if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
                throw new RuntimeException('Image harus berformat JPG/JPEG atau PNG.');
            }

            $dir = __DIR__ . '/../assets/uploads/alat_berat';
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new RuntimeException('Folder upload tidak dapat dibuat.');
            }

            $filename = 'alat_'.$id.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
            $target = $dir.'/'.$filename;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new RuntimeException('Gagal menyimpan image.');
            }

            $relative = 'assets/uploads/alat_berat/'.$filename;

            $stmt = $pdo->prepare("
                INSERT INTO alat_berat_image (alat_berat_id, file_name, file_path)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$id, $file['name'], $relative]);

            $message = 'Image berhasil diupload.';
        }

        /* DELETE IMAGE */
        elseif ($action === 'delete_image') {
            if (!$imageTableReady) throw new RuntimeException('Fitur image belum tersedia.');

            $imageId = filter_input(INPUT_POST, 'image_id', FILTER_VALIDATE_INT);

            $stmt = $pdo->prepare("
                SELECT file_path FROM alat_berat_image
                WHERE id=? AND alat_berat_id=? LIMIT 1
            ");
            $stmt->execute([$imageId, $id]);
            $img = $stmt->fetch();

            if ($img) {
                $physical = dirname(__DIR__).'/'.ltrim((string)$img['file_path'], '/');
                if (is_file($physical)) @unlink($physical);

                $stmt = $pdo->prepare("
                    DELETE FROM alat_berat_image
                    WHERE id=? AND alat_berat_id=? LIMIT 1
                ");
                $stmt->execute([$imageId, $id]);
            }

            $message = 'Image berhasil dihapus.';
        }

        else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();

        $modalMap = [
            'add_sparepart' => 'modalSparepart',
            'edit_sparepart' => 'modalSparepartEdit',
            'add_jasa' => 'modalJasa',
            'edit_jasa' => 'modalJasaEdit',
            'add_perawatan' => 'modalPerawatan',
            'edit_perawatan' => 'modalPerawatanEdit',
            'update_unit' => 'modalEditUnit'
        ];
        $openModal = $modalMap[$_POST['action'] ?? ''] ?? '';
    }
}

/* =========================================================
   LOAD UNIT
   ========================================================= */
$stmt = $pdo->prepare("SELECT * FROM alat_berat WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$unit = $stmt->fetch();

if (!$unit) {
    header('Location: alat_berat.php');
    exit;
}

/* =========================================================
   LOAD MASTER SPAREPART
   ========================================================= */
$masterSparepart = [];
try {
    $masterSparepart = $pdo->query("
        SELECT id, kode, nama, part_number, merk, satuan, harga_beli, stok
        FROM sparepart
        ORDER BY nama ASC
    ")->fetchAll();
} catch (Throwable $e) {}

/* =========================================================
   LOAD SPAREPART UNIT
   Total dihitung dari qty * harga, tidak bergantung kolom total.
   ========================================================= */
$spareparts = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            abs.id,
            abs.alat_berat_id,
            abs.sparepart_id,
            abs.qty,
            abs.harga,
            abs.keterangan,
            s.kode,
            s.nama,
            s.part_number,
            s.satuan,
            (abs.qty * abs.harga) AS total
        FROM alat_berat_sparepart abs
        INNER JOIN sparepart s ON s.id=abs.sparepart_id
        WHERE abs.alat_berat_id=?
        ORDER BY abs.id ASC
    ");
    $stmt->execute([$id]);
    $spareparts = $stmt->fetchAll();
} catch (Throwable $e) {}

$totalSparepart = 0;
foreach ($spareparts as $sp) {
    $totalSparepart += (float)$sp['total'];
}

/* =========================================================
   LOAD JASA
   ========================================================= */
$jasas = [];
try {
    $stmt = $pdo->prepare("
        SELECT *,
               (qty * biaya) AS total
        FROM alat_berat_jasa
        WHERE alat_berat_id=?
        ORDER BY id ASC
    ");
    $stmt->execute([$id]);
    $jasas = $stmt->fetchAll();
} catch (Throwable $e) {}

$totalJasa = 0;
foreach ($jasas as $j) {
    $totalJasa += (float)$j['total'];
}

/* =========================================================
   LOAD PERAWATAN
   ========================================================= */
$perawatan = [];
try {
    $stmt = $pdo->prepare("
        SELECT *,
               (finishing + suku_cadang + jasa) AS total
        FROM alat_berat_perawatan
        WHERE alat_berat_id=?
        ORDER BY tanggal DESC, id DESC
    ");
    $stmt->execute([$id]);
    $perawatan = $stmt->fetchAll();
} catch (Throwable $e) {}

$totalPerawatan = 0;
foreach ($perawatan as $p) {
    $totalPerawatan += (float)$p['total'];
}

/* =========================================================
   HPP = SELURUH KOMPONEN BIAYA
   ========================================================= */
$totalHPP = $totalSparepart + $totalJasa + $totalPerawatan;

/* Placeholder data yang sengaja belum diambil dari pembelian/sewa/operasional. */
$hargaBeliUnit = 0;
$hargaJualUnit = 0;
$kursUnit = 0;
$hargaUSDUnit = 0;
$sewaAllIn = 0;
$sewaKosongan = 0;
$jamTerakhir = 0;
$totalJam = 0;

/* =========================================================
   LOAD IMAGE
   ========================================================= */
$images = [];
if ($imageTableReady) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM alat_berat_image
            WHERE alat_berat_id=?
            ORDER BY id DESC
        ");
        $stmt->execute([$id]);
        $images = $stmt->fetchAll();
    } catch (Throwable $e) {}
}

require __DIR__ . '/../includes/header.php';
?>

<style>
.detail-page{padding-bottom:30px}
.detail-breadcrumb{display:flex;gap:8px;margin-bottom:4px;font-size:12px;color:#0d6efd;font-weight:600;text-transform:uppercase}
.detail-breadcrumb .separator{color:#8a98aa}
.detail-title-row{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;margin-bottom:22px}
.detail-title-row h1{margin:0 0 4px;font-size:21px;color:#17243d}
.detail-title-row p{margin:0;color:#8290a3;font-size:12px}
.detail-card{background:#fff;border:1px solid #dfe5ed;border-radius:4px;margin-bottom:18px;overflow:hidden}
.detail-card-header{min-height:52px;padding:0 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #dfe5ed;color:#13294b;font-size:13px;font-weight:600}
.detail-card-body{padding:18px}
.detail-btn{height:30px;padding:0 12px;border:1px solid #0d6efd;border-radius:4px;background:#fff;color:#0d6efd;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:11px;cursor:pointer}
.detail-btn-back{border-color:#7185a5;color:#637a9d}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;column-gap:80px;row-gap:12px}
.detail-field{display:grid;grid-template-columns:135px 1fr;align-items:center;font-size:12px}
.detail-field .label{color:#13294b}.detail-field .value{color:#13294b;font-weight:500}.rangka{color:#ff2f68!important}
.unit-badge{display:inline-block;padding:3px 7px;border-radius:3px;font-size:10px;font-weight:600}
.unit-badge.success{background:#08b965;color:#fff}.unit-badge.warning{background:#ffb400;color:#17243d}.unit-badge.info{background:#08b6d0;color:#fff}.unit-badge.danger{background:#dc3545;color:#fff}.unit-badge.default{background:#718096;color:#fff}
.detail-table-wrap{overflow-x:auto}
.detail-table{width:100%;border-collapse:collapse;min-width:700px}
.detail-table th{padding:9px 10px;background:#f7f9fc;border-bottom:1px solid #dfe5ed;color:#152d50;text-align:left;font-size:11px;white-space:nowrap}
.detail-table td{padding:9px 10px;border-bottom:1px solid #e2e7ee;color:#183052;font-size:11px;white-space:nowrap}
.detail-table .number{text-align:right}.detail-table .center{text-align:center}
.detail-empty{text-align:center!important;padding:22px!important;color:#8390a3!important;font-style:italic}
.detail-total{display:flex;justify-content:flex-end;gap:35px;padding:10px 14px;background:#fafbfd;border-top:1px solid #dfe5ed;color:#13294b;font-size:11px}
.detail-total strong{font-size:12px}
.section-action{height:29px;padding:0 11px;border:1px solid #0d6efd;border-radius:4px;background:#fff;color:#0d6efd;font-size:11px;cursor:pointer}
.row-actions{display:flex;justify-content:center;gap:5px}.row-actions form{margin:0}
.row-btn{height:27px;padding:0 9px;border:1px solid #8aa0bd;background:#fff;color:#526b8d;border-radius:4px;font-size:10px;cursor:pointer}
.row-btn.danger{border-color:#dc3545;color:#dc3545}
.alert{padding:10px 13px;margin:0 0 18px;border-radius:4px;font-size:12px}
.alert.success{background:#effcf5;border:1px solid #bcebd2;color:#087443}
.alert.error{background:#fff0f2;border:1px solid #ffc8d0;color:#9b1c31}
.price-grid{display:grid;grid-template-columns:repeat(3,1fr);text-align:center;gap:0}
.price-item{padding:8px 15px;border-right:1px solid #dfe5ed}.price-item:last-child{border-right:0}
.price-item .small{display:block;color:#8190a5;font-size:10px;margin-bottom:5px}.price-item .big{font-size:16px;color:#13294b;font-weight:600}
.price-hpp{margin-top:14px;padding-top:14px;border-top:1px solid #dfe5ed;text-align:center}
.price-hpp .small{color:#8190a5;font-size:10px}.price-hpp .big{display:block;color:#00a957;font-size:20px;font-weight:700;margin-top:3px}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.image-grid{display:grid;grid-template-columns:260px 1fr;gap:20px}
.upload-help{font-size:11px;color:#536984;margin-bottom:7px}
.upload-line{display:flex;height:34px}.upload-line input{min-width:0;flex:1;border:1px solid #bdcbe0;border-radius:4px 0 0 4px;font-size:10px;padding:5px}.upload-line button{width:66px;border:0;border-radius:0 4px 4px 0;background:#0d6efd;color:#fff;font-size:10px;cursor:pointer}
.image-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
.image-item{border:1px solid #dfe5ed;border-radius:4px;overflow:hidden;background:#fff}
.image-item img{width:100%;height:105px;object-fit:cover;display:block;background:#f4f6f9}
.image-item-footer{padding:7px}.image-name{font-size:9px;color:#52657f;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px}
.image-item-footer form{margin:0}.image-delete{width:100%;height:25px;border:1px solid #dc3545;background:#fff;color:#dc3545;border-radius:3px;font-size:9px;cursor:pointer}
.modal{position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.56);display:none;align-items:center;justify-content:center;padding:12px}.modal.show{display:flex}
.modal-dialog{width:min(680px,100%);max-height:94vh;overflow:auto;background:#fff;border-radius:5px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.modal-header{height:46px;padding:0 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #dfe5ed}.modal-header strong{font-size:13px;color:#17243d}.modal-close{border:0;background:transparent;color:#7d8da4;font-size:22px;cursor:pointer}
.modal-body{padding:15px}.modal-footer{display:flex;justify-content:flex-end;gap:7px;padding:11px 14px;border-top:1px solid #dfe5ed}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.form-grid.three{grid-template-columns:repeat(3,1fr)}
.form-field.full{grid-column:1/-1}.form-field label{display:block;margin-bottom:5px;font-size:10px;color:#183052}.form-field input,.form-field select,.form-field textarea{width:100%;box-sizing:border-box;height:34px;border:1px solid #bdcbe0;border-radius:4px;padding:7px 9px;color:#172d4e;font-size:11px;background:#fff}.form-field textarea{height:65px;resize:vertical}
.btn-primary,.btn-secondary{height:34px;padding:0 14px;border:0;border-radius:4px;font-size:10px;cursor:pointer}.btn-primary{background:#0d6efd;color:#fff}.btn-secondary{background:#718096;color:#fff}
@media(max-width:800px){.detail-grid,.two-col,.image-grid,.form-grid,.form-grid.three{grid-template-columns:1fr}.detail-title-row{flex-direction:column}.detail-field{grid-template-columns:125px 1fr}.price-grid{grid-template-columns:1fr}.price-item{border-right:0;border-bottom:1px solid #dfe5ed}.price-item:last-child{border-bottom:0}}
</style>

<div class="detail-page">
    <div class="detail-breadcrumb"><span>Alat Berat</span><span class="separator">/</span><span>Detail</span></div>

    <div class="detail-title-row">
        <div><h1>Detail Alat Berat</h1><p>Informasi lengkap tentang unit alat berat</p></div>
        <a href="alat_berat.php" class="detail-btn detail-btn-back">← Back</a>
    </div>

    <?php if ($message): ?><div class="alert success"><?php echo h($message); ?></div><?php endif; ?>
    <?php if ($error && !$openModal): ?><div class="alert error"><?php echo h($error); ?></div><?php endif; ?>

    <!-- DATA UMUM -->
    <section class="detail-card">
        <div class="detail-card-header"><span>Data Umum</span><button type="button" class="detail-btn" data-open="modalEditUnit">Edit</button></div>
        <div class="detail-card-body">
            <div class="detail-grid">
                <div class="detail-field"><span class="label">Kode</span><span class="value"><?php echo h($unit['kode']); ?></span></div>
                <div class="detail-field"><span class="label">Tahun Pembuatan</span><span class="value"><?php echo h($unit['tahun_pembuatan']); ?></span></div>
                <div class="detail-field"><span class="label">Tipe</span><span class="value"><?php echo h($unit['tipe']); ?></span></div>
                <div class="detail-field"><span class="label">Kondisi Unit</span><span class="value"><span class="unit-badge default"><?php echo h($unit['kondisi']); ?></span></span></div>
                <div class="detail-field"><span class="label">Nomor Rangka</span><span class="value rangka"><?php echo h($unit['nomor_rangka']); ?></span></div>
                <div class="detail-field"><span class="label">Status Unit</span><span class="value"><span class="unit-badge <?php echo badgeClass($unit['status']); ?>"><?php echo h($unit['status']); ?></span></span></div>
                <div class="detail-field"></div>
                <div class="detail-field"><span class="label">Lokasi</span><span class="value"><?php echo h($unit['lokasi']); ?></span></div>
            </div>
        </div>
    </section>

    <!-- INFORMASI HARGA -->
    <section class="detail-card">
        <div class="detail-card-header"><span>Informasi Harga</span></div>
        <div class="detail-card-body">
            <div class="price-grid">
                <div class="price-item"><span class="small">Harga Beli</span><span class="big">Rp <?php echo rupiah($hargaBeliUnit); ?></span></div>
                <div class="price-item"><span class="small">Kurs (IDR/USD)</span><span class="big"><?php echo rupiah($kursUnit); ?></span></div>
                <div class="price-item"><span class="small">USD</span><span class="big">$ <?php echo number_format($hargaUSDUnit, 3, '.', ','); ?></span></div>
            </div>
            <div class="price-hpp">
                <span class="small">Total HPP Unit (Sparepart + Jasa + Perawatan)</span>
                <span class="big">Rp <?php echo rupiah($totalHPP); ?></span>
            </div>
            <div style="text-align:center;margin-top:9px;font-size:10px;color:#8190a5;">Harga Jual: <strong style="color:#00a957;">Rp <?php echo rupiah($hargaJualUnit); ?></strong></div>
        </div>
    </section>

    <!-- RIWAYAT PEMBELIAN - DITAMPILKAN, ISI DITUNDA -->
    <section class="detail-card">
        <div class="detail-card-header"><span>Riwayat Pembelian</span></div>
        <div class="detail-table-wrap">
            <table class="detail-table">
                <thead><tr><th>No</th><th>Tanggal</th><th>No Pembelian</th><th>Kurs</th><th>Hrg IDR</th><th>Hrg USD</th><th>HPP IDR</th></tr></thead>
                <tbody><tr><td colspan="7" class="detail-empty">Belum ada riwayat pembelian. Modul pembelian akan ditambahkan berikutnya.</td></tr></tbody>
            </table>
        </div>
    </section>

    <!-- HARGA SEWA & OPERASIONAL -->
    <div class="two-col">
        <section class="detail-card">
            <div class="detail-card-header"><span>Harga Sewa</span></div>
            <div class="detail-card-body">
                <div class="price-grid">
                    <div class="price-item"><span class="small">Per Jam - All In</span><span class="big">Rp <?php echo rupiah($sewaAllIn); ?></span><span class="small">Termasuk operator & BBM</span></div>
                    <div class="price-item"><span class="small">Per Jam - Kosongan</span><span class="big">Rp <?php echo rupiah($sewaKosongan); ?></span><span class="small">Unit saja (tanpa operator)</span></div>
                </div>
            </div>
        </section>
        <section class="detail-card">
            <div class="detail-card-header"><span>Jam Operasional</span></div>
            <div class="detail-card-body">
                <div class="price-grid">
                    <div class="price-item"><span class="small">Jam Operasional Terakhir</span><span class="big"><?php echo rupiah($jamTerakhir); ?> Jam</span></div>
                    <div class="price-item"><span class="small">Total Jam Operasional</span><span class="big"><?php echo rupiah($totalJam); ?> Jam</span></div>
                </div>
            </div>
        </section>
    </div>

    <!-- SPAREPART -->
    <section class="detail-card">
        <div class="detail-card-header"><span>Sparepart Pembentuk Unit</span><button type="button" class="section-action" data-open="modalSparepart">+ Tambah Sparepart</button></div>
        <div class="detail-table-wrap">
            <table class="detail-table">
                <thead><tr><th>No</th><th>Kode</th><th>Nama Sparepart</th><th>Qty</th><th class="number">Harga</th><th class="number">Total</th><th class="center">Aksi</th></tr></thead>
                <tbody>
                <?php if (!$spareparts): ?>
                    <tr><td colspan="7" class="detail-empty">Belum ada sparepart pembentuk unit.</td></tr>
                <?php else: foreach ($spareparts as $n=>$sp): ?>
                    <tr>
                        <td><?php echo $n+1; ?></td><td><?php echo h($sp['kode']); ?></td><td><?php echo h($sp['nama']); ?></td>
                        <td><?php echo rupiah($sp['qty']); ?> <?php echo h($sp['satuan']); ?></td>
                        <td class="number"><?php echo rupiah($sp['harga']); ?></td><td class="number"><?php echo rupiah($sp['total']); ?></td>
                        <td><div class="row-actions">
                            <button type="button" class="row-btn" data-open="modalSparepartEdit"
                                data-id="<?php echo (int)$sp['id']; ?>" data-sparepart="<?php echo (int)$sp['sparepart_id']; ?>"
                                data-qty="<?php echo h($sp['qty']); ?>" data-keterangan="<?php echo h($sp['keterangan']); ?>">Edit</button>
                            <form method="post" onsubmit="return confirm('Hapus sparepart dari unit ini? Stok akan dikembalikan.');">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="delete_sparepart"><input type="hidden" name="rel_id" value="<?php echo (int)$sp['id']; ?>">
                                <button class="row-btn danger" type="submit">Hapus</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            <div class="detail-total"><span>Total Sparepart</span><strong>Rp <?php echo rupiah($totalSparepart); ?></strong></div>
        </div>
    </section>

    <!-- JASA -->
    <section class="detail-card">
        <div class="detail-card-header"><span>Jasa Pembentuk Unit</span><button type="button" class="section-action" data-open="modalJasa">+ Tambah Jasa</button></div>
        <div class="detail-table-wrap">
            <table class="detail-table">
                <thead><tr><th>No</th><th>Jasa</th><th>Keterangan</th><th>Qty</th><th class="number">Biaya</th><th class="number">Total</th><th class="center">Aksi</th></tr></thead>
                <tbody>
                <?php if (!$jasas): ?>
                    <tr><td colspan="7" class="detail-empty">Belum ada data jasa.</td></tr>
                <?php else: foreach ($jasas as $n=>$j): ?>
                    <tr>
                        <td><?php echo $n+1; ?></td><td><?php echo h($j['jenis_jasa']); ?></td><td><?php echo h($j['keterangan']); ?></td>
                        <td><?php echo rupiah($j['qty']); ?></td><td class="number"><?php echo rupiah($j['biaya']); ?></td><td class="number"><?php echo rupiah($j['total']); ?></td>
                        <td><div class="row-actions">
                            <button type="button" class="row-btn" data-open="modalJasaEdit" data-id="<?php echo (int)$j['id']; ?>" data-jenis="<?php echo h($j['jenis_jasa']); ?>" data-keterangan="<?php echo h($j['keterangan']); ?>" data-qty="<?php echo h($j['qty']); ?>" data-biaya="<?php echo h($j['biaya']); ?>" data-tanggal="<?php echo h($j['tanggal']); ?>">Edit</button>
                            <form method="post" onsubmit="return confirm('Hapus jasa ini?');">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="delete_jasa"><input type="hidden" name="jasa_id" value="<?php echo (int)$j['id']; ?>">
                                <button class="row-btn danger">Hapus</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            <div class="detail-total"><span>Total Jasa</span><strong>Rp <?php echo rupiah($totalJasa); ?></strong></div>
        </div>
    </section>

    <!-- PERAWATAN -->
    <section class="detail-card">
        <div class="detail-card-header"><span>Riwayat Perawatan</span><button type="button" class="section-action" data-open="modalPerawatan">+ Perawatan Baru</button></div>
        <div class="detail-table-wrap">
            <table class="detail-table">
                <thead><tr><th>Tanggal</th><th class="number">Finishing</th><th class="number">Suku Cadang</th><th class="number">Jasa</th><th class="number">Total</th><th class="center">Aksi</th></tr></thead>
                <tbody>
                <?php if (!$perawatan): ?>
                    <tr><td colspan="6" class="detail-empty">Belum ada data perawatan!</td></tr>
                <?php else: foreach ($perawatan as $p): ?>
                    <tr>
                        <td><?php echo dateId($p['tanggal']); ?></td><td class="number"><?php echo rupiah($p['finishing']); ?></td><td class="number"><?php echo rupiah($p['suku_cadang']); ?></td><td class="number"><?php echo rupiah($p['jasa']); ?></td><td class="number"><?php echo rupiah($p['total']); ?></td>
                        <td><div class="row-actions">
                            <button type="button" class="row-btn" data-open="modalPerawatanEdit" data-id="<?php echo (int)$p['id']; ?>" data-tanggal="<?php echo h(dateForInput($p['tanggal'])); ?>" data-keterangan="<?php echo h($p['keterangan']); ?>" data-finishing="<?php echo h($p['finishing']); ?>" data-suku="<?php echo h($p['suku_cadang']); ?>" data-jasa="<?php echo h($p['jasa']); ?>">Edit</button>
                            <form method="post" onsubmit="return confirm('Hapus riwayat perawatan ini?');">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="delete_perawatan"><input type="hidden" name="perawatan_id" value="<?php echo (int)$p['id']; ?>">
                                <button class="row-btn danger">Hapus</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            <div class="detail-total"><span>Total Perawatan</span><strong>Rp <?php echo rupiah($totalPerawatan); ?></strong></div>
        </div>
    </section>

    <!-- TOTAL HPP -->
    <section class="detail-card">
        <div class="detail-card-header"><span>Ringkasan HPP Unit</span></div>
        <div class="detail-card-body">
            <div class="detail-grid">
                <div class="detail-field"><span class="label">Sparepart</span><span class="value">Rp <?php echo rupiah($totalSparepart); ?></span></div>
                <div class="detail-field"><span class="label">Jasa Pembentuk</span><span class="value">Rp <?php echo rupiah($totalJasa); ?></span></div>
                <div class="detail-field"><span class="label">Perawatan</span><span class="value">Rp <?php echo rupiah($totalPerawatan); ?></span></div>
                <div class="detail-field"><span class="label">Total HPP</span><span class="value" style="color:#00a957;font-size:16px;">Rp <?php echo rupiah($totalHPP); ?></span></div>
            </div>
        </div>
    </section>

    <!-- IMAGE -->
    <section class="detail-card">
        <div class="detail-card-header"><span>Upload Image</span></div>
        <div class="detail-card-body">
            <div class="image-grid">
                <div>
                    <strong style="display:block;text-align:center;font-size:11px;color:#13294b;margin-bottom:16px;">Upload Image</strong>
                    <div class="upload-help">Image: JPG/PNG, Max: 3mb</div>
                    <?php if ($imageTableReady): ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                        <input type="hidden" name="action" value="upload_image">
                        <div class="upload-line"><input type="file" name="image" accept=".jpg,.jpeg,.png" required><button>Upload</button></div>
                    </form>
                    <?php else: ?><div class="alert error" style="margin:0">Tabel image belum tersedia.</div><?php endif; ?>
                </div>
                <div>
                    <strong style="display:block;text-align:center;font-size:11px;color:#13294b;margin-bottom:16px;">Daftar Image</strong>
                    <?php if (!$images): ?>
                        <div class="detail-empty" style="border:1px solid #dfe5ed">No data available in table</div>
                    <?php else: ?>
                        <div class="image-list">
                            <?php foreach ($images as $img): ?>
                                <div class="image-item">
                                    <a href="../<?php echo h($img['file_path']); ?>" target="_blank"><img src="../<?php echo h($img['file_path']); ?>" alt="<?php echo h($img['file_name']); ?>"></a>
                                    <div class="image-item-footer">
                                        <div class="image-name" title="<?php echo h($img['file_name']); ?>"><?php echo h($img['file_name']); ?></div>
                                        <form method="post" onsubmit="return confirm('Hapus image ini?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="delete_image"><input type="hidden" name="image_id" value="<?php echo (int)$img['id']; ?>">
                                            <button class="image-delete">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODALS -->
<div class="modal" id="modalEditUnit">
<div class="modal-dialog">
<div class="modal-header"><strong>Edit Alat Berat</strong><button class="modal-close" data-close>×</button></div>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="update_unit">
<div class="modal-body">
<?php if ($openModal==='modalEditUnit' && $error): ?><div class="alert error"><?php echo h($error); ?></div><?php endif; ?>
<div class="form-grid three">
<div class="form-field"><label>Kode *</label><input name="kode" required value="<?php echo h($unit['kode']); ?>"></div>
<div class="form-field"><label>Tipe *</label><input name="tipe" required value="<?php echo h($unit['tipe']); ?>"></div>
<div class="form-field"><label>Nomor Rangka *</label><input name="nomor_rangka" required value="<?php echo h($unit['nomor_rangka']); ?>"></div>
<div class="form-field"><label>Tahun Pembuatan</label><input name="tahun_pembuatan" value="<?php echo h($unit['tahun_pembuatan']); ?>"></div>
<div class="form-field"><label>Kondisi</label><select name="kondisi"><?php foreach(['Baru','Bekas'] as $x): ?><option <?php echo strcasecmp((string)$unit['kondisi'],$x)===0?'selected':''; ?>><?php echo h($x); ?></option><?php endforeach; ?></select></div>
<div class="form-field"><label>Status</label><select name="status"><?php foreach(['Tersedia','Siap Jual','Perbaikan','Disewakan','Terjual'] as $x): ?><option value="<?php echo h($x); ?>" <?php echo strcasecmp((string)$unit['status'],$x)===0?'selected':''; ?>><?php echo h($x); ?></option><?php endforeach; ?></select></div>
<div class="form-field full"><label>Lokasi</label><input name="lokasi" value="<?php echo h($unit['lokasi']); ?>"></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn-secondary" data-close>Batal</button><button class="btn-primary">Update Data</button></div>
</form></div></div>

<div class="modal" id="modalSparepart">
<div class="modal-dialog">
<div class="modal-header"><strong>Tambah Sparepart Pembentuk Unit</strong><button class="modal-close" data-close>×</button></div>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="add_sparepart">
<div class="modal-body">
<?php if ($openModal==='modalSparepart' && $error): ?><div class="alert error"><?php echo h($error); ?></div><?php endif; ?>
<div class="form-grid">
<div class="form-field full"><label>Sparepart *</label>
<select name="sparepart_id" id="add_sparepart_id" required>
<option value="">-- Pilih Sparepart --</option>
<?php foreach($masterSparepart as $sp): ?>
<option value="<?php echo (int)$sp['id']; ?>" data-harga="<?php echo h($sp['harga_beli']); ?>" data-stok="<?php echo h($sp['stok']); ?>"><?php echo h($sp['kode'].' - '.$sp['nama'].' | Stok: '.rupiah($sp['stok'])); ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-field"><label>Qty *</label><input name="qty" id="add_sp_qty" value="1" required></div>
<div class="form-field"><label>Harga Beli</label><input name="harga_display" id="add_sp_harga" value="0" readonly><small id="add_sp_stok" style="display:block;margin-top:4px;color:#8190a5;font-size:9px"></small></div>
<div class="form-field full"><label>Keterangan</label><textarea name="keterangan"></textarea></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn-secondary" data-close>Batal</button><button class="btn-primary">Simpan</button></div>
</form></div></div>

<div class="modal" id="modalSparepartEdit">
<div class="modal-dialog">
<div class="modal-header"><strong>Edit Sparepart Pembentuk Unit</strong><button class="modal-close" data-close>×</button></div>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="edit_sparepart"><input type="hidden" name="rel_id" id="sp_rel_id">
<div class="modal-body">
<?php if ($openModal==='modalSparepartEdit' && $error): ?><div class="alert error"><?php echo h($error); ?></div><?php endif; ?>
<div class="form-grid">
<div class="form-field full"><label>Sparepart *</label><select name="sparepart_id" id="edit_sparepart_id" required>
<?php foreach($masterSparepart as $sp): ?><option value="<?php echo (int)$sp['id']; ?>" data-harga="<?php echo h($sp['harga_beli']); ?>" data-stok="<?php echo h($sp['stok']); ?>"><?php echo h($sp['kode'].' - '.$sp['nama'].' | Stok: '.rupiah($sp['stok'])); ?></option><?php endforeach; ?>
</select></div>
<div class="form-field"><label>Qty *</label><input name="qty" id="edit_sp_qty" required></div>
<div class="form-field"><label>Harga Beli Saat Ini</label><input id="edit_sp_harga" readonly></div>
<div class="form-field full"><label>Keterangan</label><textarea name="keterangan" id="edit_sp_keterangan"></textarea></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn-secondary" data-close>Batal</button><button class="btn-primary">Update</button></div>
</form></div></div>

<div class="modal" id="modalJasa">
<div class="modal-dialog"><div class="modal-header"><strong>Tambah Jasa Pembentuk Unit</strong><button class="modal-close" data-close>×</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="add_jasa">
<div class="modal-body"><?php if($openModal==='modalJasa'&&$error): ?><div class="alert error"><?php echo h($error); ?></div><?php endif; ?>
<div class="form-grid"><div class="form-field"><label>Jenis Jasa *</label><input name="jenis_jasa" required></div><div class="form-field"><label>Tanggal</label><input type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>"></div><div class="form-field"><label>Qty</label><input name="qty" value="1"></div><div class="form-field"><label>Biaya</label><input name="biaya" value="0"></div><div class="form-field full"><label>Keterangan</label><textarea name="keterangan"></textarea></div></div></div>
<div class="modal-footer"><button type="button" class="btn-secondary" data-close>Batal</button><button class="btn-primary">Simpan</button></div></form></div></div>

<div class="modal" id="modalJasaEdit">
<div class="modal-dialog"><div class="modal-header"><strong>Edit Jasa Pembentuk Unit</strong><button class="modal-close" data-close>×</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="edit_jasa"><input type="hidden" name="jasa_id" id="jasa_id">
<div class="modal-body"><div class="form-grid"><div class="form-field"><label>Jenis Jasa *</label><input name="jenis_jasa" id="jasa_jenis" required></div><div class="form-field"><label>Tanggal</label><input type="date" name="tanggal" id="jasa_tanggal"></div><div class="form-field"><label>Qty</label><input name="qty" id="jasa_qty"></div><div class="form-field"><label>Biaya</label><input name="biaya" id="jasa_biaya"></div><div class="form-field full"><label>Keterangan</label><textarea name="keterangan" id="jasa_keterangan"></textarea></div></div></div>
<div class="modal-footer"><button type="button" class="btn-secondary" data-close>Batal</button><button class="btn-primary">Update</button></div></form></div></div>

<div class="modal" id="modalPerawatan">
<div class="modal-dialog"><div class="modal-header"><strong>Tambah Riwayat Perawatan</strong><button class="modal-close" data-close>×</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="add_perawatan">
<div class="modal-body"><?php if($openModal==='modalPerawatan'&&$error): ?><div class="alert error"><?php echo h($error); ?></div><?php endif; ?>
<div class="form-grid"><div class="form-field"><label>Tanggal *</label><input type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required></div><div class="form-field"><label>Finishing</label><input name="finishing" value="0"></div><div class="form-field"><label>Suku Cadang</label><input name="suku_cadang" value="0"></div><div class="form-field"><label>Jasa</label><input name="jasa" value="0"></div><div class="form-field full"><label>Keterangan</label><textarea name="keterangan"></textarea></div></div></div>
<div class="modal-footer"><button type="button" class="btn-secondary" data-close>Batal</button><button class="btn-primary">Simpan</button></div></form></div></div>

<div class="modal" id="modalPerawatanEdit">
<div class="modal-dialog"><div class="modal-header"><strong>Edit Riwayat Perawatan</strong><button class="modal-close" data-close>×</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>"><input type="hidden" name="action" value="edit_perawatan"><input type="hidden" name="perawatan_id" id="perawatan_id">
<div class="modal-body"><div class="form-grid"><div class="form-field"><label>Tanggal *</label><input type="date" name="tanggal" id="per_tanggal" required></div><div class="form-field"><label>Finishing</label><input name="finishing" id="per_finishing"></div><div class="form-field"><label>Suku Cadang</label><input name="suku_cadang" id="per_suku"></div><div class="form-field"><label>Jasa</label><input name="jasa" id="per_jasa"></div><div class="form-field full"><label>Keterangan</label><textarea name="keterangan" id="per_keterangan"></textarea></div></div></div>
<div class="modal-footer"><button type="button" class="btn-secondary" data-close>Batal</button><button class="btn-primary">Update</button></div></form></div></div>

<script>
(function(){
    const open=(id)=>{const e=document.getElementById(id);if(e){e.classList.add('show');document.body.style.overflow='hidden';}};
    const close=(e)=>{if(e)e.classList.remove('show');if(!document.querySelector('.modal.show'))document.body.style.overflow='';};

    document.querySelectorAll('[data-open]').forEach(btn=>{
        btn.addEventListener('click',function(){
            const id=this.dataset.open; open(id);

            if(id==='modalSparepartEdit'){
                document.getElementById('sp_rel_id').value=this.dataset.id||'';
                document.getElementById('edit_sparepart_id').value=this.dataset.sparepart||'';
                document.getElementById('edit_sp_qty').value=this.dataset.qty||'';
                document.getElementById('edit_sp_keterangan').value=this.dataset.keterangan||'';
                updateEditPrice();
            }
            if(id==='modalJasaEdit'){
                document.getElementById('jasa_id').value=this.dataset.id||'';
                document.getElementById('jasa_jenis').value=this.dataset.jenis||'';
                document.getElementById('jasa_keterangan').value=this.dataset.keterangan||'';
                document.getElementById('jasa_qty').value=this.dataset.qty||'';
                document.getElementById('jasa_biaya').value=this.dataset.biaya||'';
                document.getElementById('jasa_tanggal').value=this.dataset.tanggal||'';
            }
            if(id==='modalPerawatanEdit'){
                document.getElementById('perawatan_id').value=this.dataset.id||'';
                document.getElementById('per_tanggal').value=this.dataset.tanggal||'';
                document.getElementById('per_keterangan').value=this.dataset.keterangan||'';
                document.getElementById('per_finishing').value=this.dataset.finishing||'';
                document.getElementById('per_suku').value=this.dataset.suku||'';
                document.getElementById('per_jasa').value=this.dataset.jasa||'';
            }
        });
    });

    document.querySelectorAll('[data-close]').forEach(btn=>btn.addEventListener('click',()=>close(btn.closest('.modal'))));
    document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)close(m);}));
    document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal.show').forEach(close);});

    function fmt(n){return new Intl.NumberFormat('id-ID').format(Number(n||0));}

    const addSel=document.getElementById('add_sparepart_id');
    if(addSel){
        addSel.addEventListener('change',function(){
            const o=this.options[this.selectedIndex];
            document.getElementById('add_sp_harga').value=fmt(o.dataset.harga||0);
            document.getElementById('add_sp_stok').textContent=o.value?'Stok tersedia: '+fmt(o.dataset.stok||0):'';
        });
    }

    function updateEditPrice(){
        const s=document.getElementById('edit_sparepart_id');
        if(!s)return;
        const o=s.options[s.selectedIndex];
        document.getElementById('edit_sp_harga').value=fmt(o?.dataset.harga||0);
    }
    const editSel=document.getElementById('edit_sparepart_id');
    if(editSel)editSel.addEventListener('change',updateEditPrice);

    <?php if($openModal): ?>
    open('<?php echo h($openModal); ?>');
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
