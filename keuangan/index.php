<?php
$pageTitle = 'Kas & Keuangan';
$activeMenu = 'keuangan';
$breadcrumb = 'Kas & Keuangan';
require_once __DIR__ . '/../../includes/header_admin.php';
$pdo = getDB();
$paymentMethods = ['CASH', 'TRANSFER', 'GIRO', 'KARTU', 'LAINNYA'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
    if ($action === 'delete') {
        if ($id) {
            $pdo->prepare('DELETE FROM transaksi_keuangan WHERE id = :id')->execute([':id' => $id]);
            flash_set('success', 'Transaksi keuangan berhasil dihapus.');
        }
        redirect('/admin/keuangan/index.php');
    }
    $number = trim((string) ($_POST['nomor_transaksi'] ?? ''));
    $date = trim((string) ($_POST['tanggal'] ?? ''));
    $categoryId = (int) ($_POST['kategori_id'] ?? 0);
    $nominal = max(0, (float) ($_POST['nominal'] ?? 0));
    $method = trim((string) ($_POST['metode_pembayaran'] ?? 'TRANSFER'));
    $reference = trim((string) ($_POST['referensi'] ?? '')) ?: null;
    $note = trim((string) ($_POST['keterangan'] ?? '')) ?: null;
    if ($number === '' || $date === '' || $categoryId <= 0 || $nominal <= 0 || !in_array($method, $paymentMethods, true)) {
        flash_set('error', 'Nomor, tanggal, kategori, nominal, dan metode pembayaran wajib diisi dengan benar.');
        redirect('/admin/keuangan/index.php');
    }
    $data = [':number' => $number, ':date' => $date, ':category' => $categoryId, ':nominal' => $nominal, ':method' => $method, ':reference' => $reference, ':note' => $note];
    try {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE transaksi_keuangan SET nomor_transaksi = :number, tanggal = :date, kategori_id = :category, nominal = :nominal, metode_pembayaran = :method, referensi = :reference, keterangan = :note, updated_at = NOW() WHERE id = :id');
            $data[':id'] = $id; $stmt->execute($data); flash_set('success', 'Transaksi keuangan berhasil diperbarui.');
        } else {
            $admin = current_admin();
            $stmt = $pdo->prepare('INSERT INTO transaksi_keuangan (nomor_transaksi, tanggal, kategori_id, nominal, metode_pembayaran, referensi, keterangan, created_by, created_at, updated_at) VALUES (:number, :date, :category, :nominal, :method, :reference, :note, :created_by, NOW(), NOW())');
            $data[':created_by'] = $admin['id'] ?? null; $stmt->execute($data); flash_set('success', 'Transaksi keuangan berhasil ditambahkan.');
        }
    } catch (PDOException $exception) {
        flash_set('error', 'Transaksi gagal disimpan. Pastikan nomor transaksi belum digunakan.');
    }
    redirect('/admin/keuangan/index.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$typeFilter = trim((string) ($_GET['tipe'] ?? 'all'));
$monthFilter = trim((string) ($_GET['bulan'] ?? ''));
$conditions = [];
$params = [];
if ($search !== '') { $conditions[] = '(tk.nomor_transaksi LIKE :q_number OR tk.referensi LIKE :q_reference OR tk.keterangan LIKE :q_note OR kk.nama LIKE :q_category)'; $value = '%' . $search . '%'; $params[':q_number'] = $value; $params[':q_reference'] = $value; $params[':q_note'] = $value; $params[':q_category'] = $value; }
if ($typeFilter !== '' && $typeFilter !== 'all') { $conditions[] = 'kk.tipe = :tipe'; $params[':tipe'] = $typeFilter; }
if ($monthFilter !== '') { $conditions[] = "DATE_FORMAT(tk.tanggal, '%Y-%m') = :bulan"; $params[':bulan'] = $monthFilter; }
$sql = 'SELECT tk.*, kk.kode AS kategori_kode, kk.nama AS kategori, kk.tipe, a.nama AS dibuat_oleh FROM transaksi_keuangan tk INNER JOIN kategori_keuangan kk ON kk.id = tk.kategori_id LEFT JOIN admin a ON a.id = tk.created_by';
if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
$sql .= ' ORDER BY tk.tanggal DESC, tk.id DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $transactions = $stmt->fetchAll();
$globalTransactions = [];
if ($typeFilter === 'all' || $typeFilter === 'PENDAPATAN') {
    $salesConditions = ["p.status <> 'CANCELLED'"];
    $salesParams = [];
    if ($search !== '') { $salesConditions[] = '(p.nomor_penjualan LIKE :sale_search OR p.keterangan LIKE :sale_note)'; $salesParams[':sale_search'] = '%' . $search . '%'; $salesParams[':sale_note'] = '%' . $search . '%'; }
    if ($monthFilter !== '') { $salesConditions[] = "DATE_FORMAT(p.tanggal, '%Y-%m') = :sale_month"; $salesParams[':sale_month'] = $monthFilter; }
    $salesStmt = $pdo->prepare('SELECT p.id, p.nomor_penjualan, p.tanggal, p.total, p.status, p.keterangan FROM penjualan p WHERE ' . implode(' AND ', $salesConditions) . ' ORDER BY p.tanggal DESC, p.id DESC');
    $salesStmt->execute($salesParams);
    foreach ($salesStmt->fetchAll() as $sale) {
        $globalTransactions[] = ['id' => $sale['id'], 'nomor_transaksi' => $sale['nomor_penjualan'], 'tanggal' => $sale['tanggal'], 'kategori_kode' => 'PJ', 'kategori' => 'Penjualan', 'tipe' => 'PENDAPATAN', 'metode_pembayaran' => 'Penjualan', 'referensi' => $sale['status'], 'nominal' => $sale['total'], 'keterangan' => $sale['keterangan'], 'dibuat_oleh' => '-', 'is_global' => true];
    }
}
if ($typeFilter === 'all' || $typeFilter === 'PENGELUARAN') {
    $purchaseConditions = ["po.status = 'SELESAI'"];
    $purchaseParams = [];
    if ($search !== '') { $purchaseConditions[] = '(po.nomor_pembelian LIKE :purchase_search OR po.keterangan LIKE :purchase_note)'; $purchaseParams[':purchase_search'] = '%' . $search . '%'; $purchaseParams[':purchase_note'] = '%' . $search . '%'; }
    if ($monthFilter !== '') { $purchaseConditions[] = "DATE_FORMAT(po.tanggal, '%Y-%m') = :purchase_month"; $purchaseParams[':purchase_month'] = $monthFilter; }
    $purchaseStmt = $pdo->prepare('SELECT po.id, po.nomor_pembelian, po.tanggal, po.total, po.status, po.keterangan FROM pembelian_sparepart po WHERE ' . implode(' AND ', $purchaseConditions) . ' ORDER BY po.tanggal DESC, po.id DESC');
    $purchaseStmt->execute($purchaseParams);
    foreach ($purchaseStmt->fetchAll() as $purchase) {
        $globalTransactions[] = ['id' => $purchase['id'], 'nomor_transaksi' => $purchase['nomor_pembelian'], 'tanggal' => $purchase['tanggal'], 'kategori_kode' => 'PO', 'kategori' => 'Pembelian Sparepart', 'tipe' => 'PENGELUARAN', 'metode_pembayaran' => 'Pembelian', 'referensi' => $purchase['status'], 'nominal' => $purchase['total'], 'keterangan' => $purchase['keterangan'], 'dibuat_oleh' => '-', 'is_global' => true];
    }
    $maintenanceConditions = [];
    $maintenanceParams = [];
    if ($search !== '') { $maintenanceConditions[] = 'ap.keterangan LIKE :maintenance_note'; $maintenanceParams[':maintenance_note'] = '%' . $search . '%'; }
    if ($monthFilter !== '') { $maintenanceConditions[] = "DATE_FORMAT(ap.tanggal, '%Y-%m') = :maintenance_month"; $maintenanceParams[':maintenance_month'] = $monthFilter; }
    $maintenanceStmt = $pdo->prepare('SELECT ap.id, ap.tanggal, ap.total, ap.keterangan FROM alat_berat_perawatan ap' . ($maintenanceConditions ? ' WHERE ' . implode(' AND ', $maintenanceConditions) : '') . ' ORDER BY ap.tanggal DESC, ap.id DESC');
    $maintenanceStmt->execute($maintenanceParams);
    foreach ($maintenanceStmt->fetchAll() as $maintenance) {
        $globalTransactions[] = ['id' => $maintenance['id'], 'nomor_transaksi' => 'PRW-' . $maintenance['id'], 'tanggal' => $maintenance['tanggal'], 'kategori_kode' => 'PRW', 'kategori' => 'Perawatan Alat Berat', 'tipe' => 'PENGELUARAN', 'metode_pembayaran' => 'Perawatan', 'referensi' => '-', 'nominal' => $maintenance['total'], 'keterangan' => $maintenance['keterangan'], 'dibuat_oleh' => '-', 'is_global' => true];
    }
}
$transactions = array_merge($transactions, $globalTransactions);
usort($transactions, static fn ($left, $right) => [$right['tanggal'], (int) $right['id']] <=> [$left['tanggal'], (int) $left['id']]);
$categories = $pdo->query('SELECT id, kode, nama, tipe FROM kategori_keuangan ORDER BY tipe ASC, nama ASC')->fetchAll();
$totalIncome = (float) $pdo->query("SELECT COALESCE(SUM(tk.nominal), 0) FROM transaksi_keuangan tk INNER JOIN kategori_keuangan kk ON kk.id = tk.kategori_id WHERE kk.tipe = 'PENDAPATAN'")->fetchColumn();
$totalExpense = (float) $pdo->query("SELECT COALESCE(SUM(tk.nominal), 0) FROM transaksi_keuangan tk INNER JOIN kategori_keuangan kk ON kk.id = tk.kategori_id WHERE kk.tipe = 'PENGELUARAN'")->fetchColumn();
$totalIncome += (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM penjualan WHERE status <> 'CANCELLED'")->fetchColumn();
$totalExpense += (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pembelian_sparepart WHERE status = 'SELESAI'")->fetchColumn();
$totalExpense += (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM alat_berat_perawatan')->fetchColumn();
$totalTransactions = (int) $pdo->query('SELECT COUNT(*) FROM transaksi_keuangan')->fetchColumn();
$netBalance = $totalIncome - $totalExpense;
?>

<div class="stat-cards"><div class="stat-card"><div class="label">Total Transaksi</div><div class="value"><?= $totalTransactions ?></div><div class="sub">Seluruh arus kas tercatat</div></div><div class="stat-card"><div class="label">Pendapatan</div><div class="value"><?= rupiah($totalIncome) ?></div><div class="sub">Kategori pendapatan</div></div><div class="stat-card"><div class="label">Pengeluaran</div><div class="value"><?= rupiah($totalExpense) ?></div><div class="sub">Kategori pengeluaran</div></div><div class="stat-card"><div class="label">Saldo Bersih</div><div class="value"><?= rupiah($netBalance) ?></div><div class="sub">Pendapatan dikurangi pengeluaran</div></div></div>
<div class="card"><div class="toolbar"><div><div class="card-title">Transaksi Kas &amp; Keuangan</div><div class="card-sub">Catat dan pantau pendapatan serta pengeluaran perusahaan</div></div><div class="toolbar-form"><button type="button" class="btn btn-amber btn-sm" data-open-modal="finance-modal">+ Tambah Transaksi</button><form method="get" class="toolbar-form-inline"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nomor, kategori, referensi..." aria-label="Cari transaksi"><select name="tipe" aria-label="Filter tipe"><option value="all">Semua tipe</option><option value="PENDAPATAN" <?= $typeFilter === 'PENDAPATAN' ? 'selected' : '' ?>>Pendapatan</option><option value="PENGELUARAN" <?= $typeFilter === 'PENGELUARAN' ? 'selected' : '' ?>>Pengeluaran</option></select><input type="month" name="bulan" value="<?= e($monthFilter) ?>" aria-label="Filter bulan"><button type="submit" class="btn btn-amber btn-sm">Filter</button><a href="<?= BASE_URL ?>/admin/keuangan/index.php" class="btn btn-outline-dark btn-sm">Reset</a></form></div></div>
<?php if (empty($transactions)): ?><div class="empty-state"><div class="icon">&#9881;</div><h3 style="margin-bottom:6px;">Belum ada transaksi keuangan</h3><p style="max-width:44ch; margin:0 auto;">Tidak ada transaksi yang cocok dengan filter saat ini.</p></div><?php else: ?><table class="data-table inventory-table"><thead><tr><th>Nomor / Tanggal</th><th>Kategori</th><th>Tipe</th><th>Metode</th><th>Referensi</th><th>Nominal</th><th style="width:90px;">Aksi</th></tr></thead><tbody><?php foreach ($transactions as $transaction): ?><tr><td><strong><?= e($transaction['nomor_transaksi']) ?></strong><br><span class="muted"><?= e(tanggal_indo($transaction['tanggal'])) ?></span></td><td><?= e($transaction['kategori_kode']) ?><br><span class="muted"><?= e($transaction['kategori']) ?></span></td><td><span class="badge <?= $transaction['tipe'] === 'PENDAPATAN' ? 'badge-ok' : 'badge-danger' ?>"><?= e($transaction['tipe']) ?></span></td><td><?= e($transaction['metode_pembayaran'] ?: '-') ?></td><td><?= e($transaction['referensi'] ?: '-') ?></td><td><strong><?= rupiah($transaction['nominal']) ?></strong></td><td><?php if (!($transaction['is_global'] ?? false)): ?><div class="mini-actions"><a href="<?= BASE_URL ?>/admin/keuangan/detail.php?id=<?= (int) $transaction['id'] ?>" class="mini-link">Lihat</a><button type="button" class="mini-btn mini-btn-edit" data-edit-finance='<?= json_encode($transaction, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Edit</button><form method="post" action="<?= BASE_URL ?>/admin/keuangan/index.php" class="inline-delete-form" onsubmit="return confirm('Hapus transaksi keuangan ini?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $transaction['id'] ?>"><button type="submit" class="mini-btn mini-btn-delete">Hapus</button></form></div><?php else: ?><span class="muted">Data terhubung</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>

<div class="modal-backdrop" id="finance-modal" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="finance-title"><div class="modal-header"><h3 id="finance-title">Tambah Transaksi Keuangan</h3><button type="button" class="modal-close" data-close-modal="finance-modal" aria-label="Tutup">Ã—</button></div><form id="finance-form" method="post" action="<?= BASE_URL ?>/admin/keuangan/index.php"><input type="hidden" name="action" id="finance-action" value="create"><input type="hidden" name="id" id="finance-id" value=""><div class="modal-grid"><div class="field"><label for="finance-number">Nomor Transaksi</label><input id="finance-number" name="nomor_transaksi" placeholder="TRX-20260903-0001" required></div><div class="field"><label for="finance-date">Tanggal</label><input id="finance-date" name="tanggal" type="date" value="<?= date('Y-m-d') ?>" required></div><div class="field field-full"><label for="finance-category">Kategori Keuangan</label><select id="finance-category" name="kategori_id" required><option value="">Pilih kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e($category['tipe'] . ' - ' . $category['kode'] . ' - ' . $category['nama']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="finance-amount">Nominal</label><input id="finance-amount" name="nominal" type="number" min="0.01" step="0.01" required></div><div class="field"><label for="finance-method">Metode Pembayaran</label><select id="finance-method" name="metode_pembayaran"><?php foreach ($paymentMethods as $method): ?><option value="<?= e($method) ?>"><?= e($method) ?></option><?php endforeach; ?></select></div><div class="field"><label for="finance-reference">Referensi</label><input id="finance-reference" name="referensi"></div><div class="field field-full"><label for="finance-note">Keterangan</label><textarea id="finance-note" name="keterangan" rows="3"></textarea></div></div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="finance-modal">Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form></div></div>

<script>
(function () { const modal = document.getElementById('finance-modal'); const form = document.getElementById('finance-form'); const action = document.getElementById('finance-action'); const id = document.getElementById('finance-id'); const title = document.getElementById('finance-title'); const fields = { nomor_transaksi: document.getElementById('finance-number'), tanggal: document.getElementById('finance-date'), kategori_id: document.getElementById('finance-category'), nominal: document.getElementById('finance-amount'), metode_pembayaran: document.getElementById('finance-method'), referensi: document.getElementById('finance-reference'), keterangan: document.getElementById('finance-note') }; if (!modal || !form) return; const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); form.reset(); action.value = 'create'; id.value = ''; title.textContent = 'Tambah Transaksi Keuangan'; fields.tanggal.value = '<?= date('Y-m-d') ?>'; }; const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }; document.querySelectorAll('[data-open-modal="finance-modal"]').forEach((button) => button.addEventListener('click', () => { close(); open(); })); document.querySelectorAll('[data-close-modal="finance-modal"]').forEach((button) => button.addEventListener('click', close)); document.querySelectorAll('[data-edit-finance]').forEach((button) => button.addEventListener('click', () => { const data = JSON.parse(button.dataset.editFinance); action.value = 'update'; id.value = data.id; title.textContent = 'Edit Transaksi Keuangan'; Object.keys(fields).forEach((name) => { fields[name].value = data[name] ?? ''; }); open(); })); modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); }); })();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>

