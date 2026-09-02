<?php 
include 'header.php'; 
$id = $_GET['id'];
$data = mysqli_query($koneksi,"SELECT * FROM sparepart WHERE id='$id'");
if(mysqli_num_rows($data) == 0){
  header("location:suku_cadang.php");
}
$d = mysqli_fetch_assoc($data);
?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Detail Suku Cadang
      <small><?php echo $d['nama']; ?></small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="suku_cadang.php">Suku Cadang</a></li>
      <li class="active">Detail</li>
    </ol>
  </section>

  <section class="content">

    <div class="row">
      <div class="col-md-12" style="margin-bottom: 20px;">
        <a href="suku_cadang.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#edit_suku_cadang">
          <i class="fa fa-edit"></i> Edit Suku Cadang
        </button>
        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#hapus_suku_cadang">
          <i class="fa fa-trash"></i> Hapus Suku Cadang
        </button>
      </div>
    </div>
    
    <div class="row">
      <div class="col-md-6">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">Informasi Produk</h3>
          </div>
          <div class="box-body">
            <table class="table">
              <tr>
                <th width="35%">Kode Barang</th>
                <td><b><?php echo $d['kode']; ?></b></td>
              </tr>
              <tr>
                <th>Part Number</th>
                <td><?php echo $d['part_number'] ? $d['part_number'] : '-'; ?></td>
              </tr>
              <tr>
                <th>Nama Barang</th>
                <td><?php echo $d['nama']; ?></td>
              </tr>
              <tr>
                <th>Kategori</th>
                <td><?php echo $d['kategori']; ?></td>
              </tr>
              <tr>
                <th>Merk / Brand</th>
                <td><?php echo $d['merk'] ? $d['merk'] : '-'; ?></td>
              </tr>
              <tr>
                <th>Satuan</th>
                <td><?php echo $d['satuan']; ?></td>
              </tr>
              <tr>
                <th>Terdaftar Sejak</th>
                <td><?php echo date('d-m-Y H:i:s', strtotime($d['created_at'])); ?></td>
              </tr>
              <tr>
                <th>Keterangan</th>
                <td><?php echo $d['keterangan'] ? $d['keterangan'] : '-'; ?></td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="box box-success">
          <div class="box-header with-border">
            <h3 class="box-title">Informasi Stok & Harga</h3>
          </div>
          <div class="box-body">
            <table class="table">
              <tr>
                <th width="40%">Stok Saat Ini</th>
                <td>
                  <?php 
                  if($d['stok'] <= $d['stok_minimum']){
                      echo "<span class='label label-danger' style='font-size:14px;'>".$d['stok']." ".$d['satuan']."</span>";
                  } else {
                      echo "<span class='label label-success' style='font-size:14px;'>".$d['stok']." ".$d['satuan']."</span>";
                  }
                  ?>
                </td>
              </tr>
              <tr>
                <th>Stok Minimum</th>
                <td><?php echo $d['stok_minimum']." ".$d['satuan']; ?></td>
              </tr>
              <tr>
                <th>Status Stok</th>
                <td>
                  <?php 
                  if($d['stok'] == 0){
                      echo "<span class='text-danger'><i class='fa fa-warning'></i> Stok Kosong</span>";
                  } else if($d['stok'] <= $d['stok_minimum']){
                      echo "<span class='text-warning'><i class='fa fa-warning'></i> Reorder Point Tercapai</span>";
                  } else {
                      echo "<span class='text-success'><i class='fa fa-check'></i> Stok Aman</span>";
                  }
                  ?>
                </td>
              </tr>
              <tr>
                <td colspan="2"><hr style="margin: 10px 0;"></td>
              </tr>
              <tr>
                <th>Harga Modal (HPP)</th>
                <td>Rp <?php echo number_format($d['harga_modal'],0,',','.'); ?></td>
              </tr>
              <tr>
                <th>Harga Jual</th>
                <td><b>Rp <?php echo number_format($d['harga_jual'],0,',','.'); ?></b></td>
              </tr>
              <tr>
                <th>Estimasi Margin</th>
                <td>
                  <?php 
                  $margin = $d['harga_jual'] - $d['harga_modal'];
                  echo "Rp " . number_format($margin,0,',','.');
                  if($d['harga_modal'] > 0){
                    $persentase = ($margin / $d['harga_modal']) * 100;
                    echo " (".number_format($persentase, 1)."%)";
                  }
                  ?>
                </td>
              </tr>
            </table>
          </div>
        </div>
        
        <div class="box box-info">
          <div class="box-header with-border">
            <h3 class="box-title">Riwayat Transaksi Terakhir</h3>
          </div>
          <div class="box-body">
            <p class="text-muted text-center" style="padding: 10px;"><i>Fitur history transaksi akan terintegrasi saat modul Penjualan & Pembelian selesai.</i></p>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal Edit -->
