<?php 
include '../../koneksi.php';

$id = $_POST['id'];
$kode  = $_POST['kode'];
$jenis_pelanggan  = $_POST['jenis_pelanggan'];
$nama  = $_POST['nama'];
$nama_perusahaan  = $_POST['nama_perusahaan'];
$contact_person  = $_POST['contact_person'];
$telepon  = $_POST['telepon'];
$email  = $_POST['email'];
$alamat  = $_POST['alamat'];
$status_pelanggan  = $_POST['status_pelanggan'];
$kebijakan_dp  = $_POST['kebijakan_dp'];
$keterangan  = $_POST['keterangan'];

mysqli_query($koneksi, "UPDATE customer SET kode='$kode', jenis_pelanggan='$jenis_pelanggan', nama='$nama', nama_perusahaan='$nama_perusahaan', contact_person='$contact_person', telepon='$telepon', email='$email', alamat='$alamat', status_pelanggan='$status_pelanggan', kebijakan_dp='$kebijakan_dp', keterangan='$keterangan' WHERE id='$id'");

header("location:../pelanggan_detail.php?id=$id");
?>
