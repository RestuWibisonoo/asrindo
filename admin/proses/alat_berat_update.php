<?php 
include '../../koneksi.php';

$id = $_POST['id'];
$kode  = $_POST['kode'];
$tipe  = $_POST['tipe'];
$nomor_rangka  = $_POST['nomor_rangka'];
$nomor_mesin  = $_POST['nomor_mesin'];
$tahun_pembuatan  = $_POST['tahun_pembuatan'];
$kondisi  = $_POST['kondisi'];
$status  = $_POST['status'];
$lokasi  = $_POST['lokasi'];

$kurs_beli = !empty($_POST['kurs_beli']) ? $_POST['kurs_beli'] : 0;
$harga_beli_usd = !empty($_POST['harga_beli_usd']) ? $_POST['harga_beli_usd'] : 0;
$harga_beli_idr = !empty($_POST['harga_beli_idr']) ? $_POST['harga_beli_idr'] : 0;
$harga_jual_estimasi = !empty($_POST['harga_jual_estimasi']) ? $_POST['harga_jual_estimasi'] : 0;

$harga_sewa_all_in = !empty($_POST['harga_sewa_all_in']) ? $_POST['harga_sewa_all_in'] : 0;
$harga_sewa_kosongan = !empty($_POST['harga_sewa_kosongan']) ? $_POST['harga_sewa_kosongan'] : 0;

$total_jam_operasional = !empty($_POST['total_jam_operasional']) ? $_POST['total_jam_operasional'] : 0;
$jam_operasional_terakhir = !empty($_POST['jam_operasional_terakhir']) ? $_POST['jam_operasional_terakhir'] : 0;
$deskripsi = $_POST['deskripsi'];

mysqli_query($koneksi, "UPDATE alat_berat SET 
    kode='$kode', 
    tipe='$tipe', 
    nomor_rangka='$nomor_rangka', 
    nomor_mesin='$nomor_mesin', 
    tahun_pembuatan='$tahun_pembuatan', 
    kondisi='$kondisi', 
    status='$status', 
    lokasi='$lokasi',
    kurs_beli='$kurs_beli', 
    harga_beli_usd='$harga_beli_usd', 
    harga_beli_idr='$harga_beli_idr', 
    harga_jual_estimasi='$harga_jual_estimasi',
    harga_sewa_all_in='$harga_sewa_all_in', 
    harga_sewa_kosongan='$harga_sewa_kosongan',
    total_jam_operasional='$total_jam_operasional', 
    jam_operasional_terakhir='$jam_operasional_terakhir', 
    deskripsi='$deskripsi' 
    WHERE id='$id'");

header("location:../alat_berat_detail.php?id=$id");
?>
