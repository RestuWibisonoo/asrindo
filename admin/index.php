<?php include 'header.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <h1>
      Dashboard
      <small>Control panel Asrindo ERP</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">

    <div class="row">
      <div class="col-lg-4 col-xs-6">
        <div class="small-box bg-aqua">
          <div class="inner">
            <?php
            $po_aktif = mysqli_query($koneksi, "SELECT * FROM pembelian_sparepart WHERE status='DRAFT' OR status='PROSES'");
            $p = mysqli_num_rows($po_aktif);
            ?>
            <h4 style="font-weight: bolder"><?php echo $p ?></h4>
            <p>PO Pembelian Aktif</p>
          </div>
          <div class="icon">
            <i class="fa fa-shopping-cart"></i>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-xs-6">
        <div class="small-box bg-green">
          <div class="inner">
            <?php
            $bulan = date('m');
            $po_bulan_ini = mysqli_query($koneksi, "SELECT * FROM pembelian_sparepart WHERE month(tanggal)='$bulan'");
            $p = mysqli_num_rows($po_bulan_ini);
            ?>
            <h4 style="font-weight: bolder"><?php echo $p ?></h4>
            <p>Total PO Bulan Ini</p>
          </div>
          <div class="icon">
            <i class="fa fa-file-text-o"></i>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-xs-6">
        <div class="small-box bg-yellow">
          <div class="inner">
            <?php
            $bulan = date('m');
            $nilai_po = mysqli_query($koneksi, "SELECT sum(total) as total_pembelian FROM pembelian_sparepart WHERE month(tanggal)='$bulan'");
            $p = mysqli_fetch_assoc($nilai_po);
            ?>
            <h4 style="font-weight: bolder"><?php echo "Rp. " . number_format($p['total_pembelian']) . " ,-" ?></h4>
            <p>Total Nilai Pembelian Bulan Ini</p>
          </div>
          <div class="icon">
            <i class="fa fa-money"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">Selamat Datang di Sistem ERP Asrindo Global Mandiri</h3>
          </div>
          <div class="box-body">
            <p>Sistem ini digunakan untuk manajemen warehouse, alat berat, penyewaan (rental), dan keuangan.</p>
            <p>Silakan gunakan menu navigasi di sebelah atas untuk mengakses modul-modul sistem.</p>
          </div>
        </div>
      </div>
    </div>

  </section>
</div>




<?php include 'footer.php'; ?>