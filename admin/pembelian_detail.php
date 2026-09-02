<?php 
include 'header.php'; 

$id = $_GET['id'];
// Get PO Info
$po = mysqli_query($koneksi, "SELECT p.*, s.nama as nama_supplier, s.contact_person 
                              FROM pembelian_sparepart p 
                              JOIN supplier s ON p.supplier_id = s.id 
                              WHERE p.id='$id'");
if(mysqli_num_rows($po) == 0){
  header("location:pembelian.php");
}
$d = mysqli_fetch_assoc($po);
$status_po = $d['status']; // DRAFT or SELESAI
?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Detail Purchase Order
      <small><?php echo $d['nomor_pembelian']; ?></small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="pembelian.php">Pembelian</a></li>
      <li class="active">Detail PO</li>
    </ol>
  </section>

  <section class="content">

    <div class="row">
      <div class="col-md-12" style="margin-bottom: 20px;">
        <a href="pembelian.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</a>
        <?php if($status_po == 'DRAFT'){ ?>
            <a href="proses/pembelian_selesai.php?id=<?php echo $d['id']; ?>" class="btn btn-success pull-right" onclick="return confirm('Apakah Anda yakin ingin menyelesaikan PO ini? Stok akan ditambahkan secara permanen.');">
                <i class="fa fa-check-circle"></i> Selesaikan PO (Finalisasi)
            </a>
        <?php } else { ?>
            <button class="btn btn-default pull-right" onclick="window.print();"><i class="fa fa-print"></i> Cetak PO</button>
        <?php } ?>
      </div>
    </div>
    
    <div class="row">
      <!-- Informasi PO -->
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">Informasi Pembelian</h3>
          </div>
          <div class="box-body">
            <table class="table">
              <tr>
                <th width="40%">Nomor PO</th>
                <td><b><?php echo $d['nomor_pembelian']; ?></b></td>
              </tr>
              <tr>
                <th>Tanggal</th>
                <td><?php echo date('d M Y', strtotime($d['tanggal'])); ?></td>
              </tr>
              <tr>
                <th>Supplier</th>
                <td><?php echo $d['nama_supplier']; ?><br><small class="text-muted"><?php echo $d['contact_person'] ? $d['contact_person'] : '-'; ?></small></td>
              </tr>
              <tr>
                <th>Status</th>
                <td>
                    <?php 
                    if($d['status'] == 'DRAFT'){
                        echo "<span class='label label-warning'>DRAFT</span>";
                    } else if($d['status'] == 'SELESAI'){
                        echo "<span class='label label-success'>SELESAI</span>";
                    }
                    ?>
                </td>
              </tr>
              <tr>
                <th>Total Nilai</th>
                <td><b>Rp <?php echo number_format($d['total'],0,',','.'); ?></b></td>
              </tr>
              <tr>
                <th>Keterangan</th>
                <td><?php echo $d['keterangan'] ? $d['keterangan'] : '-'; ?></td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <!-- Item PO -->
      <div class="col-md-8">
        <div class="box box-success">
          <div class="box-header with-border">
            <h3 class="box-title">Daftar Item Suku Cadang</h3>
          </div>
          
          <div class="box-body">
            
            <?php if($status_po == 'DRAFT'){ ?>
            <!-- Form Tambah Item -->
            <div class="well well-sm">
                <form action="proses/pembelian_item_act.php" method="post" class="form-inline">
                    <input type="hidden" name="pembelian_id" value="<?php echo $d['id']; ?>">
                    
                    <div class="form-group" style="width: 35%;">
                        <label class="sr-only">Suku Cadang</label>
                        <select name="sparepart_id" class="form-control" style="width: 100%;" required>
                            <option value="">- Pilih Suku Cadang -</option>
                            <?php 
                            $sp = mysqli_query($koneksi, "SELECT * FROM sparepart ORDER BY nama ASC");
                            while($s = mysqli_fetch_array($sp)){
                                echo "<option value='".$s['id']."'>".$s['kode']." - ".$s['nama']." (".$s['satuan'].")</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="width: 15%;">
                        <label class="sr-only">Qty</label>
                        <input type="number" step="0.01" name="qty" class="form-control" style="width: 100%;" placeholder="Qty" required>
                    </div>
                    
                    <div class="form-group" style="width: 25%;">
                        <label class="sr-only">Harga Beli</label>
                        <input type="number" name="harga" class="form-control" style="width: 100%;" placeholder="Harga Beli / Item" required>
                    </div>
                    
                    <div class="form-group" style="width: 15%;">
                        <label class="sr-only">Diskon (Rp)</label>
                        <input type="number" name="diskon" class="form-control" style="width: 100%;" placeholder="Diskon (Opsional)" value="0">
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah</button>
                </form>
            </div>
            <hr>
            <?php } ?>

            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>NAMA ITEM</th>
                    <th>QTY</th>
                    <th>HARGA</th>
                    <th>DISKON</th>
                    <th>SUBTOTAL</th>
                    <?php if($status_po == 'DRAFT') { echo '<th width="5%">AKSI</th>'; } ?>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  $grand_total = 0;
                  $items = mysqli_query($koneksi, "SELECT d.*, s.nama, s.satuan, s.kode 
                                                   FROM pembelian_sparepart_detail d 
                                                   JOIN sparepart s ON d.sparepart_id = s.id 
                                                   WHERE d.pembelian_id='$id' ORDER BY d.id ASC");
                  while ($i = mysqli_fetch_array($items)) {
                      // Because `subtotal` is a STORED GENERATED column in MariaDB, we can just use $i['subtotal']
                      $subtotal = $i['subtotal'];
                      $grand_total += $subtotal;
                  ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><b><?php echo $i['kode']; ?></b><br><?php echo $i['nama']; ?></td>
                      <td><?php echo $i['qty']." ".$i['satuan']; ?></td>
                      <td>Rp <?php echo number_format($i['harga'],0,',','.'); ?></td>
                      <td>Rp <?php echo number_format($i['diskon'],0,',','.'); ?></td>
                      <td><b>Rp <?php echo number_format($subtotal,0,',','.'); ?></b></td>
                      
                      <?php if($status_po == 'DRAFT') { ?>
                      <td>
                        <a href="proses/pembelian_item_hapus.php?id=<?php echo $i['id']; ?>&po_id=<?php echo $d['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Hapus item ini dari PO?');">
                            <i class="fa fa-trash"></i>
                        </a>
                      </td>
                      <?php } ?>
                    </tr>
                  <?php
                  }
                  
                  // Perbarui total di tabel pembelian_sparepart (Sync) - Just to be safe and accurate
                  if($status_po == 'DRAFT' && $grand_total != $d['total']){
                      mysqli_query($koneksi, "UPDATE pembelian_sparepart SET total='$grand_total' WHERE id='$id'");
                  }
                  ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">GRAND TOTAL</th>
                        <th><h4><b>Rp <?php echo number_format($grand_total,0,',','.'); ?></b></h4></th>
                        <?php if($status_po == 'DRAFT') { echo '<th></th>'; } ?>
                    </tr>
                </tfoot>
              </table>
            </div>

            <?php if($status_po == 'DRAFT' && mysqli_num_rows($items) == 0){ ?>
                <div class="alert alert-info" style="margin-top:20px;">
                    <i class="fa fa-info-circle"></i> Draft PO masih kosong. Silakan tambahkan item suku cadang pada form di atas.
                </div>
            <?php } ?>

          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include 'footer.php'; ?>
