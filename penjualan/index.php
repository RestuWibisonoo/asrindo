<?php
$pageTitle = 'Penjualan';
$activeMenu = 'penjualan';
$breadcrumb = 'Transaksi / Penjualan';
require_once __DIR__ . '/../../includes/header_admin.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
    if ($action === 'delete') {
        if ($id) {
            $pdo->prepare('DELETE FROM penjualan WHERE id = :id')->execute([':id' => $id]);
            flash_set('success', 'Transaksi penjualan berhasil dihapus.');
        }
        redirect('/admin/penjualan/index.php');
    }

    $nomor = trim((string) ($_POST['nomor_penjualan'] ?? ''));
    $tanggal = trim((string) ($_POST['tanggal'] ?? ''));
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $alatBeratId = (int) ($_POST['alat_berat_id'] ?? 0);
    $hargaJual = max(0, (float) ($_POST['harga_jual'] ?? 0));
    $diskon = max(0, (float) ($_POST['diskon'] ?? 0));
    $hpp = max(0, (float) ($_POST['hpp'] ?? 0));
    $status = trim((string) ($_POST['status'] ?? 'DRAFT'));
    $keterangan = trim((string) ($_POST['keterangan'] ?? '')) ?: null;
    $allowedStatuses = ['DRAFT', 'CONFIRMED', 'PAID', 'CANCELLED'];
    if ($nomor === '' || $tanggal === '' || $customerId <= 0 || $alatBeratId <= 0 || !in_array($status, $allowedStatuses, true)) {
        flash_set('error', 'Nomor, tanggal, customer, unit, dan status penjualan wajib diisi dengan benar.');
        redirect('/admin/penjualan/index.php');
    }
    if ($diskon > $hargaJual) {
        flash_set('error', 'Diskon tidak boleh lebih besar dari harga jual.');
        redirect('/admin/penjualan/index.php');
    }

    try {
        $pdo->beginTransaction();
        $total = $hargaJual - $diskon;
        $admin = current_admin();
        $data = [':nomor' => $nomor, ':tanggal' => $tanggal, ':customer_id' => $customerId, ':total' => $total, ':status' => $status, ':keterangan' => $keterangan];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE penjualan SET nomor_penjualan = :nomor, tanggal = :tanggal, customer_id = :customer_id, total = :total, status = :status, keterangan = :keterangan, updated_at = NOW() WHERE id = :id');
            $data[':id'] = $id;
            $stmt->execute($data);
            $detail = $pdo->prepare('UPDATE penjualan_detail SET alat_berat_id = :alat_berat_id, harga_jual = :harga_jual, diskon = :diskon, hpp = :hpp, keterangan = :keterangan, updated_at = NOW() WHERE penjualan_id = :penjualan_id');
            $detail->execute([':alat_berat_id' => $alatBeratId, ':harga_jual' => $hargaJual, ':diskon' => $diskon, ':hpp' => $hpp, ':keterangan' => $keterangan, ':penjualan_id' => $id]);
            flash_set('success', 'Transaksi penjualan berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO penjualan (nomor_penjualan, tanggal, customer_id, total, status, keterangan, created_by, created_at, updated_at) VALUES (:nomor, :tanggal, :customer_id, :total, :status, :keterangan, :created_by, NOW(), NOW())');
            $data[':created_by'] = $admin['id'] ?? null;
            $stmt->execute($data);
            $saleId = (int) $pdo->lastInsertId();
            $detail = $pdo->prepare('INSERT INTO penjualan_detail (penjualan_id, alat_berat_id, harga_jual, diskon, hpp, keterangan, created_at, updated_at) VALUES (:penjualan_id, :alat_berat_id, :harga_jual, :diskon, :hpp, :keterangan, NOW(), NOW())');
            $detail->execute([':penjualan_id' => $saleId, ':alat_berat_id' => $alatBeratId, ':harga_jual' => $hargaJual, ':diskon' => $diskon, ':hpp' => $hpp, ':keterangan' => $keterangan]);
            flash_set('success', 'Transaksi penjualan berhasil ditambahkan.');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('error', 'Transaksi gagal disimpan. Pastikan nomor penjualan belum digunakan.');
    }
    redirect('/admin/penjualan/index.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(p.nomor_penjualan LIKE :q_nomor OR c.nama LIKE :q_customer OR ab.kode LIKE :q_kode OR ab.tipe LIKE :q_tipe)';
    $searchValue = '%' . $search . '%';
    $params[':q_nomor'] = $searchValue;
    $params[':q_customer'] = $searchValue;
    $params[':q_kode'] = $searchValue;
    $params[':q_tipe'] = $searchValue;
}
if ($statusFilter !== '' && $statusFilter !== 'all') {
    $conditions[] = 'p.status = :status';
    $params[':status'] = $statusFilter;
}
$sql = 'SELECT p.*, c.nama AS customer, pd.alat_berat_id, pd.harga_jual, pd.diskon, pd.hpp, pd.subtotal, pd.laba, ab.kode, ab.tipe FROM penjualan p INNER JOIN customer c ON c.id = p.customer_id LEFT JOIN penjualan_detail pd ON pd.penjualan_id = p.id LEFT JOIN alat_berat ab ON ab.id = pd.alat_berat_id';
if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
$sql .= ' ORDER BY p.tanggal DESC, p.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();
$customers = $pdo->query('SELECT id, kode, nama FROM customer ORDER BY nama ASC')->fetchAll();
$units = $pdo->query('SELECT id, kode, tipe, harga_jual_estimasi, harga_beli_idr FROM alat_berat ORDER BY kode ASC')->fetchAll();
$totalSales = (int) $pdo->query('SELECT COUNT(*) FROM penjualan')->fetchColumn();
$totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM penjualan WHERE status <> 'CANCELLED'")->fetchColumn();
$totalProfit = (float) $pdo->query("SELECT COALESCE(SUM(laba), 0) FROM penjualan_detail pd INNER JOIN penjualan p ON p.id = pd.penjualan_id WHERE p.status <> 'CANCELLED'")->fetchColumn();
$paidSales = (int) $pdo->query("SELECT COUNT(*) FROM penjualan WHERE status = 'PAID'")->fetchColumn();
$statuses = ['DRAFT', 'CONFIRMED', 'PAID', 'CANCELLED'];
?>

