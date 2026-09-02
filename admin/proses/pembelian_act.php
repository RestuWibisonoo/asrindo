<?php 
include '../../koneksi.php';

$nomor_pembelian = $_POST['nomor_pembelian'];
$tanggal = $_POST['tanggal'];
$supplier_id = $_POST['supplier_id'];
$keterangan = $_POST['keterangan'];

// Insert to pembelian_sparepart table with status DRAFT
$query = "INSERT INTO pembelian_sparepart (nomor_pembelian, tanggal, supplier_id, total, status, keterangan, created_at, updated_at) 
          VALUES ('$nomor_pembelian', '$tanggal', '$supplier_id', 0, 'DRAFT', '$keterangan', NOW(), NOW())";

if(mysqli_query($koneksi, $query)) {
    // Get the ID of the newly created PO
    $last_id = mysqli_insert_id($koneksi);
    // Redirect to the detail page to start adding items
    header("location:../pembelian_detail.php?id=" . $last_id);
} else {
    // If error, redirect back
    die(mysqli_error($koneksi));
}
?>
