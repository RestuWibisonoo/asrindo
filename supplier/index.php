<?php
$pageTitle = 'Supplier';
$activeMenu = 'supplier';
$breadcrumb = 'Rekanan / Supplier';
require_once __DIR__ . '/../../includes/header_admin.php';
$pdo = getDB();
$statuses = ['Aktif', 'Tidak Aktif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
    if ($action === 'delete') {
        if ($id) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM pembelian_sparepart WHERE supplier_id = :id');
            $check->execute([':id' => $id]);
            if ((int) $check->fetchColumn() > 0) flash_set('error', 'Supplier tidak dapat dihapus karena sudah memiliki transaksi pembelian.');
            else { $pdo->prepare('DELETE FROM supplier WHERE id = :id')->execute([':id' => $id]); flash_set('success', 'Data supplier berhasil dihapus.'); }
        }
        redirect('/admin/supplier/index.php');
    }
    $code = trim((string) ($_POST['kode'] ?? ''));
    $name = trim((string) ($_POST['nama'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'Aktif'));
    if ($code === '' || $name === '' || !in_array($status, $statuses, true)) {
        flash_set('error', 'Kode, nama, dan status supplier wajib diisi dengan benar.');
        redirect('/admin/supplier/index.php');
    }
    $data = [':kode' => $code, ':nama' => $name, ':alamat' => trim((string) ($_POST['alamat'] ?? '')) ?: null, ':status' => $status, ':telepon' => trim((string) ($_POST['telepon'] ?? '')) ?: null, ':email' => trim((string) ($_POST['email'] ?? '')) ?: null, ':contact' => trim((string) ($_POST['contact_person'] ?? '')) ?: null, ':negara' => trim((string) ($_POST['negara'] ?? '')) ?: null, ':keterangan' => trim((string) ($_POST['keterangan'] ?? '')) ?: null];
    if ($id) {
        $stmt = $pdo->prepare('UPDATE supplier SET kode = :kode, nama = :nama, alamat = :alamat, status = :status, telepon = :telepon, email = :email, contact_person = :contact, negara = :negara, keterangan = :keterangan, updated_at = NOW() WHERE id = :id');
        $data[':id'] = $id; $stmt->execute($data); flash_set('success', 'Data supplier berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO supplier (kode, nama, alamat, status, telepon, email, contact_person, negara, keterangan, created_at, updated_at) VALUES (:kode, :nama, :alamat, :status, :telepon, :email, :contact, :negara, :keterangan, NOW(), NOW())');
        $stmt->execute($data); flash_set('success', 'Data supplier berhasil ditambahkan.');
    }
    redirect('/admin/supplier/index.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$countryFilter = trim((string) ($_GET['negara'] ?? 'all'));
$conditions = [];
$params = [];
if ($search !== '') { $conditions[] = '(s.kode LIKE :q_code OR s.nama LIKE :q_name OR s.telepon LIKE :q_phone OR s.email LIKE :q_email OR s.contact_person LIKE :q_contact)'; $value = '%' . $search . '%'; $params[':q_code'] = $value; $params[':q_name'] = $value; $params[':q_phone'] = $value; $params[':q_email'] = $value; $params[':q_contact'] = $value; }
if ($statusFilter !== '' && $statusFilter !== 'all') { $conditions[] = 's.status = :status'; $params[':status'] = $statusFilter; }
if ($countryFilter !== '' && $countryFilter !== 'all') { $conditions[] = 's.negara = :negara'; $params[':negara'] = $countryFilter; }
$sql = 'SELECT s.*, COUNT(po.id) AS total_pembelian FROM supplier s LEFT JOIN pembelian_sparepart po ON po.supplier_id = s.id';
if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
$sql .= ' GROUP BY s.id ORDER BY s.updated_at DESC, s.id DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $suppliers = $stmt->fetchAll();
$countries = $pdo->query("SELECT DISTINCT negara FROM supplier WHERE negara IS NOT NULL AND negara <> '' ORDER BY negara ASC")->fetchAll(PDO::FETCH_COLUMN);
$totalSuppliers = (int) $pdo->query('SELECT COUNT(*) FROM supplier')->fetchColumn();
$activeSuppliers = (int) $pdo->query("SELECT COUNT(*) FROM supplier WHERE status = 'Aktif'")->fetchColumn();
$inactiveSuppliers = (int) $pdo->query("SELECT COUNT(*) FROM supplier WHERE status = 'Tidak Aktif'")->fetchColumn();
$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM pembelian_sparepart')->fetchColumn();
?>

<div class="stat-cards"><div class="stat-card"><div class="label">Total Supplier</div><div class="value"><?= $totalSuppliers ?></div><div class="sub">Seluruh rekanan terdaftar</div></div><div class="stat-card"><div class="label">Supplier Aktif</div><div class="value"><?= $activeSuppliers ?></div><div class="sub">Dapat dipilih untuk pembelian</div></div><div class="stat-card"><div class="label">Tidak Aktif</div><div class="value"><?= $inactiveSuppliers ?></div><div class="sub">Supplier nonaktif</div></div><div class="stat-card"><div class="label">Total Pembelian</div><div class="value"><?= $totalOrders ?></div><div class="sub">Transaksi seluruh supplier</div></div></div>
<div class="card"><div class="toolbar"><div><div class="card-title">Data Supplier</div><div class="card-sub">Kelola rekanan pemasok sparepart dan informasi kontaknya</div></div><div class="toolbar-form"><button type="button" class="btn btn-amber btn-sm" data-open-modal="supplier-modal">+ Tambah Supplier</button><form method="get" class="toolbar-form-inline"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari kode, nama, kontak..." aria-label="Cari supplier"><select name="negara" aria-label="Filter negara"><option value="all">Semua negara</option><?php foreach ($countries as $country): ?><option value="<?= e($country) ?>" <?= $countryFilter === $country ? 'selected' : '' ?>><?= e($country) ?></option><?php endforeach; ?></select><select name="status" aria-label="Filter status"><option value="all">Semua status</option><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-amber btn-sm">Filter</button><a href="<?= BASE_URL ?>/admin/supplier/index.php" class="btn btn-outline-dark btn-sm">Reset</a></form></div></div>
<?php if (empty($suppliers)): ?><div class="empty-state"><div class="icon">&#9881;</div><h3 style="margin-bottom:6px;">Belum ada data supplier</h3><p style="max-width:44ch; margin:0 auto;">Tidak ada supplier yang cocok dengan filter saat ini.</p></div><?php else: ?><table class="data-table inventory-table"><thead><tr><th>Supplier</th><th>Kontak</th><th>Contact Person</th><th>Negara</th><th>Status</th><th>Pembelian</th><th style="width:90px;">Aksi</th></tr></thead><tbody><?php foreach ($suppliers as $supplier): ?><tr><td><strong><?= e($supplier['kode']) ?></strong><br><span class="muted"><?= e($supplier['nama']) ?></span></td><td><?= e($supplier['telepon'] ?: '-') ?><br><span class="muted"><?= e($supplier['email'] ?: '-') ?></span></td><td><?= e($supplier['contact_person'] ?: '-') ?></td><td><?= e($supplier['negara'] ?: '-') ?></td><td><span class="badge <?= status_badge_class($supplier['status']) ?>"><?= e($supplier['status']) ?></span></td><td><?= (int) $supplier['total_pembelian'] ?> transaksi</td><td><div class="mini-actions"><a href="<?= BASE_URL ?>/admin/supplier/detail.php?id=<?= (int) $supplier['id'] ?>" class="mini-link">Lihat</a><button type="button" class="mini-btn mini-btn-edit" data-edit-supplier='<?= json_encode($supplier, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Edit</button><form method="post" action="<?= BASE_URL ?>/admin/supplier/index.php" class="inline-delete-form" onsubmit="return confirm('Hapus supplier ini?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>"><button type="submit" class="mini-btn mini-btn-delete">Hapus</button></form></div></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>

<div class="modal-backdrop" id="supplier-modal" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="supplier-title"><div class="modal-header"><h3 id="supplier-title">Tambah Supplier</h3><button type="button" class="modal-close" data-close-modal="supplier-modal" aria-label="Tutup">Ã—</button></div><form id="supplier-form" method="post" action="<?= BASE_URL ?>/admin/supplier/index.php"><input type="hidden" name="action" id="supplier-action" value="create"><input type="hidden" name="id" id="supplier-id" value=""><div class="modal-grid"><div class="field"><label for="supplier-code">Kode Supplier</label><input id="supplier-code" name="kode" required></div><div class="field"><label for="supplier-name">Nama Supplier</label><input id="supplier-name" name="nama" required></div><div class="field"><label for="supplier-status">Status</label><select id="supplier-status" name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></div><div class="field"><label for="supplier-country">Negara</label><input id="supplier-country" name="negara"></div><div class="field"><label for="supplier-phone">Telepon</label><input id="supplier-phone" name="telepon"></div><div class="field"><label for="supplier-email">Email</label><input id="supplier-email" name="email" type="email"></div><div class="field"><label for="supplier-contact">Contact Person</label><input id="supplier-contact" name="contact_person"></div><div class="field field-full"><label for="supplier-address">Alamat</label><textarea id="supplier-address" name="alamat" rows="2"></textarea></div><div class="field field-full"><label for="supplier-note">Keterangan</label><textarea id="supplier-note" name="keterangan" rows="3"></textarea></div></div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="supplier-modal">Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form></div></div>

<script>
(function () { const modal = document.getElementById('supplier-modal'); const form = document.getElementById('supplier-form'); const action = document.getElementById('supplier-action'); const id = document.getElementById('supplier-id'); const title = document.getElementById('supplier-title'); const fields = { kode: document.getElementById('supplier-code'), nama: document.getElementById('supplier-name'), status: document.getElementById('supplier-status'), negara: document.getElementById('supplier-country'), telepon: document.getElementById('supplier-phone'), email: document.getElementById('supplier-email'), contact_person: document.getElementById('supplier-contact'), alamat: document.getElementById('supplier-address'), keterangan: document.getElementById('supplier-note') }; if (!modal || !form) return; const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); form.reset(); action.value = 'create'; id.value = ''; title.textContent = 'Tambah Supplier'; }; const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }; document.querySelectorAll('[data-open-modal="supplier-modal"]').forEach((button) => button.addEventListener('click', () => { close(); open(); })); document.querySelectorAll('[data-close-modal="supplier-modal"]').forEach((button) => button.addEventListener('click', close)); document.querySelectorAll('[data-edit-supplier]').forEach((button) => button.addEventListener('click', () => { const data = JSON.parse(button.dataset.editSupplier); action.value = 'update'; id.value = data.id; title.textContent = 'Edit Supplier'; Object.keys(fields).forEach((name) => { fields[name].value = data[name] ?? ''; }); open(); })); modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); }); })();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>

