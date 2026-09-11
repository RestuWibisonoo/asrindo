<?php
$pageTitle = 'Detail Suku Cadang';
$activeMenu = 'sparepart';
$breadcrumb = 'Produk & Inventori / Suku Cadang / Detail';
require_once __DIR__ . '/../../includes/header_admin.php';

$pdo = getDB();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $postId > 0) {
        $pdo->prepare('DELETE FROM sparepart WHERE id = :id')->execute([':id' => $postId]);
        flash_set('success', 'Data suku cadang berhasil dihapus.');
        redirect('/admin/sparepart/index.php');
    }

    if (($action === 'update' || $action === 'update_price') && $postId > 0) {
        $data = [
            ':kode' => trim((string) ($_POST['kode'] ?? '')),
            ':nama' => trim((string) ($_POST['nama'] ?? '')),
            ':kategori' => trim((string) ($_POST['kategori'] ?? '')) ?: null,
            ':part_number' => trim((string) ($_POST['part_number'] ?? '')) ?: null,
            ':merk' => trim((string) ($_POST['merk'] ?? '')) ?: null,
            ':stok' => max(0, (int) ($_POST['stok'] ?? 0)),
            ':stok_minimum' => max(0, (int) ($_POST['stok_minimum'] ?? 0)),
            ':harga_modal' => max(0, (float) ($_POST['harga_modal'] ?? 0)),
            ':harga_jual' => max(0, (float) ($_POST['harga_jual'] ?? 0)),
            ':satuan' => trim((string) ($_POST['satuan'] ?? 'PCS')) ?: 'PCS',
            ':keterangan' => trim((string) ($_POST['keterangan'] ?? '')) ?: null,
            ':id' => $postId,
        ];
        if ($data[':kode'] === '' || $data[':nama'] === '') {
            flash_set('error', 'Kode dan nama suku cadang wajib diisi.');
        } else {
            $stmt = $pdo->prepare('UPDATE sparepart SET kode = :kode, nama = :nama, kategori = :kategori, part_number = :part_number, merk = :merk, stok = :stok, stok_minimum = :stok_minimum, harga_modal = :harga_modal, harga_jual = :harga_jual, satuan = :satuan, keterangan = :keterangan, updated_at = NOW() WHERE id = :id');
            $stmt->execute($data);
            flash_set('success', 'Data suku cadang berhasil diperbarui.');
        }
        redirect('/admin/sparepart/detail.php?id=' . $postId);
    }
}

