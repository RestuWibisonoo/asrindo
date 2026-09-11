<?php
$pageTitle = "Alat Berat";
$activeMenu = "alat-berat";
$breadcrumb = "Produk & Inventori / Alat Berat";
require_once __DIR__ . "/../../includes/header_admin.php";

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM alat_berat WHERE id = :id')->execute([':id' => $id]);
            flash_set('success', 'Data alat berat berhasil dihapus.');
        }
        redirect('/admin/alat-berat/index.php');
    }

    $kode = trim((string) ($_POST['kode'] ?? ''));
    $tipe = trim((string) ($_POST['tipe'] ?? ''));

    if ($kode === '' || $tipe === '') {
        flash_set('error', 'Kode unit dan tipe/model wajib diisi.');
        redirect('/admin/alat-berat/index.php');
    }

    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
    $nomorRangka = trim((string) ($_POST['nomor_rangka'] ?? ''));
    $nomorMesin = trim((string) ($_POST['nomor_mesin'] ?? ''));
    $tahun = trim((string) ($_POST['tahun_pembuatan'] ?? ''));
    $kondisi = trim((string) ($_POST['kondisi'] ?? 'Baru'));
    $status = trim((string) ($_POST['status'] ?? 'Tersedia'));
    $lokasi = trim((string) ($_POST['lokasi'] ?? ''));
    $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
    $hargaBeliUsd = (float) ($_POST['harga_beli_usd'] ?? 0);
    $kursBeli = (float) ($_POST['kurs_beli'] ?? 0);
    $hargaBeliIdr = (float) ($_POST['harga_beli_idr'] ?? 0);
    $hargaJualEstimasi = (float) ($_POST['harga_jual_estimasi'] ?? 0);
    $hargaSewaAllIn = (float) ($_POST['harga_sewa_all_in'] ?? 0);
    $hargaSewaKosongan = (float) ($_POST['harga_sewa_kosongan'] ?? 0);

    if ($id) {
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
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO alat_berat (kode, tipe, nomor_rangka, nomor_mesin, tahun_pembuatan, kondisi, status, lokasi, deskripsi, kurs_beli, harga_beli_usd, harga_beli_idr, harga_jual_estimasi, harga_sewa_all_in, harga_sewa_kosongan, total_jam_operasional, jam_operasional_terakhir, created_at, updated_at)
             VALUES (:kode, :tipe, :nomor_rangka, :nomor_mesin, :tahun_pembuatan, :kondisi, :status, :lokasi, :deskripsi, :kurs_beli, :harga_beli_usd, :harga_beli_idr, :harga_jual_estimasi, :harga_sewa_all_in, :harga_sewa_kosongan, 0, 0, NOW(), NOW())'
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
        ]);
        flash_set('success', 'Data alat berat berhasil ditambahkan.');
    }

    redirect('/admin/alat-berat/index.php');
}

$statusFilter = $_GET['status'] ?? 'all';
$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$conditions = [];

if ($statusFilter !== 'all' && $statusFilter !== '') {
    $conditions[] = 'ab.status = :status';
    $params[':status'] = $statusFilter;
}

