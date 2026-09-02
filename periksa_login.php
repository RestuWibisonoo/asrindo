<?php 
// menghubungkan dengan koneksi
include 'koneksi.php';

// menangkap data yang dikirim dari form
$username = $_POST['username'];
$sebagai = $_POST['sebagai'];

$login = mysqli_query($koneksi, "SELECT * FROM admin WHERE username='$username' AND role='$sebagai' AND status='AKTIF'");
$cek = mysqli_num_rows($login);

if($cek > 0){
	$data = mysqli_fetch_assoc($login);
	if (password_verify($_POST['password'], $data['password'])) {
		session_start();
		$_SESSION['id'] = $data['id'];
		$_SESSION['nama'] = $data['nama'];
		$_SESSION['username'] = $data['username'];
		$_SESSION['role'] = $data['role'];
		$_SESSION['status'] = "logedin";
		header("location:admin/");
	} else {
		header("location:index.php?alert=gagal");
	}
}else{
	header("location:index.php?alert=gagal");
}
