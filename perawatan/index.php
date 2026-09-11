<?php
$pageTitle = 'Perawatan';
$activeMenu = 'perawatan';
$breadcrumb = 'Produk & Inventori / Perawatan';
require_once __DIR__ . '/../../includes/header_admin.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

    if ($action === 'delete') {
        if ($id) {
            $pdo->prepare('DELETE FROM alat_berat_perawatan WHERE id = :id')->execute([':id' => $id]);
            flash_set('success', 'Data perawatan berhasil dihapus.');
        }
        redirect('/admin/perawatan/index.php');
    }

    $alatBeratId = (int) ($_POST['alat_berat_id'] ?? 0);
    $tanggal = trim((string) ($_POST['tanggal'] ?? ''));
    if ($alatBeratId <= 0 || $tanggal === '') {
        flash_set('error', 'Unit alat berat dan tanggal perawatan wajib diisi.');
        redirect('/admin/perawatan/index.php');
    }

    $data = [
        ':alat_berat_id' => $alatBeratId,
        ':tanggal' => $tanggal,
        ':keterangan' => trim((string) ($_POST['keterangan'] ?? '')) ?: null,
        ':finishing' => max(0, (float) ($_POST['finishing'] ?? 0)),
        ':suku_cadang' => max(0, (float) ($_POST['suku_cadang'] ?? 0)),
        ':jasa' => max(0, (float) ($_POST['jasa'] ?? 0)),
    ];

    if ($id) {
        $stmt = $pdo->prepare('UPDATE alat_berat_perawatan SET alat_berat_id = :alat_berat_id, tanggal = :tanggal, keterangan = :keterangan, finishing = :finishing, suku_cadang = :suku_cadang, jasa = :jasa, updated_at = NOW() WHERE id = :id');
        $data[':id'] = $id;
        $stmt->execute($data);
        flash_set('success', 'Data perawatan berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO alat_berat_perawatan (alat_berat_id, tanggal, keterangan, finishing, suku_cadang, jasa, created_at, updated_at) VALUES (:alat_berat_id, :tanggal, :keterangan, :finishing, :suku_cadang, :jasa, NOW(), NOW())');
        $stmt->execute($data);
        flash_set('success', 'Data perawatan berhasil ditambahkan.');
    }
    redirect('/admin/perawatan/index.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$dateFilter = trim((string) ($_GET['tanggal'] ?? ''));
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(ab.kode LIKE :q_kode OR ab.tipe LIKE :q_tipe OR ap.keterangan LIKE :q_keterangan)';
    $searchValue = '%' . $search . '%';
    $params[':q_kode'] = $searchValue;
    $params[':q_tipe'] = $searchValue;
    $params[':q_keterangan'] = $searchValue;
}
if ($dateFilter !== '') {
    $conditions[] = 'ap.tanggal = :tanggal';
    $params[':tanggal'] = $dateFilter;
}
$sql = 'SELECT ap.*, ab.kode, ab.tipe FROM alat_berat_perawatan ap INNER JOIN alat_berat ab ON ab.id = ap.alat_berat_id';
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY ap.tanggal DESC, ap.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$maintenanceRows = $stmt->fetchAll();
$units = $pdo->query('SELECT id, kode, tipe FROM alat_berat ORDER BY kode ASC')->fetchAll();
$totalRecords = (int) $pdo->query('SELECT COUNT(*) FROM alat_berat_perawatan')->fetchColumn();
$totalCost = (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM alat_berat_perawatan')->fetchColumn();
$thisMonth = (int) $pdo->query("SELECT COUNT(*) FROM alat_berat_perawatan WHERE YEAR(tanggal) = YEAR(CURDATE()) AND MONTH(tanggal) = MONTH(CURDATE())")->fetchColumn();
$totalServices = (float) $pdo->query('SELECT COALESCE(SUM(jasa), 0) FROM alat_berat_perawatan')->fetchColumn();
?>

<div class="stat-cards">
  <div class="stat-card"><div class="label">Total Perawatan</div><div class="value"><?= $totalRecords ?></div><div class="sub">Seluruh riwayat tercatat</div></div>
  <div class="stat-card"><div class="label">Total Biaya</div><div class="value"><?= rupiah($totalCost) ?></div><div class="sub">Finishing, suku cadang, dan jasa</div></div>
  <div class="stat-card"><div class="label">Bulan Ini</div><div class="value"><?= $thisMonth ?></div><div class="sub">Pekerjaan perawatan berjalan</div></div>
  <div class="stat-card"><div class="label">Total Jasa</div><div class="value"><?= rupiah($totalServices) ?></div><div class="sub">Akumulasi biaya jasa</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div><div class="card-title">Riwayat Perawatan Alat Berat</div><div class="card-sub">Catat pekerjaan dan biaya perawatan berdasarkan unit alat berat</div></div>
    <div class="toolbar-form"><button type="button" class="btn btn-amber btn-sm" data-open-modal="maintenance-modal">+ Tambah Perawatan</button><form method="get" class="toolbar-form-inline"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari unit atau keterangan..." aria-label="Cari perawatan"><input type="date" name="tanggal" value="<?= e($dateFilter) ?>" aria-label="Filter tanggal"><button type="submit" class="btn btn-amber btn-sm">Filter</button><a href="<?= BASE_URL ?>/admin/perawatan/index.php" class="btn btn-outline-dark btn-sm">Reset</a></form></div>
  </div>
  <?php if (empty($maintenanceRows)): ?>
    <div class="empty-state"><div class="icon">&#9881;</div><h3 style="margin-bottom:6px;">Belum ada data perawatan</h3><p style="max-width:44ch; margin:0 auto;">Tidak ada riwayat perawatan yang cocok dengan filter saat ini.</p></div>
  <?php else: ?>
    <table class="data-table inventory-table"><thead><tr><th>Tanggal</th><th>Unit</th><th>Keterangan</th><th>Finishing</th><th>Suku Cadang</th><th>Jasa</th><th>Total</th><th style="width:90px;">Aksi</th></tr></thead><tbody>
      <?php foreach ($maintenanceRows as $row): ?><tr><td><?= e(tanggal_indo($row['tanggal'])) ?></td><td><strong><?= e($row['kode']) ?></strong><br><span class="muted"><?= e($row['tipe']) ?></span></td><td><?= e($row['keterangan'] ?: '-') ?></td><td><?= rupiah($row['finishing']) ?></td><td><?= rupiah($row['suku_cadang']) ?></td><td><?= rupiah($row['jasa']) ?></td><td><strong><?= rupiah($row['total']) ?></strong></td><td><div class="mini-actions"><a href="<?= BASE_URL ?>/admin/perawatan/detail.php?id=<?= (int) $row['id'] ?>" class="mini-link">Lihat</a><button type="button" class="mini-btn mini-btn-edit" data-edit-maintenance='<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Edit</button><form method="post" action="<?= BASE_URL ?>/admin/perawatan/index.php" class="inline-delete-form" onsubmit="return confirm('Hapus data perawatan ini?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button type="submit" class="mini-btn mini-btn-delete">Hapus</button></form></div></td></tr><?php endforeach; ?></tbody></table>
  <?php endif; ?>
</div>

<div class="modal-backdrop" id="maintenance-modal" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="maintenance-title"><div class="modal-header"><h3 id="maintenance-title">Tambah Perawatan</h3><button type="button" class="modal-close" data-close-modal="maintenance-modal" aria-label="Tutup">×</button></div><form id="maintenance-form" method="post" action="<?= BASE_URL ?>/admin/perawatan/index.php"><input type="hidden" name="action" id="maintenance-action" value="create"><input type="hidden" name="id" id="maintenance-id" value=""><div class="modal-grid"><div class="field field-full"><label for="maintenance-unit">Unit Alat Berat</label><select id="maintenance-unit" name="alat_berat_id" required><option value="">Pilih unit</option><?php foreach ($units as $unit): ?><option value="<?= (int) $unit['id'] ?>"><?= e($unit['kode'] . ' - ' . $unit['tipe']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="maintenance-date">Tanggal</label><input id="maintenance-date" name="tanggal" type="date" value="<?= date('Y-m-d') ?>" required></div><div class="field"><label for="maintenance-finishing">Biaya Finishing</label><input id="maintenance-finishing" name="finishing" type="number" min="0" step="0.01" value="0"></div><div class="field"><label for="maintenance-sparepart">Biaya Suku Cadang</label><input id="maintenance-sparepart" name="suku_cadang" type="number" min="0" step="0.01" value="0"></div><div class="field"><label for="maintenance-service">Biaya Jasa</label><input id="maintenance-service" name="jasa" type="number" min="0" step="0.01" value="0"></div><div class="field field-full"><label for="maintenance-note">Keterangan</label><textarea id="maintenance-note" name="keterangan" rows="3" placeholder="Pekerjaan yang dilakukan..."></textarea></div></div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="maintenance-modal">Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form></div></div>

<script>
(function () {
  const modal = document.getElementById('maintenance-modal'); const form = document.getElementById('maintenance-form'); const action = document.getElementById('maintenance-action'); const id = document.getElementById('maintenance-id'); const title = document.getElementById('maintenance-title');
  if (!modal || !form) return;
  const fields = { alat_berat_id: document.getElementById('maintenance-unit'), tanggal: document.getElementById('maintenance-date'), finishing: document.getElementById('maintenance-finishing'), suku_cadang: document.getElementById('maintenance-sparepart'), jasa: document.getElementById('maintenance-service'), keterangan: document.getElementById('maintenance-note') };
  const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); form.reset(); action.value = 'create'; id.value = ''; title.textContent = 'Tambah Perawatan'; fields.tanggal.value = '<?= date('Y-m-d') ?>'; };
  const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); };
  document.querySelectorAll('[data-open-modal="maintenance-modal"]').forEach((button) => button.addEventListener('click', () => { close(); open(); }));
  document.querySelectorAll('[data-close-modal="maintenance-modal"]').forEach((button) => button.addEventListener('click', close));
  document.querySelectorAll('[data-edit-maintenance]').forEach((button) => button.addEventListener('click', () => { const data = JSON.parse(button.dataset.editMaintenance); action.value = 'update'; id.value = data.id; title.textContent = 'Edit Perawatan'; Object.keys(fields).forEach((name) => { fields[name].value = data[name] ?? (name === 'tanggal' ? '<?= date('Y-m-d') ?>' : 0); }); open(); }));
  modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); });
})();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>