if ($search !== '') {
    $conditions[] = '(ab.kode LIKE :q OR ab.tipe LIKE :q OR ab.nomor_rangka LIKE :q OR ab.nomor_mesin LIKE :q OR ab.lokasi LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$sql = "
    SELECT ab.*,
        COALESCE((
            SELECT g.file_foto
            FROM galeri_alat_berat g
            WHERE g.alat_berat_id = ab.id AND g.tampil_publik = 1
            ORDER BY g.id DESC
            LIMIT 1
        ), '') AS foto
    FROM alat_berat ab
";

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY ab.updated_at DESC, ab.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll();

$totalUnit = (int) $pdo->query('SELECT COUNT(*) FROM alat_berat')->fetchColumn();
$totalNilai = (float) $pdo->query('SELECT COALESCE(SUM(harga_beli_idr),0) FROM alat_berat')->fetchColumn();
$readyUnit = (int) $pdo->query("SELECT COUNT(*) FROM alat_berat WHERE status IN ('Tersedia', 'Siap Jual')")->fetchColumn();
$maintenanceUnit = (int) $pdo->query("SELECT COUNT(*) FROM alat_berat WHERE status IN ('Perbaikan', 'Dalam Perbaikan')")->fetchColumn();
$statusList = $pdo->query('SELECT status, COUNT(*) jumlah FROM alat_berat GROUP BY status ORDER BY jumlah DESC, status ASC')->fetchAll();
$statusOptions = ['Tersedia', 'Siap Jual', 'Perbaikan', 'Dipesan', 'Terjual', 'Rusak'];
?>

<div class="stat-cards">
  <div class="stat-card">
    <div class="label">Total Unit</div>
    <div class="value"><?= $totalUnit ?></div>
    <div class="sub">Seluruh unit aktif di inventori</div>
  </div>
  <div class="stat-card">
    <div class="label">Nilai Inventori</div>
    <div class="value"><?= rupiah($totalNilai) ?></div>
    <div class="sub">Berdasarkan harga beli IDR</div>
  </div>
  <div class="stat-card">
    <div class="label">Siap Dijual</div>
    <div class="value"><?= $readyUnit ?></div>
    <div class="sub">Unit dengan status tersedia</div>
  </div>
  <div class="stat-card">
    <div class="label">Dalam Perbaikan</div>
    <div class="value"><?= $maintenanceUnit ?></div>
    <div class="sub">Unit yang sedang ditangani</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div>
      <div class="card-title">Inventori Alat Berat</div>
      <div class="card-sub">Pantau ketersediaan, status, lokasi, dan estimasi nilai unit</div>
    </div>
    <div class="toolbar-form">
      <button type="button" class="btn btn-amber btn-sm" data-open-modal="ab-modal">+ Tambah Alat Berat</button>
      <form method="get" class="toolbar-form-inline">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari kode, tipe, lokasi..." aria-label="Cari alat berat">
        <select name="status" aria-label="Filter status">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua status</option>
          <?php foreach ($statusOptions as $option): ?>
            <option value="<?= e($option) ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-amber btn-sm">Filter</button>
        <a href="<?= BASE_URL ?>/admin/alat-berat/index.php" class="btn btn-outline-dark btn-sm">Reset</a>
      </form>
    </div>
  </div>

  <?php if (empty($units)): ?>
    <div class="empty-state">
      <div class="icon">&#9881;</div>
      <h3 style="margin-bottom:6px;">Belum ada data unit</h3>
      <p style="max-width:44ch; margin:0 auto;">Tidak ada alat berat yang cocok dengan filter saat ini. Coba ubah kata kunci atau status pencarian.</p>
    </div>
  <?php else: ?>
    <table class="data-table inventory-table">
      <thead>
        <tr>
          <th style="width:72px;">Foto</th>
          <th>Unit</th>
          <th>Nomor</th>
          <th>Thn</th>
          <th>Status</th>
          <th>Kondisi</th>
          <th>Lokasi</th>
          <th>Estimasi Jual</th>
          <th style="width:90px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($units as $unit): ?>
        <tr id="unit-<?= (int) $unit['id'] ?>">
          <td>
            <?php if (!empty($unit['foto'])): ?>
              <div class="inventory-thumb">
                <img src="<?= e($unit['foto']) ?>" alt="<?= e($unit['tipe']) ?>">
              </div>
            <?php else: ?>
              <div class="inventory-thumb placeholder">AB</div>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= e($unit['kode']) ?></strong><br>
            <span class="muted"><?= e($unit['tipe']) ?></span>
          </td>
          <td>
            <?= e($unit['nomor_rangka'] ?: '-') ?><br>
            <span class="muted"><?= e($unit['nomor_mesin'] ?: '-') ?></span>
          </td>
          <td><?= e($unit['tahun_pembuatan'] ?: '-') ?></td>
          <td><span class="badge <?= status_badge_class($unit['status']) ?>"><?= e($unit['status']) ?></span></td>
          <td><?= e($unit['kondisi']) ?></td>
          <td><?= e($unit['lokasi'] ?: '-') ?></td>
          <td><?= rupiah($unit['harga_jual_estimasi']) ?></td>
          <td>
            <div class="mini-actions">
              <a href="<?= BASE_URL ?>/admin/alat-berat/detail.php?id=<?= (int) $unit['id'] ?>" class="mini-link">Lihat</a>
              <button type="button" class="mini-btn mini-btn-edit" data-edit-unit='<?= json_encode([
                  'id' => (int) $unit['id'],
                  'kode' => $unit['kode'],
                  'tipe' => $unit['tipe'],
                  'nomor_rangka' => $unit['nomor_rangka'],
                  'nomor_mesin' => $unit['nomor_mesin'],
                  'tahun_pembuatan' => $unit['tahun_pembuatan'],
                  'kondisi' => $unit['kondisi'],
                  'status' => $unit['status'],
                  'lokasi' => $unit['lokasi'],
                  'deskripsi' => $unit['deskripsi'],
                  'harga_beli_usd' => $unit['harga_beli_usd'],
                  'kurs_beli' => $unit['kurs_beli'],
                  'harga_beli_idr' => $unit['harga_beli_idr'],
                  'harga_jual_estimasi' => $unit['harga_jual_estimasi'],
                  'harga_sewa_all_in' => $unit['harga_sewa_all_in'],
                  'harga_sewa_kosongan' => $unit['harga_sewa_kosongan'],
              ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>Edit</button>
              <form method="post" action="<?= BASE_URL ?>/admin/alat-berat/index.php" class="inline-delete-form" onsubmit="return confirm('Hapus alat berat ini?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $unit['id'] ?>">
                <button type="submit" class="mini-btn mini-btn-delete">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div style="display:grid; grid-template-columns:1.2fr .8fr; gap:20px; margin-top:20px;">
  <div class="card">
    <div class="card-title">Distribusi Status Unit</div>
    <div class="card-sub">Ringkasan status inventori saat ini</div>
    <table class="data-table">
      <thead>
        <tr><th>Status</th><th>Jumlah</th></tr>
      </thead>
      <tbody>
        <?php if (empty($statusList)): ?>
          <tr><td colspan="2">Belum ada data status.</td></tr>
        <?php else: ?>
          <?php foreach ($statusList as $item): ?>
            <tr>
              <td><span class="badge <?= status_badge_class($item['status']) ?>"><?= e($item['status']) ?></span></td>
              <td><?= (int) $item['jumlah'] ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-title">Catatan Operasional</div>
    <div class="card-sub">Monitoring cepat untuk tim inventory</div>
    <div class="quick-note">
      <strong><?= $readyUnit ?></strong>
      <span>unit siap dijual atau tersedia</span>
    </div>
    <div class="quick-note">
      <strong><?= $maintenanceUnit ?></strong>
      <span>unit sedang dalam perbaikan</span>
    </div>
    <div class="quick-note">
      <strong><?= rupiah($totalNilai) ?></strong>
      <span>nilai total inventori</span>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="ab-modal" aria-hidden="true">
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="ab-modal-title">
    <div class="modal-header">
      <h3 id="ab-modal-title">Tambah Alat Berat</h3>
      <button type="button" class="modal-close" data-close-modal="ab-modal" aria-label="Tutup">×</button>
    </div>

    <form id="ab-form" method="post" action="<?= BASE_URL ?>/admin/alat-berat/index.php">
      <input type="hidden" name="action" id="ab-form-action" value="create">
      <input type="hidden" name="id" id="ab-form-id" value="">
      <div class="modal-grid">
        <div class="field">
          <label for="kode">Kode Unit</label>
          <input id="kode" name="kode" type="text" placeholder="contoh: AGM 099" required>
        </div>
        <div class="field">
          <label for="tipe">Type / Model</label>
          <input id="tipe" name="tipe" type="text" placeholder="contoh: CAT 306" required>
        </div>
        <div class="field">
          <label for="nomor_rangka">Nomor Rangka</label>
          <input id="nomor_rangka" name="nomor_rangka" type="text" placeholder="Nomor rangka">
        </div>
        <div class="field">
          <label for="nomor_mesin">Nomor Mesin</label>
          <input id="nomor_mesin" name="nomor_mesin" type="text" placeholder="Nomor mesin">
        </div>
        <div class="field">
          <label for="tahun_pembuatan">Tahun Pembuatan</label>
          <input id="tahun_pembuatan" name="tahun_pembuatan" type="number" min="1900" max="2100" placeholder="2026">
        </div>
        <div class="field">
          <label for="kondisi">Kondisi</label>
          <select id="kondisi" name="kondisi">
            <option value="Baru">Baru</option>
            <option value="Bekas">Bekas</option>
            <option value="Rekondisi">Rekondisi</option>
          </select>
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="Tersedia">Tersedia</option>
            <option value="Siap Jual">Siap Jual</option>
            <option value="Perbaikan">Perbaikan</option>
            <option value="Dipesan">Dipesan</option>
            <option value="Terjual">Terjual</option>
            <option value="Rusak">Rusak</option>
          </select>
        </div>
        <div class="field">
          <label for="lokasi">Lokasi Saat Ini</label>
          <input id="lokasi" name="lokasi" type="text" placeholder="contoh: Garasi AGM">
        </div>
        <div class="field field-full">
          <label for="deskripsi">Keterangan Tambahan</label>
          <textarea id="deskripsi" name="deskripsi" rows="3" placeholder="Catatan tertentu terkait unit..."></textarea>
        </div>

        <div class="field field-full">
          <label>Harga Lengkap</label>
          <div class="price-grid">
            <div class="field">
              <label for="harga_beli_usd">Harga Beli (USD)</label>
              <input id="harga_beli_usd" name="harga_beli_usd" type="number" step="0.01" value="0">
            </div>
            <div class="field">
              <label for="kurs_beli">Kurs Berlaku</label>
              <input id="kurs_beli" name="kurs_beli" type="number" step="0.01" value="0">
            </div>
            <div class="field">
              <label for="harga_beli_idr">Harga Beli (IDR)</label>
              <input id="harga_beli_idr" name="harga_beli_idr" type="number" step="0.01" value="0">
            </div>
            <div class="field">
              <label for="harga_jual_estimasi">Estimasi Harga Jual</label>
              <input id="harga_jual_estimasi" name="harga_jual_estimasi" type="number" step="0.01" value="0">
            </div>
            <div class="field">
              <label for="harga_sewa_all_in">Tarif Sewa All-in</label>
              <input id="harga_sewa_all_in" name="harga_sewa_all_in" type="number" step="0.01" value="0">
            </div>
            <div class="field">
              <label for="harga_sewa_kosongan">Tarif Sewa Kosongan</label>
              <input id="harga_sewa_kosongan" name="harga_sewa_kosongan" type="number" step="0.01" value="0">
            </div>
          </div>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-outline-dark btn-sm" data-close-modal="ab-modal">Batal</button>
        <button type="submit" class="btn btn-amber btn-sm">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    const modal = document.getElementById('ab-modal');
    const form = document.getElementById('ab-form');
    const actionInput = document.getElementById('ab-form-action');
    const idInput = document.getElementById('ab-form-id');
    const modalTitle = document.getElementById('ab-modal-title');
    const formFields = {
      kode: document.getElementById('kode'),
      tipe: document.getElementById('tipe'),
      nomor_rangka: document.getElementById('nomor_rangka'),
      nomor_mesin: document.getElementById('nomor_mesin'),
      tahun_pembuatan: document.getElementById('tahun_pembuatan'),
      kondisi: document.getElementById('kondisi'),
      status: document.getElementById('status'),
      lokasi: document.getElementById('lokasi'),
      deskripsi: document.getElementById('deskripsi'),
      harga_beli_usd: document.getElementById('harga_beli_usd'),
      kurs_beli: document.getElementById('kurs_beli'),
      harga_beli_idr: document.getElementById('harga_beli_idr'),
      harga_jual_estimasi: document.getElementById('harga_jual_estimasi'),
      harga_sewa_all_in: document.getElementById('harga_sewa_all_in'),
      harga_sewa_kosongan: document.getElementById('harga_sewa_kosongan')
    };

    if (!modal || !form) return;

    const openModal = () => {
      modal.classList.add('show');
      modal.setAttribute('aria-hidden', 'false');
    };

    const closeModal = () => {
      modal.classList.remove('show');
      modal.setAttribute('aria-hidden', 'true');
      form.reset();
      actionInput.value = 'create';
      idInput.value = '';
      modalTitle.textContent = 'Tambah Alat Berat';
    };

    document.querySelectorAll('[data-open-modal="ab-modal"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        closeModal();
        openModal();
      });
    });

    document.querySelectorAll('[data-close-modal="ab-modal"]').forEach((btn) => {
      btn.addEventListener('click', closeModal);
    });

    document.querySelectorAll('[data-edit-unit]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const data = JSON.parse(btn.getAttribute('data-edit-unit'));
        actionInput.value = 'update';
        idInput.value = data.id || '';
        modalTitle.textContent = 'Edit Alat Berat';

        formFields.kode.value = data.kode || '';
        formFields.tipe.value = data.tipe || '';
        formFields.nomor_rangka.value = data.nomor_rangka || '';
        formFields.nomor_mesin.value = data.nomor_mesin || '';
        formFields.tahun_pembuatan.value = data.tahun_pembuatan || '';
        formFields.kondisi.value = data.kondisi || 'Baru';
        formFields.status.value = data.status || 'Tersedia';
        formFields.lokasi.value = data.lokasi || '';
        formFields.deskripsi.value = data.deskripsi || '';
        formFields.harga_beli_usd.value = data.harga_beli_usd ?? 0;
        formFields.kurs_beli.value = data.kurs_beli ?? 0;
        formFields.harga_beli_idr.value = data.harga_beli_idr ?? 0;
        formFields.harga_jual_estimasi.value = data.harga_jual_estimasi ?? 0;
        formFields.harga_sewa_all_in.value = data.harga_sewa_all_in ?? 0;
        formFields.harga_sewa_kosongan.value = data.harga_sewa_kosongan ?? 0;

        openModal();
      });
    });

    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modal.classList.contains('show')) {
        closeModal();
      }
    });
  })();
</script>

<?php require __DIR__ . "/../../includes/footer_admin.php"; ?>
