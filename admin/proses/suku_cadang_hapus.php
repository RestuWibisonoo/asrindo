<?php 
include '../../koneksi.php';

$id = $_GET['id'];

try {
    mysqli_query($koneksi, "DELETE FROM sparepart WHERE id='$id'");
    header("location:../suku_cadang.php");
} catch (mysqli_sql_exception $e) {
    header("location:../suku_cadang.php?pesan=gagal");
}
?>
