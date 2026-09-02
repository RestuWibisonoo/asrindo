<?php include 'header.php'; ?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Alat Berat
      <small>Data Inventori Alat Berat (Heavy Equipment)</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Alat Berat</li>
    </ol>
  </section>

  <section class="content">

    <!-- Statistik Alat Berat -->
    <div class="row">
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-aqua">
          <div class="inner">
            <?php
            $alat = mysqli_query($koneksi, "SELECT count(*) as total FROM alat_berat");
            $a = mysqli_fetch_assoc($alat);
            echo "<h4><b>" . $a['total'] . "</b></h4>";
            ?>
            <p>Total Unit</p>
          </div>
          <div class="icon"><i class="fa fa-truck"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-green">
          <div class="inner">
            <?php
            $tersedia = mysqli_query($koneksi, "SELECT count(*) as total FROM alat_berat WHERE status='Tersedia'");
            $t = mysqli_fetch_assoc($tersedia);
            echo "<h4><b>" . $t['total'] . "</b></h4>";
            ?>
            <p>Unit Tersedia</p>
          </div>
          <div class="icon"><i class="fa fa-check"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-yellow">
          <div class="inner">
            <?php
            $nilai = mysqli_query($koneksi, "SELECT sum(harga_beli_idr) as total_nilai FROM alat_berat");
            $n = mysqli_fetch_assoc($nilai);
            echo "<h4><b>Rp. " . number_format($n['total_nilai'], 0, ',', '.') . "</b></h4>";
            ?>
            <p>Total Nilai Inventori</p>
          </div>
          <div class="icon"><i class="fa fa-money"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-red">
          <div class="inner">
            <?php
            $hm = mysqli_query($koneksi, "SELECT avg(total_jam_operasional) as rata_hm FROM alat_berat");
            $h = mysqli_fetch_assoc($hm);
            echo "<h4><b>" . number_format($h['rata_hm'], 1) . " Jam</b></h4>";
            ?>
            <p>Rata-rata HM (Hour Meter)</p>
          </div>
          <div class="icon"><i class="fa fa-clock-o"></i></div>
        </div>
      </div>
    </div>

    <div class="row">
      <section class="col-lg-12">

        <?php
        if (isset($_GET['pesan'])) {
          if ($_GET['pesan'] == "gagal") {
            echo "<div class='alert alert-danger'><b>Gagal!</b> Data alat berat tidak dapat dihapus karena sudah terkait dengan transaksi.</div>";
          }
        }
        ?>

        <div class="box box-success">

          <div class="box-header">
            <h3 class="box-title">Daftar Alat Berat</h3>
            <div class="btn-group pull-right">
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#tambahAlat">
                <i class="fa fa-plus"></i> &nbsp Tambah Alat Berat
              </button>
            </div>
          </div>

          <div class="box-body">

            <!-- Modal Tambah -->
            <form action="proses/alat_berat_act.php" method="post">
              <div class="modal fade" id="tambahAlat" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                      </button>
                      <h5 class="modal-title" id="exampleModalLabel">Tambah Alat Berat Baru</h5>
                    </div>
                    <div class="modal-body">

                      <ul class="nav nav-tabs" role="tablist">
                        <li role="presentation" class="active"><a href="#umum" aria-controls="umum" role="tab"
                            data-toggle="tab">Data Umum</a></li>
                        <li role="presentation"><a href="#harga" aria-controls="harga" role="tab" data-toggle="tab">Data
                            Harga</a></li>
                        <li role="presentation"><a href="#sewa" aria-controls="sewa" role="tab" data-toggle="tab">Harga
                            Sewa</a></li>
                        <li role="presentation"><a href="#operasional" aria-controls="operasional" role="tab"
                            data-toggle="tab">Operasional</a></li>
                      </ul>

                      <div class="tab-content" style="padding-top: 15px;">
                        <!-- Data Umum -->
                        <div role="tabpanel" class="tab-pane active" id="umum">
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-group">
                                <label>Kode Alat Berat <span class="text-danger">*</span></label>
                                <input type="text" name="kode" required="required" class="form-control"
                                  placeholder="AGM 137">
                              </div>
                              <div class="form-group">
                                <label>Tipe Alat Berat <span class="text-danger">*</span></label>
                                <input type="text" name="tipe" required="required" class="form-control"
                                  placeholder="CAT 305">
                              </div>
                              <div class="form-group">
                                <label>Nomor Rangka</label>
                                <input type="text" name="nomor_rangka" class="form-control" placeholder="CAT3055EVW...">
                              </div>
                              <div class="form-group">
                                <label>Nomor Mesin</label>
                                <input type="text" name="nomor_mesin" class="form-control" placeholder="Engine No...">
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group">
                                <label>Tahun Pembuatan</label>
                                <input type="number" name="tahun_pembuatan" class="form-control" placeholder="2018"
                                  value="<?php echo date('Y'); ?>">
                              </div>
                              <div class="form-group">
                                <label>Kondisi <span class="text-danger">*</span></label>
                                <select name="kondisi" class="form-control" required>
                                  <option value="Baru">Baru</option>
                                  <option value="Bekas">Bekas</option>
                                  <option value="Refurbished">Refurbished</option>
                                </select>
                              </div>
                              <div class="form-group">
                                <label>Status Unit <span class="text-danger">*</span></label>
                                <select name="status" class="form-control" required>
                                  <option value="Tersedia">Tersedia</option>
                                  <option value="Disewakan">Disewakan</option>
                                  <option value="Terjual">Terjual</option>
                                  <option value="Perbaikan">Perbaikan</option>
                                </select>
                              </div>
                              <div class="form-group">
                                <label>Lokasi Saat Ini</label>
                                <input type="text" name="lokasi" class="form-control" placeholder="Gudang A / Proyek X">
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Data Harga -->
                        <div role="tabpanel" class="tab-pane" id="harga">
                          <div class="form-group">
                            <label>Kurs Beli (IDR per USD)</label>
                            <input type="number" step="0.01" name="kurs_beli" class="form-control"
                              placeholder="Misal: 15500">
                          </div>
                          <div class="form-group">
                            <label>Harga Beli Asal (USD)</label>
                            <input type="number" step="0.01" name="harga_beli_usd" class="form-control"
                              placeholder="Misal: 15000">
                          </div>
                          <div class="form-group">
                            <label>Harga Beli Pokok (IDR)</label>
                            <input type="number" step="0.01" name="harga_beli_idr" class="form-control"
                              placeholder="Misal: 250000000">
                            <small class="text-muted">Masukkan harga HPP final dalam rupiah termasuk cukai dsb jika
                              ada.</small>
                          </div>
                          <div class="form-group">
                            <label>Harga Jual (Estimasi/Pricelist)</label>
                            <input type="number" step="0.01" name="harga_jual_estimasi" class="form-control"
                              placeholder="Misal: 300000000">
                          </div>
                        </div>

                        <!-- Harga Sewa -->
                        <div role="tabpanel" class="tab-pane" id="sewa">
                          <div class="form-group">
                            <label>Harga Sewa per Jam (All-in) IDR</label>
                            <input type="number" step="0.01" name="harga_sewa_all_in" class="form-control"
                              placeholder="250000">
                            <small class="text-muted">Termasuk operator dan solar.</small>
                          </div>
                          <div class="form-group">
                            <label>Harga Sewa per Jam (Kosongan) IDR</label>
                            <input type="number" step="0.01" name="harga_sewa_kosongan" class="form-control"
                              placeholder="150000">
                            <small class="text-muted">Hanya sewa unit alat berat saja.</small>
                          </div>
                        </div>

                        <!-- Operasional -->
                        <div role="tabpanel" class="tab-pane" id="operasional">
                          <div class="form-group">
                            <label>Total Jam Operasional (Hour Meter Aktual)</label>
                            <input type="number" step="0.1" name="total_jam_operasional" class="form-control"
                              placeholder="Misal: 1950.5">
                          </div>
                          <div class="form-group">
                            <label>Jam Operasional Terakhir (Pemakaian Terakhir)</label>
                            <input type="number" step="0.1" name="jam_operasional_terakhir" class="form-control"
                              placeholder="0">
                          </div>
                          <div class="form-group">
                            <label>Deskripsi / Catatan Khusus</label>
                            <textarea name="deskripsi" class="form-control" rows="3"
                              placeholder="Catatan kondisi mesin, kelengkapan, dsb."></textarea>
                          </div>
                        </div>
                      </div>

                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                      <button type="submit" class="btn btn-primary">Simpan Alat Berat</button>
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
                    <th>KODE UNIT</th>
                    <th>TIPE ALAT</th>
                    <th>TAHUN</th>
                    <th>LOKASI</th>
                    <th>HM AKTUAL</th>
                    <th>STATUS</th>
                    <th width="10%">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  $data = mysqli_query($koneksi, "SELECT * FROM alat_berat ORDER BY id DESC");
                  while ($d = mysqli_fetch_array($data)) {
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><b><?php echo $d['kode']; ?></b></td>
                      <td><?php echo $d['tipe']; ?></td>
                      <td><?php echo $d['tahun_pembuatan']; ?></td>
                      <td><?php echo $d['lokasi']; ?></td>
                      <td><?php echo number_format($d['total_jam_operasional'], 1); ?></td>
                      <td>
                        <?php
                        if ($d['status'] == 'Tersedia') {
                          echo "<span class='label label-success'>Tersedia</span>";
                        } else if ($d['status'] == 'Disewakan') {
                          echo "<span class='label label-primary'>Disewakan</span>";
                        } else if ($d['status'] == 'Perbaikan') {
                          echo "<span class='label label-warning'>Perbaikan</span>";
                        } else if ($d['status'] == 'Terjual') {
                          echo "<span class='label label-danger'>Terjual</span>";
                        } else {
                          echo "<span class='label label-default'>" . $d['status'] . "</span>";
                        }
                        ?>
                      </td>
                      <td>
                        <a href="alat_berat_detail.php?id=<?php echo $d['id']; ?>" class="btn btn-info btn-sm">
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