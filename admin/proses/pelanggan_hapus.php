<?php 
include '../../koneksi.php';

$id = $_GET['id'];

try {
    mysqli_query($koneksi, "DELETE FROM customer WHERE id='$id'");
    header("location:../pelanggan.php");
} catch (mysqli_sql_exception $e) {
    header("location:../pelanggan.php?pesan=gagal");
}
?>
