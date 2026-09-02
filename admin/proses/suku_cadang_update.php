<?php 
include '../../koneksi.php';

$id = $_POST['id'];
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

mysqli_query($koneksi, "UPDATE sparepart SET 
    kode='$kode', 
    part_number='$part_number', 
    nama='$nama', 
    kategori='$kategori', 
    merk='$merk', 
    stok='$stok', 
    stok_minimum='$stok_minimum', 
    satuan='$satuan', 
    harga_modal='$harga_modal', 
    harga_jual='$harga_jual', 
    keterangan='$keterangan' 
    WHERE id='$id'") or die(mysqli_error($koneksi));

header("location:../suku_cadang.php");
?>
