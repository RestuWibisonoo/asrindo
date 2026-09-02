<?php 
include '../../koneksi.php';

$id = $_GET['id'];

try {
    mysqli_query($koneksi, "DELETE FROM supplier WHERE id='$id'");
    header("location:../supplier.php");
} catch (mysqli_sql_exception $e) {
    header("location:../supplier.php?pesan=gagal");
}
?>