<div class="stat-cards"><div class="stat-card"><div class="label">Total Transaksi</div><div class="value"><?= $totalSales ?></div><div class="sub">Seluruh penjualan tercatat</div></div><div class="stat-card"><div class="label">Nilai Penjualan</div><div class="value"><?= rupiah($totalRevenue) ?></div><div class="sub">Tidak termasuk transaksi batal</div></div><div class="stat-card"><div class="label">Estimasi Laba</div><div class="value"><?= rupiah($totalProfit) ?></div><div class="sub">Berdasarkan HPP detail</div></div><div class="stat-card"><div class="label">Sudah Dibayar</div><div class="value"><?= $paidSales ?></div><div class="sub">Transaksi berstatus PAID</div></div></div>
 
<div class="card"><div class="toolbar"><div><div class="card-title">Transaksi Penjualan</div><div class="card-sub">Kelola penjualan unit alat berat dan detail nilai transaksinya</div></div><div class="toolbar-form"><button type="button" class="btn btn-amber btn-sm" data-open-modal="sales-modal">+ Tambah Penjualan</button><form method="get" class="toolbar-form-inline"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nomor, customer, unit..." aria-label="Cari penjualan"><select name="status" aria-label="Filter status"><option value="all">Semua status</option><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-amber btn-sm">Filter</button><a href="<?= BASE_URL ?>/admin/penjualan/index.php" class="btn btn-outline-dark btn-sm">Reset</a></form></div></div>
<?php if (empty($sales)): ?><div class="empty-state"><div class="icon">&#9881;</div><h3 style="margin-bottom:6px;">Belum ada transaksi penjualan</h3><p style="max-width:44ch; margin:0 auto;">Tidak ada transaksi yang cocok dengan pencarian saat ini.</p></div><?php else: ?><table class="data-table inventory-table"><thead><tr><th>Nomor / Tanggal</th><th>Customer</th><th>Unit</th><th>Status</th><th>Total</th><th>Laba</th><th style="width:90px;">Aksi</th></tr></thead><tbody><?php foreach ($sales as $sale): ?><tr><td><strong><?= e($sale['nomor_penjualan']) ?></strong><br><span class="muted"><?= e(tanggal_indo($sale['tanggal'])) ?></span></td><td><?= e($sale['customer']) ?></td><td><strong><?= e($sale['kode'] ?: '-') ?></strong><br><span class="muted"><?= e($sale['tipe'] ?: '-') ?></span></td><td><span class="badge <?= status_badge_class($sale['status']) ?>"><?= e($sale['status']) ?></span></td><td><?= rupiah($sale['total']) ?></td><td><?= rupiah($sale['laba'] ?? 0) ?></td><td><div class="mini-actions"><a href="<?= BASE_URL ?>/admin/penjualan/detail.php?id=<?= (int) $sale['id'] ?>" class="mini-link">Lihat</a><button type="button" class="mini-btn mini-btn-edit" data-edit-sale='<?= json_encode($sale, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Edit</button><form method="post" action="<?= BASE_URL ?>/admin/penjualan/index.php" class="inline-delete-form" onsubmit="return confirm('Hapus transaksi penjualan ini?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $sale['id'] ?>"><button type="submit" class="mini-btn mini-btn-delete">Hapus</button></form></div></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>

