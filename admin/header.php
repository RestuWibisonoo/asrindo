<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Administrator - Asrindo ERP</title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <link rel="stylesheet" href="../assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../assets/bower_components/Ionicons/css/ionicons.min.css">
  <link rel="stylesheet" href="../assets/dist/css/AdminLTE.min.css">
  <link rel="stylesheet" href="../assets/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="../assets/dist/css/skins/_all-skins.min.css">
  <link rel="stylesheet" href="../assets/bower_components/morris.js/morris.css">
  <link rel="stylesheet" href="../assets/bower_components/jvectormap/jquery-jvectormap.css">
  <link rel="stylesheet" href="../assets/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css">
  <link rel="stylesheet" href="../assets/bower_components/bootstrap-daterangepicker/daterangepicker.css">
  <link rel="stylesheet" href="../assets/plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.min.css">
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">

  <?php
  include '../koneksi.php';
  date_default_timezone_set('Asia/Jakarta');
  session_start();
  if ($_SESSION['status'] != "logedin") {
    header("location:../index.php?alert=belum_login");
  }
  ?>

</head>

<body class="hold-transition skin-green layout-top-nav">

  <style>
    #table-datatable {
      width: 100% !important;
    }

    #table-datatable .sorting_disabled {
      border: 1px solid #f4f4f4;
    }

    .dropdown-menu>li>a {
      padding: 8px 20px;
    }
  </style>

  <div class="wrapper">

    <header class="main-header">
      <nav class="navbar navbar-static-top">
        <div class="container-fluid">

          <div class="navbar-header">
            <a href="index.php" class="navbar-brand"><b>ASRINDO</b> ERP</a>
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse">
              <i class="fa fa-bars"></i>
            </button>
          </div>

          <div class="collapse navbar-collapse pull-left" id="navbar-collapse">

            <ul class="nav navbar-nav">

              <li class="active">
                <a href="index.php">
                  <i class="fa fa-home"></i> &nbsp; <span>DASHBOARD</span> <span class="sr-only">(current)</span>
                </a>
              </li>

              <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i
                    class="fa fa-shopping-cart"></i> &nbsp; TRANSAKSI <span class="caret"></span></a>
                <ul class="dropdown-menu" role="menu">
                  <li><a href="rental.php"><i class="fa fa-exchange"></i> <span>RENTAL</span></a></li>
                  <li><a href="penjualan.php"><i class="fa fa-money"></i> <span>PENJUALAN</span></a></li>
                  <li><a href="pembelian.php"><i class="fa fa-download"></i> <span>PEMBELIAN</span></a></li>
                </ul>
              </li>

              <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i
                    class="fa fa-cubes"></i> &nbsp; PRODUK <span class="caret"></span></a>
                <ul class="dropdown-menu" role="menu">
                  <li><a href="alat_berat.php"><i class="fa fa-truck"></i> <span>ALAT BERAT</span></a></li>
                  <li><a href="suku_cadang.php"><i class="fa fa-cogs"></i> <span>SUKU CADANG</span></a></li>
                  <li><a href="perawatan.php"><i class="fa fa-wrench"></i> <span>PERAWATAN</span></a></li>
                </ul>
              </li>

              <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i
                    class="fa fa-users"></i> &nbsp; REKANAN <span class="caret"></span></a>
                <ul class="dropdown-menu" role="menu">
                  <li><a href="pelanggan.php"><i class="fa fa-user"></i> <span>PELANGGAN</span></a></li>
                  <li><a href="supplier.php"><i class="fa fa-truck"></i> <span>SUPPLIER</span></a></li>
                </ul>
              </li>

              <li>
                <a href="laporan.php">
                  <i class="fa fa-file"></i> &nbsp; <span>LAPORAN</span>
                </a>
              </li>

            </ul>


          </div>

          <div class="navbar-custom-menu">
            <ul class="nav navbar-nav">

              <!-- Notifications could go here -->

              <li class="dropdown user user-menu">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                  <?php
                  // Placeholder user image
                  ?>
                  <img src="../gambar/sistem/user.png" class="user-image">
                  <span class="hidden-xs"><?php echo $_SESSION['nama']; ?> - ADMINISTRATOR</span>
                </a>
              </li>

              <li>
                <a href="logout.php">
                  <i class="fa fa-sign-out"></i> <span>LOGOUT</span>
                </a>
              </li>

            </ul>
          </div>
        </div>
      </nav>

    </header>