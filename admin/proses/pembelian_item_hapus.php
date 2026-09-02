<?php 
include '../../koneksi.php';

$id = $_GET['id'];
$po_id = $_GET['po_id'];

// Delete item
mysqli_query($koneksi, "DELETE FROM pembelian_sparepart_detail WHERE id='$id'");

// Update the header total
$sum = mysqli_query($koneksi, "SELECT SUM(subtotal) as total FROM pembelian_sparepart_detail WHERE pembelian_id='$po_id'");
$s = mysqli_fetch_assoc($sum);
$total = empty($s['total']) ? 0 : $s['total'];
mysqli_query($koneksi, "UPDATE pembelian_sparepart SET total='$total' WHERE id='$po_id'");

header("location:../pembelian_detail.php?id=" . $po_id);
?>
