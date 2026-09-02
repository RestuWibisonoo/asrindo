<?php
include 'header.php';
$id = $_GET['id'];
$data = mysqli_query($koneksi, "SELECT * FROM alat_berat WHERE id='$id'");
$d = mysqli_fetch_array($data);

if (!$d) {
  echo "<script>window.location='alat_berat.php';</script>";
  exit;
}
?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Detail Alat Berat
      <small>Informasi Unit <?php echo $d['kode']; ?></small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="alat_berat.php">Alat Berat</a></li>
      <li class="active">Detail</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <!-- Kiri: Info Utama & Operasional -->
      <div class="col-md-5">

        <!-- Box Data Umum -->
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">Data Umum Unit</h3>
            <div class="box-tools pull-right">
              <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editAlat">
                <i class="fa fa-edit"></i> Edit
              </button>
              <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#hapusAlat">
                <i class="fa fa-trash"></i> Hapus
              </button>
            </div>
          </div>
          <div class="box-body">
            <table class="table table-bordered">
              <tr>
                <th width="40%">Kode Unit</th>
                <td><b><?php echo $d['kode']; ?></b></td>
              </tr>
              <tr>
                <th>Tipe / Model</th>
                <td><?php echo $d['tipe']; ?></td>
              </tr>
              <tr>
                <th>Nomor Rangka</th>
                <td><?php echo $d['nomor_rangka']; ?></td>
              </tr>
              <tr>
                <th>Nomor Mesin</th>
                <td><?php echo $d['nomor_mesin']; ?></td>
              </tr>
              <tr>
                <th>Tahun Pembuatan</th>
                <td><?php echo $d['tahun_pembuatan']; ?></td>
              </tr>
              <tr>
                <th>Kondisi</th>
                <td><?php echo $d['kondisi']; ?></td>
              </tr>
              <tr>
                <th>Status</th>
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
              </tr>
              <tr>
                <th>Lokasi Saat Ini</th>
                <td><?php echo $d['lokasi']; ?></td>
              </tr>
              <tr>
                <th>Keterangan Tambahan</th>
                <td><?php echo $d['deskripsi']; ?></td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Box Operasional -->
        <div class="box box-info">
          <div class="box-header with-border">
            <h3 class="box-title">Data Operasional (Hour Meter)</h3>
          </div>
          <div class="box-body">
            <div class="row text-center">
              <div class="col-xs-6 border-right">
                <h3 class="text-aqua"><b><?php echo number_format($d['total_jam_operasional'], 1); ?></b></h3>
                <p>Total HM Aktual</p>
              </div>
              <div class="col-xs-6">
                <h3 class="text-muted"><b><?php echo number_format($d['jam_operasional_terakhir'], 1); ?></b></h3>
                <p>HM Pemakaian Terakhir</p>
              </div>
            </div>
          </div>
        </div>

      </div> <!-- End Kiri -->

      <!-- Kanan: Harga, Sewa, Log -->
      <div class="col-md-7">

        <div class="row">
          <div class="col-md-6">
            <!-- Box Harga & Finansial -->
            <div class="box box-success">
              <div class="box-header with-border">
                <h3 class="box-title">Informasi Harga Unit</h3>
              </div>
              <div class="box-body">
                <table class="table table-striped">
                  <tr>
                    <th>Harga Beli (Asal USD)</th>
                    <td>$ <?php echo number_format($d['harga_beli_usd'], 2); ?></td>
                  </tr>
                  <tr>
                    <th>Kurs Berlaku</th>
                    <td>Rp <?php echo number_format($d['kurs_beli'], 0, ',', '.'); ?></td>
                  </tr>
                  <tr>
                    <th>Total Harga Pokok (IDR)</th>
                    <td class="text-success"><b>Rp <?php echo number_format($d['harga_beli_idr'], 0, ',', '.'); ?></b>
                    </td>
                  </tr>
                  <tr>
                    <th>Estimasi Harga Jual</th>
                    <td class="text-primary"><b>Rp
                        <?php echo number_format($d['harga_jual_estimasi'], 0, ',', '.'); ?></b></td>
                  </tr>
                </table>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <!-- Box Tarif Sewa -->
            <div class="box box-warning">
              <div class="box-header with-border">
                <h3 class="box-title">Tarif Rental (Per Jam)</h3>
              </div>
              <div class="box-body">
                <table class="table table-striped">
                  <tr>
                    <th>Paket All-in</th>
                    <td><b>Rp <?php echo number_format($d['harga_sewa_all_in'], 0, ',', '.'); ?></b></td>
                  </tr>
                  <tr>
                    <td colspan="2"><small class="text-muted">*Termasuk Operator & BBM</small></td>
                  </tr>
                  <tr>
                    <th>Paket Kosongan</th>
                    <td><b>Rp <?php echo number_format($d['harga_sewa_kosongan'], 0, ',', '.'); ?></b></td>
                  </tr>
                  <tr>
                    <td colspan="2"><small class="text-muted">*Hanya sewa unit alat</small></td>
                  </tr>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Box Riwayat Perbaikan Placeholder -->
        <div class="box box-danger">
          <div class="box-header with-border">
            <h3 class="box-title">Log Perawatan (Maintenance)</h3>
            <div class="box-tools pull-right">
              <button class="btn btn-box-tool" disabled><i class="fa fa-plus"></i></button>
            </div>
          </div>
          <div class="box-body">
            <p class="text-muted text-center" style="padding: 20px;">
              <i>Data perawatan akan muncul otomatis saat Anda membuat laporan perbaikan di menu "Perawatan".</i>
            </p>
          </div>
        </div>

        <!-- Box Riwayat Transaksi Placeholder -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title">Riwayat Penyewaan & Penjualan</h3>
          </div>
          <div class="box-body">
            <p class="text-muted text-center" style="padding: 20px;">
              <i>Riwayat transaksi (Rental / Penjualan) akan terekam otomatis di sini.</i>
            </p>
          </div>
        </div>

      </div> <!-- End Kanan -->
    </div>
  </section>