<div class="modal-backdrop" id="sales-modal" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="sales-title"><div class="modal-header"><h3 id="sales-title">Tambah Penjualan</h3><button type="button" class="modal-close" data-close-modal="sales-modal" aria-label="Tutup">×</button></div><form id="sales-form" method="post" action="<?= BASE_URL ?>/admin/penjualan/index.php"><input type="hidden" name="action" id="sales-action" value="create"><input type="hidden" name="id" id="sales-id" value=""><div class="modal-grid"><div class="field"><label for="sales-number">Nomor Penjualan</label><input id="sales-number" name="nomor_penjualan" type="text" placeholder="PJ-20260903-0001" required></div><div class="field"><label for="sales-date">Tanggal</label><input id="sales-date" name="tanggal" type="date" value="<?= date('Y-m-d') ?>" required></div><div class="field field-full"><label for="sales-customer">Customer</label><select id="sales-customer" name="customer_id" required><option value="">Pilih customer</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"><?= e($customer['kode'] . ' - ' . $customer['nama']) ?></option><?php endforeach; ?></select></div><div class="field field-full"><label for="sales-unit">Unit Alat Berat</label><select id="sales-unit" name="alat_berat_id" required><option value="">Pilih unit</option><?php foreach ($units as $unit): ?><option value="<?= (int) $unit['id'] ?>"><?= e($unit['kode'] . ' - ' . $unit['tipe']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="sales-price">Harga Jual</label><input id="sales-price" name="harga_jual" type="number" min="0" step="0.01" value="0" required></div><div class="field"><label for="sales-discount">Diskon</label><input id="sales-discount" name="diskon" type="number" min="0" step="0.01" value="0"></div><div class="field"><label for="sales-hpp">HPP</label><input id="sales-hpp" name="hpp" type="number" min="0" step="0.01" value="0"></div><div class="field"><label for="sales-status">Status</label><select id="sales-status" name="status"><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>"><?= e($option) ?></option><?php endforeach; ?></select></div><div class="field field-full"><label for="sales-note">Keterangan</label><textarea id="sales-note" name="keterangan" rows="3"></textarea></div></div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="sales-modal">Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form></div></div>

<script>
(function () { const modal = document.getElementById('sales-modal'); const form = document.getElementById('sales-form'); const action = document.getElementById('sales-action'); const id = document.getElementById('sales-id'); const title = document.getElementById('sales-title'); if (!modal || !form) return; const fields = { nomor_penjualan: document.getElementById('sales-number'), tanggal: document.getElementById('sales-date'), customer_id: document.getElementById('sales-customer'), alat_berat_id: document.getElementById('sales-unit'), harga_jual: document.getElementById('sales-price'), diskon: document.getElementById('sales-discount'), hpp: document.getElementById('sales-hpp'), status: document.getElementById('sales-status'), keterangan: document.getElementById('sales-note') }; const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); form.reset(); action.value = 'create'; id.value = ''; title.textContent = 'Tambah Penjualan'; fields.tanggal.value = '<?= date('Y-m-d') ?>'; }; const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }; document.querySelectorAll('[data-open-modal="sales-modal"]').forEach((button) => button.addEventListener('click', () => { close(); open(); })); document.querySelectorAll('[data-close-modal="sales-modal"]').forEach((button) => button.addEventListener('click', close)); document.querySelectorAll('[data-edit-sale]').forEach((button) => button.addEventListener('click', () => { const data = JSON.parse(button.dataset.editSale); action.value = 'update'; id.value = data.id; title.textContent = 'Edit Penjualan'; Object.keys(fields).forEach((name) => { fields[name].value = data[name] ?? (name === 'tanggal' ? '<?= date('Y-m-d') ?>' : 0); }); open(); })); modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); }); })();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>