<form action="proses/suku_cadang_update.php" method="post">
  <div class="modal fade" id="edit_suku_cadang" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h5 class="modal-title">Edit Suku Cadang</h5>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="<?php echo $d['id']; ?>">

          <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                <label>Kode Barang <span class="text-danger">*</span></label>
                <input type="text" name="kode" required="required" class="form-control" value="<?php echo $d['kode']; ?>" readonly>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                <label>Part Number</label>
                <input type="text" name="part_number" class="form-control" value="<?php echo $d['part_number']; ?>">
                </div>
            </div>
          </div>

          <div class="form-group">
            <label>Nama Suku Cadang <span class="text-danger">*</span></label>
            <input type="text" name="nama" required="required" class="form-control" value="<?php echo $d['nama']; ?>">
          </div>

          <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                <label>Kategori <span class="text-danger">*</span></label>
                <select name="kategori" class="form-control" required>
                    <option <?php if($d['kategori'] == "Filter") echo "selected='selected'"; ?> value="Filter">Filter</option>
                    <option <?php if($d['kategori'] == "Oli & Pelumas") echo "selected='selected'"; ?> value="Oli & Pelumas">Oli & Pelumas</option>
                    <option <?php if($d['kategori'] == "Gigi & Bucket") echo "selected='selected'"; ?> value="Gigi & Bucket">Gigi & Bucket</option>
                    <option <?php if($d['kategori'] == "Hydraulic") echo "selected='selected'"; ?> value="Hydraulic">Hydraulic</option>
                    <option <?php if($d['kategori'] == "Engine Parts") echo "selected='selected'"; ?> value="Engine Parts">Engine Parts</option>
                    <option <?php if($d['kategori'] == "Undercarriage") echo "selected='selected'"; ?> value="Undercarriage">Undercarriage</option>
                    <option <?php if($d['kategori'] == "Lainnya") echo "selected='selected'"; ?> value="Lainnya">Lainnya</option>
                </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                <label>Satuan <span class="text-danger">*</span></label>
                <select name="satuan" class="form-control" required>
                    <option <?php if($d['satuan'] == "Pcs") echo "selected='selected'"; ?> value="Pcs">Pcs</option>
                    <option <?php if($d['satuan'] == "Set") echo "selected='selected'"; ?> value="Set">Set</option>
                    <option <?php if($d['satuan'] == "Liter") echo "selected='selected'"; ?> value="Liter">Liter</option>
                    <option <?php if($d['satuan'] == "Drum") echo "selected='selected'"; ?> value="Drum">Drum</option>
                    <option <?php if($d['satuan'] == "Pail") echo "selected='selected'"; ?> value="Pail">Pail</option>
                    <option <?php if($d['satuan'] == "Unit") echo "selected='selected'"; ?> value="Unit">Unit</option>
                </select>
                </div>
            </div>
          </div>

          <div class="form-group">
            <label>Merk / Brand</label>
            <input type="text" name="merk" class="form-control" value="<?php echo $d['merk']; ?>">
          </div>
          
          <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" name="stok" class="form-control" value="<?php echo $d['stok']; ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Stok Minimum</label>
                    <input type="number" name="stok_minimum" class="form-control" value="<?php echo $d['stok_minimum']; ?>">
                </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Harga Modal</label>
                    <input type="number" name="harga_modal" class="form-control" value="<?php echo $d['harga_modal']; ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Harga Jual</label>
                    <input type="number" name="harga_jual" class="form-control" value="<?php echo $d['harga_jual']; ?>">
                </div>
            </div>
          </div>

          <div class="form-group">
            <label>Keterangan Tambahan</label>
            <textarea name="keterangan" class="form-control" rows="2"><?php echo $d['keterangan']; ?></textarea>
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

<!-- Modal Hapus -->
<div class="modal fade" id="hapus_suku_cadang" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h5 class="modal-title">Peringatan!</h5>
      </div>
      <div class="modal-body">
        <p>Yakin ingin menghapus data <b><?php echo $d['nama'] ?></b> ?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
        <a href="proses/suku_cadang_hapus.php?id=<?php echo $d['id'] ?>" class="btn btn-danger">Hapus</a>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
