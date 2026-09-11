<?php
$pageTitle = 'Detail Perawatan';
$activeMenu = 'perawatan';
$breadcrumb = 'Produk & Inventori / Perawatan / Detail';
require_once __DIR__ . '/../../includes/header_admin.php';

$pdo = getDB();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $postId > 0) {
        $pdo->prepare('DELETE FROM alat_berat_perawatan WHERE id = :id')->execute([':id' => $postId]);
        flash_set('success', 'Data perawatan berhasil dihapus.');
        redirect('/admin/perawatan/index.php');
    }
    if ($action === 'update' && $postId > 0) {
        $alatBeratId = (int) ($_POST['alat_berat_id'] ?? 0);
        $tanggal = trim((string) ($_POST['tanggal'] ?? ''));
        if ($alatBeratId <= 0 || $tanggal === '') {
            flash_set('error', 'Unit alat berat dan tanggal perawatan wajib diisi.');
        } else {
            $stmt = $pdo->prepare('UPDATE alat_berat_perawatan SET alat_berat_id = :alat_berat_id, tanggal = :tanggal, keterangan = :keterangan, finishing = :finishing, suku_cadang = :suku_cadang, jasa = :jasa, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                ':alat_berat_id' => $alatBeratId,
                ':tanggal' => $tanggal,
                ':keterangan' => trim((string) ($_POST['keterangan'] ?? '')) ?: null,
                ':finishing' => max(0, (float) ($_POST['finishing'] ?? 0)),
                ':suku_cadang' => max(0, (float) ($_POST['suku_cadang'] ?? 0)),
                ':jasa' => max(0, (float) ($_POST['jasa'] ?? 0)),
                ':id' => $postId,
            ]);
            flash_set('success', 'Data perawatan berhasil diperbarui.');
        }
        redirect('/admin/perawatan/detail.php?id=' . $postId);
    }
}

$stmt = $pdo->prepare('SELECT ap.*, ab.kode, ab.tipe FROM alat_berat_perawatan ap INNER JOIN alat_berat ab ON ab.id = ap.alat_berat_id WHERE ap.id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    flash_set('error', 'Data perawatan tidak ditemukan.');
    redirect('/admin/perawatan/index.php');
}
$units = $pdo->query('SELECT id, kode, tipe FROM alat_berat ORDER BY kode ASC')->fetchAll();
?>

<div class="ab-detail-layout">
  <div class="ab-detail-panel">
    <div class="panel-head"><h3>Data Perawatan</h3><div class="detail-actions"><button type="button" class="btn btn-edit" data-open-maintenance-detail="maintenance-detail-modal">Edit</button><form method="post" action="<?= BASE_URL ?>/admin/perawatan/detail.php?id=<?= (int) $row['id'] ?>" onsubmit="return confirm('Hapus data perawatan ini?');" style="display:inline;"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button type="submit" class="btn btn-delete">Hapus</button></form></div></div>
        <table class="detail-table"><tr><th>Tanggal</th><td><?= e(tanggal_indo($row['tanggal'])) ?></td></tr><tr><th>Unit Alat Berat</th><td><strong><?= e($row['kode']) ?></strong><br><?= e($row['tipe']) ?></td></tr><tr><th>Keterangan</th><td><?= e($row['keterangan'] ?: '-') ?></td></tr></table>
    </div>
    <div class="ab-detail-panel"><div class="panel-head"><h3>Rincian Biaya</h3></div><div class="unit-summary"><div class="summary-row"><span class="label">Finishing</span><span class="value"><?= rupiah($row['finishing']) ?></span></div><div class="summary-row"><span class="label">Suku Cadang</span><span class="value"><?= rupiah($row['suku_cadang']) ?></span></div><div class="summary-row"><span class="label">Jasa</span><span class="value"><?= rupiah($row['jasa']) ?></span></div><div class="summary-row"><span class="label"><strong>Total Biaya</strong></span><span class="value"><strong><?= rupiah($row['total']) ?></strong></span></div></div></div>
</div>

<div class="modal-backdrop" id="maintenance-detail-modal" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="maintenance-detail-title"><div class="modal-header"><h3 id="maintenance-detail-title">Edit Perawatan</h3><button type="button" class="modal-close" data-close-maintenance-detail aria-label="Tutup">×</button></div><form method="post" action="<?= BASE_URL ?>/admin/perawatan/detail.php?id=<?= (int) $row['id'] ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><div class="modal-grid"><div class="field field-full"><label for="detail-unit">Unit Alat Berat</label><select id="detail-unit" name="alat_berat_id" required><?php foreach ($units as $unit): ?><option value="<?= (int) $unit['id'] ?>" <?= (int) $unit['id'] === (int) $row['alat_berat_id'] ? 'selected' : '' ?>><?= e($unit['kode'] . ' - ' . $unit['tipe']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="detail-tanggal">Tanggal</label><input id="detail-tanggal" name="tanggal" type="date" value="<?= e($row['tanggal']) ?>" required></div><div class="field"><label for="detail-finishing">Biaya Finishing</label><input id="detail-finishing" name="finishing" type="number" min="0" step="0.01" value="<?= (float) $row['finishing'] ?>"></div><div class="field"><label for="detail-suku-cadang">Biaya Suku Cadang</label><input id="detail-suku-cadang" name="suku_cadang" type="number" min="0" step="0.01" value="<?= (float) $row['suku_cadang'] ?>"></div><div class="field"><label for="detail-jasa">Biaya Jasa</label><input id="detail-jasa" name="jasa" type="number" min="0" step="0.01" value="<?= (float) $row['jasa'] ?>"></div><div class="field field-full"><label for="detail-keterangan">Keterangan</label><textarea id="detail-keterangan" name="keterangan" rows="3"><?= e($row['keterangan'] ?? '') ?></textarea></div></div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-maintenance-detail>Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form></div></div>

<script>
(function () { const modal = document.getElementById('maintenance-detail-modal'); if (!modal) return; const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); }; document.querySelector('[data-open-maintenance-detail]')?.addEventListener('click', () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }); document.querySelectorAll('[data-close-maintenance-detail]').forEach((button) => button.addEventListener('click', close)); modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); }); })();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>
