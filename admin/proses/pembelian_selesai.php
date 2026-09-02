<?php 
include '../../koneksi.php';

$id = $_GET['id'];

// 1. Cek apakah ada item di dalam PO ini
$cek = mysqli_query($koneksi, "SELECT * FROM pembelian_sparepart_detail WHERE pembelian_id='$id'");
if(mysqli_num_rows($cek) == 0){
    // Jika tidak ada item, tidak bisa diselesaikan
    header("location:../pembelian_detail.php?id=$id&pesan=kosong");
    exit();
}

// 2. Loop melalui semua item dan tambahkan ke master stok sparepart
while($d = mysqli_fetch_array($cek)){
    $sparepart_id = $d['sparepart_id'];
    $qty = $d['qty'];
    $harga = $d['harga']; // harga beli terbaru

    // Update stok dan update harga_modal (berdasarkan instruksi review)
    // Harga_modal diset menjadi harga beli terbaru. Jika ingin dirata-rata (average cost), logikanya akan lebih kompleks.
    // Untuk saat ini, kita gunakan harga beli terakhir (Last in, first out concept for cost)
    mysqli_query($koneksi, "UPDATE sparepart SET 
                            stok = stok + $qty, 
                            harga_modal = '$harga' 
                            WHERE id = '$sparepart_id'") or die(mysqli_error($koneksi));
}

// 3. Ubah status PO menjadi SELESAI
mysqli_query($koneksi, "UPDATE pembelian_sparepart SET status='SELESAI' WHERE id='$id'") or die(mysqli_error($koneksi));

// 4. Redirect kembali ke halaman detail
header("location:../pembelian_detail.php?id=$id");
?>
