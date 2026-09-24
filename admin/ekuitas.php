<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();
$pageTitle = 'Ekuitas (Modal)';

function h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function rupiah($value): string { return 'Rp ' . number_format((float) ($value ?? 0), 0, ',', '.'); }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_ekuitas') {
        $jenis = $_POST['jenis'] ?? 'MODAL_SETOR';
        $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
        $nominal = (float) str_replace(['Rp', '.', ' '], '', $_POST['nominal'] ?? '0');
        $rekeningId = filter_var($_POST['rekening_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($nominal <= 0) {
            $error = 'Nominal harus lebih dari 0.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO ekuitas (jenis, tanggal, nominal, keterangan, rekening_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$jenis, $tanggal, $nominal, $keterangan, $rekeningId]);
                $ekuitasId = $pdo->lastInsertId();

                if ($rekeningId) {
                    if ($jenis === 'MODAL_SETOR') {
                        $prefix = 'PM-' . date('Ymd', strtotime($tanggal)) . '-';
                        $stmt = $pdo->prepare("SELECT nomor_pemasukan FROM pemasukan WHERE nomor_pemasukan LIKE ? ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$prefix . '%']);
                        $last = (string) $stmt->fetchColumn();
                        $next = 1;
                        if ($last !== '') {
                            $suffix = substr($last, -4);
                            if (ctype_digit($suffix)) $next = (int) $suffix + 1;
                        }
                        $noTrx = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

                        $stmt = $pdo->prepare("INSERT INTO pemasukan (nomor_pemasukan, tanggal, sumber, nominal, keterangan, rekening_id, referensi) VALUES (?, ?, 'NON_PENJUALAN', ?, ?, ?, ?)");
                        $stmt->execute([$noTrx, $tanggal, $nominal, 'Setoran Modal: ' . $keterangan, $rekeningId, 'EKUITAS-' . $ekuitasId]);
                    } elseif ($jenis === 'PRIVE') {
                        $prefix = 'PK-' . date('Ymd', strtotime($tanggal)) . '-';
                        $stmt = $pdo->prepare("SELECT nomor_pengeluaran FROM pengeluaran WHERE nomor_pengeluaran LIKE ? ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$prefix . '%']);
                        $last = (string) $stmt->fetchColumn();
                        $next = 1;
                        if ($last !== '') {
                            $suffix = substr($last, -4);
                            if (ctype_digit($suffix)) $next = (int) $suffix + 1;
                        }
                        $noTrx = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

                        $stmt = $pdo->prepare("INSERT INTO pengeluaran (nomor_pengeluaran, tanggal, sumber, jenis_pengeluaran, nominal, keterangan, rekening_id, referensi) VALUES (?, ?, 'NON_PEMBELIAN', 'Prive', ?, ?, ?, ?)");
                        $stmt->execute([$noTrx, $tanggal, $nominal, 'Tarik Modal / Prive: ' . $keterangan, $rekeningId, 'EKUITAS-' . $ekuitasId]);
                    }
                }

                $pdo->commit();
                $success = 'Data ekuitas berhasil disimpan.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal menyimpan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_ekuitas') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM pemasukan WHERE referensi = ?");
                $stmt->execute(['EKUITAS-' . $id]);
                
                $stmt = $pdo->prepare("DELETE FROM pengeluaran WHERE referensi = ?");
                $stmt->execute(['EKUITAS-' . $id]);
                
                $stmt = $pdo->prepare("DELETE FROM ekuitas WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                $success = 'Data ekuitas berhasil dihapus.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal menghapus: ' . $e->getMessage();
            }
        }
    }
}

// Rekening list
$rekeningList = $pdo->query("SELECT id, nama_rekening, nama_bank, nomor_rekening FROM rekening ORDER BY nama_bank, nama_rekening")->fetchAll(PDO::FETCH_ASSOC);

// Total Ekuitas
$totalModal = (float) $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM ekuitas WHERE jenis = 'MODAL_SETOR'")->fetchColumn();
$totalPrive = (float) $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM ekuitas WHERE jenis = 'PRIVE'")->fetchColumn();
$netModal = $totalModal - $totalPrive;

