<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();
$pageTitle = 'Aset Tetap';

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) ($value ?? 0), 0, ',', '.');
}

function redirectWithMessage(string $type, string $message): never
{
    header('Location: aset_tetap.php?' . http_build_query([
        'msg' => $message,
        'type' => $type,
    ]));
    exit;
}

function generateKodeAset(PDO $pdo): string
{
    $prefix = 'AST-' . date('Ym') . '-';
    $stmt = $pdo->prepare("SELECT kode FROM aset_tetap WHERE kode LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = (string) ($stmt->fetchColumn() ?: '');

    $number = 1;
    if (preg_match('/-(\d+)$/', $last, $m)) {
        $number = (int) $m[1] + 1;
    }

    return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
}

function calculateDepreciation(array $asset, string $asOf): array
{
    $acquisition = (float) ($asset['harga_beli'] ?? $asset['nilai_perolehan'] ?? 0);
    $persen = (float) ($asset['persentase_penyusutan'] ?? 0);
    $date = (string) $asset['tanggal_perolehan'];

    $depreciable = max(0, $acquisition);
    
    $months = 0;
    if ($date !== '' && $asOf >= $date) {
        $start = new DateTimeImmutable(date('Y-m-01', strtotime($date)));
        $end = new DateTimeImmutable(date('Y-m-01', strtotime($asOf)));
        $months = max(0, ((int) $end->format('Y') - (int) $start->format('Y')) * 12 + ((int) $end->format('n') - (int) $start->format('n')));
    }

    $annualDepreciation = $depreciable * ($persen / 100);
    $monthly = $annualDepreciation / 12;

    if ($depreciable <= 0 || $persen <= 0 || $months <= 0) {
        return [
            'months' => $months,
            'depreciation' => 0.0,
            'book_value' => $acquisition,
            'monthly' => $monthly,
            'annual_percentage' => $persen,
        ];
    }

    $depreciation = min($depreciable, $monthly * $months);

    return [
        'months' => $months,
        'depreciation' => $depreciation,
        'book_value' => max(0, $acquisition - $depreciation),
        'monthly' => $monthly,
        'annual_percentage' => $persen,
    ];
}

/* -------------------------------------------------------------------------
 * ACTIONS
 * ---------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $kode = trim((string) ($_POST['kode'] ?? ''));
            $nama = trim((string) ($_POST['nama'] ?? ''));
            
            $kategoriInput = trim((string) ($_POST['kategori'] ?? ''));
            $kategori = $kategoriInput !== '' ? strtoupper(str_replace(' ', '_', $kategoriInput)) : 'LAINNYA';
            
            $tanggal = trim((string) ($_POST['tanggal_perolehan'] ?? ''));
            $hargaBeli = (float) ($_POST['harga_beli'] ?? 0);
            $umur = (int) ($_POST['umur_manfaat_tahun'] ?? 0);
            $persen = (float) ($_POST['persentase_penyusutan'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'AKTIF');
            $lokasi = trim((string) ($_POST['lokasi'] ?? ''));
            $nomorIdentitas = trim((string) ($_POST['nomor_identitas'] ?? ''));
            $keterangan = trim((string) ($_POST['keterangan'] ?? ''));

            if ($kode === '') {
                $kode = generateKodeAset($pdo);
            }
            if ($nama === '') {
                throw new RuntimeException('Nama aset wajib diisi.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                throw new RuntimeException('Tanggal perolehan tidak valid.');
            }
            if ($hargaBeli <= 0) {
                throw new RuntimeException('Harga beli harus lebih besar dari 0.');
            }
            if ($umur < 1 || $umur > 100) {
                throw new RuntimeException('Umur manfaat harus antara 1 sampai 100 tahun.');
            }
            if ($persen <= 0 || $persen > 100) {
                throw new RuntimeException('Persentase penyusutan harus lebih besar dari 0 dan maksimal 100.');
            }
            if (!in_array($status, ['AKTIF', 'DIJUAL', 'RUSAK', 'TIDAK_AKTIF'], true)) {
                throw new RuntimeException('Status aset tidak valid.');
            }

            $check = $pdo->prepare("SELECT id FROM aset_tetap WHERE kode = ? AND id <> ? LIMIT 1");
            $check->execute([$kode, $id]);
            if ($check->fetchColumn()) {
                throw new RuntimeException('Kode aset sudah digunakan.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE aset_tetap SET kode=?, nama=?, kategori=?, tanggal_perolehan=?, nilai_perolehan=?, nilai_residu=0, umur_manfaat_tahun=?, persentase_penyusutan=?, metode_penyusutan='GARIS_LURUS', status=?, lokasi=?, nomor_identitas=?, keterangan=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([$kode, $nama, $kategori, $tanggal, $hargaBeli, $umur, $persen, $status, $lokasi, $nomorIdentitas, $keterangan, $id]);
                redirectWithMessage('success', 'Aset tetap berhasil diperbarui.');
            }

            $stmt = $pdo->prepare("INSERT INTO aset_tetap (kode, nama, kategori, tanggal_perolehan, nilai_perolehan, nilai_residu, umur_manfaat_tahun, persentase_penyusutan, metode_penyusutan, status, lokasi, nomor_identitas, keterangan) VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'GARIS_LURUS', ?, ?, ?, ?)");
            $stmt->execute([$kode, $nama, $kategori, $tanggal, $hargaBeli, $umur, $persen, $status, $lokasi, $nomorIdentitas, $keterangan]);
            redirectWithMessage('success', 'Aset tetap berhasil ditambahkan.');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Aset tidak valid.');
            }
            $stmt = $pdo->prepare("DELETE FROM aset_tetap WHERE id = ?");
            $stmt->execute([$id]);
            redirectWithMessage('success', 'Aset tetap berhasil dihapus.');
        }
    } catch (Throwable $e) {
        redirectWithMessage('error', $e->getMessage());
    }
}

$flashMessage = trim((string) ($_GET['msg'] ?? ''));
$flashType = (string) ($_GET['type'] ?? 'success');

/* -------------------------------------------------------------------------
 * DAFTAR KATEGORI
 * ---------------------------------------------------------------------- */
$stmtCat = $pdo->query("SELECT DISTINCT kategori FROM aset_tetap WHERE kategori IS NOT NULL AND kategori != ''");
$existingCats = $stmtCat->fetchAll(PDO::FETCH_COLUMN);
$allCats = [
    'KENDARAAN_OPERASIONAL' => 'Kendaraan Operasional',
    'MESIN' => 'Mesin',
    'PERALATAN_KANTOR' => 'Peralatan Kantor',
    'KOMPUTER' => 'Komputer / IT',
    'BANGUNAN' => 'Bangunan',
    'FURNITURE' => 'Furniture',
    'LAINNYA' => 'Lainnya'
];
foreach ($existingCats as $c) {
    if (!isset($allCats[$c]) && trim($c) !== '') {
        $allCats[$c] = ucwords(strtolower(str_replace('_', ' ', $c)));
    }
}

/* -------------------------------------------------------------------------
 * DATA ASET
 * ---------------------------------------------------------------------- */
$today = date('Y-m-d');
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$sql = "SELECT *, nilai_perolehan AS harga_beli FROM aset_tetap WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (kode LIKE ? OR nama LIKE ? OR kategori LIKE ? OR nomor_identitas LIKE ? OR lokasi LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

if ($statusFilter !== '' && in_array($statusFilter, ['AKTIF', 'DIJUAL', 'RUSAK', 'TIDAK_AKTIF'], true)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY tanggal_perolehan DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalHargaBeli = 0.0;
$totalPenyusutan = 0.0;
$totalNilaiBuku = 0.0;
$activeCount = 0;

foreach ($assets as &$asset) {
    $dep = calculateDepreciation($asset, $today);
    $asset['penyusutan'] = $dep['depreciation'];
    $asset['nilai_buku'] = $dep['book_value'];
    $asset['penyusutan_bulanan'] = $dep['monthly'];
    $asset['penyusutan_persen'] = $dep['annual_percentage'];
    $asset['umur_berjalan_bulan'] = $dep['months'];

    if ($asset['status'] === 'AKTIF') {
        $totalHargaBeli += (float) $asset['harga_beli'];
        $totalPenyusutan += (float) $asset['penyusutan'];
        $totalNilaiBuku += (float) $asset['nilai_buku'];
        $activeCount++;
    }
}
unset($asset);

require __DIR__ . '/../includes/header.php';
?>

<style>
    /* Mengadopsi style grid dashboard & laporan */
    .dashboard-finance-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
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
    .badge-success { background:#e7f6ec; color:#16743a; }
    .badge-warning { background:#fef3c7; color:#92400e; }
    .badge-danger { background:#fee2e2; color:#b91c1c; }
    .badge-info { background:#e0f2fe; color:#0369a1; }
    .badge-neutral { background:#f1f5f9; color:#475569; border: 1px solid #cbd5e1; }

    .panel-subtitle { display: block; margin-top: 3px; color: #718096; font-size: 12px; font-weight: 400; }
    .empty-state { padding:36px 20px !important; text-align:center !important; color:#64748b; font-size:13px; font-style:italic; }

    /* Panel toolbar & filters */
    .panel-toolbar {
        padding: 12px 16px;
        border-bottom: 1px solid #edf0f4;
        background: #fafbfc;
    }
    .toolbar-filter-form {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .toolbar-input {
        width: 280px;
        height: 36px;
        box-sizing: border-box;
        border: 1px solid #bdcadc;
        border-radius: 4px;
        padding: 8px 10px;
        background: #fff;
        color: #172b4d;
        font-size: 13px;
        outline: none;
    }
    .toolbar-select {
        width: 160px;
        height: 36px;
        box-sizing: border-box;
        border: 1px solid #bdcadc;
        border-radius: 4px;
        padding: 8px 10px;
        background: #fff;
        color: #172b4d;
        font-size: 13px;
        outline: none;
    }
    .toolbar-input:focus, .toolbar-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 2px rgba(13,110,253,.08);
    }

    .asset-actions { display:flex; gap:6px; flex-wrap:wrap; }
    
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
        width: min(760px, 100%);
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
        .toolbar-input, .toolbar-select { width:100%; }
        .toolbar-filter-form { width:100%; }
    }
    @media (max-width: 500px) { .dashboard-finance-grid { grid-template-columns:1fr; } }
</style>

<div>
    <section class="page-heading">
        <div>
            <h1>Aset Tetap</h1>
            <p>Aset operasional perusahaan seperti mobil operasional, komputer, mesin, dan peralatan kantor.</p>
        </div>
        <div>
            <button class="btn-primary" type="button" onclick="openAssetModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Aset Tetap
            </button>
        </div>
    </section>

    <?php if ($flashMessage !== ''): ?>
        <div class="alert alert-<?php echo $flashType === 'error' ? 'error' : 'success'; ?>">
            <?php echo h($flashMessage); ?>
        </div>
    <?php endif; ?>

    <section class="dashboard-finance-grid">
        <article class="dashboard-finance-card">
            <strong><?php echo number_format($activeCount, 0, ',', '.'); ?> aset</strong>
            <span>Jumlah Aset Aktif</span>
            <div class="muted" style="margin-top:6px; padding-left:4px;">Aset operasional yang berstatus aktif digunakan.</div>
        </article>
        <article class="dashboard-finance-card net">
            <strong><?php echo rupiah($totalHargaBeli); ?></strong>
            <span>Total Nilai Perolehan</span>
            <div class="muted" style="margin-top:6px; padding-left:4px;">Total akumulasi harga beli awal aset.</div>
        </article>
        <article class="dashboard-finance-card warning">
            <strong><?php echo rupiah($totalPenyusutan); ?></strong>
            <span>Akumulasi Penyusutan</span>
            <div class="muted" style="margin-top:6px; padding-left:4px;">Total depresiasi/penyusutan berjalan.</div>
        </article>
        <article class="dashboard-finance-card success">
            <strong><?php echo rupiah($totalNilaiBuku); ?></strong>
            <span>Nilai Buku Aset</span>
            <div class="muted" style="margin-top:6px; padding-left:4px;">Nilai buku bersih (Harga Beli - Penyusutan).</div>
        </article>
    </section>

    <section class="dashboard-panel" style="margin-top:20px;">
        <div class="panel-heading">
            <div>
                <h2>Daftar Aset Tetap</h2>
                <span class="panel-subtitle">Total <?php echo count($assets); ?> aset operasional tercatat</span>
            </div>
        </div>
        <div class="panel-toolbar">
            <form method="get" class="toolbar-filter-form">
                <input type="text" name="q" value="<?php echo h($search); ?>" placeholder="Cari kode, nama, nomor, lokasi..." class="toolbar-input">
                <select name="status" class="toolbar-select">
                    <option value="">Semua status</option>
                    <?php foreach (['AKTIF','DIJUAL','RUSAK','TIDAK_AKTIF'] as $st): ?>
                        <option value="<?php echo h($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo h(str_replace('_', ' ', $st)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-secondary" type="submit">Filter</button>
                <?php if ($search !== '' || $statusFilter !== ''): ?>
                    <a class="btn-secondary" href="aset_tetap.php" style="text-decoration:none;">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="table-responsive">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Aset</th>
                        <th>Kategori</th>
                        <th>Tgl Perolehan</th>
                        <th class="money">Harga Beli</th>
                        <th class="center">Penyusutan / Thn</th>
                        <th class="money">Penyusutan / Bln</th>
                        <th class="money">Akumulasi Penyusutan</th>
                        <th class="money">Nilai Buku</th>
                        <th class="center">Status</th>
                        <th class="center" style="width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$assets): ?>
                    <tr><td colspan="11" class="empty-state">Belum ada aset tetap.</td></tr>
                <?php else: ?>
                    <?php foreach ($assets as $asset): ?>
                        <?php 
                            $persenFormat = number_format($asset['penyusutan_persen'], 2, ',', '.');
                            $persenFormat = str_replace(',00', '', $persenFormat);
                            $statusClass = 'badge-neutral';
                            if ($asset['status'] === 'AKTIF') {
                                $statusClass = 'badge-success';
                            } elseif ($asset['status'] === 'DIJUAL') {
                                $statusClass = 'badge-info';
                            } elseif ($asset['status'] === 'RUSAK') {
                                $statusClass = 'badge-danger';
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo h($asset['kode']); ?></strong><span class="muted"><?php echo h($asset['nomor_identitas']); ?></span></td>
                            <td><strong><?php echo h($asset['nama']); ?></strong><span class="muted"><?php echo h($asset['lokasi']); ?></span></td>
                            <td><?php echo h(str_replace('_', ' ', $asset['kategori'])); ?></td>
                            <td><?php echo h(date('d-m-Y', strtotime($asset['tanggal_perolehan']))); ?></td>
                            <td class="money"><?php echo rupiah($asset['harga_beli']); ?></td>
                            <td class="center"><span class="badge badge-neutral"><?php echo $persenFormat; ?>%</span></td>
                            <td class="money"><?php echo rupiah($asset['penyusutan_bulanan']); ?></td>
                            <td class="money"><?php echo rupiah($asset['penyusutan']); ?></td>
                            <td class="money"><strong><?php echo rupiah($asset['nilai_buku']); ?></strong><span class="muted"><?php echo $asset['umur_berjalan_bulan']; ?> bulan</span></td>
                            <td class="center"><span class="badge <?php echo $statusClass; ?>"><?php echo h(str_replace('_', ' ', $asset['status'])); ?></span></td>
                            <td class="center">
                                <div class="asset-actions" style="justify-content:center;">
                                    <button type="button" class="btn-secondary btn-sm" onclick='editAsset(<?php echo json_encode($asset, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Edit</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus aset ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $asset['id']; ?>">
                                        <button class="btn-danger btn-sm" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="liability-modal" id="assetModal">
    <div class="liability-modal-box">
        <form method="post">
            <div class="liability-modal-header">
                <div>
                    <h3 id="assetModalTitle">Tambah Aset Tetap</h3>
                    <p>Masukkan data aset operasional secara lengkap</p>
                </div>
                <button class="liability-modal-close" type="button" onclick="closeAssetModal()">&times;</button>
            </div>
            <div class="liability-modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="asset_id" value="0">
                
                <div class="liability-form-grid">
                    <div class="liability-field">
                        <label>Kode Aset</label>
                        <input type="text" name="kode" id="asset_kode" placeholder="Kosongkan untuk otomatis">
                    </div>
                    <div class="liability-field">
                        <label>Nama Aset <span>*</span></label>
                        <input type="text" name="nama" id="asset_nama" required placeholder="Contoh: Toyota Hilux Operasional">
                    </div>
                    
                    <div class="liability-field">
                        <label>Kategori <span>*</span></label>
                        <div style="display: flex; gap: 8px;">
                            <select name="kategori" id="asset_kategori" required style="flex: 1;">
                                <?php foreach ($allCats as $catVal => $catLabel): ?>
                                    <option value="<?php echo h($catVal); ?>"><?php echo h($catLabel); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-secondary" onclick="tambahKategori()" style="white-space: nowrap; height: 36px; margin: 0;">+ Baru</button>
                        </div>
                    </div>

                    <div class="liability-field">
                        <label>Nomor Identitas</label>
                        <input type="text" name="nomor_identitas" id="asset_nomor_identitas" placeholder="No. polisi / serial number / inventaris">
                    </div>
                    
                    <div class="liability-field">
                        <label>Tanggal Perolehan <span>*</span></label>
                        <input type="date" name="tanggal_perolehan" id="asset_tanggal" value="<?php echo h(date('Y-m-d')); ?>" required>
                    </div>
                    
                    <div class="liability-field">
                        <label>Harga Beli <span>*</span></label>
                        <input type="number" step="0.01" min="0" name="harga_beli" id="asset_harga_beli" required placeholder="0">
                    </div>
                    
                    <div class="liability-field">
                        <label>Umur Manfaat (Tahun) <span>*</span></label>
                        <input type="number" min="1" max="100" name="umur_manfaat_tahun" id="asset_umur" value="5" required>
                    </div>
                    
                    <div class="liability-field" style="background:#f4f7fb; padding:10px; border-radius:8px; border:1px solid #dce3eb;">
                        <label style="color:#0f4c81; margin-bottom: 2px;">Persentase Penyusutan (%/Tahun) <span>*</span></label>
                        <input type="number" step="0.01" min="0.01" max="100" name="persentase_penyusutan" id="asset_persen" value="20" required placeholder="Contoh: 20" style="margin-top: 6px;">
                    </div>
                    
                    <div class="liability-field">
                        <label>Status</label>
                        <select name="status" id="asset_status">
                            <option value="AKTIF">Aktif</option>
                            <option value="DIJUAL">Dijual</option>
                            <option value="RUSAK">Rusak</option>
                            <option value="TIDAK_AKTIF">Tidak Aktif</option>
                        </select>
                    </div>
                    
                    <div class="liability-field">
                        <label>Lokasi / Penanggung Jawab</label>
                        <input type="text" name="lokasi" id="asset_lokasi" placeholder="Contoh: Kantor / Budi">
                    </div>
                    
                    <div class="liability-field liability-field-full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="asset_keterangan" placeholder="Catatan aset, nomor dokumen pembelian, kondisi, dan sebagainya."></textarea>
                    </div>
                </div>
            </div>
            <div class="liability-modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAssetModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Aset</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('assetModal');

    window.openAssetModal = function () {
        document.getElementById('assetModalTitle').textContent = 'Tambah Aset Tetap';
        document.getElementById('asset_id').value = '0';
        document.getElementById('asset_kode').value = '';
        document.getElementById('asset_nama').value = '';
        document.getElementById('asset_kategori').value = 'KENDARAAN_OPERASIONAL';
        document.getElementById('asset_nomor_identitas').value = '';
        document.getElementById('asset_tanggal').value = "<?php echo h(date('Y-m-d')); ?>";
        document.getElementById('asset_harga_beli').value = '';
        document.getElementById('asset_umur').value = '5';
        document.getElementById('asset_persen').value = '20';
        document.getElementById('asset_status').value = 'AKTIF';
        document.getElementById('asset_lokasi').value = '';
        document.getElementById('asset_keterangan').value = '';
        modal.classList.add('show');
    };

    window.editAsset = function (asset) {
        document.getElementById('assetModalTitle').textContent = 'Edit Aset Tetap';
        document.getElementById('asset_id').value = asset.id || 0;
        document.getElementById('asset_kode').value = asset.kode || '';
        document.getElementById('asset_nama').value = asset.nama || '';
        
        let select = document.getElementById('asset_kategori');
        let catVal = asset.kategori || 'LAINNYA';
        
        let exists = Array.from(select.options).some(opt => opt.value === catVal);
        if (!exists && catVal) {
            let newOpt = document.createElement('option');
            newOpt.value = catVal;
            newOpt.text = catVal.replace(/_/g, ' ');
            select.add(newOpt);
        }
        select.value = catVal;
        
        document.getElementById('asset_nomor_identitas').value = asset.nomor_identitas || '';
        document.getElementById('asset_tanggal').value = asset.tanggal_perolehan || '';
        document.getElementById('asset_harga_beli').value = asset.harga_beli || asset.nilai_perolehan || 0;
        document.getElementById('asset_umur').value = asset.umur_manfaat_tahun || 5;
        document.getElementById('asset_persen').value = asset.persentase_penyusutan || 20;
        document.getElementById('asset_status').value = asset.status || 'AKTIF';
        document.getElementById('asset_lokasi').value = asset.lokasi || '';
        document.getElementById('asset_keterangan').value = asset.keterangan || '';
        modal.classList.add('show');
    };

    window.tambahKategori = function() {
        let inputCat = prompt("Masukkan nama kategori aset baru:");
        if (inputCat !== null && inputCat.trim() !== "") {
            let labelCat = inputCat.trim();
            let valueCat = labelCat.toUpperCase().replace(/\s+/g, '_');
            let select = document.getElementById('asset_kategori');
            let exists = false;
            
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === valueCat) {
                    exists = true;
                    select.selectedIndex = i;
                    break;
                }
            }
            
            if (!exists) {
                let opt = document.createElement('option');
                opt.value = valueCat;
                opt.text = labelCat.replace(/\b\w/g, l => l.toUpperCase());
                select.add(opt);
                select.value = valueCat;
            }
        }
    };

    window.closeAssetModal = function () {
        modal.classList.remove('show');
    };

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeAssetModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAssetModal();
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>