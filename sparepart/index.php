<?php
$pageTitle = "Suku Cadang";
$activeMenu = "sparepart";
$breadcrumb = "Produk & Inventori / Suku Cadang";
require_once __DIR__ . "/../../includes/header_admin.php";
?>


<?php
$pageTitle = 'Suku Cadang';
$activeMenu = 'sparepart';
$breadcrumb = 'Produk & Inventori / Suku Cadang';
require_once __DIR__ . '/../../includes/header_admin.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

    if ($action === 'delete') {
        if ($id) {
            $pdo->prepare('DELETE FROM sparepart WHERE id = :id')->execute([':id' => $id]);
            flash_set('success', 'Data suku cadang berhasil dihapus.');
        }
        redirect('/admin/sparepart/index.php');
    }

    $kode = trim((string) ($_POST['kode'] ?? ''));
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($kode === '' || $nama === '') {
        flash_set('error', 'Kode dan nama suku cadang wajib diisi.');
        redirect('/admin/sparepart/index.php');
    }

    $data = [
        ':kode' => $kode,
        ':nama' => $nama,
        ':kategori' => trim((string) ($_POST['kategori'] ?? '')) ?: null,
        ':part_number' => trim((string) ($_POST['part_number'] ?? '')) ?: null,
        ':merk' => trim((string) ($_POST['merk'] ?? '')) ?: null,
        ':stok' => max(0, (int) ($_POST['stok'] ?? 0)),
        ':stok_minimum' => max(0, (int) ($_POST['stok_minimum'] ?? 0)),
        ':harga_modal' => max(0, (float) ($_POST['harga_modal'] ?? 0)),
        ':harga_jual' => max(0, (float) ($_POST['harga_jual'] ?? 0)),
        ':satuan' => trim((string) ($_POST['satuan'] ?? 'PCS')) ?: 'PCS',
        ':keterangan' => trim((string) ($_POST['keterangan'] ?? '')) ?: null,
    ];

    if ($id) {
        $stmt = $pdo->prepare('UPDATE sparepart SET kode = :kode, nama = :nama, kategori = :kategori, part_number = :part_number, merk = :merk, stok = :stok, stok_minimum = :stok_minimum, harga_modal = :harga_modal, harga_jual = :harga_jual, satuan = :satuan, keterangan = :keterangan, updated_at = NOW() WHERE id = :id');
        $data[':id'] = $id;
        $stmt->execute($data);
        flash_set('success', 'Data suku cadang berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO sparepart (kode, nama, kategori, part_number, merk, stok, stok_minimum, harga_modal, harga_jual, satuan, keterangan, created_at, updated_at) VALUES (:kode, :nama, :kategori, :part_number, :merk, :stok, :stok_minimum, :harga_modal, :harga_jual, :satuan, :keterangan, NOW(), NOW())');
        $stmt->execute($data);
        flash_set('success', 'Data suku cadang berhasil ditambahkan.');
    }

    redirect('/admin/sparepart/index.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$categoryFilter = trim((string) ($_GET['kategori'] ?? 'all'));
$conditions = [];
$params = [];
if ($search !== '') {
  $conditions[] = '(kode LIKE :q_kode OR nama LIKE :q_nama OR part_number LIKE :q_part_number OR merk LIKE :q_merk)';
  $searchValue = '%' . $search . '%';
  $params[':q_kode'] = $searchValue;
  $params[':q_nama'] = $searchValue;
  $params[':q_part_number'] = $searchValue;
  $params[':q_merk'] = $searchValue;
}
if ($categoryFilter !== '' && $categoryFilter !== 'all') {
    $conditions[] = 'kategori = :kategori';
    $params[':kategori'] = $categoryFilter;
}
$sql = 'SELECT * FROM sparepart';
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY updated_at DESC, id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parts = $stmt->fetchAll();

$totalPart = (int) $pdo->query('SELECT COUNT(*) FROM sparepart')->fetchColumn();
$totalStock = (int) $pdo->query('SELECT COALESCE(SUM(stok), 0) FROM sparepart')->fetchColumn();
$totalValue = (float) $pdo->query('SELECT COALESCE(SUM(stok * harga_modal), 0) FROM sparepart')->fetchColumn();
$lowStock = (int) $pdo->query('SELECT COUNT(*) FROM sparepart WHERE stok <= stok_minimum')->fetchColumn();
$categories = $pdo->query("SELECT DISTINCT kategori FROM sparepart WHERE kategori IS NOT NULL AND kategori <> '' ORDER BY kategori ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="stat-cards">
  <div class="stat-card"><div class="label">Jenis Suku Cadang</div><div class="value"><?= $totalPart ?></div><div class="sub">Seluruh item terdaftar</div></div>
  <div class="stat-card"><div class="label">Total Stok</div><div class="value"><?= $totalStock ?></div><div class="sub">Gabungan semua satuan</div></div>
  <div class="stat-card"><div class="label">Nilai Persediaan</div><div class="value"><?= rupiah($totalValue) ?></div><div class="sub">Berdasarkan harga modal</div></div>
  <div class="stat-card"><div class="label">Stok Menipis</div><div class="value"><?= $lowStock ?></div><div class="sub">Perlu segera diperiksa</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div><div class="card-title">Inventori Suku Cadang</div><div class="card-sub">Kelola stok, harga, dan informasi komponen alat berat</div></div>
    <div class="toolbar-form">
      <button type="button" class="btn btn-amber btn-sm" data-open-modal="sp-modal">+ Tambah Suku Cadang</button>
      <form method="get" class="toolbar-form-inline">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari kode, nama, part number..." aria-label="Cari suku cadang">
        <select name="kategori" aria-label="Filter kategori"><option value="all">Semua kategori</option><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select>
        <button type="submit" class="btn btn-amber btn-sm">Filter</button>
        <a href="<?= BASE_URL ?>/admin/sparepart/index.php" class="btn btn-outline-dark btn-sm">Reset</a>
      </form>
    </div>
  </div>

  <?php if (empty($parts)): ?>
    <div class="empty-state"><div class="icon">&#9881;</div><h3 style="margin-bottom:6px;">Belum ada data suku cadang</h3><p style="max-width:44ch; margin:0 auto;">Tidak ada suku cadang yang cocok dengan pencarian saat ini.</p></div>
  <?php else: ?>
    <table class="data-table inventory-table"><thead><tr><th>Item</th><th>Part Number</th><th>Merk</th><th>Kategori</th><th>Stok</th><th>Harga Modal</th><th>Harga Jual</th><th style="width:90px;">Aksi</th></tr></thead><tbody>
      <?php foreach ($parts as $part): ?><tr>
        <td><strong><?= e($part['kode']) ?></strong><br><span class="muted"><?= e($part['nama']) ?></span></td>
        <td><?= e($part['part_number'] ?: '-') ?></td><td><?= e($part['merk'] ?: '-') ?></td><td><?= e($part['kategori'] ?: '-') ?></td>
        <td><span class="badge <?= (int) $part['stok'] <= (int) $part['stok_minimum'] ? 'badge-danger' : 'badge-ok' ?>"><?= (int) $part['stok'] . ' ' . e($part['satuan']) ?></span></td>
        <td><?= rupiah($part['harga_modal']) ?></td><td><?= rupiah($part['harga_jual']) ?></td>
        <td><div class="mini-actions"><a href="<?= BASE_URL ?>/admin/sparepart/detail.php?id=<?= (int) $part['id'] ?>" class="mini-link">Lihat</a><button type="button" class="mini-btn mini-btn-edit" data-edit-part='<?= json_encode($part, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Edit</button><form method="post" action="<?= BASE_URL ?>/admin/sparepart/index.php" class="inline-delete-form" onsubmit="return confirm('Hapus suku cadang ini?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $part['id'] ?>"><button type="submit" class="mini-btn mini-btn-delete">Hapus</button></form></div></td>
      </tr><?php endforeach; ?></tbody></table>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:20px;"><div class="card-title">Catatan Stok</div><div class="card-sub">Item dengan stok sama atau di bawah batas minimum perlu ditindaklanjuti.</div><div class="quick-note"><strong><?= $lowStock ?></strong><span>suku cadang berada di bawah batas minimum</span></div><div class="quick-note"><strong><?= rupiah($totalValue) ?></strong><span>nilai persediaan berdasarkan harga modal</span></div></div>

<div class="modal-backdrop" id="sp-modal" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="sp-modal-title"><div class="modal-header"><h3 id="sp-modal-title">Tambah Suku Cadang</h3><button type="button" class="modal-close" data-close-modal="sp-modal" aria-label="Tutup">×</button></div>
  <form id="sp-form" method="post" action="<?= BASE_URL ?>/admin/sparepart/index.php"><input type="hidden" name="action" id="sp-form-action" value="create"><input type="hidden" name="id" id="sp-form-id" value=""><div class="modal-grid">
    <div class="field"><label for="sp-kode">Kode</label><input id="sp-kode" name="kode" type="text" required></div><div class="field"><label for="sp-nama">Nama Suku Cadang</label><input id="sp-nama" name="nama" type="text" required></div>
    <div class="field"><label for="sp-kategori">Kategori</label><input id="sp-kategori" name="kategori" type="text"></div><div class="field"><label for="sp-part-number">Part Number</label><input id="sp-part-number" name="part_number" type="text"></div>
    <div class="field"><label for="sp-merk">Merk</label><input id="sp-merk" name="merk" type="text"></div><div class="field"><label for="sp-satuan">Satuan</label><input id="sp-satuan" name="satuan" type="text" value="PCS" required></div>
    <div class="field"><label for="sp-stok">Stok</label><input id="sp-stok" name="stok" type="number" min="0" step="1" value="0"></div><div class="field"><label for="sp-stok-minimum">Stok Minimum</label><input id="sp-stok-minimum" name="stok_minimum" type="number" min="0" step="1" value="0"></div>
    <div class="field"><label for="sp-harga-modal">Harga Modal</label><input id="sp-harga-modal" name="harga_modal" type="number" min="0" step="0.01" value="0"></div><div class="field"><label for="sp-harga-jual">Harga Jual</label><input id="sp-harga-jual" name="harga_jual" type="number" min="0" step="0.01" value="0"></div>
    <div class="field field-full"><label for="sp-keterangan">Keterangan</label><textarea id="sp-keterangan" name="keterangan" rows="3"></textarea></div>
  </div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="sp-modal">Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form>
</div></div>

<script>
(function () {
  const modal = document.getElementById('sp-modal'); const form = document.getElementById('sp-form'); const action = document.getElementById('sp-form-action'); const id = document.getElementById('sp-form-id'); const title = document.getElementById('sp-modal-title');
  const fields = ['kode', 'nama', 'kategori', 'part_number', 'merk', 'satuan', 'stok', 'stok_minimum', 'harga_modal', 'harga_jual', 'keterangan'].reduce((result, name) => { result[name] = document.getElementById('sp-' + name.replaceAll('_', '-')); return result; }, {});
  if (!modal || !form) return;
  const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); form.reset(); action.value = 'create'; id.value = ''; title.textContent = 'Tambah Suku Cadang'; };
  document.querySelectorAll('[data-open-modal="sp-modal"]').forEach((button) => button.addEventListener('click', () => { close(); modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }));
  document.querySelectorAll('[data-close-modal="sp-modal"]').forEach((button) => button.addEventListener('click', close));
  document.querySelectorAll('[data-edit-part]').forEach((button) => button.addEventListener('click', () => { const data = JSON.parse(button.dataset.editPart); action.value = 'update'; id.value = data.id; title.textContent = 'Edit Suku Cadang'; Object.keys(fields).forEach((name) => { fields[name].value = data[name] ?? (name === 'satuan' ? 'PCS' : 0); }); modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }));
  modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); });
})();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>
