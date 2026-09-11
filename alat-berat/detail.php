<?php
$pageTitle = 'Detail Alat Berat';
$activeMenu = 'alat-berat';
$breadcrumb = 'Produk & Inventori / Alat Berat / Detail';
require_once __DIR__ . '/../../includes/header_admin.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if (($action = ($_POST['action'] ?? '')) === 'delete') {
        if ($id > 0) {
            $pdo->prepare('DELETE FROM alat_berat WHERE id = :id')->execute([':id' => $id]);
            flash_set('success', 'Data alat berat berhasil dihapus.');
            redirect('/admin/alat-berat/index.php');
        }
    }

    if ($action === 'update' || $action === 'create') {
        $kode = trim((string) ($_POST['kode'] ?? ''));
        $tipe = trim((string) ($_POST['tipe'] ?? ''));
        if ($kode === '' || $tipe === '') {
            flash_set('error', 'Kode unit dan tipe/model wajib diisi.');
            redirect('/admin/alat-berat/detail.php?id=' . $id);
        }

        $nomorRangka = trim((string) ($_POST['nomor_rangka'] ?? ''));
        $nomorMesin = trim((string) ($_POST['nomor_mesin'] ?? ''));
        $tahun = trim((string) ($_POST['tahun_pembuatan'] ?? ''));
        $kondisi = trim((string) ($_POST['kondisi'] ?? 'Baru'));
        $status = trim((string) ($_POST['status'] ?? 'Tersedia'));
        $lokasi = trim((string) ($_POST['lokasi'] ?? ''));
        $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
        $kursBeli = (float) ($_POST['kurs_beli'] ?? 0);
        $hargaBeliUsd = (float) ($_POST['harga_beli_usd'] ?? 0);
        $hargaBeliIdr = (float) ($_POST['harga_beli_idr'] ?? 0);
        $hargaJualEstimasi = (float) ($_POST['harga_jual_estimasi'] ?? 0);
        $hargaSewaAllIn = (float) ($_POST['harga_sewa_all_in'] ?? 0);
        $hargaSewaKosongan = (float) ($_POST['harga_sewa_kosongan'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE alat_berat SET kode = :kode, tipe = :tipe, nomor_rangka = :nomor_rangka, nomor_mesin = :nomor_mesin, tahun_pembuatan = :tahun_pembuatan, kondisi = :kondisi, status = :status, lokasi = :lokasi, deskripsi = :deskripsi, kurs_beli = :kurs_beli, harga_beli_usd = :harga_beli_usd, harga_beli_idr = :harga_beli_idr, harga_jual_estimasi = :harga_jual_estimasi, harga_sewa_all_in = :harga_sewa_all_in, harga_sewa_kosongan = :harga_sewa_kosongan, updated_at = NOW() WHERE id = :id'
            );
            $stmt->execute([
                ':kode' => $kode,
                ':tipe' => $tipe,
                ':nomor_rangka' => $nomorRangka !== '' ? $nomorRangka : null,
                ':nomor_mesin' => $nomorMesin !== '' ? $nomorMesin : null,
                ':tahun_pembuatan' => $tahun !== '' ? $tahun : null,
                ':kondisi' => $kondisi,
                ':status' => $status,
                ':lokasi' => $lokasi !== '' ? $lokasi : null,
                ':deskripsi' => $deskripsi !== '' ? $deskripsi : null,
                ':kurs_beli' => $kursBeli,
                ':harga_beli_usd' => $hargaBeliUsd,
                ':harga_beli_idr' => $hargaBeliIdr,
                ':harga_jual_estimasi' => $hargaJualEstimasi,
                ':harga_sewa_all_in' => $hargaSewaAllIn,
                ':harga_sewa_kosongan' => $hargaSewaKosongan,
                ':id' => $id,
            ]);
            flash_set('success', 'Data alat berat berhasil diperbarui.');
        }
        redirect('/admin/alat-berat/detail.php?id=' . $id);
    }

    if (($action = ($_POST['action'] ?? '')) === 'update_price') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE alat_berat SET kurs_beli = :kurs_beli, harga_beli_usd = :harga_beli_usd, harga_beli_idr = :harga_beli_idr, harga_jual_estimasi = :harga_jual_estimasi, harga_sewa_all_in = :harga_sewa_all_in, harga_sewa_kosongan = :harga_sewa_kosongan, updated_at = NOW() WHERE id = :id'
            );
            $stmt->execute([
                ':kurs_beli' => (float) ($_POST['kurs_beli'] ?? 0),
                ':harga_beli_usd' => (float) ($_POST['harga_beli_usd'] ?? 0),
                ':harga_beli_idr' => (float) ($_POST['harga_beli_idr'] ?? 0),
                ':harga_jual_estimasi' => (float) ($_POST['harga_jual_estimasi'] ?? 0),
                ':harga_sewa_all_in' => (float) ($_POST['harga_sewa_all_in'] ?? 0),
                ':harga_sewa_kosongan' => (float) ($_POST['harga_sewa_kosongan'] ?? 0),
                ':id' => $id,
            ]);
            flash_set('success', 'Data harga alat berat berhasil diperbarui.');
            redirect('/admin/alat-berat/detail.php?id=' . $id);
        }
    }
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$unit = $id ? $pdo->prepare('SELECT * FROM alat_berat WHERE id = :id LIMIT 1') : null;
if ($unit) {
    $unit->execute([':id' => $id]);
    $unit = $unit->fetch();
}

