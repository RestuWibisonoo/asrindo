<?php
$pageTitle = 'Pembelian';
$activeMenu = 'pembelian';
$breadcrumb = 'Transaksi / Pembelian';
require_once __DIR__ . '/../../includes/header_admin.php';
$pdo = getDB();
$statuses = ['DRAFT', 'PROSES', 'SELESAI', 'CANCELLED'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
    if ($action === 'delete') {
        if ($id) {
            try {
                $pdo->beginTransaction();
                $oldPurchase = $pdo->prepare('SELECT status FROM pembelian_sparepart WHERE id = :id FOR UPDATE');
                $oldPurchase->execute([':id' => $id]);
                if (($oldPurchase->fetchColumn() ?: '') === 'SELESAI') {
                    $oldItems = $pdo->prepare('SELECT sparepart_id, qty FROM pembelian_sparepart_detail WHERE pembelian_id = :id');
                    $oldItems->execute([':id' => $id]);
                    foreach ($oldItems->fetchAll() as $oldItem) {
                        $stockUpdate = $pdo->prepare('UPDATE sparepart SET stok = stok - :qty WHERE id = :part AND stok >= :qty_check');
                        $stockUpdate->execute([':qty' => $oldItem['qty'], ':part' => $oldItem['sparepart_id'], ':qty_check' => $oldItem['qty']]);
                        if ($stockUpdate->rowCount() !== 1) throw new RuntimeException('Stock pembelian sudah berubah.');
                    }
                }
                $pdo->prepare('DELETE FROM pembelian_sparepart WHERE id = :id')->execute([':id' => $id]);
                $pdo->commit();
                flash_set('success', 'Transaksi pembelian berhasil dihapus.');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash_set('error', 'Pembelian selesai tidak dapat dihapus karena stok sudah berubah.');
            }
        }
        redirect('/admin/pembelian/index.php');
    }
    $number = trim((string) ($_POST['nomor_pembelian'] ?? ''));
    $date = trim((string) ($_POST['tanggal'] ?? ''));
    $supplierId = (int) ($_POST['supplier_id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? 'DRAFT'));
    $note = trim((string) ($_POST['keterangan'] ?? '')) ?: null;
    $partIds = $_POST['sparepart_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $prices = $_POST['harga'] ?? [];
    $discounts = $_POST['diskon'] ?? [];
    $items = [];
    foreach ($partIds as $key => $partId) {
        $partId = (int) $partId;
        $qty = max(0, (float) ($qtys[$key] ?? 0));
        $price = max(0, (float) ($prices[$key] ?? 0));
        $discount = max(0, (float) ($discounts[$key] ?? 0));
        if ($partId > 0 && $qty > 0 && $discount <= $qty * $price) $items[] = [$partId, $qty, $price, $discount];
    }
    if ($number === '' || $date === '' || $supplierId <= 0 || !in_array($status, $statuses, true) || empty($items)) {
        flash_set('error', 'Nomor, tanggal, supplier, status, dan minimal satu item wajib diisi.');
        redirect('/admin/pembelian/index.php');
    }
    try {
        $pdo->beginTransaction();
        $total = array_sum(array_map(static fn ($item) => $item[1] * $item[2] - $item[3], $items));
        $data = [':number' => $number, ':date' => $date, ':supplier' => $supplierId, ':total' => $total, ':status' => $status, ':note' => $note];
        if ($id) {
            $stmt = $pdo->prepare('SELECT status FROM pembelian_sparepart WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $old = $stmt->fetch();
            if ($old && $old['status'] === 'SELESAI') {
                $oldItems = $pdo->prepare('SELECT sparepart_id, qty FROM pembelian_sparepart_detail WHERE pembelian_id = :id');
                $oldItems->execute([':id' => $id]);
                foreach ($oldItems->fetchAll() as $oldItem) {
                    $stockUpdate = $pdo->prepare('UPDATE sparepart SET stok = stok - :qty WHERE id = :part AND stok >= :qty_check');
                    $stockUpdate->execute([':qty' => $oldItem['qty'], ':part' => $oldItem['sparepart_id'], ':qty_check' => $oldItem['qty']]);
                    if ($stockUpdate->rowCount() !== 1) throw new RuntimeException('Stock pembelian sudah berubah.');
                }
            }
            $stmt = $pdo->prepare('UPDATE pembelian_sparepart SET nomor_pembelian = :number, tanggal = :date, supplier_id = :supplier, total = :total, status = :status, keterangan = :note, updated_at = NOW() WHERE id = :id');
            $data[':id'] = $id;
            $stmt->execute($data);
            $pdo->prepare('DELETE FROM pembelian_sparepart_detail WHERE pembelian_id = :id')->execute([':id' => $id]);
            $purchaseId = $id;
            flash_set('success', 'Transaksi pembelian berhasil diperbarui.');
        } else {
            $admin = current_admin();
            $stmt = $pdo->prepare('INSERT INTO pembelian_sparepart (nomor_pembelian, tanggal, supplier_id, total, status, keterangan, created_by, created_at, updated_at) VALUES (:number, :date, :supplier, :total, :status, :note, :created_by, NOW(), NOW())');
            $data[':created_by'] = $admin['id'] ?? null;
            $stmt->execute($data);
            $purchaseId = (int) $pdo->lastInsertId();
            flash_set('success', 'Transaksi pembelian berhasil ditambahkan.');
        }
        $detail = $pdo->prepare('INSERT INTO pembelian_sparepart_detail (pembelian_id, sparepart_id, qty, harga, diskon, keterangan, created_at, updated_at) VALUES (:purchase, :part, :qty, :price, :discount, NULL, NOW(), NOW())');
        foreach ($items as $item) {
            $detail->execute([':purchase' => $purchaseId, ':part' => $item[0], ':qty' => $item[1], ':price' => $item[2], ':discount' => $item[3]]);
            if ($status === 'SELESAI') {
                $stockUpdate = $pdo->prepare('UPDATE sparepart SET stok = stok + :qty WHERE id = :part');
                $stockUpdate->execute([':qty' => $item[1], ':part' => $item[0]]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('error', 'Transaksi pembelian gagal disimpan. Pastikan nomor belum digunakan.');
    }
    redirect('/admin/pembelian/index.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(po.nomor_pembelian LIKE :q_number OR s.nama LIKE :q_supplier)';
    $value = '%' . $search . '%';
    $params[':q_number'] = $value; $params[':q_supplier'] = $value;
}
if ($statusFilter !== '' && $statusFilter !== 'all') { $conditions[] = 'po.status = :status'; $params[':status'] = $statusFilter; }
$sql = 'SELECT po.*, s.kode AS supplier_kode, s.nama AS supplier, COUNT(pd.id) AS item_count FROM pembelian_sparepart po INNER JOIN supplier s ON s.id = po.supplier_id LEFT JOIN pembelian_sparepart_detail pd ON pd.pembelian_id = po.id';
if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
$sql .= ' GROUP BY po.id ORDER BY po.tanggal DESC, po.id DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $purchases = $stmt->fetchAll();
$suppliers = $pdo->query("SELECT id, kode, nama FROM supplier WHERE status = 'Aktif' ORDER BY nama ASC")->fetchAll();
$parts = $pdo->query('SELECT id, kode, nama, satuan, harga_modal FROM sparepart ORDER BY nama ASC')->fetchAll();
foreach ($purchases as &$purchase) {
    $details = $pdo->prepare('SELECT pd.*, sp.kode, sp.nama, sp.satuan FROM pembelian_sparepart_detail pd INNER JOIN sparepart sp ON sp.id = pd.sparepart_id WHERE pd.pembelian_id = :id ORDER BY pd.id ASC');
    $details->execute([':id' => $purchase['id']]);
    $purchase['details'] = $details->fetchAll();
}
unset($purchase);
$totalPurchases = (int) $pdo->query('SELECT COUNT(*) FROM pembelian_sparepart')->fetchColumn();
$totalValue = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pembelian_sparepart WHERE status <> 'CANCELLED'")->fetchColumn();
$activeOrders = (int) $pdo->query("SELECT COUNT(*) FROM pembelian_sparepart WHERE status IN ('DRAFT', 'PROSES')")->fetchColumn();
$completedOrders = (int) $pdo->query("SELECT COUNT(*) FROM pembelian_sparepart WHERE status = 'SELESAI'")->fetchColumn();
?>

<div class="stat-cards"><div class="stat-card"><div class="label">Total Pembelian</div><div class="value"><?= $totalPurchases ?></div><div class="sub">Seluruh purchase order</div></div><div class="stat-card"><div class="label">Nilai Pembelian</div><div class="value"><?= rupiah($totalValue) ?></div><div class="sub">Tidak termasuk transaksi batal</div></div><div class="stat-card"><div class="label">Pesanan Aktif</div><div class="value"><?= $activeOrders ?></div><div class="sub">Draft atau sedang diproses</div></div><div class="stat-card"><div class="label">Selesai</div><div class="value"><?= $completedOrders ?></div><div class="sub">Stok sudah ditambahkan</div></div></div>
<div class="card"><div class="toolbar"><div><div class="card-title">Transaksi Pembelian Sparepart</div><div class="card-sub">Kelola pembelian suku cadang dari supplier dan penerimaan stok</div></div><div class="toolbar-form"><button type="button" class="btn btn-amber btn-sm" data-open-modal="purchase-modal">+ Tambah Pembelian</button><form method="get" class="toolbar-form-inline"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nomor atau supplier..." aria-label="Cari pembelian"><select name="status" aria-label="Filter status"><option value="all">Semua status</option><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-amber btn-sm">Filter</button><a href="<?= BASE_URL ?>/admin/pembelian/index.php" class="btn btn-outline-dark btn-sm">Reset</a></form></div></div>
<?php if (empty($purchases)): ?><div class="empty-state"><div class="icon">&#9881;</div><h3 style="margin-bottom:6px;">Belum ada pembelian</h3><p style="max-width:44ch; margin:0 auto;">Belum ada transaksi pembelian yang cocok dengan filter.</p></div><?php else: ?><table class="data-table inventory-table"><thead><tr><th>Nomor / Tanggal</th><th>Supplier</th><th>Item</th><th>Status</th><th>Total</th><th style="width:90px;">Aksi</th></tr></thead><tbody><?php foreach ($purchases as $purchase): ?><tr><td><strong><?= e($purchase['nomor_pembelian']) ?></strong><br><span class="muted"><?= e(tanggal_indo($purchase['tanggal'])) ?></span></td><td><?= e($purchase['supplier_kode'] . ' - ' . $purchase['supplier']) ?></td><td><?= (int) $purchase['item_count'] ?> item</td><td><span class="badge <?= status_badge_class($purchase['status']) ?>"><?= e($purchase['status']) ?></span></td><td><?= rupiah($purchase['total']) ?></td><td><div class="mini-actions"><a href="<?= BASE_URL ?>/admin/pembelian/detail.php?id=<?= (int) $purchase['id'] ?>" class="mini-link">Lihat</a><button type="button" class="mini-btn mini-btn-edit" data-edit-purchase='<?= json_encode($purchase, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Edit</button><form method="post" action="<?= BASE_URL ?>/admin/pembelian/index.php" class="inline-delete-form" onsubmit="return confirm('Hapus transaksi pembelian ini?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $purchase['id'] ?>"><button type="submit" class="mini-btn mini-btn-delete">Hapus</button></form></div></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>

<div class="modal-backdrop" id="purchase-modal" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="purchase-title"><div class="modal-header"><h3 id="purchase-title">Tambah Pembelian</h3><button type="button" class="modal-close" data-close-modal="purchase-modal" aria-label="Tutup">Ã—</button></div><form id="purchase-form" method="post" action="<?= BASE_URL ?>/admin/pembelian/index.php"><input type="hidden" name="action" id="purchase-action" value="create"><input type="hidden" name="id" id="purchase-id" value=""><div class="modal-grid"><div class="field"><label for="purchase-number">Nomor Pembelian</label><input id="purchase-number" name="nomor_pembelian" placeholder="PO-20260903-0001" required></div><div class="field"><label for="purchase-date">Tanggal</label><input id="purchase-date" name="tanggal" type="date" value="<?= date('Y-m-d') ?>" required></div><div class="field"><label for="purchase-supplier">Supplier</label><select id="purchase-supplier" name="supplier_id" required><option value="">Pilih supplier</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['id'] ?>"><?= e($supplier['kode'] . ' - ' . $supplier['nama']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="purchase-status">Status</label><select id="purchase-status" name="status"><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>"><?= e($option) ?></option><?php endforeach; ?></select></div><div class="field field-full"><label>Item Pembelian</label><div id="purchase-items"></div><button type="button" class="btn btn-outline-dark btn-sm" id="add-purchase-item">+ Tambah Item</button></div><div class="field field-full"><label for="purchase-note">Keterangan</label><textarea id="purchase-note" name="keterangan" rows="3"></textarea></div></div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="purchase-modal">Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form></div></div>

<script>
(function () { const modal = document.getElementById('purchase-modal'); const form = document.getElementById('purchase-form'); const action = document.getElementById('purchase-action'); const id = document.getElementById('purchase-id'); const title = document.getElementById('purchase-title'); const items = document.getElementById('purchase-items'); const add = document.getElementById('add-purchase-item'); const parts = <?= json_encode($parts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; if (!modal || !form) return; const row = (data = {}) => { const wrapper = document.createElement('div'); wrapper.className = 'purchase-item-row'; wrapper.style.cssText = 'display:grid;grid-template-columns:2fr .8fr 1.2fr 1.2fr auto;gap:8px;margin-bottom:10px;align-items:end'; wrapper.innerHTML = '<select name="sparepart_id[]" required><option value="">Pilih item</option>' + parts.map((part) => '<option value="' + part.id + '" ' + (String(data.sparepart_id || '') === String(part.id) ? 'selected' : '') + '>' + part.kode + ' - ' + part.nama + '</option>').join('') + '</select><input name="qty[]" type="number" min="0.01" step="0.01" placeholder="Qty" value="' + (data.qty || 1) + '" required><input name="harga[]" type="number" min="0" step="0.01" placeholder="Harga" value="' + (data.harga || 0) + '" required><input name="diskon[]" type="number" min="0" step="0.01" placeholder="Diskon" value="' + (data.diskon || 0) + '"><button type="button" class="mini-btn mini-btn-delete remove-purchase-item">Hapus</button>'; wrapper.querySelector('.remove-purchase-item').addEventListener('click', () => wrapper.remove()); items.appendChild(wrapper); }; const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); form.reset(); action.value = 'create'; id.value = ''; title.textContent = 'Tambah Pembelian'; items.innerHTML = ''; row(); }; const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }; add.addEventListener('click', () => row()); document.querySelectorAll('[data-open-modal="purchase-modal"]').forEach((button) => button.addEventListener('click', () => { close(); open(); })); document.querySelectorAll('[data-close-modal="purchase-modal"]').forEach((button) => button.addEventListener('click', close)); document.querySelectorAll('[data-edit-purchase]').forEach((button) => button.addEventListener('click', () => { const data = JSON.parse(button.dataset.editPurchase); action.value = 'update'; id.value = data.id; title.textContent = 'Edit Pembelian'; document.getElementById('purchase-number').value = data.nomor_pembelian; document.getElementById('purchase-date').value = data.tanggal; document.getElementById('purchase-supplier').value = data.supplier_id; document.getElementById('purchase-status').value = data.status; document.getElementById('purchase-note').value = data.keterangan || ''; items.innerHTML = ''; (data.details || []).forEach((detail) => row(detail)); open(); })); modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); }); row(); })();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>

