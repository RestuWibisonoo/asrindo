-- phpMyAdmin SQL Dump
-- version 4.9.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 10, 2026 at 09:08 AM
-- Server version: 10.4.10-MariaDB
-- PHP Version: 7.4.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `asrindo`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `karyawan_id` int(10) UNSIGNED DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ADMIN',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AKTIF',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_admin_karyawan` (`karyawan_id`),
  KEY `idx_admin_role` (`role`),
  KEY `idx_admin_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `karyawan_id`, `username`, `password`, `nama`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', '$2y$10$9zE9kiXXfBTJagLWAeIDDex057ZkPb1b5YJoYBZjZ7LJzafs10FBq', 'Djayus Norsalim', 'ADMIN', 'AKTIF', '2026-09-10 14:09:00', '2026-09-01 14:14:04', '2026-09-10 07:09:00'),
(2, 2, 'mekanik', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCqg7x8b9gY8wQfJvZ6S', 'Andi Pratama', 'MEKANIK', 'AKTIF', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 3, 'sales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCqg7x8b9gY8wQfJvZ6S', 'Siti Rahma', 'SALES', 'AKTIF', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Table structure for table `alat_berat`
--

DROP TABLE IF EXISTS `alat_berat`;
CREATE TABLE IF NOT EXISTS `alat_berat` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nomor_rangka` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tahun_pembuatan` year(4) DEFAULT NULL,
  `kondisi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lokasi` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`),
  UNIQUE KEY `nomor_rangka` (`nomor_rangka`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alat_berat`
--

INSERT INTO `alat_berat` (`id`, `kode`, `tipe`, `nomor_rangka`, `tahun_pembuatan`, `kondisi`, `status`, `lokasi`, `created_at`, `updated_at`) VALUES
(1, 'AGM 099', 'CAT 306', 'CAT3055EAVE209321', 2020, 'Bekas', 'Siap Jual', 'Garasi AGM', '2026-09-01 14:14:04', '2026-09-09 07:40:17'),
(2, 'AGM 100', 'CAT 320', 'CAT320ABCDE00101', 2019, 'Bekas', 'Siap Jual', 'Garasi AGM', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'AGM 101', 'Komatsu PC200', 'KMTPC200ABCDE002', 2021, 'Bekas', 'Tersedia', 'Workshop AGMQ', '2026-09-01 14:14:04', '2026-09-09 14:57:55'),
(4, 'AGM 102', 'Komatsu PC200', 'KMTPC200ABCDE00212', 2026, 'Baru', 'TERJUAL', 'Workshop AGMQ', '2026-09-09 15:07:32', '2026-09-09 16:26:52');

-- --------------------------------------------------------

--
-- Table structure for table `alat_berat_image`
--

DROP TABLE IF EXISTS `alat_berat_image`;
CREATE TABLE IF NOT EXISTS `alat_berat_image` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_abi_alat_berat` (`alat_berat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alat_berat_image`
--

INSERT INTO `alat_berat_image` (`id`, `alat_berat_id`, `file_name`, `file_path`, `created_at`) VALUES
(1, 3, 'Black and Yellow Minimalist No Smoking No Vaping Yard Sign.png', 'assets/uploads/alat_berat/alat_3_20260909055525_8069b622.png', '2026-09-09 05:55:25');

-- --------------------------------------------------------

--
-- Table structure for table `alat_berat_jasa`
--

DROP TABLE IF EXISTS `alat_berat_jasa`;
CREATE TABLE IF NOT EXISTS `alat_berat_jasa` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `jenis_jasa` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `biaya` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) GENERATED ALWAYS AS (`qty` * `biaya`) STORED,
  `tanggal` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_abj_alat_berat` (`alat_berat_id`),
  KEY `idx_abj_jenis_jasa` (`jenis_jasa`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alat_berat_jasa`
--

INSERT INTO `alat_berat_jasa` (`id`, `alat_berat_id`, `jenis_jasa`, `keterangan`, `qty`, `biaya`, `tanggal`, `created_at`, `updated_at`) VALUES
(1, 1, 'Perakitan', 'Perakitan dan pemasangan komponen', '1.00', '2500000.00', '2026-03-10', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 1, 'Finishing', 'Finishing body unit', '1.00', '1000000.00', '2026-03-14', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 2, 'Painting', 'Pengecatan ulang unit', '1.00', '3000000.00', '2026-04-05', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(4, 3, 'Jasa Pasang Oli', 'jasa pasang oli', '1.00', '1000000.00', '2026-09-09', '2026-09-09 05:56:25', '2026-09-09 05:56:25');

-- --------------------------------------------------------

--
-- Table structure for table `alat_berat_perawatan`
--

DROP TABLE IF EXISTS `alat_berat_perawatan`;
CREATE TABLE IF NOT EXISTS `alat_berat_perawatan` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finishing` decimal(18,2) NOT NULL DEFAULT 0.00,
  `suku_cadang` decimal(18,2) NOT NULL DEFAULT 0.00,
  `jasa` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) GENERATED ALWAYS AS (`finishing` + `suku_cadang` + `jasa`) STORED,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_abp_alat_berat` (`alat_berat_id`),
  KEY `idx_abp_tanggal` (`tanggal`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alat_berat_perawatan`
--

INSERT INTO `alat_berat_perawatan` (`id`, `alat_berat_id`, `tanggal`, `keterangan`, `finishing`, `suku_cadang`, `jasa`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-03-14', 'Perawatan dan finishing unit', '1000000.00', '83975.00', '0.00', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 1, '2026-08-10', 'Pemeriksaan rutin', '0.00', '0.00', '0.00', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 3, '2026-07-20', 'Penggantian filter dan pemeriksaan hydraulic', '500000.00', '1575000.00', '750000.00', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(4, 3, '2026-09-09', 'Cat full', '1000.00', '2111.00', '23.00', '2026-09-09 06:52:50', '2026-09-09 06:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `alat_berat_sparepart`
--

DROP TABLE IF EXISTS `alat_berat_sparepart`;
CREATE TABLE IF NOT EXISTS `alat_berat_sparepart` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `sparepart_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `harga` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) GENERATED ALWAYS AS (`qty` * `harga`) STORED,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_abs_alat_berat` (`alat_berat_id`),
  KEY `idx_abs_sparepart` (`sparepart_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alat_berat_sparepart`
--

INSERT INTO `alat_berat_sparepart` (`id`, `alat_berat_id`, `sparepart_id`, `qty`, `harga`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '4.00', '500000.00', 'Filter untuk unit AGM 099', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 1, 2, '1.00', '85000000.00', 'Hydraulic pump AGM 099', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 2, 3, '2.00', '2000000.00', 'Seal kit AGM 100', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(4, 3, 1, '3.00', '525000.00', 'Filter untuk unit AGM 101', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(5, 3, 2, '1.00', '342234234.00', '', '2026-09-09 06:20:55', '2026-09-09 06:20:55'),
(6, 3, 3, '3.00', '120.00', '', '2026-09-09 14:28:58', '2026-09-09 14:28:58');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

DROP TABLE IF EXISTS `customer`;
CREATE TABLE IF NOT EXISTS `customer` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`id`, `kode`, `nama`, `alamat`, `telepon`, `email`, `contact_person`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'CUS001', 'PT Maju Tambang', 'Kalimantan Timur', '0541-5551001', 'procurement@majutambang.example', 'Rudi', 'Customer perusahaan tambang', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'CUS002', 'PT Karya Mineral', 'Kalimantan Selatan', '0511-5552002', 'purchasing@karyamineral.example', 'Sinta', 'Customer alat berat', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'CUS003', 'CV Mitra Konstruksi', 'Jawa Tengah', '024-5553003', 'admin@mitrakonstruksi.example', 'Dedi', 'Customer bidang konstruksi', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(4, 'CUS004', 'Nor', 'Solo', '098', 'nor@gmail.com', 'Nor', NULL, '2026-09-09 07:22:38', '2026-09-09 07:22:38');

-- --------------------------------------------------------

--
-- Table structure for table `karyawan`
--

DROP TABLE IF EXISTS `karyawan`;
CREATE TABLE IF NOT EXISTS `karyawan` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nik` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jabatan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departemen` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AKTIF',
  `tanggal_masuk` date DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nik` (`nik`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `karyawan`
--

INSERT INTO `karyawan` (`id`, `nik`, `nama`, `jabatan`, `departemen`, `telepon`, `email`, `alamat`, `status`, `tanggal_masuk`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'KRY001', 'Budi Santoso', 'Admin Gudang', 'Gudang', '081200000001', 'budi@asrindo.example', 'Magelang', 'AKTIF', '2023-01-10', 'Admin gudang dan inventory', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'KRY002', 'Andi Pratama', 'Mekanik', 'Workshop', '081200000002', 'andi@asrindo.example', 'Magelang', 'AKTIF', '2022-05-15', 'Mekanik alat berat', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'KRY003', 'Siti Rahma', 'Sales', 'Penjualan', '081200000003', 'siti@asrindo.example', 'Yogyakarta', 'AKTIF', '2024-02-01', 'Sales alat berat', '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_keuangan`
--

DROP TABLE IF EXISTS `kategori_keuangan`;
CREATE TABLE IF NOT EXISTS `kategori_keuangan` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe` enum('PENDAPATAN','PENGELUARAN') COLLATE utf8mb4_unicode_ci NOT NULL,
  `kelompok_laporan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kategori_keuangan`
--

INSERT INTO `kategori_keuangan` (`id`, `kode`, `nama`, `tipe`, `kelompok_laporan`, `keterangan`, `created_at`, `updated_at`) VALUES
(22, 'SEWA', 'Pendapatan Sewa', 'PENDAPATAN', 'PENDAPATAN', 'Pendapatan yang berasal dari kegiatan sewa/rental alat berat.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(23, 'GUDANG_SEWA', 'Sewa Gudang', 'PENGELUARAN', 'MODAL_ASET', 'Pengeluaran untuk sewa gudang.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(24, 'GUDANG_PERAWATAN', 'Biaya Perawatan Gudang', 'PENGELUARAN', 'MODAL_ASET', 'Pengeluaran untuk perbaikan dan perawatan gudang.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(25, 'GAJI', 'Pembayaran Gaji', 'PENGELUARAN', 'BEBAN_OPERASIONAL', 'Pengeluaran pembayaran gaji dan upah karyawan.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(26, 'ASET', 'Belanja Aset', 'PENGELUARAN', 'BEBAN_OPERASIONAL', 'Pengeluaran untuk belanja aset sesuai pencatatan operasional perusahaan.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(27, 'KONSUMSI', 'Belanja Konsumsi', 'PENGELUARAN', 'BEBAN_OPERASIONAL', 'Pengeluaran untuk konsumsi dan kebutuhan konsumsi perusahaan.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(28, 'IKLAN', 'Biaya Iklan', 'PENGELUARAN', 'BEBAN_OPERASIONAL', 'Pengeluaran untuk iklan, promosi, dan kegiatan pemasaran.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(29, 'MAINTENANCE', 'Biaya Maintenance', 'PENGELUARAN', 'BEBAN_OPERASIONAL', 'Pengeluaran untuk maintenance dan pemeliharaan operasional.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(30, 'SERVICE_TAMU', 'Service Tamu', 'PENGELUARAN', 'BEBAN_OPERASIONAL', 'Pengeluaran untuk kebutuhan pelayanan/service tamu.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(31, 'BAHAN_BAKAR', 'Belanja Bahan Bakar', 'PENGELUARAN', 'BEBAN_OPERASIONAL', 'Pengeluaran pembelian bahan bakar untuk kebutuhan operasional.', '2026-09-10 06:21:15', '2026-09-10 06:21:15'),
(32, 'LAIN_LAIN', 'Lain-lain', 'PENGELUARAN', 'BEBAN_OPERASIONAL', 'Pengeluaran lainnya yang tidak termasuk kategori operasional khusus.', '2026-09-10 06:21:15', '2026-09-10 06:21:15');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_pemasukan`
--

DROP TABLE IF EXISTS `kategori_pemasukan`;
CREATE TABLE IF NOT EXISTS `kategori_pemasukan` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kategori_pemasukan_kode` (`kode`),
  KEY `idx_kategori_pemasukan_nama` (`nama`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kategori_pemasukan`
--

INSERT INTO `kategori_pemasukan` (`id`, `kode`, `nama`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'K001', 'Kas Operasional', 'Pemasukan kas operasional', '2026-09-10 03:41:18', '2026-09-10 03:41:18'),
(2, 'K002', 'Pendapatan Jasa', 'Pendapatan dari jasa di luar transaksi penjualan alat berat', '2026-09-10 03:41:18', '2026-09-10 03:41:18'),
(3, 'K003', 'Pendapatan Lain-lain', 'Pemasukan lain yang bukan berasal dari penjualan', '2026-09-10 03:41:18', '2026-09-10 03:41:18'),
(4, 'K004', 'Pengembalian Dana', 'Dana yang diterima kembali oleh perusahaan', '2026-09-10 03:41:18', '2026-09-10 03:41:18');

-- --------------------------------------------------------

--
-- Table structure for table `pemasukan`
--

DROP TABLE IF EXISTS `pemasukan`;
CREATE TABLE IF NOT EXISTS `pemasukan` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_pemasukan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal` date NOT NULL,
  `sumber` enum('PENJUALAN','NON_PENJUALAN') COLLATE utf8mb4_unicode_ci NOT NULL,
  `penjualan_id` int(10) UNSIGNED DEFAULT NULL,
  `jadwal_pembayaran_id` int(10) UNSIGNED DEFAULT NULL,
  `kategori_id` int(10) UNSIGNED DEFAULT NULL,
  `jenis_pembayaran` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nominal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `metode_pembayaran` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referensi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pemasukan_nomor` (`nomor_pemasukan`),
  UNIQUE KEY `uk_pemasukan_jadwal_pembayaran` (`jadwal_pembayaran_id`),
  KEY `idx_pemasukan_tanggal` (`tanggal`),
  KEY `idx_pemasukan_sumber` (`sumber`),
  KEY `idx_pemasukan_penjualan` (`penjualan_id`),
  KEY `idx_pemasukan_kategori` (`kategori_id`),
  KEY `idx_pemasukan_created_by` (`created_by`),
  KEY `idx_pemasukan_jadwal_pembayaran` (`jadwal_pembayaran_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pemasukan`
--

INSERT INTO `pemasukan` (`id`, `nomor_pemasukan`, `tanggal`, `sumber`, `penjualan_id`, `jadwal_pembayaran_id`, `kategori_id`, `jenis_pembayaran`, `nominal`, `metode_pembayaran`, `referensi`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 'PM-20260910-0001', '2026-09-10', 'PENJUALAN', 6, 4, NULL, 'DP', '33000000.00', 'TUNAI', '12', 'DP OK', 1, '2026-09-10 05:21:49', '2026-09-10 05:21:49'),
(9, 'PM-20260910-0002', '2026-09-10', 'NON_PENJUALAN', NULL, NULL, 22, 'PENDAPATAN', '2000000.00', 'TUNAI', 'sd', 'Sewa AGM01', 1, '2026-09-10 06:39:43', '2026-09-10 06:39:43'),
(10, 'PM-20260910-0003', '2026-07-01', 'NON_PENJUALAN', NULL, NULL, 22, 'PENDAPATAN', '2000000.00', 'TUNAI', 'asd', 'Sewa AGM1', 1, '2026-09-10 06:50:33', '2026-09-10 06:50:33');

-- --------------------------------------------------------

--
-- Table structure for table `pembelian_alat_berat`
--

DROP TABLE IF EXISTS `pembelian_alat_berat`;
CREATE TABLE IF NOT EXISTS `pembelian_alat_berat` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_pembelian` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal` date NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `estimasi_kedatangan` date DEFAULT NULL,
  `kedatangan_aktual` date DEFAULT NULL,
  `kurs_pembelian` decimal(18,2) NOT NULL DEFAULT 0.00,
  `biaya_bea_cukai` decimal(18,2) NOT NULL DEFAULT 0.00,
  `biaya_pengiriman` decimal(18,2) NOT NULL DEFAULT 0.00,
  `biaya_lain` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PROSES',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomor_pembelian` (`nomor_pembelian`),
  KEY `idx_pab_tanggal` (`tanggal`),
  KEY `idx_pab_supplier` (`supplier_id`),
  KEY `idx_pab_status` (`status`),
  KEY `idx_pab_estimasi` (`estimasi_kedatangan`),
  KEY `idx_pab_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pembelian_alat_berat`
--

INSERT INTO `pembelian_alat_berat` (`id`, `nomor_pembelian`, `tanggal`, `supplier_id`, `estimasi_kedatangan`, `kedatangan_aktual`, `kurs_pembelian`, `biaya_bea_cukai`, `biaya_pengiriman`, `biaya_lain`, `total`, `status`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PO-001', '2026-09-09', 3, '2026-09-09', NULL, '10000.00', '2000000.00', '200000.00', '0.00', '432200000.00', 'PROSES', NULL, 1, '2026-09-09 08:02:58', '2026-09-09 08:10:56'),
(2, 'PO002', '2026-09-09', 3, '2026-09-09', NULL, '10000.00', '2000000.00', '250000.00', '0.00', '202250000.00', 'PROSES', NULL, 1, '2026-09-09 14:27:29', '2026-09-09 14:27:29'),
(3, 'PO003', '2026-09-09', 2, '2026-09-09', NULL, '10000.00', '2000000.00', '1000000.00', '0.00', '253000000.00', 'PROSES', NULL, 1, '2026-09-09 16:03:29', '2026-09-09 16:03:29');

-- --------------------------------------------------------

--
-- Table structure for table `pembelian_alat_berat_detail`
--

DROP TABLE IF EXISTS `pembelian_alat_berat_detail`;
CREATE TABLE IF NOT EXISTS `pembelian_alat_berat_detail` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pembelian_id` int(10) UNSIGNED NOT NULL,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `harga_beli` decimal(18,2) NOT NULL DEFAULT 0.00,
  `harga_usd` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18,2) GENERATED ALWAYS AS (`harga_beli`) STORED,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pabd_pembelian` (`pembelian_id`),
  KEY `idx_pabd_alat_berat` (`alat_berat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pembelian_alat_berat_detail`
--

INSERT INTO `pembelian_alat_berat_detail` (`id`, `pembelian_id`, `alat_berat_id`, `harga_beli`, `harga_usd`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '200000000.00', '20000.00', NULL, '2026-09-09 08:02:58', '2026-09-09 08:02:58'),
(2, 1, 2, '230000000.00', '0.00', NULL, '2026-09-09 08:10:37', '2026-09-09 08:10:56'),
(3, 2, 3, '200000000.00', '20000.00', NULL, '2026-09-09 14:27:29', '2026-09-09 14:27:29'),
(4, 3, 4, '250000000.00', '25000.00', NULL, '2026-09-09 16:03:29', '2026-09-09 16:03:29');

-- --------------------------------------------------------

--
-- Table structure for table `pembelian_pembayaran`
--

DROP TABLE IF EXISTS `pembelian_pembayaran`;
CREATE TABLE IF NOT EXISTS `pembelian_pembayaran` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_pembayaran` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sumber` enum('SPAREPART','ALAT_BERAT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `pembelian_sparepart_id` int(10) UNSIGNED DEFAULT NULL,
  `pembelian_alat_berat_id` int(10) UNSIGNED DEFAULT NULL,
  `termin_ke` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `jenis_pembayaran` enum('DP','CICILAN','PELUNASAN','LAINNYA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CICILAN',
  `tanggal_jatuh_tempo` date NOT NULL,
  `nominal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('BELUM_BAYAR','PAID','BATAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BELUM_BAYAR',
  `tanggal_bayar` date DEFAULT NULL,
  `metode_pembayaran` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referensi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pembelian_pembayaran_nomor` (`nomor_pembayaran`),
  KEY `idx_pp_sumber` (`sumber`),
  KEY `idx_pp_sparepart` (`pembelian_sparepart_id`),
  KEY `idx_pp_alat_berat` (`pembelian_alat_berat_id`),
  KEY `idx_pp_jatuh_tempo` (`tanggal_jatuh_tempo`),
  KEY `idx_pp_status` (`status`),
  KEY `idx_pp_tanggal_bayar` (`tanggal_bayar`),
  KEY `idx_pp_created_by` (`created_by`)
) ;

--
-- Dumping data for table `pembelian_pembayaran`
--

INSERT INTO `pembelian_pembayaran` (`id`, `nomor_pembayaran`, `sumber`, `pembelian_sparepart_id`, `pembelian_alat_berat_id`, `termin_ke`, `jenis_pembayaran`, `tanggal_jatuh_tempo`, `nominal`, `status`, `tanggal_bayar`, `metode_pembayaran`, `referensi`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 'JAD-20260910052827-33', 'ALAT_BERAT', NULL, 3, 1, 'DP', '2026-09-10', '53000000.00', 'PAID', '2026-09-10', 'CASH', '23', 'OK DP', NULL, '2026-09-10 05:28:27', '2026-09-10 05:28:50'),
(7, 'JAD-20260910052833-94', 'ALAT_BERAT', NULL, 3, 2, 'DP', '2026-09-10', '200000000.00', 'BELUM_BAYAR', NULL, NULL, NULL, NULL, NULL, '2026-09-10 05:28:33', '2026-09-10 05:28:33');

-- --------------------------------------------------------

--
-- Table structure for table `pembelian_sparepart`
--

DROP TABLE IF EXISTS `pembelian_sparepart`;
CREATE TABLE IF NOT EXISTS `pembelian_sparepart` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_pembelian` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal` date NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_psp_nomor` (`nomor_pembelian`),
  KEY `idx_psp_tanggal` (`tanggal`),
  KEY `idx_psp_supplier` (`supplier_id`),
  KEY `idx_psp_status` (`status`),
  KEY `idx_psp_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pembelian_sparepart`
--

INSERT INTO `pembelian_sparepart` (`id`, `nomor_pembelian`, `tanggal`, `supplier_id`, `total`, `status`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 'P-001', '2026-09-10', 3, '25000.00', 'SELESAI', 'ok', NULL, '2026-09-10 05:34:26', '2026-09-10 05:35:26');

-- --------------------------------------------------------

--
-- Table structure for table `pembelian_sparepart_detail`
--

DROP TABLE IF EXISTS `pembelian_sparepart_detail`;
CREATE TABLE IF NOT EXISTS `pembelian_sparepart_detail` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pembelian_id` int(10) UNSIGNED NOT NULL,
  `sparepart_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(18,2) NOT NULL DEFAULT 0.00,
  `harga` decimal(18,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pspd_pembelian` (`pembelian_id`),
  KEY `idx_pspd_sparepart` (`sparepart_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pembelian_sparepart_detail`
--

INSERT INTO `pembelian_sparepart_detail` (`id`, `pembelian_id`, `sparepart_id`, `qty`, `harga`, `diskon`, `subtotal`, `keterangan`, `created_at`, `updated_at`) VALUES
(6, 4, 1, '10.00', '1000.00', '0.00', '10000.00', NULL, '2026-09-10 05:34:26', '2026-09-10 05:34:26'),
(7, 4, 2, '10.00', '1500.00', '0.00', '15000.00', NULL, '2026-09-10 05:35:07', '2026-09-10 05:35:07');

-- --------------------------------------------------------

--
-- Table structure for table `pengeluaran`
--

DROP TABLE IF EXISTS `pengeluaran`;
CREATE TABLE IF NOT EXISTS `pengeluaran` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_pengeluaran` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal` date NOT NULL,
  `sumber` enum('PEMBELIAN','NON_PEMBELIAN') COLLATE utf8mb4_unicode_ci NOT NULL,
  `pembelian_pembayaran_id` int(10) UNSIGNED DEFAULT NULL,
  `pembelian_sparepart_id` int(10) UNSIGNED DEFAULT NULL,
  `kategori_id` int(10) UNSIGNED DEFAULT NULL,
  `jenis_pengeluaran` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nominal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `metode_pembayaran` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referensi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pengeluaran_nomor` (`nomor_pengeluaran`),
  UNIQUE KEY `uk_pengeluaran_pembayaran` (`pembelian_pembayaran_id`),
  UNIQUE KEY `uk_pengeluaran_pembelian_sparepart` (`pembelian_sparepart_id`),
  KEY `idx_pengeluaran_tanggal` (`tanggal`),
  KEY `idx_pengeluaran_sumber` (`sumber`),
  KEY `idx_pengeluaran_pembayaran` (`pembelian_pembayaran_id`),
  KEY `idx_pengeluaran_pembelian_sparepart` (`pembelian_sparepart_id`),
  KEY `idx_pengeluaran_kategori` (`kategori_id`),
  KEY `idx_pengeluaran_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengeluaran`
--

INSERT INTO `pengeluaran` (`id`, `nomor_pengeluaran`, `tanggal`, `sumber`, `pembelian_pembayaran_id`, `pembelian_sparepart_id`, `kategori_id`, `jenis_pengeluaran`, `nominal`, `metode_pembayaran`, `referensi`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PK-20260910-0001', '2026-09-10', 'PEMBELIAN', 6, NULL, NULL, 'Pembayaran Pembelian Alat Berat', '53000000.00', 'CASH', '23', 'OK DP', 1, '2026-09-10 05:28:50', '2026-09-10 05:28:50'),
(3, 'PK-SP-20260910-00000004', '2026-09-10', 'PEMBELIAN', NULL, 4, NULL, 'Pembayaran Pembelian Sparepart', '25000.00', NULL, 'P-001', 'Pembayaran pembelian sparepart P-001', 1, '2026-09-10 05:35:26', '2026-09-10 05:35:26'),
(4, 'PK-20260910-0002', '2026-09-10', 'NON_PEMBELIAN', NULL, NULL, 26, 'Belanja Aset', '2000000.00', 'TUNAI', 'sdf', 'Sewa Gudang', 1, '2026-09-10 06:45:35', '2026-09-10 06:45:35'),
(5, 'PK-20260910-0003', '2026-09-10', 'NON_PEMBELIAN', NULL, NULL, 24, 'Biaya Perawatan Gudang', '500000.00', 'TUNAI', 'sdf', 'Biaya perawatan gudang', 1, '2026-09-10 06:46:04', '2026-09-10 06:46:04'),
(6, 'PK-20260910-0004', '2026-09-10', 'NON_PEMBELIAN', NULL, NULL, 25, 'Pembayaran Gaji', '3000000.00', NULL, NULL, 'Gaji A', 1, '2026-09-10 06:46:26', '2026-09-10 06:46:26'),
(7, 'PK-20260910-0005', '2026-09-10', 'NON_PEMBELIAN', NULL, NULL, 25, 'Pembayaran Gaji', '2000000.00', 'TUNAI', 'sdf', 'Gaji B', 1, '2026-09-10 06:46:39', '2026-09-10 06:46:39'),
(8, 'PK-20260910-0006', '2026-09-10', 'NON_PEMBELIAN', NULL, NULL, 27, 'Belanja Konsumsi', '1000000.00', 'TUNAI', 'Ad', 'Makan 1', 1, '2026-09-10 06:46:59', '2026-09-10 06:46:59'),
(9, 'PK-20260910-0007', '2026-09-10', 'NON_PEMBELIAN', NULL, NULL, 29, 'Biaya Maintenance', '2000000.00', 'TUNAI', 'sdf', 'Maintenance', 1, '2026-09-10 06:47:21', '2026-09-10 06:47:21'),
(10, 'PK-20260910-0008', '2026-09-10', 'NON_PEMBELIAN', NULL, NULL, 30, 'Service Tamu', '1000000.00', 'TUNAI', 'asd', 'Service Tamu', 1, '2026-09-10 06:47:42', '2026-09-10 06:47:42'),
(11, 'PK-20260910-0009', '2026-09-10', 'NON_PEMBELIAN', NULL, NULL, 31, 'Belanja Bahan Bakar', '2000000.00', 'TUNAI', 'sdf', 'BBM', 1, '2026-09-10 06:48:00', '2026-09-10 06:48:00'),
(12, 'PK-20260910-0010', '2026-07-01', 'NON_PEMBELIAN', NULL, NULL, 31, 'Belanja Bahan Bakar', '300000.00', 'TUNAI', 'df', 'BBM', 1, '2026-09-10 06:50:56', '2026-09-10 06:50:56');

-- --------------------------------------------------------

--
-- Table structure for table `penjualan`
--

DROP TABLE IF EXISTS `penjualan`;
CREATE TABLE IF NOT EXISTS `penjualan` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_penjualan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal` date NOT NULL,
  `tanggal_jatuh_tempo` date NOT NULL,
  `ppn` int(10) NOT NULL DEFAULT 0,
  `alamat_pengiriman` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomor_penjualan` (`nomor_penjualan`),
  KEY `idx_penjualan_tanggal` (`tanggal`),
  KEY `idx_penjualan_customer` (`customer_id`),
  KEY `idx_penjualan_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `penjualan`
--

INSERT INTO `penjualan` (`id`, `nomor_penjualan`, `tanggal`, `tanggal_jatuh_tempo`, `ppn`, `alamat_pengiriman`, `customer_id`, `total`, `status`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PJ-20260910-0001', '2026-09-10', '0000-00-00', 0, '', 1, '750000000.00', 'DRAFT', 'Penjualan unit AGM 100', 3, '2026-09-01 14:14:04', '2026-09-10 05:19:47'),
(2, 'PJ-20260915-0002', '2026-09-15', '0000-00-00', 0, '', 2, '625000000.00', 'DRAFT', 'Penjualan unit AGM 101', 3, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'PJ-20260920-0003', '2026-09-20', '0000-00-00', 0, '', 3, '450000000.00', 'DRAFT', 'Penjualan unit AGM 099', 3, '2026-09-01 14:14:04', '2026-09-10 05:20:03'),
(6, 'PJ001', '2026-09-09', '2026-10-01', 11, 'b', 4, '333000000.00', 'DRAFT', 's', 1, '2026-09-09 16:10:41', '2026-09-10 05:21:32');

-- --------------------------------------------------------

--
-- Table structure for table `penjualan_detail`
--

DROP TABLE IF EXISTS `penjualan_detail`;
CREATE TABLE IF NOT EXISTS `penjualan_detail` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `penjualan_id` int(10) UNSIGNED NOT NULL,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `harga_jual` decimal(18,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18,2) GENERATED ALWAYS AS (`harga_jual` - `diskon`) STORED,
  `hpp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `laba` decimal(18,2) GENERATED ALWAYS AS (`harga_jual` - `diskon` - `hpp`) STORED,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pjd_penjualan` (`penjualan_id`),
  KEY `idx_pjd_alat_berat` (`alat_berat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `penjualan_detail`
--

INSERT INTO `penjualan_detail` (`id`, `penjualan_id`, `alat_berat_id`, `harga_jual`, `diskon`, `hpp`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, 2, '750000000.00', '0.00', '250000000.00', 'Unit CAT 320', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 2, 3, '625000000.00', '25000000.00', '300000000.00', 'Unit Komatsu PC200', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 3, 1, '450000000.00', '0.00', '292261640.00', 'Unit CAT 306', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(4, 6, 4, '300000000.00', '0.00', '253000000.00', 'a', '2026-09-09 16:10:41', '2026-09-09 16:10:41');

-- --------------------------------------------------------

--
-- Table structure for table `penjualan_pembayaran`
--

DROP TABLE IF EXISTS `penjualan_pembayaran`;
CREATE TABLE IF NOT EXISTS `penjualan_pembayaran` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `penjualan_id` int(10) UNSIGNED NOT NULL,
  `termin_ke` int(10) UNSIGNED NOT NULL,
  `jenis_pembayaran` enum('DP','CICILAN','PELUNASAN') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CICILAN',
  `tanggal_jatuh_tempo` date NOT NULL,
  `nominal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('BELUM_BAYAR','PAID') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BELUM_BAYAR',
  `tanggal_bayar` date DEFAULT NULL,
  `metode_pembayaran` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referensi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_penjualan_pembayaran_termin` (`penjualan_id`,`termin_ke`),
  KEY `idx_penjualan_pembayaran_penjualan` (`penjualan_id`),
  KEY `idx_penjualan_pembayaran_jatuh_tempo` (`tanggal_jatuh_tempo`),
  KEY `idx_penjualan_pembayaran_status` (`status`),
  KEY `idx_penjualan_pembayaran_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `penjualan_pembayaran`
--

INSERT INTO `penjualan_pembayaran` (`id`, `penjualan_id`, `termin_ke`, `jenis_pembayaran`, `tanggal_jatuh_tempo`, `nominal`, `status`, `tanggal_bayar`, `metode_pembayaran`, `referensi`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 6, 1, 'DP', '2026-09-10', '33000000.00', 'PAID', '2026-09-10', 'TUNAI', '12', 'DP OK', NULL, '2026-09-10 05:21:15', '2026-09-10 05:21:49'),
(5, 6, 2, 'DP', '2026-10-01', '300000000.00', 'BELUM_BAYAR', NULL, NULL, NULL, NULL, NULL, '2026-09-10 05:21:32', '2026-09-10 05:21:32');

-- --------------------------------------------------------

--
-- Table structure for table `sparepart`
--

DROP TABLE IF EXISTS `sparepart`;
CREATE TABLE IF NOT EXISTS `sparepart` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `part_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `merk` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `satuan` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PCS',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stok` int(10) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sparepart`
--

INSERT INTO `sparepart` (`id`, `kode`, `nama`, `part_number`, `merk`, `satuan`, `keterangan`, `stok`, `created_at`, `updated_at`) VALUES
(1, 'SP001', 'Oil Filter', 'CAT-1R0739', 'CAT', 'PCS', 'Filter oli engine', 23, '2026-09-01 14:14:04', '2026-09-10 05:35:26'),
(2, 'SP002', 'Hydraulic Pump', 'HP-306-001', 'CAT', 'PCS', 'Pompa hydraulic excavator', 30, '2026-09-01 14:14:04', '2026-09-10 05:35:26'),
(3, 'SP003', 'Seal Kit Hydraulic', 'SK-306-001', 'CAT', 'SET', 'Seal kit hydraulic system', 8, '2026-09-01 14:14:04', '2026-09-09 14:28:58'),
(4, 'SP004', 'Sealer', 'SEAL', 'Toshiba', 'PCS', 'ad', 0, '2026-09-09 15:19:13', '2026-09-09 15:19:32');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

DROP TABLE IF EXISTS `supplier`;
CREATE TABLE IF NOT EXISTS `supplier` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AKTIF',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`id`, `kode`, `nama`, `alamat`, `telepon`, `email`, `contact_person`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'SUP001', 'PT Caterpillar Indonesia', 'Jakarta', '021-5551001', 'sales@cat-indonesia.example', 'Andi', 'AKTIF', 'Supplier sparepart alat berat', '2026-09-01 14:14:04', '2026-09-09 08:28:48'),
(2, 'SUP002', 'PT United Equipment', 'Surabaya', '031-5552002', 'sales@united-equipment.example', 'Budi', 'AKTIF', 'Supplier komponen dan sparepart', '2026-09-01 14:14:04', '2026-09-09 08:30:27'),
(3, 'SUP003', 'CV Sumber Teknik', 'Semarang', '024-5553003', 'info@sumber-teknik.example', 'Citra', 'AKTIF', 'Supplier sparepart umum', '2026-09-01 14:14:04', '2026-09-09 08:30:30'),
(4, 'SUP004', 'RJcom', 'Magelang', '085643764044', 'djayus.nur@gmail.com', 'Djayus Norsalim', 'AKTIF', NULL, '2026-09-09 07:19:33', '2026-09-09 08:30:33'),
(5, 'SUP005', 'Salim', 'Semarang', '92', 'salim@gmail.com', 'Salim', 'AKTIF', NULL, '2026-09-09 08:30:15', '2026-09-09 08:30:15');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_keuangan`
--

DROP TABLE IF EXISTS `transaksi_keuangan`;
CREATE TABLE IF NOT EXISTS `transaksi_keuangan` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_transaksi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal` date NOT NULL,
  `kategori_id` int(10) UNSIGNED NOT NULL,
  `nominal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `metode_pembayaran` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referensi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nomor_transaksi` (`nomor_transaksi`),
  KEY `idx_tk_tanggal` (`tanggal`),
  KEY `idx_tk_kategori` (`kategori_id`),
  KEY `idx_tk_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_karyawan` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `alat_berat_image`
--
ALTER TABLE `alat_berat_image`
  ADD CONSTRAINT `fk_abi_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `alat_berat_jasa`
--
ALTER TABLE `alat_berat_jasa`
  ADD CONSTRAINT `fk_abj_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `alat_berat_perawatan`
--
ALTER TABLE `alat_berat_perawatan`
  ADD CONSTRAINT `fk_abp_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `alat_berat_sparepart`
--
ALTER TABLE `alat_berat_sparepart`
  ADD CONSTRAINT `fk_abs_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_abs_sparepart` FOREIGN KEY (`sparepart_id`) REFERENCES `sparepart` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pemasukan`
--
ALTER TABLE `pemasukan`
  ADD CONSTRAINT `fk_pemasukan_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pemasukan_jadwal_pembayaran` FOREIGN KEY (`jadwal_pembayaran_id`) REFERENCES `penjualan_pembayaran` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pemasukan_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_keuangan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pemasukan_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pembelian_alat_berat`
--
ALTER TABLE `pembelian_alat_berat`
  ADD CONSTRAINT `fk_pab_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pab_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pembelian_alat_berat_detail`
--
ALTER TABLE `pembelian_alat_berat_detail`
  ADD CONSTRAINT `fk_pabd_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pabd_pembelian` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian_alat_berat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pembelian_pembayaran`
--
ALTER TABLE `pembelian_pembayaran`
  ADD CONSTRAINT `fk_pp_alat_berat` FOREIGN KEY (`pembelian_alat_berat_id`) REFERENCES `pembelian_alat_berat` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pp_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pp_sparepart` FOREIGN KEY (`pembelian_sparepart_id`) REFERENCES `pembelian_sparepart` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pembelian_sparepart`
--
ALTER TABLE `pembelian_sparepart`
  ADD CONSTRAINT `fk_psp_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_psp_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pembelian_sparepart_detail`
--
ALTER TABLE `pembelian_sparepart_detail`
  ADD CONSTRAINT `fk_pspd_pembelian` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian_sparepart` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pspd_sparepart` FOREIGN KEY (`sparepart_id`) REFERENCES `sparepart` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD CONSTRAINT `fk_pengeluaran_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pengeluaran_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_keuangan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pengeluaran_pembayaran` FOREIGN KEY (`pembelian_pembayaran_id`) REFERENCES `pembelian_pembayaran` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pengeluaran_pembelian_sparepart` FOREIGN KEY (`pembelian_sparepart_id`) REFERENCES `pembelian_sparepart` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `penjualan`
--
ALTER TABLE `penjualan`
  ADD CONSTRAINT `fk_penjualan_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penjualan_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `penjualan_detail`
--
ALTER TABLE `penjualan_detail`
  ADD CONSTRAINT `fk_pjd_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pjd_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `penjualan_pembayaran`
--
ALTER TABLE `penjualan_pembayaran`
  ADD CONSTRAINT `fk_penjualan_pembayaran_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penjualan_pembayaran_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  ADD CONSTRAINT `fk_transaksi_keuangan_admin` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transaksi_keuangan_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_keuangan` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