</div>

<!-- Modal Edit -->
<form action="proses/alat_berat_update.php" method="post">
  <div class="modal fade" id="editAlat" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h5 class="modal-title">Edit Alat Berat - <?php echo $d['kode']; ?></h5>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="<?php echo $d['id']; ?>">

          <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active"><a href="#edit_umum" aria-controls="edit_umum" role="tab"
                data-toggle="tab">Data Umum</a></li>
            <li role="presentation"><a href="#edit_harga" aria-controls="edit_harga" role="tab" data-toggle="tab">Data
                Harga</a></li>
            <li role="presentation"><a href="#edit_sewa" aria-controls="edit_sewa" role="tab" data-toggle="tab">Harga
                Sewa</a></li>
            <li role="presentation"><a href="#edit_operasional" aria-controls="edit_operasional" role="tab"
                data-toggle="tab">Operasional</a></li>
          </ul>

          <div class="tab-content" style="padding-top: 15px;">
            <!-- Data Umum -->
            <div role="tabpanel" class="tab-pane active" id="edit_umum">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Kode Alat Berat <span class="text-danger">*</span></label>
                    <input type="text" name="kode" required="required" class="form-control"
                      value="<?php echo $d['kode']; ?>">
                  </div>
                  <div class="form-group">
                    <label>Tipe Alat Berat <span class="text-danger">*</span></label>
                    <input type="text" name="tipe" required="required" class="form-control"
                      value="<?php echo $d['tipe']; ?>">
                  </div>
                  <div class="form-group">
                    <label>Nomor Rangka</label>
                    <input type="text" name="nomor_rangka" class="form-control"
                      value="<?php echo $d['nomor_rangka']; ?>">
                  </div>
                  <div class="form-group">
                    <label>Nomor Mesin</label>
                    <input type="text" name="nomor_mesin" class="form-control" value="<?php echo $d['nomor_mesin']; ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Tahun Pembuatan</label>
                    <input type="number" name="tahun_pembuatan" class="form-control"
                      value="<?php echo $d['tahun_pembuatan']; ?>">
                  </div>
                  <div class="form-group">
                    <label>Kondisi <span class="text-danger">*</span></label>
                    <select name="kondisi" class="form-control" required>
                      <option value="Baru" <?php if ($d['kondisi'] == 'Baru')
                        echo 'selected'; ?>>Baru</option>
                      <option value="Bekas" <?php if ($d['kondisi'] == 'Bekas')
                        echo 'selected'; ?>>Bekas</option>
                      <option value="Refurbished" <?php if ($d['kondisi'] == 'Refurbished')
                        echo 'selected'; ?>>Refurbished
                      </option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Status Unit <span class="text-danger">*</span></label>
                    <select name="status" class="form-control" required>
                      <option value="Tersedia" <?php if ($d['status'] == 'Tersedia')
                        echo 'selected'; ?>>Tersedia</option>
                      <option value="Disewakan" <?php if ($d['status'] == 'Disewakan')
                        echo 'selected'; ?>>Disewakan
                      </option>
                      <option value="Terjual" <?php if ($d['status'] == 'Terjual')
                        echo 'selected'; ?>>Terjual</option>
                      <option value="Perbaikan" <?php if ($d['status'] == 'Perbaikan')
                        echo 'selected'; ?>>Perbaikan
                      </option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Lokasi Saat Ini</label>
                    <input type="text" name="lokasi" class="form-control" value="<?php echo $d['lokasi']; ?>">
                  </div>
                </div>
              </div>
            </div>

            <!-- Data Harga -->
            <div role="tabpanel" class="tab-pane" id="edit_harga">
              <div class="form-group">
                <label>Kurs Beli (IDR per USD)</label>
                <input type="number" step="0.01" name="kurs_beli" class="form-control"
                  value="<?php echo $d['kurs_beli']; ?>">
              </div>
              <div class="form-group">
                <label>Harga Beli Asal (USD)</label>
                <input type="number" step="0.01" name="harga_beli_usd" class="form-control"
                  value="<?php echo $d['harga_beli_usd']; ?>">
              </div>
              <div class="form-group">
                <label>Harga Beli Pokok (IDR)</label>
                <input type="number" step="0.01" name="harga_beli_idr" class="form-control"
                  value="<?php echo $d['harga_beli_idr']; ?>">
              </div>
              <div class="form-group">
                <label>Harga Jual (Estimasi/Pricelist)</label>
                <input type="number" step="0.01" name="harga_jual_estimasi" class="form-control"
                  value="<?php echo $d['harga_jual_estimasi']; ?>">
              </div>
            </div>

            <!-- Harga Sewa -->
            <div role="tabpanel" class="tab-pane" id="edit_sewa">
              <div class="form-group">
                <label>Harga Sewa per Jam (All-in) IDR</label>
                <input type="number" step="0.01" name="harga_sewa_all_in" class="form-control"
                  value="<?php echo $d['harga_sewa_all_in']; ?>">
              </div>
              <div class="form-group">
                <label>Harga Sewa per Jam (Kosongan) IDR</label>
                <input type="number" step="0.01" name="harga_sewa_kosongan" class="form-control"
                  value="<?php echo $d['harga_sewa_kosongan']; ?>">
              </div>
            </div>

            <!-- Operasional -->
            <div role="tabpanel" class="tab-pane" id="edit_operasional">
              <div class="form-group">
                <label>Total Jam Operasional (Hour Meter Aktual)</label>
                <input type="number" step="0.1" name="total_jam_operasional" class="form-control"
                  value="<?php echo $d['total_jam_operasional']; ?>">
              </div>
              <div class="form-group">
                <label>Jam Operasional Terakhir (Pemakaian Terakhir)</label>
                <input type="number" step="0.1" name="jam_operasional_terakhir" class="form-control"
                  value="<?php echo $d['jam_operasional_terakhir']; ?>">
              </div>
              <div class="form-group">
                <label>Deskripsi / Catatan Khusus</label>
                <textarea name="deskripsi" class="form-control" rows="3"><?php echo $d['deskripsi']; ?></textarea>
              </div>
            </div>
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

<!-- Modal Hapus -->
<div class="modal fade" id="hapusAlat" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h5 class="modal-title">Konfirmasi Penghapusan</h5>
      </div>
      <div class="modal-body">
        <p>Apakah Anda yakin ingin menghapus Unit Alat Berat <b><?php echo $d['kode']; ?> -
            <?php echo $d['tipe']; ?></b>?</p>
        <p class="text-danger"><small>Tindakan ini tidak dapat dibatalkan.</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <a href="proses/alat_berat_hapus.php?id=<?php echo $d['id']; ?>" class="btn btn-danger">Ya, Hapus Data</a>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>