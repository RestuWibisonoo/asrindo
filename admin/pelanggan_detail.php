<?php
include 'header.php';
$id = $_GET['id'];
$data = mysqli_query($koneksi, "SELECT * FROM customer WHERE id='$id'");
if (mysqli_num_rows($data) == 0) {
  header("location:pelanggan.php");
}
$d = mysqli_fetch_assoc($data);
?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Detail Pelanggan
      <small><?php echo $d['nama']; ?></small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="pelanggan.php">Pelanggan</a></li>
      <li class="active">Detail Pelanggan</li>
    </ol>
  </section>

  <section class="content">

    <div class="row">
      <!-- Tombol Aksi -->
      <div class="col-md-12" style="margin-bottom: 20px;">
        <a href="pelanggan.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#edit_pelanggan">
          <i class="fa fa-edit"></i> Edit Pelanggan
        </button>
        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#hapus_pelanggan">
          <i class="fa fa-trash"></i> Hapus Pelanggan
        </button>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">Informasi Pelanggan</h3>
          </div>
          <div class="box-body">
            <table class="table">
              <tr>
                <th width="35%">Kode Pelanggan</th>
                <td><?php echo $d['kode']; ?></td>
              </tr>
              <tr>
                <th>Nama Lengkap (PIC/Individu)</th>
                <td><?php echo $d['nama']; ?></td>
              </tr>
              <tr>
                <th>Jenis Pelanggan</th>
                <td><?php echo $d['jenis_pelanggan']; ?></td>
              </tr>
              <tr>
                <th>Nama Perusahaan</th>
                <td><?php echo $d['nama_perusahaan'] ? $d['nama_perusahaan'] : '-'; ?></td>
              </tr>
              <tr>
                <th>Contact Person</th>
                <td><?php echo $d['contact_person'] ? $d['contact_person'] : '-'; ?></td>
              </tr>
              <tr>
                <th>Status Pelanggan</th>
                <td>
                  <?php
                  if ($d['status_pelanggan'] == 'Baru') {
                    echo "<span class='label label-success'>Baru</span>";
                  } else if ($d['status_pelanggan'] == 'Reguler') {
                    echo "<span class='label label-primary'>Reguler</span>";
                  } else {
                    echo "<span class='label label-danger'>Blacklist</span>";
                  }
                  ?>
                </td>
              </tr>
              <tr>
                <th>Terdaftar Sejak</th>
                <td><?php echo date('d-m-Y', strtotime($d['created_at'])); ?></td>
              </tr>
              <tr>
                <th>Keterangan</th>
                <td><?php echo $d['keterangan']; ?></td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="box box-success">
          <div class="box-header with-border">
            <h3 class="box-title">Kontak</h3>
          </div>
          <div class="box-body">
            <table class="table">
              <tr>
                <th width="30%">Telepon / HP</th>
                <td><?php echo $d['telepon']; ?></td>
              </tr>
              <tr>
                <th>Email</th>
                <td><?php echo $d['email'] ? $d['email'] : '-'; ?></td>
              </tr>
              <tr>
                <th>Alamat Lengkap</th>
                <td><?php echo $d['alamat'] ? $d['alamat'] : '-'; ?></td>
              </tr>
            </table>
          </div>
        </div>

        <div class="box box-info">
          <div class="box-header with-border">
            <h3 class="box-title">Kebijakan DP & Statistik</h3>
          </div>
          <div class="box-body">
            <table class="table">
              <tr>
                <th width="40%">Kebijakan DP Required</th>
                <td><?php echo number_format($d['kebijakan_dp'], 2); ?>%</td>
              </tr>
              <tr>
                <th>Status DP</th>
                <td>
                  <?php
                  if ($d['status_pelanggan'] == 'Reguler') {
                    echo "<span class='label label-primary'>Fleksibel</span>";
                  } else {
                    echo "<span class='label label-warning'>Strict</span>";
                  }
                  ?>
                </td>
              </tr>
              <tr>
                <th>Total Transaksi Rental</th>
                <td>0 <small class="text-muted">(Belum ada modul rental)</small></td>
              </tr>
              <tr>
                <th>Transaksi Terakhir</th>
                <td>-</td>
              </tr>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Edit -->
    <form action="proses/pelanggan_update.php" method="post">
      <div class="modal fade" id="edit_pelanggan" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
              <h5 class="modal-title">Edit Pelanggan</h5>
            </div>
            <div class="modal-body">
              <input type="hidden" name="id" value="<?php echo $d['id']; ?>">

              <div class="form-group">
                <label>Kode Pelanggan <span class="text-danger">*</span></label>
                <input type="text" name="kode" required="required" class="form-control"
                  value="<?php echo $d['kode']; ?>" readonly>
              </div>
              <div class="form-group">
                <label>Jenis Pelanggan <span class="text-danger">*</span></label>
                <select name="jenis_pelanggan" class="form-control" required>
                  <option value="Perusahaan" <?php if ($d['jenis_pelanggan'] == "Perusahaan")
                    echo "selected"; ?>>
                    Perusahaan</option>
                  <option value="Individu" <?php if ($d['jenis_pelanggan'] == "Individu")
                    echo "selected"; ?>>Individu
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Nama Lengkap (PIC/Individu) <span class="text-danger">*</span></label>
                <input type="text" name="nama" required="required" class="form-control"
                  value="<?php echo $d['nama']; ?>">
              </div>
              <div class="form-group">
                <label>Nama Perusahaan</label>
                <input type="text" name="nama_perusahaan" class="form-control"
                  value="<?php echo $d['nama_perusahaan']; ?>">
              </div>
              <div class="form-group">
                <label>Contact Person</label>
                <input type="text" name="contact_person" class="form-control"
                  value="<?php echo $d['contact_person']; ?>">
              </div>
              <div class="form-group">
                <label>Telepon <span class="text-danger">*</span></label>
                <input type="text" name="telepon" required="required" class="form-control"
                  value="<?php echo $d['telepon']; ?>">
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo $d['email']; ?>">
              </div>
              <div class="form-group">
                <label>Alamat Lengkap</label>
                <textarea name="alamat" class="form-control" rows="3"><?php echo $d['alamat']; ?></textarea>
              </div>
              <div class="form-group">
                <label>Status Pelanggan <span class="text-danger">*</span></label>
                <select name="status_pelanggan" class="form-control" required>
                  <option value="Baru" <?php if ($d['status_pelanggan'] == "Baru")
                    echo "selected"; ?>>Baru</option>
                  <option value="Reguler" <?php if ($d['status_pelanggan'] == "Reguler")
                    echo "selected"; ?>>Reguler
                  </option>
                  <option value="Blacklist" <?php if ($d['status_pelanggan'] == "Blacklist")
                    echo "selected"; ?>>Blacklist
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Kebijakan DP (%) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" name="kebijakan_dp" required="required" class="form-control"
                  value="<?php echo $d['kebijakan_dp']; ?>">
              </div>
              <div class="form-group">
                <label>Keterangan Tambahan</label>
                <textarea name="keterangan" class="form-control" rows="2"><?php echo $d['keterangan']; ?></textarea>
              </div>

            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
              <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
          </div>
        </div>
      </div>
    </form>

    <!-- modal hapus -->
    <div class="modal fade" id="hapus_pelanggan" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
            <h5 class="modal-title">Peringatan!</h5>
          </div>
          <div class="modal-body">
            <p>Yakin ingin menghapus pelanggan <b><?php echo $d['nama']; ?></b>?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            <a href="proses/pelanggan_hapus.php?id=<?php echo $d['id']; ?>" class="btn btn-danger">Hapus</a>
          </div>
        </div>
      </div>
    </div>

  </section>

</div>
<?php include 'footer.php'; ?>