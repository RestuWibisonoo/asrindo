<?php include 'header.php'; ?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Pelanggan
      <small>Data Pelanggan (Customer)</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Pelanggan</li>
    </ol>
  </section>

  <section class="content">

    <!-- Statistik Pelanggan -->
    <div class="row">
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-aqua">
          <div class="inner">
            <?php
            $pelanggan = mysqli_query($koneksi, "SELECT * FROM customer");
            echo "<h4><b>" . mysqli_num_rows($pelanggan) . "</b></h4>";
            ?>
            <p>Total Pelanggan</p>
          </div>
          <div class="icon"><i class="fa fa-users"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-green">
          <div class="inner">
            <?php
            $pelanggan_baru = mysqli_query($koneksi, "SELECT * FROM customer WHERE status_pelanggan='Baru'");
            echo "<h4><b>" . mysqli_num_rows($pelanggan_baru) . "</b></h4>";
            ?>
            <p>Pelanggan Baru</p>
          </div>
          <div class="icon"><i class="fa fa-user-plus"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-blue">
          <div class="inner">
            <?php
            $pelanggan_reg = mysqli_query($koneksi, "SELECT * FROM customer WHERE status_pelanggan='Reguler'");
            echo "<h4><b>" . mysqli_num_rows($pelanggan_reg) . "</b></h4>";
            ?>
            <p>Pelanggan Reguler</p>
          </div>
          <div class="icon"><i class="fa fa-check-circle"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-red">
          <div class="inner">
            <?php
            $pelanggan_bl = mysqli_query($koneksi, "SELECT * FROM customer WHERE status_pelanggan='Blacklist'");
            echo "<h4><b>" . mysqli_num_rows($pelanggan_bl) . "</b></h4>";
            ?>
            <p>Blacklist</p>
          </div>
          <div class="icon"><i class="fa fa-ban"></i></div>
        </div>
      </div>
    </div>

    <div class="row">
      <section class="col-lg-12">

        <?php
        if (isset($_GET['pesan'])) {
          if ($_GET['pesan'] == "gagal") {
            echo "<div class='alert alert-danger'><b>Gagal!</b> Data pelanggan tidak dapat dihapus karena sudah memiliki data transaksi.</div>";
          }
        }
        ?>

        <div class="box box-success">

          <div class="box-header">
            <h3 class="box-title">Daftar Pelanggan</h3>
            <div class="btn-group pull-right">
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#tambahPelanggan">
                <i class="fa fa-plus"></i> &nbsp Tambah Pelanggan
              </button>
            </div>
          </div>

          <div class="box-body">

            <!-- Modal Tambah -->
            <form action="proses/pelanggan_act.php" method="post">
              <div class="modal fade" id="tambahPelanggan" tabindex="-1" role="dialog"
                aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                      </button>
                      <h5 class="modal-title" id="exampleModalLabel">Tambah Pelanggan Baru</h5>
                    </div>
                    <div class="modal-body">

                      <div class="form-group">
                        <label>Kode Pelanggan <span class="text-danger">*</span></label>
                        <input type="text" name="kode" required="required" class="form-control" placeholder="CUS00X">
                      </div>
                      <div class="form-group">
                        <label>Jenis Pelanggan <span class="text-danger">*</span></label>
                        <select name="jenis_pelanggan" class="form-control" required>
                          <option value="Perusahaan">Perusahaan</option>
                          <option value="Individu">Individu</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label>Nama Lengkap / PIC <span class="text-danger">*</span></label>
                        <input type="text" name="nama" required="required" class="form-control"
                          placeholder="Nama Lengkap">
                      </div>
                      <div class="form-group">
                        <label>Nama Perusahaan</label>
                        <input type="text" name="nama_perusahaan" class="form-control"
                          placeholder="Kosongkan jika Individu">
                      </div>
                      <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Nama Kontak">
                      </div>
                      <div class="form-group">
                        <label>Telepon / HP <span class="text-danger">*</span></label>
                        <input type="text" name="telepon" required="required" class="form-control" placeholder="08xxxx">
                      </div>
                      <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" placeholder="email@domain.com">
                      </div>
                      <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control" rows="3"></textarea>
                      </div>
                      <div class="form-group">
                        <label>Keterangan Tambahan</label>
                        <textarea name="keterangan" class="form-control" rows="2"></textarea>
                      </div>

                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                      <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                  </div>
                </div>
              </div>
            </form>

            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="table-datatable">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>NAMA LENGKAP</th>
                    <th>PERUSAHAAN</th>
                    <th>JENIS</th>
                    <th>CONTACT PERSON</th>
                    <th>TELEPON</th>
                    <th>STATUS</th>
                    <th width="10%">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  $data = mysqli_query($koneksi, "SELECT * FROM customer ORDER BY id DESC");
                  while ($d = mysqli_fetch_array($data)) {
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo $d['nama']; ?></td>
                      <td><?php echo $d['nama_perusahaan'] ? $d['nama_perusahaan'] : '-'; ?></td>
                      <td><?php echo $d['jenis_pelanggan']; ?></td>
                      <td><?php echo $d['contact_person'] ? $d['contact_person'] : '-'; ?></td>
                      <td><?php echo $d['telepon']; ?></td>
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
                      <td>
                        <a href="pelanggan_detail.php?id=<?php echo $d['id']; ?>" class="btn btn-info btn-sm">
                          <i class="fa fa-eye"></i> Detail
                        </a>
                      </td>
                    </tr>
                  <?php
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </section>
    </div>
  </section>

</div>
<?php include 'footer.php'; ?>