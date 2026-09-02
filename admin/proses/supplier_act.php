<?php 
include '../../koneksi.php';

$kode  = $_POST['kode'];
$nama  = $_POST['nama'];
$contact_person  = $_POST['contact_person'];
$negara  = $_POST['negara'];
$telepon  = $_POST['telepon'];
$email  = $_POST['email'];
$alamat  = $_POST['alamat'];
$status  = $_POST['status'];
$keterangan  = $_POST['keterangan'];

mysqli_query($koneksi, "INSERT INTO supplier (kode, nama, contact_person, negara, telepon, email, alamat, status, keterangan) VALUES ('$kode', '$nama', '$contact_person', '$negara', '$telepon', '$email', '$alamat', '$status', '$keterangan')");

header("location:../supplier.php");
?>
