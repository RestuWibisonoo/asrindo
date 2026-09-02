<?php include 'header.php'; ?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Pembelian Suku Cadang
      <small>Data Transaksi Purchase Order (PO)</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Pembelian Suku Cadang</li>
    </ol>
  </section>

  <section class="content">

    <div class="row">
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-aqua">
          <div class="inner">
            <?php
            $po_total = mysqli_query($koneksi, "SELECT count(*) as total FROM pembelian_sparepart");
            $pt = mysqli_fetch_assoc($po_total);
            echo "<h4><b>" . $pt['total'] . "</b></h4>";
            ?>
            <p>Total Transaksi PO</p>
          </div>
          <div class="icon"><i class="fa fa-shopping-cart"></i></div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-yellow">
          <div class="inner">
            <?php
            $po_draft = mysqli_query($koneksi, "SELECT count(*) as total FROM pembelian_sparepart WHERE status='DRAFT'");
            $pd = mysqli_fetch_assoc($po_draft);
            echo "<h4><b>" . $pd['total'] . "</b></h4>";
            ?>
            <p>PO Status DRAFT</p>
          </div>
          <div class="icon"><i class="fa fa-file-text-o"></i></div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-green">
          <div class="inner">
            <?php
            $po_selesai = mysqli_query($koneksi, "SELECT count(*) as total FROM pembelian_sparepart WHERE status='SELESAI'");
            $ps = mysqli_fetch_assoc($po_selesai);
            echo "<h4><b>" . $ps['total'] . "</b></h4>";
            ?>
            <p>PO Selesai</p>
          </div>
          <div class="icon"><i class="fa fa-check-square-o"></i></div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="small-box bg-red">
          <div class="inner">
            <?php
            $po_nilai = mysqli_query($koneksi, "SELECT sum(total) as nilai FROM pembelian_sparepart WHERE status='SELESAI'");
            $pn = mysqli_fetch_assoc($po_nilai);
            echo "<h4><b>Rp. " . number_format($pn['nilai'] ? $pn['nilai'] : 0, 0, ',', '.') . "</b></h4>";
            ?>
            <p>Nilai Pembelian Sukses</p>
          </div>
          <div class="icon"><i class="fa fa-money"></i></div>
        </div>
      </div>
    </div>

    <div class="row">
      <section class="col-lg-12">
        <div class="box box-success">
          <div class="box-header">
            <h3 class="box-title">Daftar Transaksi PO</h3>
            <div class="btn-group pull-right">
              <a href="pembelian_tambah.php" class="btn btn-primary btn-sm">
                <i class="fa fa-plus"></i> &nbsp Buat PO Baru
              </a>
            </div>
          </div>

          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="table-datatable">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>TANGGAL</th>
                    <th>NOMOR PO</th>
                    <th>SUPPLIER</th>
                    <th>TOTAL NILAI</th>
                    <th>STATUS</th>
                    <th width="10%">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  $data = mysqli_query($koneksi, "SELECT p.*, s.nama as nama_supplier FROM pembelian_sparepart p JOIN supplier s ON p.supplier_id = s.id ORDER BY p.id DESC");
                  while ($d = mysqli_fetch_array($data)) {
                  ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo date('d-m-Y', strtotime($d['tanggal'])); ?></td>
                      <td><b><?php echo $d['nomor_pembelian']; ?></b></td>
                      <td><?php echo $d['nama_supplier']; ?></td>
                      <td><?php echo "Rp " . number_format($d['total'],0,',','.'); ?></td>
                      <td>
                          <?php 
                          if($d['status'] == 'DRAFT'){
                              echo "<span class='label label-warning'>DRAFT</span>";
                          } else if($d['status'] == 'SELESAI'){
                              echo "<span class='label label-success'>SELESAI</span>";
                          } else {
                              echo "<span class='label label-default'>".$d['status']."</span>";
                          }
                          ?>
                      </td>
                      <td>
                        <a href="pembelian_detail.php?id=<?php echo $d['id']; ?>" class="btn btn-info btn-sm">
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
