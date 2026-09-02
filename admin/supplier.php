<?php include 'header.php'; ?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Supplier
      <small>Data Pemasok / Supplier</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Supplier</li>
    </ol>
  </section>

  <section class="content">

    <!-- Statistik Supplier -->
    <div class="row">
      <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-yellow">
          <div class="inner">
            <?php
            $supplier = mysqli_query($koneksi, "SELECT * FROM supplier");
            echo "<h4><b>" . mysqli_num_rows($supplier) . "</b></h4>";
            ?>
            <p>Total Supplier</p>
          </div>
          <div class="icon"><i class="fa fa-truck"></i></div>
        </div>
      </div>
    </div>

    <div class="row">
      <section class="col-lg-12">

        <?php
        if (isset($_GET['pesan'])) {
          if ($_GET['pesan'] == "gagal") {
            echo "<div class='alert alert-danger'><b>Gagal!</b> Data supplier tidak dapat dihapus karena sudah terkait dengan data lain.</div>";
          }
        }
        ?>

        <div class="box box-success">

          <div class="box-header">
            <h3 class="box-title">Daftar Supplier</h3>
            <div class="btn-group pull-right">
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#tambahSupplier">
                <i class="fa fa-plus"></i> &nbsp Tambah Supplier
              </button>
            </div>
          </div>

          <div class="box-body">

            <!-- Modal Tambah -->
            <form action="proses/supplier_act.php" method="post">
              <div class="modal fade" id="tambahSupplier" tabindex="-1" role="dialog"
                aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                      </button>
                      <h5 class="modal-title" id="exampleModalLabel">Tambah Supplier Baru</h5>
                    </div>
                    <div class="modal-body">

                      <div class="form-group">
                        <label>Kode Supplier <span class="text-danger">*</span></label>
                        <input type="text" name="kode" required="required" class="form-control" placeholder="SUP00X">
                      </div>
                      <div class="form-group">
                        <label>Nama Perusahaan <span class="text-danger">*</span></label>
                        <input type="text" name="nama" required="required" class="form-control"
                          placeholder="Nama Supplier">
                      </div>
                      <div class="form-group">
                        <label>Nama Kontak <span class="text-danger">*</span></label>
                        <input type="text" name="contact_person" required="required" class="form-control"
                          placeholder="Nama Kontak">
                      </div>
                      <div class="form-group">
                        <label>Negara <span class="text-danger">*</span></label>
                        <input type="text" name="negara" required="required" class="form-control" placeholder="Negara">
                      </div>
                      <div class="form-group">
                        <label>Telepon / HP</label>
                        <input type="text" name="telepon" class="form-control" placeholder="08xxxx">
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
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" required>
                          <option value="Aktif">Aktif</option>
                          <option value="Tidak Aktif">Tidak Aktif</option>
                        </select>
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
                    <th>NAMA PERUSAHAAN</th>
                    <th>NAMA KONTAK</th>
                    <th>NEGARA</th>
                    <th>TELEPON</th>
                    <th>STATUS</th>
                    <th width="15%">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  $data = mysqli_query($koneksi, "SELECT * FROM supplier ORDER BY id DESC");
                  while ($d = mysqli_fetch_array($data)) {
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo $d['nama']; ?></td>
                      <td><?php echo $d['contact_person']; ?></td>
                      <td><?php echo $d['negara']; ?></td>
                      <td><?php echo $d['telepon']; ?></td>
                      <td>
                        <?php
                        if ($d['status'] == 'Aktif') {
                          echo "<span class='label label-success'>Aktif</span>";
                        } else {
                          echo "<span class='label label-danger'>Tidak Aktif</span>";
                        }
                        ?>
                      </td>
                      <td>
                        <button type="button" class="btn btn-warning btn-sm" data-toggle="modal"
                          data-target="#edit_supplier_<?php echo $d['id'] ?>">
                          <i class="fa fa-cog"></i>
                        </button>

                        <button type="button" class="btn btn-danger btn-sm" data-toggle="modal"
                          data-target="#hapus_supplier_<?php echo $d['id'] ?>">
                          <i class="fa fa-trash"></i>
                        </button>

                        <!-- Modal Edit -->
                        <form action="proses/supplier_update.php" method="post">
                          <div class="modal fade" id="edit_supplier_<?php echo $d['id'] ?>" tabindex="-1" role="dialog"
                            aria-hidden="true">
                            <div class="modal-dialog" role="document">
                              <div class="modal-content">
                                <div class="modal-header">
                                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                  </button>
                                  <h5 class="modal-title">Edit Supplier</h5>
                                </div>
                                <div class="modal-body">
                                  <input type="hidden" name="id" value="<?php echo $d['id']; ?>">

                                  <div class="form-group">
                                    <label>Kode Supplier <span class="text-danger">*</span></label>
                                    <input type="text" name="kode" required="required" class="form-control"
                                      value="<?php echo $d['kode']; ?>" readonly>
                                  </div>
                                  <div class="form-group">
                                    <label>Nama Perusahaan <span class="text-danger">*</span></label>
                                    <input type="text" name="nama" required="required" class="form-control"
                                      value="<?php echo $d['nama']; ?>">
                                  </div>
                                  <div class="form-group">
                                    <label>Nama Kontak <span class="text-danger">*</span></label>
                                    <input type="text" name="contact_person" required="required" class="form-control"
                                      value="<?php echo $d['contact_person']; ?>">
                                  </div>
                                  <div class="form-group">
                                    <label>Negara <span class="text-danger">*</span></label>
                                    <input type="text" name="negara" required="required" class="form-control"
                                      value="<?php echo $d['negara']; ?>">
                                  </div>
                                  <div class="form-group">
                                    <label>Telepon</label>
                                    <input type="text" name="telepon" class="form-control"
                                      value="<?php echo $d['telepon']; ?>">
                                  </div>
                                  <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control"
                                      value="<?php echo $d['email']; ?>">
                                  </div>
                                  <div class="form-group">
                                    <label>Alamat Lengkap</label>
                                    <textarea name="alamat" class="form-control"
                                      rows="3"><?php echo $d['alamat']; ?></textarea>
                                  </div>
                                  <div class="form-group">
                                    <label>Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-control" required>
                                      <option value="Aktif" <?php if ($d['status'] == 'Aktif')
                                        echo 'selected'; ?>>Aktif
                                      </option>
                                      <option value="Tidak Aktif" <?php if ($d['status'] == 'Tidak Aktif')
                                        echo 'selected'; ?>>Tidak Aktif</option>
                                    </select>
                                  </div>
                                  <div class="form-group">
                                    <label>Keterangan Tambahan</label>
                                    <textarea name="keterangan" class="form-control"
                                      rows="2"><?php echo $d['keterangan']; ?></textarea>
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
                        <div class="modal fade" id="hapus_supplier_<?php echo $d['id'] ?>" tabindex="-1" role="dialog"
                          aria-hidden="true">
                          <div class="modal-dialog" role="document">
                            <div class="modal-content">
                              <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                  <span aria-hidden="true">&times;</span>
                                </button>
                                <h5 class="modal-title">Peringatan!</h5>
                              </div>
                              <div class="modal-body">
                                <p>Yakin ingin menghapus supplier <b><?php echo $d['nama']; ?></b>?</p>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                <a href="proses/supplier_hapus.php?id=<?php echo $d['id'] ?>"
                                  class="btn btn-danger">Hapus</a>
                              </div>
                            </div>
                          </div>
                        </div>

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