if (!$unit) {
    $unit = [
        'kode' => 'SUP003',
        'tipe' => 'berat bgt',
        'nomor_rangka' => 'asdadad',
        'nomor_mesin' => '21312',
        'tahun_pembuatan' => '2026',
        'kondisi' => 'Baru',
        'status' => 'Tersedia',
        'lokasi' => '',
        'deskripsi' => '',
        'harga_beli_usd' => 0,
        'kurs_beli' => 0,
        'harga_beli_idr' => 0,
        'harga_jual_estimasi' => 0,
        'harga_sewa_all_in' => 0,
        'harga_sewa_kosongan' => 0,
        'total_jam_operasional' => 0,
        'jam_operasional_terakhir' => 0,
    ];
}
?>

<div class="ab-detail-layout">
  <div class="ab-detail-panel">
    <div class="panel-head">
      <h3>Data Umum Unit</h3>
      <div class="detail-actions">
        <button type="button" class="btn btn-edit" data-open-detail-edit="ab-detail-edit">Edit</button>
        <form method="post" action="<?= BASE_URL ?>/admin/alat-berat/detail.php?id=<?= (int) $unit['id'] ?>" onsubmit="return confirm('Hapus alat berat ini?');" style="display:inline;">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $unit['id'] ?>">
          <button type="submit" class="btn btn-delete">Hapus</button>
        </form>
      </div>
    </div>

    <table class="detail-table">
      <tr>
        <th>Kode Unit</th>
        <td><?= e($unit['kode']) ?></td>
      </tr>
      <tr>
        <th>Type / Model</th>
        <td><?= e($unit['tipe']) ?></td>
      </tr>
      <tr>
        <th>Nomor Rangka</th>
        <td><?= e($unit['nomor_rangka'] ?: '-') ?></td>
      </tr>
      <tr>
        <th>Nomor Mesin</th>
        <td><?= e($unit['nomor_mesin'] ?: '-') ?></td>
      </tr>
      <tr>
        <th>Tahun Pembuatan</th>
        <td><?= e($unit['tahun_pembuatan'] ?: '-') ?></td>
      </tr>
      <tr>
        <th>Kondisi</th>
        <td><?= e($unit['kondisi']) ?></td>
      </tr>
      <tr>
        <th>Status</th>
        <td><span class="detail-status"><?= e($unit['status']) ?></span></td>
      </tr>
      <tr>
        <th>Lokasi Saat Ini</th>
        <td><?= e($unit['lokasi'] ?: '-') ?></td>
      </tr>
      <tr>
        <th>Keterangan Tambahan</th>
        <td><?= e($unit['deskripsi'] ?: '-') ?></td>
      </tr>
    </table>

    <div class="detail-operational">
      <div class="panel-head" style="padding:0 0 12px; background:none; border:none;">
        <h3 style="font-size:1.08rem;">Data Operasional (Hour Meter)</h3>
      </div>
      <div class="detail-metrics">
        <div class="metric-box">
          <span class="value"><?= number_format((float) ($unit['total_jam_operasional'] ?? 0), 1) ?></span>
          <span class="label">Total HM Aktual</span>
        </div>
        <div class="metric-box">
          <span class="value"><?= number_format((float) ($unit['jam_operasional_terakhir'] ?? 0), 1) ?></span>
          <span class="label">HM Pemakaian Terakhir</span>
        </div>
      </div>
    </div>
  </div>

  <div class="ab-detail-panel">
    <div class="panel-head">
      <h3>Informasi Harga Unit</h3>
    </div>
    <div class="unit-summary">
      <div class="summary-row">
        <span class="label">Harga Beli (USD)</span>
        <span class="value">$ <?= number_format((float) ($unit['harga_beli_usd'] ?? 0), 2) ?></span>
      </div>
      <div class="summary-row">
        <span class="label">Kurs Berlaku</span>
        <span class="value"><?= rupiah($unit['kurs_beli'] ?? 0) ?></span>
      </div>
      <div class="summary-row">
        <span class="label">Total Harga Pokok (IDR)</span>
        <span class="value"><?= rupiah($unit['harga_beli_idr'] ?? 0) ?></span>
      </div>
      <div class="summary-row">
        <span class="label">Estimasi Harga Jual</span>
        <span class="value"><?= rupiah($unit['harga_jual_estimasi'] ?? 0) ?></span>
      </div>
    </div>
    <div class="detail-box">
      <h4>Tarif Rental (Per Jam)</h4>
      <div class="summary-row">
        <span class="label">Paket All-in</span>
        <span class="value"><?= rupiah($unit['harga_sewa_all_in'] ?? 0) ?></span>
      </div>
      <div class="summary-row">
        <span class="label">Paket Kosongan</span>
        <span class="value"><?= rupiah($unit['harga_sewa_kosongan'] ?? 0) ?></span>
      </div>
    </div>

    <div class="detail-box">
      <h4>Edit Harga Lengkap</h4>
      <form method="post" action="<?= BASE_URL ?>/admin/alat-berat/detail.php?id=<?= (int) $unit['id'] ?>">
        <input type="hidden" name="action" value="update_price">
        <input type="hidden" name="id" value="<?= (int) $unit['id'] ?>">
        <div class="price-grid">
          <div class="field">
            <label for="detail_harga_beli_usd">Harga Beli (USD)</label>
            <input id="detail_harga_beli_usd" name="harga_beli_usd" type="number" step="0.01" value="<?= (float) ($unit['harga_beli_usd'] ?? 0) ?>">
          </div>
          <div class="field">
            <label for="detail_kurs_beli">Kurs Berlaku</label>
            <input id="detail_kurs_beli" name="kurs_beli" type="number" step="0.01" value="<?= (float) ($unit['kurs_beli'] ?? 0) ?>">
          </div>
          <div class="field">
            <label for="detail_harga_beli_idr">Harga Beli (IDR)</label>
            <input id="detail_harga_beli_idr" name="harga_beli_idr" type="number" step="0.01" value="<?= (float) ($unit['harga_beli_idr'] ?? 0) ?>">
          </div>
          <div class="field">
            <label for="detail_harga_jual_estimasi">Estimasi Harga Jual</label>
            <input id="detail_harga_jual_estimasi" name="harga_jual_estimasi" type="number" step="0.01" value="<?= (float) ($unit['harga_jual_estimasi'] ?? 0) ?>">
          </div>
          <div class="field">
            <label for="detail_harga_sewa_all_in">Tarif Sewa All-in</label>
            <input id="detail_harga_sewa_all_in" name="harga_sewa_all_in" type="number" step="0.01" value="<?= (float) ($unit['harga_sewa_all_in'] ?? 0) ?>">
          </div>
          <div class="field">
            <label for="detail_harga_sewa_kosongan">Tarif Sewa Kosongan</label>
            <input id="detail_harga_sewa_kosongan" name="harga_sewa_kosongan" type="number" step="0.01" value="<?= (float) ($unit['harga_sewa_kosongan'] ?? 0) ?>">
          </div>
        </div>
        <div class="modal-actions" style="margin-top:16px; justify-content:flex-start;">
          <button type="submit" class="btn btn-amber btn-sm">Simpan Harga</button>
        </div>
      </form>
    </div>
    <div class="detail-box">
      <h4>Log Perawatan (Maintenance)</h4>
      <div class="empty-mini">Data perawatan akan muncul di sini.</div>
    </div>
    <div class="detail-box">
      <h4>Riwayat Penyewaan &amp; Penjualan</h4>
      <div class="empty-mini">Riwayat transaksi akan muncul di sini.</div>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="ab-detail-edit" aria-hidden="true">
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="ab-detail-title">
    <div class="modal-header">
      <h3 id="ab-detail-title">Edit Alat Berat</h3>
      <button type="button" class="modal-close" data-close-modal="ab-detail-edit" aria-label="Tutup">×</button>
    </div>

    <form method="post" action="<?= BASE_URL ?>/admin/alat-berat/detail.php?id=<?= (int) $unit['id'] ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int) $unit['id'] ?>">
      <div class="modal-grid">
        <div class="field">
          <label for="detail_kode">Kode Unit</label>
          <input id="detail_kode" name="kode" type="text" value="<?= e($unit['kode']) ?>" required>
        </div>
        <div class="field">
          <label for="detail_tipe">Type / Model</label>
          <input id="detail_tipe" name="tipe" type="text" value="<?= e($unit['tipe']) ?>" required>
        </div>
        <div class="field">
          <label for="detail_nomor_rangka">Nomor Rangka</label>
          <input id="detail_nomor_rangka" name="nomor_rangka" type="text" value="<?= e($unit['nomor_rangka'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="detail_nomor_mesin">Nomor Mesin</label>
          <input id="detail_nomor_mesin" name="nomor_mesin" type="text" value="<?= e($unit['nomor_mesin'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="detail_tahun_pembuatan">Tahun Pembuatan</label>
          <input id="detail_tahun_pembuatan" name="tahun_pembuatan" type="number" min="1900" max="2100" value="<?= e($unit['tahun_pembuatan'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="detail_kondisi">Kondisi</label>
          <select id="detail_kondisi" name="kondisi">
            <option value="Baru" <?= (($unit['kondisi'] ?? '') === 'Baru') ? 'selected' : '' ?>>Baru</option>
            <option value="Bekas" <?= (($unit['kondisi'] ?? '') === 'Bekas') ? 'selected' : '' ?>>Bekas</option>
            <option value="Rekondisi" <?= (($unit['kondisi'] ?? '') === 'Rekondisi') ? 'selected' : '' ?>>Rekondisi</option>
          </select>
        </div>
        <div class="field">
          <label for="detail_status">Status</label>
          <select id="detail_status" name="status">
            <option value="Tersedia" <?= (($unit['status'] ?? '') === 'Tersedia') ? 'selected' : '' ?>>Tersedia</option>
            <option value="Siap Jual" <?= (($unit['status'] ?? '') === 'Siap Jual') ? 'selected' : '' ?>>Siap Jual</option>
            <option value="Perbaikan" <?= (($unit['status'] ?? '') === 'Perbaikan') ? 'selected' : '' ?>>Perbaikan</option>
            <option value="Dipesan" <?= (($unit['status'] ?? '') === 'Dipesan') ? 'selected' : '' ?>>Dipesan</option>
            <option value="Terjual" <?= (($unit['status'] ?? '') === 'Terjual') ? 'selected' : '' ?>>Terjual</option>
            <option value="Rusak" <?= (($unit['status'] ?? '') === 'Rusak') ? 'selected' : '' ?>>Rusak</option>
          </select>
        </div>
        <div class="field">
          <label for="detail_lokasi">Lokasi</label>
          <input id="detail_lokasi" name="lokasi" type="text" value="<?= e($unit['lokasi'] ?? '') ?>">
        </div>
        <div class="field field-full">
          <label for="detail_deskripsi">Keterangan Tambahan</label>
          <textarea id="detail_deskripsi" name="deskripsi" rows="3"><?= e($unit['deskripsi'] ?? '') ?></textarea>
        </div>
        <div class="field field-full">
          <label>Harga Lengkap</label>
          <div class="price-grid">
            <div class="field">
              <label for="detail_modal_harga_beli_usd">Harga Beli (USD)</label>
              <input id="detail_modal_harga_beli_usd" name="harga_beli_usd" type="number" step="0.01" value="<?= (float) ($unit['harga_beli_usd'] ?? 0) ?>">
            </div>
            <div class="field">
              <label for="detail_modal_kurs_beli">Kurs Berlaku</label>
              <input id="detail_modal_kurs_beli" name="kurs_beli" type="number" step="0.01" value="<?= (float) ($unit['kurs_beli'] ?? 0) ?>">
            </div>
            <div class="field">
              <label for="detail_modal_harga_beli_idr">Harga Beli (IDR)</label>
              <input id="detail_modal_harga_beli_idr" name="harga_beli_idr" type="number" step="0.01" value="<?= (float) ($unit['harga_beli_idr'] ?? 0) ?>">
            </div>
            <div class="field">
              <label for="detail_modal_harga_jual_estimasi">Estimasi Harga Jual</label>
              <input id="detail_modal_harga_jual_estimasi" name="harga_jual_estimasi" type="number" step="0.01" value="<?= (float) ($unit['harga_jual_estimasi'] ?? 0) ?>">
            </div>
            <div class="field">
              <label for="detail_modal_harga_sewa_all_in">Tarif Sewa All-in</label>
              <input id="detail_modal_harga_sewa_all_in" name="harga_sewa_all_in" type="number" step="0.01" value="<?= (float) ($unit['harga_sewa_all_in'] ?? 0) ?>">
            </div>
            <div class="field">
              <label for="detail_modal_harga_sewa_kosongan">Tarif Sewa Kosongan</label>
              <input id="detail_modal_harga_sewa_kosongan" name="harga_sewa_kosongan" type="number" step="0.01" value="<?= (float) ($unit['harga_sewa_kosongan'] ?? 0) ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="ab-detail-edit">Batal</button>
        <button type="submit" class="btn btn-amber btn-sm">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    const modal = document.getElementById('ab-detail-edit');
    if (!modal) return;

    document.querySelectorAll('[data-open-detail-edit]').forEach((btn) => {
      btn.addEventListener('click', () => {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
      });
    });

    document.querySelectorAll('[data-close-modal="ab-detail-edit"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
      });
    });

    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
      }
    });
  })();
</script>

<?php require __DIR__ . '/../../includes/footer_admin.php'; ?>
