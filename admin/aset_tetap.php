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
    .asset-page { padding: 4px 0 30px; }
    .asset-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
    .asset-toolbar-left { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .asset-toolbar form { display:flex; gap:8px; flex-wrap:wrap; }
    .asset-toolbar input, .asset-toolbar select, .asset-modal input, .asset-modal select, .asset-modal textarea {
        box-sizing:border-box; width:100%; border:1px solid #d7dee7; border-radius:8px; padding:9px 10px; background:#fff; font:inherit;
    }
    .asset-toolbar input { width:260px; }
    .asset-toolbar select { width:150px; }
    .asset-btn { border:0; border-radius:8px; padding:9px 13px; cursor:pointer; font-weight:600; }
    .asset-btn-primary { background:#0f4c81; color:#fff; }
    .asset-btn-secondary { background:#eef2f6; color:#263445; }
    .asset-btn-danger { background:#b42318; color:#fff; }
    .asset-btn-small { padding:7px 9px; font-size:12px; }
    .asset-summary { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-bottom:16px; }
    .asset-summary-card { background:#fff; border:1px solid #dce3eb; border-radius:10px; padding:14px; box-shadow:0 1px 2px rgba(0,0,0,.03); }
    .asset-summary-card .label { font-size:12px; color:#657386; margin-bottom:5px; }
    .asset-summary-card .value { font-size:19px; font-weight:700; color:#172334; }
    .asset-table-wrap { overflow:auto; border:1px solid #dce3eb; border-radius:10px; background:#fff; }
    .asset-table { width:100%; min-width:1120px; border-collapse:collapse; }
    .asset-table th, .asset-table td { padding:10px 11px; border-bottom:1px solid #edf1f5; vertical-align:top; text-align:left; }
    .asset-table th { background:#f7f9fb; color:#425166; font-size:12px; white-space:nowrap; }
    .asset-table td { font-size:13px; color:#263445; }
    .asset-table tr:last-child td { border-bottom:0; }
    .asset-money { white-space:nowrap; text-align:right !important; }
    .asset-center { text-align:center !important; }
    .asset-muted { color:#7a8798; font-size:11px; }
    .asset-status { display:inline-flex; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; }
    .asset-status-active { background:#e7f6ec; color:#16743a; }
    .asset-status-other { background:#eef2f6; color:#5c6878; }
    .asset-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .asset-empty { padding:32px; text-align:center; color:#778396; }
    .asset-alert { border-radius:8px; padding:10px 12px; margin-bottom:14px; font-size:13px; }
    .asset-alert-success { background:#eaf7ee; color:#176b36; border:1px solid #bfe5ca; }
    .asset-alert-error { background:#fff0ef; color:#a32920; border:1px solid #f1c0bc; }
    .asset-modal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.48); display:none; align-items:center; justify-content:center; z-index:9999; padding:18px; }
    .asset-modal-backdrop.show { display:flex; }
    .asset-modal { width:min(780px, 100%); max-height:calc(100vh - 36px); overflow:auto; background:#fff; border-radius:12px; box-shadow:0 20px 50px rgba(0,0,0,.2); }
    .asset-modal-header { display:flex; justify-content:space-between; align-items:center; padding:15px 18px; border-bottom:1px solid #e5e9ef; position:sticky; top:0; background:#fff; z-index:2; }
    .asset-modal-header h2 { margin:0; font-size:18px; }
    .asset-modal-close { border:0; background:transparent; font-size:26px; cursor:pointer; color:#64748b; }
    .asset-modal-body { padding:18px; }
    .asset-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:13px; }
    .asset-form-group.full { grid-column:1 / -1; }
    .asset-form-group label { display:block; font-size:12px; font-weight:700; color:#435166; margin-bottom:5px; }
    .asset-form-group textarea { min-height:88px; resize:vertical; }
    .asset-modal-footer { display:flex; justify-content:flex-end; gap:8px; padding:14px 18px; border-top:1px solid #e5e9ef; }
    .asset-delete-form { display:inline; }
    @media (max-width: 800px) {
        .asset-summary { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .asset-form-grid { grid-template-columns:1fr; }
        .asset-form-group.full { grid-column:auto; }
        .asset-toolbar input, .asset-toolbar select { width:100%; }
        .asset-toolbar form { width:100%; }
    }
    @media (max-width: 480px) { .asset-summary { grid-template-columns:1fr; } }
</style>

<div class="asset-page">
    <div class="admin-page-header">
        <div>
            <h1>Aset Tetap</h1>
            <div style="font-size:12px;color:#718096;margin-top:3px;">Aset operasional perusahaan seperti mobil operasional, komputer, mesin, dan peralatan kantor.</div>
        </div>
    </div>

    <?php if ($flashMessage !== ''): ?>
        <div class="asset-alert asset-alert-<?php echo $flashType === 'error' ? 'error' : 'success'; ?>">
            <?php echo h($flashMessage); ?>
        </div>
    <?php endif; ?>

    <div class="asset-summary">
        <div class="asset-summary-card"><div class="label">Jumlah Aset Aktif</div><div class="value"><?php echo number_format($activeCount, 0, ',', '.'); ?> aset</div></div>
        <div class="asset-summary-card"><div class="label">Total Harga Beli</div><div class="value"><?php echo rupiah($totalHargaBeli); ?></div></div>
        <div class="asset-summary-card"><div class="label">Akumulasi Penyusutan</div><div class="value"><?php echo rupiah($totalPenyusutan); ?></div></div>
        <div class="asset-summary-card"><div class="label">Nilai Buku Aset</div><div class="value"><?php echo rupiah($totalNilaiBuku); ?></div></div>
    </div>

    <div class="asset-toolbar">
        <div class="asset-toolbar-left">
            <form method="get">
                <input type="text" name="q" value="<?php echo h($search); ?>" placeholder="Cari kode, nama, nomor, lokasi...">
                <select name="status">
                    <option value="">Semua status</option>
                    <?php foreach (['AKTIF','DIJUAL','RUSAK','TIDAK_AKTIF'] as $st): ?>
                        <option value="<?php echo h($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo h(str_replace('_', ' ', $st)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="asset-btn asset-btn-secondary" type="submit">Filter</button>
                <?php if ($search !== '' || $statusFilter !== ''): ?><a class="asset-btn asset-btn-secondary" href="aset_tetap.php" style="text-decoration:none;">Reset</a><?php endif; ?>
            </form>
        </div>
        <button class="asset-btn asset-btn-primary" type="button" onclick="openAssetModal()">+ Tambah Aset Tetap</button>
    </div>

    <div class="admin-card">
        <div class="asset-table-wrap">
            <table class="asset-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Aset</th>
                        <th>Kategori</th>
                        <th>Tgl Perolehan</th>
                        <th>Harga Beli</th>
                        <th class="asset-center">Penyusutan / Tahun</th>
                        <th class="asset-money">Penyusutan / Bulan</th>
                        <th class="asset-money">Akumulasi Penyusutan</th>
                        <th class="asset-money">Nilai Buku</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$assets): ?>
                    <tr><td colspan="11" class="asset-empty">Belum ada aset tetap.</td></tr>
                <?php else: ?>
                    <?php foreach ($assets as $asset): ?>
                        <?php 
                            $persenFormat = number_format($asset['penyusutan_persen'], 2, ',', '.');
                            $persenFormat = str_replace(',00', '', $persenFormat);
                        ?>
                        <tr>
                            <td><strong><?php echo h($asset['kode']); ?></strong><div class="asset-muted"><?php echo h($asset['nomor_identitas']); ?></div></td>
                            <td><strong><?php echo h($asset['nama']); ?></strong><div class="asset-muted"><?php echo h($asset['lokasi']); ?></div></td>
                            <td><?php echo h(str_replace('_', ' ', $asset['kategori'])); ?></td>
                            <td><?php echo h(date('d-m-Y', strtotime($asset['tanggal_perolehan']))); ?></td>
                            <td class="asset-money"><?php echo rupiah($asset['harga_beli']); ?></td>
                            <td class="asset-center"><span style="background:#f1f5f9; padding:3px 6px; border-radius:4px; font-weight:bold; color:#475569;"><?php echo $persenFormat; ?>%</span></td>
                            <td class="asset-money"><?php echo rupiah($asset['penyusutan_bulanan']); ?></td>
                            <td class="asset-money"><?php echo rupiah($asset['penyusutan']); ?></td>
                            <td class="asset-money"><strong><?php echo rupiah($asset['nilai_buku']); ?></strong><div class="asset-muted"><?php echo $asset['umur_berjalan_bulan']; ?> bulan</div></td>
                            <td><span class="asset-status <?php echo $asset['status'] === 'AKTIF' ? 'asset-status-active' : 'asset-status-other'; ?>"><?php echo h(str_replace('_', ' ', $asset['status'])); ?></span></td>
                            <td>
                                <div class="asset-actions">
                                    <button type="button" class="asset-btn asset-btn-secondary asset-btn-small" onclick='editAsset(<?php echo json_encode($asset, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Edit</button>
                                    <form method="post" class="asset-delete-form" onsubmit="return confirm('Hapus aset ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $asset['id']; ?>">
                                        <button class="asset-btn asset-btn-danger asset-btn-small" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="asset-modal-backdrop" id="assetModal">
    <div class="asset-modal" role="dialog" aria-modal="true" aria-labelledby="assetModalTitle">
        <form method="post">
            <div class="asset-modal-header">
                <h2 id="assetModalTitle">Tambah Aset Tetap</h2>
                <button class="asset-modal-close" type="button" onclick="closeAssetModal()">&times;</button>
            </div>
            <div class="asset-modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="asset_id" value="0">
                <div class="asset-form-grid">
                    <div class="asset-form-group">
                        <label>Kode Aset</label>
                        <input type="text" name="kode" id="asset_kode" placeholder="Kosongkan untuk otomatis">
                    </div>
                    <div class="asset-form-group">
                        <label>Nama Aset *</label>
                        <input type="text" name="nama" id="asset_nama" required placeholder="Contoh: Toyota Hilux Operasional">
                    </div>
                    
                    <div class="asset-form-group">
                        <label>Kategori *</label>
                        <div style="display: flex; gap: 8px;">
                            <select name="kategori" id="asset_kategori" required style="flex: 1;">
                                <?php foreach ($allCats as $catVal => $catLabel): ?>
                                    <option value="<?php echo h($catVal); ?>"><?php echo h($catLabel); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="asset-btn asset-btn-secondary" onclick="tambahKategori()" style="white-space: nowrap;">+ Baru</button>
                        </div>
                    </div>

                    <div class="asset-form-group">
                        <label>Nomor Identitas</label>
                        <input type="text" name="nomor_identitas" id="asset_nomor_identitas" placeholder="No. polisi / serial number / inventaris">
                    </div>
                    <div class="asset-form-group">
                        <label>Tanggal Perolehan *</label>
                        <input type="date" name="tanggal_perolehan" id="asset_tanggal" value="<?php echo h(date('Y-m-d')); ?>" required>
                    </div>
                    <div class="asset-form-group">
                        <label>Harga Beli *</label>
                        <input type="number" step="0.01" min="0" name="harga_beli" id="asset_harga_beli" required placeholder="0">
                    </div>
                    <div class="asset-form-group">
                        <label>Umur Manfaat (Tahun) *</label>
                        <input type="number" min="1" max="100" name="umur_manfaat_tahun" id="asset_umur" value="5" required>
                    </div>
                    <div class="asset-form-group" style="background:#f4f7fb; padding:10px; border-radius:8px; border:1px solid #dce3eb;">
                        <label style="color:#0f4c81;">Persentase Penyusutan (%/Tahun) *</label>
                        <input type="number" step="0.01" min="0.01" max="100" name="persentase_penyusutan" id="asset_persen" value="20" required placeholder="Contoh: 20">
                    </div>
                    <div class="asset-form-group">
                        <label>Status</label>
                        <select name="status" id="asset_status">
                            <option value="AKTIF">Aktif</option>
                            <option value="DIJUAL">Dijual</option>
                            <option value="RUSAK">Rusak</option>
                            <option value="TIDAK_AKTIF">Tidak Aktif</option>
                        </select>
                    </div>
                    <div class="asset-form-group">
                        <label>Lokasi / Penanggung Jawab</label>
                        <input type="text" name="lokasi" id="asset_lokasi" placeholder="Contoh: Kantor / Budi">
                    </div>
                    <div class="asset-form-group full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="asset_keterangan" placeholder="Catatan aset, nomor dokumen pembelian, kondisi, dan sebagainya."></textarea>
                    </div>
                </div>
            </div>
            <div class="asset-modal-footer">
                <button type="button" class="asset-btn asset-btn-secondary" onclick="closeAssetModal()">Batal</button>
                <button type="submit" class="asset-btn asset-btn-primary">Simpan Aset</button>
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