// List
$ekuitasList = $pdo->query("
    SELECT e.*, r.nama_bank, r.nama_rekening 
    FROM ekuitas e 
    LEFT JOIN rekening r ON e.rekening_id = r.id 
    ORDER BY e.tanggal DESC, e.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/header.php';
?>

<style>
    /* Mengadopsi style grid dashboard & laporan */
    .dashboard-finance-grid{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:12px;
        margin-bottom:20px;
    }
    
    .dashboard-finance-card{
        position:relative;
        background:#fff;
        border:1px solid #dce3eb;
        border-radius:7px;
        padding:15px 16px;
        box-shadow:0 1px 2px rgba(23,43,77,.04);
    }
    
    .dashboard-finance-card::before{
        content:'';
        position:absolute;
        left:0;
        top:10px;
        bottom:10px;
        width:3px;
        border-radius:0 3px 3px 0;
        background:#0d6efd;
    }
    
    .dashboard-finance-card.net::before{background:#0d6efd}
    .dashboard-finance-card.expense::before{background:#dc3545}
    .dashboard-finance-card.warning::before{background:#f59e0b}
    .dashboard-finance-card.success::before{background:#10b981}
    
    .dashboard-finance-card strong{
        display:block;
        padding-left:4px;
        color:#172b4d;
        font-size:18px;
        margin-bottom:5px;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    
    .dashboard-finance-card span{
        display:block;
        padding-left:4px;
        color:#64748b;
        font-size:11px;
    }
    
    .dashboard-finance-card.net strong{color:#0d6efd}
    .dashboard-finance-card.expense strong{color:#dc3545}
    .dashboard-finance-card.warning strong{color:#b45309}
    .dashboard-finance-card.success strong{color:#047857}
    
    /* Tombol utama */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #0d6efd;
        color: #fff;
        padding: 9px 15px;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s;
        text-decoration: none;
    }
    
    .btn-primary:hover {
        background: #0b5ed7;
    }

    .btn-secondary {
        border: 1px solid #bdcadc;
        background: #fff;
        color: #314b72;
        border-radius: 4px;
        padding: 8px 14px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-secondary:hover {
        background: #f1f4f9;
    }

    .btn-danger {
        border: 1px solid #e2a8a8;
        background: #fff;
        color: #b42318;
        border-radius: 4px;
        padding: 8px 14px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px;
    }

    .btn-danger:hover {
        background: #fff0ef;
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 11px;
    }
    
    /* Table utilities */
    .dashboard-table tbody tr:hover { background: #fafcff; }
    .dashboard-table .money { text-align: right !important; font-weight: 600; white-space: nowrap; }
    .dashboard-table .center { text-align: center !important; }
    .dashboard-table .muted { color: #7a8798; font-size: 11px; margin-top: 3px; display: block; }
    
    .badge { display:inline-flex; align-items:center; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700; }
    .badge-modal { background:#e7f0fd; color:#0c4a6e; }
    .badge-prive { background:#ffedd5; color:#c2410c; }
    
    .panel-subtitle { display: block; margin-top: 3px; color: #718096; font-size: 12px; font-weight: 400; }
    .empty-state { padding:36px 20px !important; text-align:center !important; color:#64748b; font-size:13px; font-style:italic; }

    .alert { padding: 12px 14px; border-radius: 6px; margin-bottom: 18px; font-size: 14px; }
    .alert-success { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
    .alert-error { background:#fff1f2; color:#b42318; border:1px solid #fecdd3; }
    
    /* Modal styles */
    .liability-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        box-sizing: border-box;
        background: rgba(8, 19, 37, .58);
    }
    
    .liability-modal.show {
        display: flex;
        touch-action: none;
    }
    
    .liability-modal-box {
        width: min(600px, 100%);
        max-height: calc(100vh - 36px);
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        border-radius: 6px;
        background: #fff;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
        display: block;
    }
    
    .liability-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 15px;
        padding: 16px 18px;
        border-bottom: 1px solid #dbe2ec;
    }
    
    .liability-modal-header h3 {
        margin: 0 0 4px;
        color: #0a2347;
        font-size: 18px;
    }
    
    .liability-modal-header p {
        margin: 0;
        color: #7c8ba1;
        font-size: 12px;
    }
    
    .liability-modal-close {
        width: 34px;
        height: 34px;
        border: 0;
        background: transparent;
        color: #7b899e;
        font-size: 26px;
        cursor: pointer;
        line-height: 1;
        flex-shrink: 0;
    }
    
    .liability-modal-body {
        padding: 20px 18px;
    }
    
    .liability-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 13px 18px;
        border-top: 1px solid #dbe2ec;
    }
    
    .liability-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    
    .liability-field {
        min-width: 0;
    }
    
    .liability-field-full {
        grid-column: 1 / -1;
    }
    
    .liability-field label {
        display: block;
        margin-bottom: 6px;
        color: #263d60;
        font-size: 12px;
        font-weight: 500;
    }
    
    .liability-field label span {
        color: #e53935;
    }
    
    .liability-field input,
    .liability-field select,
    .liability-field textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #bdcadc;
        border-radius: 4px;
        background: #fff;
        color: #183457;
        padding: 8px 10px;
        outline: none;
        font: inherit;
        font-size: 13px;
    }
    
    .liability-field input,
    .liability-field select {
        height: 36px;
    }
    
    .liability-field textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .liability-field input:focus,
    .liability-field select:focus,
    .liability-field textarea:focus {
        border-color: #1473e6;
        box-shadow: 0 0 0 2px rgba(20, 115, 230, .08);
    }
    
    @media (max-width: 900px) {
        .dashboard-finance-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .liability-form-grid { grid-template-columns:1fr; }
        .liability-field-full { grid-column:auto; }
    }
    @media (max-width: 500px) { .dashboard-finance-grid { grid-template-columns:1fr; } }
</style>

<div>
    <section class="page-heading">
        <div>
            <h1>Ekuitas (Modal)</h1>
            <p>Pencatatan setoran modal awal / tambahan modal dan penarikan modal (prive).</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button class="btn-primary" type="button" onclick="openModal('MODAL_SETOR')">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Modal
            </button>
            <button class="btn-secondary" type="button" onclick="openModal('PRIVE')">
                Tarik Modal (Prive)
            </button>
        </div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>

    <section class="dashboard-finance-grid">
        <article class="dashboard-finance-card success">
            <strong><?php echo rupiah($totalModal); ?></strong>
            <span>Total Setoran Modal</span>
            <div class="muted" style="margin-top:6px; padding-left:4px;">Total modal yang disetor ke kas/rekening.</div>
        </article>
        <article class="dashboard-finance-card expense">
            <strong><?php echo rupiah($totalPrive); ?></strong>
            <span>Total Penarikan (Prive)</span>
            <div class="muted" style="margin-top:6px; padding-left:4px;">Total penarikan dana oleh pemilik/direksi.</div>
        </article>
        <article class="dashboard-finance-card net">
            <strong><?php echo rupiah($netModal); ?></strong>
            <span>Total Ekuitas Bersih</span>
            <div class="muted" style="margin-top:6px; padding-left:4px;">Sisa ekuitas modal disetor (Modal - Prive).</div>
        </article>
    </section>

    <section class="dashboard-panel" style="margin-top:20px;">
        <div class="panel-heading">
            <div>
                <h2>Riwayat Ekuitas</h2>
                <span class="panel-subtitle">Total <?php echo count($ekuitasList); ?> riwayat transaksi modal</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th>Rekening Saldo</th>
                        <th class="money">Nominal</th>
                        <th class="center" style="width: 80px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$ekuitasList): ?>
                    <tr><td colspan="6" class="empty-state">Belum ada riwayat modal.</td></tr>
                <?php else: ?>
                    <?php foreach ($ekuitasList as $row): ?>
                    <tr>
                        <td><?php echo h(date('d/m/Y', strtotime($row['tanggal']))); ?></td>
                        <td>
                            <?php if($row['jenis'] === 'MODAL_SETOR'): ?>
                                <span class="badge badge-modal">Setoran Modal</span>
                            <?php else: ?>
                                <span class="badge badge-prive">Prive / Tarik Modal</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($row['keterangan']); ?></td>
                        <td>
                            <?php if ($row['rekening_id']): ?>
                                <?php echo h($row['nama_rekening'] . ' - ' . $row['nama_bank']); ?>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="money" style="color: <?php echo $row['jenis'] === 'MODAL_SETOR' ? '#15803d' : '#b91c1c'; ?>">
                            <?php echo $row['jenis'] === 'MODAL_SETOR' ? '+' : '-'; ?> <?php echo rupiah($row['nominal']); ?>
                        </td>
                        <td class="center">
                            <form method="post" onsubmit="return confirm('Hapus data ekuitas ini? Saldo rekening juga akan disesuaikan otomatis.');">
                                <input type="hidden" name="action" value="delete_ekuitas">
                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                <button class="btn-danger btn-sm" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Modal Tambah/Tarik -->
<div class="liability-modal" id="modalEkuitas">
    <div class="liability-modal-box">
        <form method="post">
            <div class="liability-modal-header">
                <div>
                    <h3 id="modalTitle">Tambah Modal</h3>
                    <p id="modalDesc">Catat uang masuk ke rekening sebagai modal</p>
                </div>
                <button type="button" class="liability-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="liability-modal-body">
                <input type="hidden" name="action" value="add_ekuitas">
                <input type="hidden" name="jenis" id="modalJenis" value="MODAL_SETOR">
                
                <div class="liability-form-grid">
                    <div class="liability-field liability-field-full">
                        <label>Rekening Saldo <span>*</span></label>
                        <select name="rekening_id" required>
                            <option value="">-- Pilih Rekening --</option>
                            <?php foreach ($rekeningList as $rek): ?>
                                <option value="<?php echo $rek['id']; ?>"><?php echo h($rek['nama_rekening'] . ' - ' . $rek['nama_bank']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span style="font-size:11px;color:#64748b;display:block;margin-top:4px;" id="rekHelp">Dana akan masuk ke rekening ini.</span>
                    </div>
                    
                    <div class="liability-field">
                        <label>Tanggal <span>*</span></label>
                        <input type="date" name="tanggal" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="liability-field">
                        <label>Nominal (Rp) <span>*</span></label>
                        <input type="text" name="nominal" required onkeyup="formatRupiah(this)">
                    </div>
                    
                    <div class="liability-field liability-field-full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" placeholder="Keterangan tambahan (opsional)"></textarea>
                    </div>
                </div>
            </div>
            <div class="liability-modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn-primary" id="btnSimpan">Simpan Modal</button>
            </div>
        </form>
    </div>
</div>

<script>
const modalEkuitas = document.getElementById('modalEkuitas');

function openModal(jenis) {
    document.getElementById('modalJenis').value = jenis;
    if (jenis === 'MODAL_SETOR') {
        document.getElementById('modalTitle').textContent = 'Tambah Modal Awal';
        document.getElementById('modalDesc').textContent = 'Catat uang masuk ke rekening sebagai modal usaha';
        document.getElementById('btnSimpan').textContent = 'Simpan Modal';
        document.getElementById('rekHelp').textContent = 'Dana akan masuk (bertambah) ke rekening ini.';
    } else {
        document.getElementById('modalTitle').textContent = 'Tarik Modal (Prive)';
        document.getElementById('modalDesc').textContent = 'Catat penarikan dana perusahaan oleh pemilik/direksi';
        document.getElementById('btnSimpan').textContent = 'Simpan Penarikan';
        document.getElementById('rekHelp').textContent = 'Dana akan dipotong (berkurang) dari rekening ini.';
    }
    modalEkuitas.classList.add('show');
}

function closeModal() {
    modalEkuitas.classList.remove('show');
}

modalEkuitas.addEventListener('click', function(e) {
    if (e.target === modalEkuitas) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

function formatRupiah(obj) {
    let val = obj.value.replace(/[^,\d]/g, '').toString();
    let split = val.split(',');
    let sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
    obj.value = rupiah;
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
