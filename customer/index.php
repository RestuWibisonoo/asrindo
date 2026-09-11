<?php
$pageTitle = 'Pelanggan';
$activeMenu = 'customer';
$breadcrumb = 'Rekanan / Pelanggan';
require_once __DIR__ . '/../../includes/header_admin.php';
$pdo = getDB();
$statuses = ['Baru', 'Reguler', 'Blacklist'];
$types = ['Individu', 'Perusahaan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
    if ($action === 'delete') {
        if ($id) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM penjualan WHERE customer_id = :id');
            $check->execute([':id' => $id]);
            if ((int) $check->fetchColumn() > 0) {
                flash_set('error', 'Pelanggan tidak dapat dihapus karena sudah memiliki transaksi penjualan.');
            } else {
                $pdo->prepare('DELETE FROM customer WHERE id = :id')->execute([':id' => $id]);
                flash_set('success', 'Data pelanggan berhasil dihapus.');
            }
        }
        redirect('/admin/customer/index.php');
    }
    $code = trim((string) ($_POST['kode'] ?? ''));
    $name = trim((string) ($_POST['nama'] ?? ''));
    $type = trim((string) ($_POST['jenis_pelanggan'] ?? 'Perusahaan'));
    $status = trim((string) ($_POST['status_pelanggan'] ?? 'Baru'));
    $dp = max(0, min(100, (float) ($_POST['kebijakan_dp'] ?? 30)));
    if ($code === '' || $name === '' || !in_array($type, $types, true) || !in_array($status, $statuses, true)) {
        flash_set('error', 'Kode, nama, jenis, dan status pelanggan wajib diisi dengan benar.');
        redirect('/admin/customer/index.php');
    }
    $data = [':kode' => $code, ':nama' => $name, ':jenis' => $type, ':perusahaan' => trim((string) ($_POST['nama_perusahaan'] ?? '')) ?: null, ':alamat' => trim((string) ($_POST['alamat'] ?? '')) ?: null, ':telepon' => trim((string) ($_POST['telepon'] ?? '')) ?: null, ':email' => trim((string) ($_POST['email'] ?? '')) ?: null, ':contact' => trim((string) ($_POST['contact_person'] ?? '')) ?: null, ':keterangan' => trim((string) ($_POST['keterangan'] ?? '')) ?: null, ':status' => $status, ':dp' => $dp];
    if ($id) {
        $stmt = $pdo->prepare('UPDATE customer SET kode = :kode, nama = :nama, jenis_pelanggan = :jenis, nama_perusahaan = :perusahaan, alamat = :alamat, telepon = :telepon, email = :email, contact_person = :contact, keterangan = :keterangan, status_pelanggan = :status, kebijakan_dp = :dp, updated_at = NOW() WHERE id = :id');
        $data[':id'] = $id; $stmt->execute($data); flash_set('success', 'Data pelanggan berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO customer (kode, nama, jenis_pelanggan, nama_perusahaan, alamat, telepon, email, contact_person, keterangan, status_pelanggan, kebijakan_dp, created_at, updated_at) VALUES (:kode, :nama, :jenis, :perusahaan, :alamat, :telepon, :email, :contact, :keterangan, :status, :dp, NOW(), NOW())');
        $stmt->execute($data); flash_set('success', 'Data pelanggan berhasil ditambahkan.');
    }
    redirect('/admin/customer/index.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$typeFilter = trim((string) ($_GET['jenis'] ?? 'all'));
$conditions = [];
$params = [];
if ($search !== '') { $conditions[] = '(c.kode LIKE :q_code OR c.nama LIKE :q_name OR c.nama_perusahaan LIKE :q_company OR c.telepon LIKE :q_phone OR c.email LIKE :q_email)'; $value = '%' . $search . '%'; $params[':q_code'] = $value; $params[':q_name'] = $value; $params[':q_company'] = $value; $params[':q_phone'] = $value; $params[':q_email'] = $value; }
if ($statusFilter !== '' && $statusFilter !== 'all') { $conditions[] = 'c.status_pelanggan = :status'; $params[':status'] = $statusFilter; }
if ($typeFilter !== '' && $typeFilter !== 'all') { $conditions[] = 'c.jenis_pelanggan = :jenis'; $params[':jenis'] = $typeFilter; }
$sql = 'SELECT c.*, COUNT(p.id) AS total_penjualan FROM customer c LEFT JOIN penjualan p ON p.customer_id = c.id';
if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
$sql .= ' GROUP BY c.id ORDER BY c.updated_at DESC, c.id DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $customers = $stmt->fetchAll();
$totalCustomers = (int) $pdo->query('SELECT COUNT(*) FROM customer')->fetchColumn();
$companyCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customer WHERE jenis_pelanggan = 'Perusahaan'")->fetchColumn();
$regularCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customer WHERE status_pelanggan = 'Reguler'")->fetchColumn();
$blacklistedCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customer WHERE status_pelanggan = 'Blacklist'")->fetchColumn();
?>

<div class="stat-cards"><div class="stat-card"><div class="label">Total Pelanggan</div><div class="value"><?= $totalCustomers ?></div><div class="sub">Seluruh pelanggan terdaftar</div></div><div class="stat-card"><div class="label">Perusahaan</div><div class="value"><?= $companyCustomers ?></div><div class="sub">Customer berbentuk perusahaan</div></div><div class="stat-card"><div class="label">Reguler</div><div class="value"><?= $regularCustomers ?></div><div class="sub">Pelanggan aktif reguler</div></div><div class="stat-card"><div class="label">Blacklist</div><div class="value"><?= $blacklistedCustomers ?></div><div class="sub">Perlu perhatian khusus</div></div></div>
<div class="card"><div class="toolbar"><div><div class="card-title">Data Pelanggan</div><div class="card-sub">Kelola data customer dan kebijakan uang muka transaksi</div></div><div class="toolbar-form"><button type="button" class="btn btn-amber btn-sm" data-open-modal="customer-modal">+ Tambah Pelanggan</button><form method="get" class="toolbar-form-inline"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari kode, nama, telepon..." aria-label="Cari pelanggan"><select name="jenis" aria-label="Filter jenis"><option value="all">Semua jenis</option><?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select><select name="status" aria-label="Filter status"><option value="all">Semua status</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-amber btn-sm">Filter</button><a href="<?= BASE_URL ?>/admin/customer/index.php" class="btn btn-outline-dark btn-sm">Reset</a></form></div></div>
<?php if (empty($customers)): ?><div class="empty-state"><div class="icon">&#9881;</div><h3 style="margin-bottom:6px;">Belum ada data pelanggan</h3><p style="max-width:44ch; margin:0 auto;">Tidak ada pelanggan yang cocok dengan filter saat ini.</p></div><?php else: ?><table class="data-table inventory-table"><thead><tr><th>Pelanggan</th><th>Jenis</th><th>Kontak</th><th>Status</th><th>Kebijakan DP</th><th>Transaksi</th><th style="width:90px;">Aksi</th></tr></thead><tbody><?php foreach ($customers as $customer): ?><tr><td><strong><?= e($customer['kode']) ?></strong><br><span class="muted"><?= e($customer['nama']) ?></span></td><td><?= e($customer['jenis_pelanggan']) ?><br><span class="muted"><?= e($customer['nama_perusahaan'] ?: '-') ?></span></td><td><?= e($customer['telepon'] ?: '-') ?><br><span class="muted"><?= e($customer['email'] ?: '-') ?></span></td><td><span class="badge <?= status_badge_class($customer['status_pelanggan']) ?>"><?= e($customer['status_pelanggan']) ?></span></td><td><?= number_format((float) $customer['kebijakan_dp'], 2, ',', '.') ?>%</td><td><?= (int) $customer['total_penjualan'] ?> transaksi</td><td><div class="mini-actions"><a href="<?= BASE_URL ?>/admin/customer/detail.php?id=<?= (int) $customer['id'] ?>" class="mini-link">Lihat</a><button type="button" class="mini-btn mini-btn-edit" data-edit-customer='<?= json_encode($customer, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Edit</button><form method="post" action="<?= BASE_URL ?>/admin/customer/index.php" class="inline-delete-form" onsubmit="return confirm('Hapus pelanggan ini?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $customer['id'] ?>"><button type="submit" class="mini-btn mini-btn-delete">Hapus</button></form></div></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>

<div class="modal-backdrop" id="customer-modal" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="customer-title"><div class="modal-header"><h3 id="customer-title">Tambah Pelanggan</h3><button type="button" class="modal-close" data-close-modal="customer-modal" aria-label="Tutup">Ã—</button></div><form id="customer-form" method="post" action="<?= BASE_URL ?>/admin/customer/index.php"><input type="hidden" name="action" id="customer-action" value="create"><input type="hidden" name="id" id="customer-id" value=""><div class="modal-grid"><div class="field"><label for="customer-code">Kode Pelanggan</label><input id="customer-code" name="kode" required></div><div class="field"><label for="customer-name">Nama</label><input id="customer-name" name="nama" required></div><div class="field"><label for="customer-type">Jenis Pelanggan</label><select id="customer-type" name="jenis_pelanggan"><?php foreach ($types as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></div><div class="field"><label for="customer-company">Nama Perusahaan</label><input id="customer-company" name="nama_perusahaan"></div><div class="field"><label for="customer-phone">Telepon</label><input id="customer-phone" name="telepon"></div><div class="field"><label for="customer-email">Email</label><input id="customer-email" name="email" type="email"></div><div class="field"><label for="customer-contact">Contact Person</label><input id="customer-contact" name="contact_person"></div><div class="field"><label for="customer-dp">Kebijakan DP (%)</label><input id="customer-dp" name="kebijakan_dp" type="number" min="0" max="100" step="0.01" value="30"></div><div class="field field-full"><label for="customer-address">Alamat</label><textarea id="customer-address" name="alamat" rows="2"></textarea></div><div class="field"><label for="customer-status">Status</label><select id="customer-status" name="status_pelanggan"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></div><div class="field"><label for="customer-note">Keterangan</label><textarea id="customer-note" name="keterangan" rows="2"></textarea></div></div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="customer-modal">Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form></div></div>

<script>
(function () { const modal = document.getElementById('customer-modal'); const form = document.getElementById('customer-form'); const action = document.getElementById('customer-action'); const id = document.getElementById('customer-id'); const title = document.getElementById('customer-title'); const fields = { kode: document.getElementById('customer-code'), nama: document.getElementById('customer-name'), jenis_pelanggan: document.getElementById('customer-type'), nama_perusahaan: document.getElementById('customer-company'), telepon: document.getElementById('customer-phone'), email: document.getElementById('customer-email'), contact_person: document.getElementById('customer-contact'), kebijakan_dp: document.getElementById('customer-dp'), alamat: document.getElementById('customer-address'), status_pelanggan: document.getElementById('customer-status'), keterangan: document.getElementById('customer-note') }; if (!modal || !form) return; const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); form.reset(); action.value = 'create'; id.value = ''; title.textContent = 'Tambah Pelanggan'; fields.kebijakan_dp.value = 30; }; const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }; document.querySelectorAll('[data-open-modal="customer-modal"]').forEach((button) => button.addEventListener('click', () => { close(); open(); })); document.querySelectorAll('[data-close-modal="customer-modal"]').forEach((button) => button.addEventListener('click', close)); document.querySelectorAll('[data-edit-customer]').forEach((button) => button.addEventListener('click', () => { const data = JSON.parse(button.dataset.editCustomer); action.value = 'update'; id.value = data.id; title.textContent = 'Edit Pelanggan'; Object.keys(fields).forEach((name) => { fields[name].value = data[name] ?? ''; }); open(); })); modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); }); })();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>

