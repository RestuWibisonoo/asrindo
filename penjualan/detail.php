<?php
$pageTitle = 'Detail Penjualan';
$activeMenu = 'penjualan';
$breadcrumb = 'Transaksi / Penjualan / Detail';
require_once __DIR__ . '/../../includes/header_admin.php';
$pdo = getDB();
$id = (int) ($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $postId > 0) {
        $pdo->prepare('DELETE FROM penjualan WHERE id = :id')->execute([':id' => $postId]);
        flash_set('success', 'Transaksi penjualan berhasil dihapus.');
        redirect('/admin/penjualan/index.php');
    }
    if ($action === 'update' && $postId > 0) {
        $nomor = trim((string) ($_POST['nomor_penjualan'] ?? ''));
        $tanggal = trim((string) ($_POST['tanggal'] ?? ''));
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $unitId = (int) ($_POST['alat_berat_id'] ?? 0);
        $price = max(0, (float) ($_POST['harga_jual'] ?? 0));
        $discount = max(0, (float) ($_POST['diskon'] ?? 0));
        $hpp = max(0, (float) ($_POST['hpp'] ?? 0));
        $status = trim((string) ($_POST['status'] ?? 'DRAFT'));
        if ($nomor === '' || $tanggal === '' || $customerId <= 0 || $unitId <= 0 || $discount > $price || !in_array($status, ['DRAFT', 'CONFIRMED', 'PAID', 'CANCELLED'], true)) {
            flash_set('error', 'Data transaksi tidak valid.');
        } else {
            $note = trim((string) ($_POST['keterangan'] ?? '')) ?: null;
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE penjualan SET nomor_penjualan = :nomor, tanggal = :tanggal, customer_id = :customer_id, total = :total, status = :status, keterangan = :keterangan, updated_at = NOW() WHERE id = :id');
                $stmt->execute([':nomor' => $nomor, ':tanggal' => $tanggal, ':customer_id' => $customerId, ':total' => $price - $discount, ':status' => $status, ':keterangan' => $note, ':id' => $postId]);
                $stmt = $pdo->prepare('UPDATE penjualan_detail SET alat_berat_id = :unit_id, harga_jual = :price, diskon = :discount, hpp = :hpp, keterangan = :note, updated_at = NOW() WHERE penjualan_id = :sale_id');
                $stmt->execute([':unit_id' => $unitId, ':price' => $price, ':discount' => $discount, ':hpp' => $hpp, ':note' => $note, ':sale_id' => $postId]);
                $pdo->commit();
                flash_set('success', 'Transaksi penjualan berhasil diperbarui.');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash_set('error', 'Transaksi gagal diperbarui.');
            }
        }
        redirect('/admin/penjualan/detail.php?id=' . $postId);
    }
}
$stmt = $pdo->prepare('SELECT p.*, c.kode AS customer_kode, c.nama AS customer, pd.alat_berat_id, pd.harga_jual, pd.diskon, pd.hpp, pd.laba, ab.kode, ab.tipe FROM penjualan p INNER JOIN customer c ON c.id = p.customer_id LEFT JOIN penjualan_detail pd ON pd.penjualan_id = p.id LEFT JOIN alat_berat ab ON ab.id = pd.alat_berat_id WHERE p.id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$sale = $stmt->fetch();
if (!$sale) { flash_set('error', 'Transaksi penjualan tidak ditemukan.'); redirect('/admin/penjualan/index.php'); }
$customers = $pdo->query('SELECT id, kode, nama FROM customer ORDER BY nama ASC')->fetchAll();
$units = $pdo->query('SELECT id, kode, tipe FROM alat_berat ORDER BY kode ASC')->fetchAll();
$statuses = ['DRAFT', 'CONFIRMED', 'PAID', 'CANCELLED'];
?>

<div class="ab-detail-layout">
  <div class="ab-detail-panel"><div class="panel-head"><h3>Data Transaksi</h3><div class="detail-actions"><button type="button" class="btn btn-edit" data-open-sale-detail>Edit</button><form method="post" action="<?= BASE_URL ?>/admin/penjualan/detail.php?id=<?= (int) $sale['id'] ?>" onsubmit="return confirm('Hapus transaksi penjualan ini?');" style="display:inline;"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $sale['id'] ?>"><button type="submit" class="btn btn-delete">Hapus</button></form></div></div><table class="detail-table"><tr><th>Nomor Penjualan</th><td><?= e($sale['nomor_penjualan']) ?></td></tr><tr><th>Tanggal</th><td><?= e(tanggal_indo($sale['tanggal'])) ?></td></tr><tr><th>Customer</th><td><?= e($sale['customer_kode'] . ' - ' . $sale['customer']) ?></td></tr><tr><th>Status</th><td><span class="badge <?= status_badge_class($sale['status']) ?>"><?= e($sale['status']) ?></span></td></tr><tr><th>Keterangan</th><td><?= e($sale['keterangan'] ?: '-') ?></td></tr></table></div>
  <div class="ab-detail-panel"><div class="panel-head"><h3>Detail Unit &amp; Nilai</h3></div><div class="unit-summary"><div class="summary-row"><span class="label">Unit</span><span class="value"><?= e(($sale['kode'] ?: '-') . ' - ' . ($sale['tipe'] ?: '-')) ?></span></div><div class="summary-row"><span class="label">Harga Jual</span><span class="value"><?= rupiah($sale['harga_jual'] ?? 0) ?></span></div><div class="summary-row"><span class="label">Diskon</span><span class="value"><?= rupiah($sale['diskon'] ?? 0) ?></span></div><div class="summary-row"><span class="label">HPP</span><span class="value"><?= rupiah($sale['hpp'] ?? 0) ?></span></div><div class="summary-row"><span class="label"><strong>Total</strong></span><span class="value"><strong><?= rupiah($sale['total']) ?></strong></span></div><div class="summary-row"><span class="label"><strong>Laba</strong></span><span class="value"><strong><?= rupiah($sale['laba'] ?? 0) ?></strong></span></div></div></div>
</div>

<div class="modal-backdrop" id="sale-detail-modal" aria-hidden="true"><div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="sale-detail-title"><div class="modal-header"><h3 id="sale-detail-title">Edit Penjualan</h3><button type="button" class="modal-close" data-close-sale-detail aria-label="Tutup">×</button></div><form method="post" action="<?= BASE_URL ?>/admin/penjualan/detail.php?id=<?= (int) $sale['id'] ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $sale['id'] ?>"><div class="modal-grid"><div class="field"><label for="detail-number">Nomor Penjualan</label><input id="detail-number" name="nomor_penjualan" value="<?= e($sale['nomor_penjualan']) ?>" required></div><div class="field"><label for="detail-date">Tanggal</label><input id="detail-date" name="tanggal" type="date" value="<?= e($sale['tanggal']) ?>" required></div><div class="field field-full"><label for="detail-customer">Customer</label><select id="detail-customer" name="customer_id" required><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>" <?= (int) $customer['id'] === (int) $sale['customer_id'] ? 'selected' : '' ?>><?= e($customer['kode'] . ' - ' . $customer['nama']) ?></option><?php endforeach; ?></select></div><div class="field field-full"><label for="detail-unit">Unit Alat Berat</label><select id="detail-unit" name="alat_berat_id" required><?php foreach ($units as $unit): ?><option value="<?= (int) $unit['id'] ?>" <?= (int) $unit['id'] === (int) $sale['alat_berat_id'] ? 'selected' : '' ?>><?= e($unit['kode'] . ' - ' . $unit['tipe']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="detail-price">Harga Jual</label><input id="detail-price" name="harga_jual" type="number" min="0" step="0.01" value="<?= (float) ($sale['harga_jual'] ?? 0) ?>"></div><div class="field"><label for="detail-discount">Diskon</label><input id="detail-discount" name="diskon" type="number" min="0" step="0.01" value="<?= (float) ($sale['diskon'] ?? 0) ?>"></div><div class="field"><label for="detail-hpp">HPP</label><input id="detail-hpp" name="hpp" type="number" min="0" step="0.01" value="<?= (float) ($sale['hpp'] ?? 0) ?>"></div><div class="field"><label for="detail-status">Status</label><select id="detail-status" name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $sale['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div><div class="field field-full"><label for="detail-note">Keterangan</label><textarea id="detail-note" name="keterangan" rows="3"><?= e($sale['keterangan'] ?? '') ?></textarea></div></div><div class="modal-actions"><button type="button" class="btn btn-outline-dark btn-sm" data-close-sale-detail>Batal</button><button type="submit" class="btn btn-amber btn-sm">Simpan</button></div></form></div></div>

<script>
(function () { const modal = document.getElementById('sale-detail-modal'); if (!modal) return; const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); }; document.querySelector('[data-open-sale-detail]')?.addEventListener('click', () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }); document.querySelectorAll('[data-close-sale-detail]').forEach((button) => button.addEventListener('click', close)); modal.addEventListener('click', (event) => { if (event.target === modal) close(); }); document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('show')) close(); }); })();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>
