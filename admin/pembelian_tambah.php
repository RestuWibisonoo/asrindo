<?php include 'header.php'; ?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Buat Purchase Order Baru
      <small>Tambah Transaksi Pembelian Suku Cadang</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="pembelian.php">Pembelian</a></li>
      <li class="active">Tambah PO</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-6 col-lg-offset-3">
        <div class="box box-primary">
          <div class="box-header">
            <h3 class="box-title">Informasi Purchase Order</h3>
            <a href="pembelian.php" class="btn btn-default btn-sm pull-right"><i class="fa fa-reply"></i> &nbsp Kembali</a>
          </div>

          <div class="box-body">
            <form action="proses/pembelian_act.php" method="post">
              
              <div class="form-group">
                <label>Nomor PO (Invoice Pembelian) <span class="text-danger">*</span></label>
                <?php 
                $tgl = date('Ymd');
                $rand = rand(100,999);
                $generate_no = "PO-".$tgl."-".$rand;
                ?>
                <input type="text" class="form-control" name="nomor_pembelian" required="required" value="<?php echo $generate_no; ?>">
                <small class="text-muted">Dibuat otomatis, bisa diubah manual jika perlu menyamakan dengan nota fisik.</small>
              </div>

              <div class="form-group">
                <label>Tanggal Transaksi <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="tanggal" required="required" value="<?php echo date('Y-m-d'); ?>">
              </div>

              <div class="form-group">
                <label>Supplier <span class="text-danger">*</span></label>
                <select name="supplier_id" class="form-control" required="required">
                  <option value="">- Pilih Supplier -</option>
                  <?php 
                  $supplier = mysqli_query($koneksi,"SELECT * FROM supplier ORDER BY nama ASC");
                  while($s = mysqli_fetch_array($supplier)){
                    echo "<option value='".$s['id']."'>".$s['nama']." (".$s['contact_person'].")</option>";
                  }
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label>Keterangan Tambahan</label>
                <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Belanja rutin bulanan"></textarea>
              </div>

              <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-save"></i> Buat Draft PO & Lanjut Isi Item</button>
              </div>

            </form>
          </div>
        </div>
      </section>
    </div>
  </section>
</div>

<?php include 'footer.php'; ?>
