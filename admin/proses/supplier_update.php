<?php 
include '../../koneksi.php';

$id = $_POST['id'];
$kode  = $_POST['kode'];
$nama  = $_POST['nama'];
$contact_person  = $_POST['contact_person'];
$negara  = $_POST['negara'];
$telepon  = $_POST['telepon'];
$email  = $_POST['email'];
$alamat  = $_POST['alamat'];
$status  = $_POST['status'];
$keterangan  = $_POST['keterangan'];

mysqli_query($koneksi, "UPDATE supplier SET kode='$kode', nama='$nama', contact_person='$contact_person', negara='$negara', telepon='$telepon', email='$email', alamat='$alamat', status='$status', keterangan='$keterangan' WHERE id='$id'");

header("location:../supplier.php");
?>
