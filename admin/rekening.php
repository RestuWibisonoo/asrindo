<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();
$pageTitle = 'Manajemen Rekening';

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return number_format((float) ($value ?? 0), 0, ',', '.');
}

function redirectPage(string $type, string $message): void
{
    header('Location: rekening.php?' . http_build_query([
        'msg_type' => $type,
        'msg'      => $message
    ]));
    exit;
}

/*
|--------------------------------------------------------------------------
| CRUD
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        /* ── TAMBAH ── */
        if ($action === 'create') {
            $namaRekening  = trim((string) ($_POST['nama_rekening']  ?? ''));
            $namaBank      = trim((string) ($_POST['nama_bank']      ?? ''));
            $nomorRekening = trim((string) ($_POST['nomor_rekening'] ?? ''));
            $atasNama      = trim((string) ($_POST['atas_nama']      ?? ''));
            $saldoAwalRaw  = str_replace(['.', ',', ' '], '', trim((string) ($_POST['saldo_awal'] ?? '0')));
            $saldoAwal     = (float) $saldoAwalRaw;
            $status        = $_POST['status'] ?? 'Aktif';
            $keterangan    = trim((string) ($_POST['keterangan'] ?? ''));

            if ($namaRekening === '' || $namaBank === '') {
                redirectPage('error', 'Nama rekening dan nama bank wajib diisi.');
            }

            if (!in_array($status, ['Aktif', 'Tidak Aktif'], true)) {
                $status = 'Aktif';
            }

            $stmt = $pdo->prepare("
                INSERT INTO rekening (nama_rekening, nama_bank, nomor_rekening, atas_nama, saldo_awal, status, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $namaRekening,
                $namaBank,
                $nomorRekening !== '' ? $nomorRekening : null,
                $atasNama      !== '' ? $atasNama      : null,
                $saldoAwal,
                $status,
                $keterangan !== '' ? $keterangan : null,
            ]);

            redirectPage('success', 'Rekening "' . $namaRekening . '" berhasil ditambahkan.');
        }

        /* ── EDIT ── */
        if ($action === 'update') {
            $id            = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $namaRekening  = trim((string) ($_POST['nama_rekening']  ?? ''));
            $namaBank      = trim((string) ($_POST['nama_bank']      ?? ''));
            $nomorRekening = trim((string) ($_POST['nomor_rekening'] ?? ''));
            $atasNama      = trim((string) ($_POST['atas_nama']      ?? ''));
            $saldoAwalRaw  = str_replace(['.', ',', ' '], '', trim((string) ($_POST['saldo_awal'] ?? '0')));
            $saldoAwal     = (float) $saldoAwalRaw;
            $status        = $_POST['status'] ?? 'Aktif';
            $keterangan    = trim((string) ($_POST['keterangan'] ?? ''));

            if (!$id || $namaRekening === '' || $namaBank === '') {
                redirectPage('error', 'ID, nama rekening, dan nama bank wajib diisi.');
            }

            if (!in_array($status, ['Aktif', 'Tidak Aktif'], true)) {
                $status = 'Aktif';
            }

            $stmt = $pdo->prepare("
                UPDATE rekening
                SET nama_rekening  = ?,
                    nama_bank      = ?,
                    nomor_rekening = ?,
                    atas_nama      = ?,
                    saldo_awal     = ?,
                    status         = ?,
                    keterangan     = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $namaRekening,
                $namaBank,
                $nomorRekening !== '' ? $nomorRekening : null,
                $atasNama      !== '' ? $atasNama      : null,
                $saldoAwal,
                $status,
                $keterangan !== '' ? $keterangan : null,
                $id,
            ]);

            redirectPage('success', 'Rekening berhasil diperbarui.');
        }

        /* ── HAPUS ── */
        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id) {
                redirectPage('error', 'ID rekening tidak valid.');
            }

            // Cek apakah ada transaksi yang terkait
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM pemasukan WHERE rekening_id = ?
                UNION ALL
                SELECT COUNT(*) FROM pengeluaran WHERE rekening_id = ?
            ");
            $stmt->execute([$id, $id]);
            $counts = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $total  = array_sum($counts);

            if ($total > 0) {
                redirectPage('error', 'Rekening tidak dapat dihapus karena masih memiliki ' . $total . ' transaksi terkait.');
            }

            $stmt = $pdo->prepare("DELETE FROM rekening WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);

            redirectPage('success', 'Rekening berhasil dihapus.');
        }

    } catch (Throwable $e) {
        redirectPage('error', $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| FETCH DATA — saldo dihitung real-time
|--------------------------------------------------------------------------
*/
$rekeningList = $pdo->query("
    SELECT
        r.id,
        r.nama_rekening,
        r.nama_bank,
        r.nomor_rekening,
        r.atas_nama,
        r.saldo_awal,
        r.status,
        r.keterangan,
        COALESCE(pm.total_masuk,  0) AS total_masuk,
        COALESCE(pk.total_keluar, 0) AS total_keluar,
        r.saldo_awal
            + COALESCE(pm.total_masuk,  0)
            - COALESCE(pk.total_keluar, 0) AS saldo
    FROM rekening r
    LEFT JOIN (
        SELECT rekening_id, SUM(nominal) AS total_masuk
        FROM pemasukan
        WHERE rekening_id IS NOT NULL
        GROUP BY rekening_id
    ) pm ON pm.rekening_id = r.id
    LEFT JOIN (
        SELECT rekening_id, SUM(nominal) AS total_keluar
        FROM pengeluaran
        WHERE rekening_id IS NOT NULL
        GROUP BY rekening_id
    ) pk ON pk.rekening_id = r.id
    ORDER BY r.status ASC, r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalSaldo   = array_sum(array_column($rekeningList, 'saldo'));

/* Pesan flash */
$msgType = h($_GET['msg_type'] ?? '');
$msgText = h($_GET['msg']      ?? '');

$extraHead = <<<'HTML'
<style>
.page-content { padding: 26px; }

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 22px;
}
.page-heading h1 { margin: 0 0 4px; font-size: 25px; color: #071b3a; }
.page-heading p  { margin: 0; color: #71809a; font-size: 13px; }

.summary-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.summary-card {
    background: #fff;
    border: 1px solid #dbe2ec;
    border-radius: 6px;
    padding: 16px 18px;
}
.summary-card span { display: block; color: #7a889d; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.summary-card strong { font-size: 20px; color: #0a2347; }

.card { background: #fff; border: 1px solid #dbe2ec; border-radius: 4px; overflow: hidden; }
.card-header { padding: 16px 18px; border-bottom: 1px solid #dbe2ec; color: #0b2853; display: flex; justify-content: space-between; align-items: center; }
.muted { color: #8290a7; font-size: 12px; }
.table-wrap { width: 100%; overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; min-width: 820px; }
.data-table th, .data-table td { padding: 12px 10px; border-bottom: 1px solid #dbe2ec; text-align: left; font-size: 13px; color: #0b2853; vertical-align: middle; }
.data-table thead th { background: #f5f7fa; font-weight: 600; white-space: nowrap; }
.data-table tbody tr:hover { background: #fafcff; }
.money-cell { text-align: right !important; white-space: nowrap; }

.status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.status-success { background: #10b95d; color: #fff; }
.status-muted   { background: #b0bec5; color: #fff; }

.btn { border: 1px solid transparent; border-radius: 4px; padding: 9px 15px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; gap: 5px; box-sizing: border-box; }
.btn-sm { padding: 6px 11px; font-size: 12px; }
.btn-primary { background: #086cff; color: #fff; }
.btn-primary:hover { background: #0058d8; }
.btn-outline { background: #fff; border-color: #7890b1; color: #58708f; }
.btn-outline:hover { background: #f4f6f9; }
.btn-danger-outline { background: #fff; border-color: #ff5a64; color: #f04450; }
.btn-danger-outline:hover { background: #fff0f1; }
.btn-info-outline { background: #fff; border-color: #0db6d1; color: #0ba3bc; }
.btn-info-outline:hover { background: #e6f9fc; }
.btn-light { background: #eef1f5; border-color: #d8dfe8; color: #52647d; }

.alert { padding: 12px 16px; border-radius: 4px; font-size: 13px; margin-bottom: 18px; }
.alert-success { background: #e6f9f0; color: #0e7a47; border: 1px solid #a3e6c5; }
.alert-error   { background: #fef0f0; color: #c0392b; border: 1px solid #f5c0bc; }

/* Modal */
.modal-backdrop { position: fixed; inset: 0; z-index: 9999; background: rgba(8,19,37,.58); display: none; align-items: center; justify-content: center; padding: 18px; box-sizing: border-box; }
.modal-backdrop.show { display: flex; }
.modal { width: min(520px,100%); max-height: calc(100vh - 36px); background: #fff; border-radius: 6px; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
.modal-header { padding: 16px 18px; border-bottom: 1px solid #dbe2ec; display: flex; justify-content: space-between; align-items: flex-start; }
.modal-header h2 { margin: 0; color: #0a2347; font-size: 17px; }
.modal-close { border: 0; background: transparent; color: #7b899e; font-size: 26px; cursor: pointer; line-height: 1; }
.modal-body { padding: 20px; }
.modal-footer { border-top: 1px solid #dbe2ec; padding: 13px 18px; display: flex; justify-content: flex-end; gap: 8px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; margin-bottom: 5px; color: #263d60; font-size: 12px; font-weight: 600; }
.form-group input, .form-group select, .form-group textarea { width: 100%; box-sizing: border-box; padding: 9px 10px; border: 1px solid #bdcadc; border-radius: 4px; background: #fff; color: #183457; outline: none; font: inherit; font-size: 13px; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #1473e6; box-shadow: 0 0 0 2px rgba(20,115,230,.08); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>
HTML;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="page-heading">
        <div>
            <h1>Manajemen Rekening</h1>
            <p>Kelola rekening bank perusahaan dan pantau mutasi kas masuk &amp; keluar.</p>
        </div>
        <button type="button" class="btn btn-primary" onclick="openAddModal()">
            + Tambah Rekening
        </button>
    </div>

    <?php if ($msgText): ?>
        <div class="alert alert-<?php echo $msgType === 'success' ? 'success' : 'error'; ?>">
            <?php echo $msgText; ?>
        </div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="summary-row">
        <div class="summary-card">
            <span>Total Rekening</span>
            <strong><?php echo count($rekeningList); ?></strong>
        </div>
        <div class="summary-card">
            <span>Total Saldo Semua Rekening</span>
            <strong>Rp <?php echo rupiah($totalSaldo); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Daftar Rekening</strong>
            <span class="muted"><?php echo count($rekeningList); ?> rekening</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Rekening</th>
                        <th>Bank</th>
                        <th>No. Rekening</th>
                        <th>Atas Nama</th>
                        <th class="money-cell">Saldo Awal</th>
                        <th class="money-cell">Total Masuk</th>
                        <th class="money-cell">Total Keluar</th>
                        <th class="money-cell" style="background:#f0f7ff;">Saldo Saat Ini</th>
                        <th>Status</th>
                        <th style="width:200px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekeningList)): ?>
                        <tr>
                            <td colspan="11" style="text-align:center;padding:30px;color:#8a96aa;">
                                Belum ada data rekening. Tambahkan rekening pertama Anda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rekeningList as $i => $r): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><strong><?php echo h($r['nama_rekening']); ?></strong></td>
                                <td><?php echo h($r['nama_bank']); ?></td>
                                <td><?php echo h($r['nomor_rekening'] ?? '-'); ?></td>
                                <td><?php echo h($r['atas_nama'] ?? '-'); ?></td>
                                <td class="money-cell">Rp <?php echo rupiah($r['saldo_awal']); ?></td>
                                <td class="money-cell" style="color:#10b95d;">Rp <?php echo rupiah($r['total_masuk']); ?></td>
                                <td class="money-cell" style="color:#e74c3c;">Rp <?php echo rupiah($r['total_keluar']); ?></td>
                                <td class="money-cell" style="background:#f8fbff;font-weight:600;color:#086cff;">
                                    Rp <?php echo rupiah($r['saldo']); ?>
                                </td>
                                <td>
                                    <?php if ($r['status'] === 'Aktif'): ?>
                                        <span class="status-badge status-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="status-badge status-muted">Tidak Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="rekening_detail.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-info-outline">Mutasi</a>
                                    <button type="button" class="btn btn-sm btn-outline"
                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES); ?>)">
                                        Edit
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger-outline"
                                        onclick="openDeleteModal(<?php echo (int)$r['id']; ?>, '<?php echo h($r['nama_rekening']); ?>')">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit Rekening -->
<div class="modal-backdrop" id="modalFormRekening">
    <div class="modal">
        <form method="POST" action="rekening.php">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id"     id="formId"     value="">
            <div class="modal-header">
                <h2 id="modalFormTitle">Tambah Rekening</h2>
                <button type="button" class="modal-close" onclick="closeModal('modalFormRekening')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Rekening / Alias <span style="color:red">*</span></label>
                        <input type="text" name="nama_rekening" id="formNamaRekening" placeholder="cth: Rekening BCA Penjualan" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Bank <span style="color:red">*</span></label>
                        <input type="text" name="nama_bank" id="formNamaBank" placeholder="cth: BCA, Mandiri, Tunai" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nomor Rekening</label>
                        <input type="text" name="nomor_rekening" id="formNomorRekening" placeholder="Masukkan nomor rekening">
                    </div>
                    <div class="form-group">
                        <label>Atas Nama</label>
                        <input type="text" name="atas_nama" id="formAtasNama" placeholder="Nama pemilik rekening">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Saldo Awal (Rp)</label>
                        <input type="number" name="saldo_awal" id="formSaldoAwal" min="0" step="1" value="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="formStatus">
                            <option value="Aktif">Aktif</option>
                            <option value="Tidak Aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Keterangan</label>
                    <textarea name="keterangan" id="formKeterangan" rows="2" placeholder="Keterangan tambahan (opsional)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalFormRekening')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal-backdrop" id="modalDeleteRekening">
    <div class="modal" style="width:min(420px,100%);">
        <form method="POST" action="rekening.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId" value="">
            <div class="modal-header">
                <h2>Hapus Rekening</h2>
                <button type="button" class="modal-close" onclick="closeModal('modalDeleteRekening')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin:0;color:#4a5b77;font-size:13px;">
                    Apakah Anda yakin ingin menghapus rekening <strong id="deleteNama"></strong>?<br>
                    Rekening tidak dapat dihapus jika masih memiliki riwayat transaksi.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalDeleteRekening')">Batal</button>
                <button type="submit" class="btn btn-danger-outline" style="background:#e74c3c;color:#fff;">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('show');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}
function openAddModal() {
    document.getElementById('modalFormTitle').innerText = 'Tambah Rekening';
    document.getElementById('formAction').value        = 'create';
    document.getElementById('formId').value            = '';
    document.getElementById('formNamaRekening').value  = '';
    document.getElementById('formNamaBank').value      = '';
    document.getElementById('formNomorRekening').value = '';
    document.getElementById('formAtasNama').value      = '';
    document.getElementById('formSaldoAwal').value     = '0';
    document.getElementById('formStatus').value        = 'Aktif';
    document.getElementById('formKeterangan').value    = '';
    openModal('modalFormRekening');
}
function openEditModal(r) {
    document.getElementById('modalFormTitle').innerText = 'Edit Rekening';
    document.getElementById('formAction').value        = 'update';
    document.getElementById('formId').value            = r.id;
    document.getElementById('formNamaRekening').value  = r.nama_rekening || '';
    document.getElementById('formNamaBank').value      = r.nama_bank     || '';
    document.getElementById('formNomorRekening').value = r.nomor_rekening|| '';
    document.getElementById('formAtasNama').value      = r.atas_nama     || '';
    document.getElementById('formSaldoAwal').value     = r.saldo_awal    || '0';
    document.getElementById('formStatus').value        = r.status        || 'Aktif';
    document.getElementById('formKeterangan').value    = r.keterangan    || '';
    openModal('modalFormRekening');
}
function openDeleteModal(id, nama) {
    document.getElementById('deleteId').value   = id;
    document.getElementById('deleteNama').innerText = nama;
    openModal('modalDeleteRekening');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
