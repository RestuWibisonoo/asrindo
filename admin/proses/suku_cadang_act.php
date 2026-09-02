<?php 
include '../../koneksi.php';

$kode = $_POST['kode'];
$part_number = $_POST['part_number'];
$nama = $_POST['nama'];
$kategori = $_POST['kategori'];
$merk = $_POST['merk'];
$stok = $_POST['stok'];
$stok_minimum = $_POST['stok_minimum'];
$satuan = $_POST['satuan'];
$harga_modal = $_POST['harga_modal'];
$harga_jual = $_POST['harga_jual'];
$keterangan = $_POST['keterangan'];

mysqli_query($koneksi, "INSERT INTO sparepart VALUES (NULL, '$kode', '$nama', '$kategori', '$part_number', '$merk', '$stok', '$stok_minimum', '$harga_modal', '$harga_jual', '$satuan', '$keterangan', NOW(), NOW())") or die(mysqli_error($koneksi));

header("location:../suku_cadang.php");
?>
