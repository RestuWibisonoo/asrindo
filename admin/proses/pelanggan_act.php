<?php 
include '../../koneksi.php';

$kode  = $_POST['kode'];
$jenis_pelanggan  = $_POST['jenis_pelanggan'];
$nama  = $_POST['nama'];
$nama_perusahaan  = $_POST['nama_perusahaan'];
$contact_person  = $_POST['contact_person'];
$telepon  = $_POST['telepon'];
$email  = $_POST['email'];
$alamat  = $_POST['alamat'];
$keterangan  = $_POST['keterangan'];

mysqli_query($koneksi, "INSERT INTO customer (kode, jenis_pelanggan, nama, nama_perusahaan, contact_person, telepon, email, alamat, keterangan) VALUES ('$kode', '$jenis_pelanggan', '$nama', '$nama_perusahaan', '$contact_person', '$telepon', '$email', '$alamat', '$keterangan')");

header("location:../pelanggan.php");
?>
