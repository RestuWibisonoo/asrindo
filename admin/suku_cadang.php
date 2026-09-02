<?php include 'header.php'; ?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Suku Cadang
      <small>Data Suku Cadang (Spare Parts)</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Suku Cadang</li>
    </ol>
  </section>

  <section class="content">

    <!-- Statistik Suku Cadang -->
    <div class="row">
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-aqua">
          <div class="inner">
            <?php
            $jenis = mysqli_query($koneksi, "SELECT count(*) as total FROM sparepart");
            $j = mysqli_fetch_assoc($jenis);
            echo "<h4><b>" . $j['total'] . "</b></h4>";
            ?>
            <p>Total Jenis Barang</p>
          </div>
          <div class="icon"><i class="fa fa-cubes"></i></div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-green">
          <div class="inner">
            <?php
            $nilai = mysqli_query($koneksi, "SELECT sum(stok * harga_modal) as total_nilai FROM sparepart");
            $n = mysqli_fetch_assoc($nilai);
            echo "<h4><b>Rp. " . number_format($n['total_nilai'], 0, ',', '.') . "</b></h4>";
            ?>
            <p>Total Nilai Inventori</p>
          </div>
          <div class="icon"><i class="fa fa-money"></i></div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-yellow">
          <div class="inner">
            <?php
            $rendah = mysqli_query($koneksi, "SELECT count(*) as total FROM sparepart WHERE stok <= stok_minimum AND stok > 0");
            $r = mysqli_fetch_assoc($rendah);
            echo "<h4><b>" . $r['total'] . "</b></h4>";
            ?>
            <p>Item Menipis (Warning)</p>
          </div>
          <div class="icon"><i class="fa fa-warning"></i></div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-red">
          <div class="inner">
            <?php
            $kosong = mysqli_query($koneksi, "SELECT count(*) as total FROM sparepart WHERE stok = 0");
            $k = mysqli_fetch_assoc($kosong);
            echo "<h4><b>" . $k['total'] . "</b></h4>";
            ?>
            <p>Stok Habis (Kosong)</p>
          </div>
          <div class="icon"><i class="fa fa-times-circle"></i></div>
        </div>
      </div>
    </div>

    <div class="row">
      <section class="col-lg-12">
        
        <?php
        if (isset($_GET['pesan'])) {
          if ($_GET['pesan'] == "gagal") {
            echo "<div class='alert alert-danger'><b>Gagal!</b> Data suku cadang tidak dapat dihapus karena sudah terkait dengan transaksi.</div>";
          }
        }
        ?>

        <div class="box box-success">

          <div class="box-header">
            <h3 class="box-title">Daftar Suku Cadang</h3>
            <div class="btn-group pull-right">
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#tambahSukuCadang">
                <i class="fa fa-plus"></i> &nbsp Tambah Suku Cadang
              </button>
            </div>
          </div>

          <div class="box-body">

            <!-- Modal Tambah -->
            <form action="proses/suku_cadang_act.php" method="post">
              <div class="modal fade" id="tambahSukuCadang" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                      </button>
                      <h5 class="modal-title" id="exampleModalLabel">Tambah Suku Cadang Baru</h5>
                    </div>
                    <div class="modal-body">
                      
                      <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                            <label>Kode Barang <span class="text-danger">*</span></label>
                            <input type="text" name="kode" required="required" class="form-control" placeholder="SP00X">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                            <label>Part Number</label>
                            <input type="text" name="part_number" class="form-control" placeholder="PN-XXXX">
                            </div>
                        </div>
                      </div>

                      <div class="form-group">
                        <label>Nama Suku Cadang <span class="text-danger">*</span></label>
                        <input type="text" name="nama" required="required" class="form-control" placeholder="Nama Suku Cadang">
                      </div>

                      <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                            <label>Kategori <span class="text-danger">*</span></label>
                            <select name="kategori" class="form-control" required>
                                <option value="">- Pilih Kategori -</option>
                                <option value="Filter">Filter</option>
                                <option value="Oli & Pelumas">Oli & Pelumas</option>
                                <option value="Gigi & Bucket">Gigi & Bucket</option>
                                <option value="Hydraulic">Hydraulic</option>
                                <option value="Engine Parts">Engine Parts</option>
                                <option value="Undercarriage">Undercarriage</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                            <label>Satuan <span class="text-danger">*</span></label>
                            <select name="satuan" class="form-control" required>
                                <option value="Pcs">Pcs</option>
                                <option value="Set">Set</option>
                                <option value="Liter">Liter</option>
                                <option value="Drum">Drum</option>
                                <option value="Pail">Pail</option>
                                <option value="Unit">Unit</option>
                            </select>
                            </div>
                        </div>
                      </div>

                      <div class="form-group">
                        <label>Merk / Brand</label>
                        <input type="text" name="merk" class="form-control" placeholder="Caterpillar / Komatsu / dll">
                      </div>
                      
                      <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Stok Awal</label>
                                <input type="number" name="stok" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Stok Minimum</label>
                                <input type="number" name="stok_minimum" class="form-control" value="5">
                            </div>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Harga Modal</label>
                                <input type="number" name="harga_modal" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Harga Jual</label>
                                <input type="number" name="harga_jual" class="form-control" value="0">
                            </div>
                        </div>
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
                    <th>KODE / P.N.</th>
                    <th>NAMA BARANG</th>
                    <th>KATEGORI</th>
                    <th>MERK</th>
                    <th>STOK</th>
                    <th>SATUAN</th>
                    <th>HARGA JUAL</th>
                    <th width="10%">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  $data = mysqli_query($koneksi, "SELECT * FROM sparepart ORDER BY id DESC");
                  while ($d = mysqli_fetch_array($data)) {
                  ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td>
                          <b><?php echo $d['kode']; ?></b>
                          <br>
                          <small class="text-muted"><?php echo $d['part_number']; ?></small>
                      </td>
                      <td><?php echo $d['nama']; ?></td>
                      <td><?php echo $d['kategori']; ?></td>
                      <td><?php echo $d['merk']; ?></td>
                      <td>
                          <?php 
                          if($d['stok'] <= $d['stok_minimum']){
                              echo "<span class='label label-danger'>".$d['stok']."</span>";
                          } else {
                              echo "<span class='label label-success'>".$d['stok']."</span>";
                          }
                          ?>
                      </td>
                      <td><?php echo $d['satuan']; ?></td>
                      <td><?php echo "Rp " . number_format($d['harga_jual'],0,',','.'); ?></td>
                      <td>
                        <a href="suku_cadang_detail.php?id=<?php echo $d['id']; ?>" class="btn btn-info btn-sm">
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
