<?php 
include '../../koneksi.php';
$id = $_GET['id'];

try {
    mysqli_query($koneksi, "DELETE FROM alat_berat WHERE id='$id'");
    header("location:../alat_berat.php");
} catch (mysqli_sql_exception $e) {
    header("location:../alat_berat.php?pesan=gagal");
}
?>