$stmt = $pdo->prepare('SELECT * FROM sparepart WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$part = $stmt->fetch();
if (!$part) {
    flash_set('error', 'Data suku cadang tidak ditemukan.');
    redirect('/admin/sparepart/index.php');
}
?>

<div class="ab-detail-layout">
  <div class="ab-detail-panel">
    <div class="panel-head">
      <h3>Data Suku Cadang</h3>
      <div class="detail-actions">
        <button type="button" class="btn btn-edit" data-open-detail-edit="sp-detail-edit">Edit</button>
        <form method="post" action="<?= BASE_URL ?>/admin/sparepart/detail.php?id=<?= (int) $part['id'] ?>" onsubmit="return confirm('Hapus suku cadang ini?');" style="display:inline;">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $part['id'] ?>">
          <button type="submit" class="btn btn-delete">Hapus</button>
        </form>
      </div>
    </div>
    <table class="detail-table">
      <tr><th>Kode</th><td><?= e($part['kode']) ?></td></tr>
      <tr><th>Nama</th><td><?= e($part['nama']) ?></td></tr>
      <tr><th>Kategori</th><td><?= e($part['kategori'] ?: '-') ?></td></tr>
      <tr><th>Part Number</th><td><?= e($part['part_number'] ?: '-') ?></td></tr>
      <tr><th>Merk</th><td><?= e($part['merk'] ?: '-') ?></td></tr>
      <tr><th>Satuan</th><td><?= e($part['satuan']) ?></td></tr>
      <tr><th>Keterangan</th><td><?= e($part['keterangan'] ?: '-') ?></td></tr>
    </table>
  </div>

  <div class="ab-detail-panel">
    <div class="panel-head"><h3>Informasi Stok &amp; Harga</h3></div>
    <div class="unit-summary">
      <div class="summary-row"><span class="label">Stok Saat Ini</span><span class="value"><?= (int) $part['stok'] . ' ' . e($part['satuan']) ?></span></div>
      <div class="summary-row"><span class="label">Stok Minimum</span><span class="value"><?= (int) $part['stok_minimum'] . ' ' . e($part['satuan']) ?></span></div>
      <div class="summary-row"><span class="label">Harga Modal</span><span class="value"><?= rupiah($part['harga_modal']) ?></span></div>
      <div class="summary-row"><span class="label">Harga Jual</span><span class="value"><?= rupiah($part['harga_jual']) ?></span></div>
    </div>
    <div class="detail-box">
      <h4>Edit Stok &amp; Harga Lengkap</h4>
      <form method="post" action="<?= BASE_URL ?>/admin/sparepart/detail.php?id=<?= (int) $part['id'] ?>">
        <input type="hidden" name="action" value="update_price"><input type="hidden" name="id" value="<?= (int) $part['id'] ?>">
        <div class="price-grid">
          <div class="field"><label for="detail_stok">Stok</label><input id="detail_stok" name="stok" type="number" min="0" value="<?= (int) $part['stok'] ?>"></div>
          <div class="field"><label for="detail_stok_minimum">Stok Minimum</label><input id="detail_stok_minimum" name="stok_minimum" type="number" min="0" value="<?= (int) $part['stok_minimum'] ?>"></div>
          <div class="field"><label for="detail_harga_modal">Harga Modal</label><input id="detail_harga_modal" name="harga_modal" type="number" min="0" step="0.01" value="<?= (float) $part['harga_modal'] ?>"></div>
          <div class="field"><label for="detail_harga_jual">Harga Jual</label><input id="detail_harga_jual" name="harga_jual" type="number" min="0" step="0.01" value="<?= (float) $part['harga_jual'] ?>"></div>
        </div>
        <div class="modal-actions" style="margin-top:16px; justify-content:flex-start;"><button type="submit" class="btn btn-amber btn-sm">Simpan Stok &amp; Harga</button></div>
      </form>
    </div>
    <div class="detail-box"><h4>Status Persediaan</h4><div class="quick-note"><strong><?= (int) $part['stok'] <= (int) $part['stok_minimum'] ? 'Menipis' : 'Aman' ?></strong><span>Batas minimum: <?= (int) $part['stok_minimum'] . ' ' . e($part['satuan']) ?></span></div></div>
  </div>
</div>

<div class="modal-backdrop" id="sp-detail-edit" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="sp-detail-title"><div class="modal-header"><h3 id="sp-detail-title">Edit Suku Cadang</h3><button type="button" class="modal-close" data-close-modal="sp-detail-edit" aria-label="Tutup">×</button></div>
  <form method="post" action="<?= BASE_URL ?>/admin/sparepart/detail.php?id=<?= (int) $part['id'] ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $part['id'] ?>"><div class="modal-grid">
    <div class="field"><label for="edit-kode">Kode</label><input id="edit-kode" name="kode" value="<?= e($part['kode']) ?>" required></div><div class="field"><label for="edit-nama">Nama Suku Cadang</label><input id="edit-nama" name="nama" value="<?= e($part['nama']) ?>" required></div>
    <div class="field"><label for="edit-kategori">Kategori</label><input id="edit-kategori" name="kategori" value="<?= e($part['kategori'] ?? '') ?>"></div><div class="field"><label for="edit-part-number">Part Number</label><input id="edit-part-number" name="part_number" value="<?= e($part['part_number'] ?? '') ?>"></div>
    <div class="field"><label for="edit-merk">Merk</label><input id="edit-merk" name="merk" value="<?= e($part['merk'] ?? '') ?>"></div><div class="field"><label for="edit-satuan">Satuan</label><input id="edit-satuan" name="satuan" value="<?= e($part['satuan']) ?>" required></div>
    <div class="field"><label for="edit-stok">Stok</label><input id="edit-stok" name="stok" type="number" min="0" value="<?= (int) $part['stok'] ?>"></div><div class="field"><label for="edit-stok-minimum">Stok Minimum</label><input id="edit-stok-minimum" name="stok_minimum" type="number" min="0" value="<?= (int) $part['stok_minimum'] ?>"></div>
    <div class="field"><label for="edit-harga-modal">Harga Modal</label><input id="edit-harga-modal" name="harga_modal" type="number" min="0" step="0.01" value="<?= (float) $part['harga_modal'] ?>"></div><div class="field"><label for="edit-harga-jual">Harga Jual</label><input id="edit-harga-jual" name="harga_jual" type="number" min="0" step="0.01" value="<?= (float) $part['harga_jual'] ?>"></div>
    <div class="field field-full"><label for="edit-keterangan">Keterangan</label><textarea id="edit-keterangan" name="keterangan" rows="3"><?= e($part['keterangan'] ?? '') ?></textarea></div>
  </div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="sp-detail-edit">Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form>
</div></div>

<script>
(function () {
  const modal = document.getElementById('sp-detail-edit'); if (!modal) return;
  const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); };
  document.querySelectorAll('[data-open-detail-edit="sp-detail-edit"]').forEach((button) => button.addEventListener('click', () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }));
  document.querySelectorAll('[data-close-modal="sp-detail-edit"]').forEach((button) => button.addEventListener('click', close));
  modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); });
})();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>
