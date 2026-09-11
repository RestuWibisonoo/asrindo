<?php
$pageTitle = "Pengaturan";
$activeMenu = "pengaturan";
$breadcrumb = "Pengaturan";
require_once __DIR__ . "/../../includes/header_admin.php";

$pdo = getDB();
$fields = ['nama_perusahaan', 'tagline', 'deskripsi', 'alamat', 'telepon', 'whatsapp', 'email', 'jam_operasional', 'cp_nama', 'cp_jabatan', 'cp_telepon', 'cp_email', 'logo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    foreach ($fields as $field) $data[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
    if (empty($data['nama_perusahaan'])) {
        flash_set('error', 'Nama perusahaan wajib diisi.');
    } else {
        $stmt = $pdo->prepare('UPDATE pengaturan SET nama_perusahaan = :nama_perusahaan, tagline = :tagline, deskripsi = :deskripsi, alamat = :alamat, telepon = :telepon, whatsapp = :whatsapp, email = :email, jam_operasional = :jam_operasional, cp_nama = :cp_nama, cp_jabatan = :cp_jabatan, cp_telepon = :cp_telepon, cp_email = :cp_email, logo = :logo, updated_at = NOW() WHERE id = 1');
        $stmt->execute(array_combine(array_map(static fn ($field) => ':' . $field, $fields), array_values($data)));
        flash_set('success', 'Pengaturan perusahaan berhasil disimpan.');
    }
    redirect('/admin/pengaturan/index.php');
}

$settings = $pdo->query('SELECT * FROM pengaturan WHERE id = 1 LIMIT 1')->fetch() ?: array_fill_keys($fields, '');
?>

<form method="post" action="<?= BASE_URL ?>/admin/pengaturan/index.php">
  <div class="ab-detail-layout">
    <div class="ab-detail-panel"><div class="panel-head"><h3>Profil Perusahaan</h3></div><div style="padding:20px;"><div class="field"><label for="nama_perusahaan">Nama Perusahaan</label><input id="nama_perusahaan" name="nama_perusahaan" value="<?= e($settings['nama_perusahaan'] ?? '') ?>" required></div><div class="field"><label for="tagline">Tagline</label><input id="tagline" name="tagline" value="<?= e($settings['tagline'] ?? '') ?>"></div><div class="field"><label for="deskripsi">Deskripsi</label><textarea id="deskripsi" name="deskripsi" rows="5"><?= e($settings['deskripsi'] ?? '') ?></textarea></div><div class="field"><label for="alamat">Alamat</label><textarea id="alamat" name="alamat" rows="3"><?= e($settings['alamat'] ?? '') ?></textarea></div><div class="field"><label for="jam_operasional">Jam Operasional</label><input id="jam_operasional" name="jam_operasional" value="<?= e($settings['jam_operasional'] ?? '') ?>"></div></div></div>
    <div class="ab-detail-panel"><div class="panel-head"><h3>Kontak Perusahaan</h3></div><div style="padding:20px;"><div class="field"><label for="telepon">Telepon</label><input id="telepon" name="telepon" value="<?= e($settings['telepon'] ?? '') ?>"></div><div class="field"><label for="whatsapp">WhatsApp</label><input id="whatsapp" name="whatsapp" value="<?= e($settings['whatsapp'] ?? '') ?>"></div><div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= e($settings['email'] ?? '') ?>"></div><div class="field"><label for="logo">Logo (URL atau path file)</label><input id="logo" name="logo" value="<?= e($settings['logo'] ?? '') ?>"></div><div class="detail-box"><h4>Contact Person</h4><div class="field"><label for="cp_nama">Nama</label><input id="cp_nama" name="cp_nama" value="<?= e($settings['cp_nama'] ?? '') ?>"></div><div class="field"><label for="cp_jabatan">Jabatan</label><input id="cp_jabatan" name="cp_jabatan" value="<?= e($settings['cp_jabatan'] ?? '') ?>"></div><div class="field"><label for="cp_telepon">Telepon</label><input id="cp_telepon" name="cp_telepon" value="<?= e($settings['cp_telepon'] ?? '') ?>"></div><div class="field"><label for="cp_email">Email</label><input id="cp_email" name="cp_email" type="email" value="<?= e($settings['cp_email'] ?? '') ?>"></div></div></div></div>
  </div>
  <div class="modal-actions" style="margin-top:20px;"><button type="submit" class="btn btn-amber">Simpan Pengaturan</button></div>
</form>

<?php require __DIR__ . "/../../includes/footer_admin.php"; ?>
