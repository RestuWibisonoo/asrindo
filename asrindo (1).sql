-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 02 Sep 2026 pada 22.10
-- Versi server: 10.4.28-MariaDB
-- Versi PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
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
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(10) UNSIGNED NOT NULL,
  `karyawan_id` int(10) UNSIGNED DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'ADMIN',
  `status` varchar(30) NOT NULL DEFAULT 'AKTIF',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id`, `karyawan_id`, `username`, `password`, `nama`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', '$2y$10$L2o1nXJXrMLfSI5FZy2C0.CQgoqR1fJXwfMwutw/DmYSX5NkWFz.G', 'Budi Santoso', 'ADMIN', 'AKTIF', NULL, '2026-09-01 14:14:04', '2026-09-02 16:52:11'),
(2, 2, 'mekanik', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCqg7x8b9gY8wQfJvZ6S', 'Andi Pratama', 'MEKANIK', 'AKTIF', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 3, 'sales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCqg7x8b9gY8wQfJvZ6S', 'Siti Rahma', 'SALES', 'AKTIF', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `alat_berat`
--

CREATE TABLE `alat_berat` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `tipe` varchar(100) NOT NULL,
  `nomor_rangka` varchar(100) DEFAULT NULL,
  `nomor_mesin` varchar(100) DEFAULT NULL,
  `tahun_pembuatan` year(4) DEFAULT NULL,
  `kondisi` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `lokasi` varchar(150) DEFAULT NULL,
  `kurs_beli` decimal(15,2) DEFAULT 0.00,
  `harga_beli_usd` decimal(15,2) DEFAULT 0.00,
  `harga_beli_idr` decimal(15,2) DEFAULT 0.00,
  `harga_jual_estimasi` decimal(15,2) DEFAULT 0.00,
  `harga_sewa_all_in` decimal(15,2) DEFAULT 0.00,
  `harga_sewa_kosongan` decimal(15,2) DEFAULT 0.00,
  `total_jam_operasional` decimal(10,2) DEFAULT 0.00,
  `jam_operasional_terakhir` decimal(10,2) DEFAULT 0.00,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `alat_berat`
--

INSERT INTO `alat_berat` (`id`, `kode`, `tipe`, `nomor_rangka`, `nomor_mesin`, `tahun_pembuatan`, `kondisi`, `status`, `lokasi`, `kurs_beli`, `harga_beli_usd`, `harga_beli_idr`, `harga_jual_estimasi`, `harga_sewa_all_in`, `harga_sewa_kosongan`, `total_jam_operasional`, `jam_operasional_terakhir`, `deskripsi`, `created_at`, `updated_at`) VALUES
(1, 'AGM 099', 'CAT 306', 'CAT3055EAVE209321', NULL, '2020', 'Bekas', 'Perbaikan', 'Garasi AGM', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'AGM 100', 'CAT 320', 'CAT320ABCDE00101', NULL, '2019', 'Bekas', 'Siap Jual', 'Garasi AGM', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'AGM 101', 'Komatsu PC200', 'KMTPC200ABCDE002', NULL, '2021', 'Bekas', 'Perbaikan', 'Workshop AGM', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(4, 'SUP003', 'berat bgt', 'asdadad', '21312', '2026', 'Baru', 'Tersedia', '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', '2026-09-02 18:40:09', '2026-09-02 18:40:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `alat_berat_jasa`
--

CREATE TABLE `alat_berat_jasa` (
  `id` int(10) UNSIGNED NOT NULL,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `jenis_jasa` varchar(100) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `biaya` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) GENERATED ALWAYS AS (`qty` * `biaya`) STORED,
  `tanggal` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `alat_berat_jasa`
--

INSERT INTO `alat_berat_jasa` (`id`, `alat_berat_id`, `jenis_jasa`, `keterangan`, `qty`, `biaya`, `tanggal`, `created_at`, `updated_at`) VALUES
(1, 1, 'Perakitan', 'Perakitan dan pemasangan komponen', 1.00, 2500000.00, '2026-03-10', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 1, 'Finishing', 'Finishing body unit', 1.00, 1000000.00, '2026-03-14', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 2, 'Painting', 'Pengecatan ulang unit', 1.00, 3000000.00, '2026-04-05', '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `alat_berat_perawatan`
--

CREATE TABLE `alat_berat_perawatan` (
  `id` int(10) UNSIGNED NOT NULL,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `finishing` decimal(18,2) NOT NULL DEFAULT 0.00,
  `suku_cadang` decimal(18,2) NOT NULL DEFAULT 0.00,
  `jasa` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) GENERATED ALWAYS AS (`finishing` + `suku_cadang` + `jasa`) STORED,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `alat_berat_perawatan`
--

INSERT INTO `alat_berat_perawatan` (`id`, `alat_berat_id`, `tanggal`, `keterangan`, `finishing`, `suku_cadang`, `jasa`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-03-14', 'Perawatan dan finishing unit', 1000000.00, 83975.00, 0.00, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 1, '2026-08-10', 'Pemeriksaan rutin', 0.00, 0.00, 0.00, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 3, '2026-07-20', 'Penggantian filter dan pemeriksaan hydraulic', 500000.00, 1575000.00, 750000.00, '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `alat_berat_sparepart`
--

CREATE TABLE `alat_berat_sparepart` (
  `id` int(10) UNSIGNED NOT NULL,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `sparepart_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `harga` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) GENERATED ALWAYS AS (`qty` * `harga`) STORED,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `alat_berat_sparepart`
--

INSERT INTO `alat_berat_sparepart` (`id`, `alat_berat_id`, `sparepart_id`, `qty`, `harga`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 4.00, 500000.00, 'Filter untuk unit AGM 099', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 1, 2, 1.00, 85000000.00, 'Hydraulic pump AGM 099', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 2, 3, 2.00, 2000000.00, 'Seal kit AGM 100', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(4, 3, 1, 3.00, 525000.00, 'Filter untuk unit AGM 101', '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `customer`
--

CREATE TABLE `customer` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `jenis_pelanggan` enum('Individu','Perusahaan') DEFAULT 'Perusahaan',
  `nama_perusahaan` varchar(200) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `status_pelanggan` enum('Baru','Reguler','Blacklist') DEFAULT 'Baru',
  `kebijakan_dp` decimal(5,2) DEFAULT 30.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `customer`
--

INSERT INTO `customer` (`id`, `kode`, `nama`, `jenis_pelanggan`, `nama_perusahaan`, `alamat`, `telepon`, `email`, `contact_person`, `keterangan`, `status_pelanggan`, `kebijakan_dp`, `created_at`, `updated_at`) VALUES
(1, 'CUS001', 'PT Maju Tambang', 'Perusahaan', NULL, 'Kalimantan Timur', '0541-5551001', 'procurement@majutambang.example', 'Rudi', 'Customer perusahaan tambang', 'Baru', 30.00, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'CUS002', 'PT Karya Mineral', 'Perusahaan', '', 'Kalimantan Selatan', '0511-5552002', 'purchasing@karyamineral.example', 'Sinta', 'Customer alat berat', 'Blacklist', 30.00, '2026-09-01 14:14:04', '2026-09-02 18:13:51'),
(3, 'CUS003', 'CV Mitra Konstruksi', 'Perusahaan', NULL, 'Jawa Tengah', '024-5553003', 'admin@mitrakonstruksi.example', 'Dedi', 'Customer bidang konstruksi', 'Baru', 30.00, '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `karyawan`
--

CREATE TABLE `karyawan` (
  `id` int(10) UNSIGNED NOT NULL,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `departemen` varchar(100) DEFAULT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'AKTIF',
  `tanggal_masuk` date DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `karyawan`
--

INSERT INTO `karyawan` (`id`, `nik`, `nama`, `jabatan`, `departemen`, `telepon`, `email`, `alamat`, `status`, `tanggal_masuk`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'KRY001', 'Budi Santoso', 'Admin Gudang', 'Gudang', '081200000001', 'budi@asrindo.example', 'Magelang', 'AKTIF', '2023-01-10', 'Admin gudang dan inventory', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'KRY002', 'Andi Pratama', 'Mekanik', 'Workshop', '081200000002', 'andi@asrindo.example', 'Magelang', 'AKTIF', '2022-05-15', 'Mekanik alat berat', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'KRY003', 'Siti Rahma', 'Sales', 'Penjualan', '081200000003', 'siti@asrindo.example', 'Yogyakarta', 'AKTIF', '2024-02-01', 'Sales alat berat', '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_keuangan`
--

CREATE TABLE `kategori_keuangan` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `tipe` enum('PENDAPATAN','PENGELUARAN') NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kategori_keuangan`
--

INSERT INTO `kategori_keuangan` (`id`, `kode`, `nama`, `tipe`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'PENJUALAN', 'Penjualan Alat Berat', 'PENDAPATAN', 'Pendapatan dari penjualan unit alat berat', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'SEWA', 'Pendapatan Sewa', 'PENDAPATAN', 'Pendapatan dari jasa sewa alat berat', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'GUDANG', 'Sewa Gudang', 'PENGELUARAN', 'Biaya sewa gudang', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(4, 'GAJI', 'Gaji Karyawan', 'PENGELUARAN', 'Biaya gaji dan upah karyawan', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(5, 'LISTRIK', 'Listrik dan Utilitas', 'PENGELUARAN', 'Biaya listrik dan utilitas perusahaan', '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembelian_sparepart`
--

CREATE TABLE `pembelian_sparepart` (
  `id` int(10) UNSIGNED NOT NULL,
  `nomor_pembelian` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'DRAFT',
  `keterangan` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pembelian_sparepart`
--

INSERT INTO `pembelian_sparepart` (`id`, `nomor_pembelian`, `tanggal`, `supplier_id`, `total`, `status`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PO-20260901-0001', '2026-09-01', 1, 87000000.00, 'SELESAI', 'Pembelian hydraulic pump dan oil filter', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'PO-20260902-0002', '2026-09-02', 2, 4000000.00, 'SELESAI', 'Pembelian seal kit', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'PO-20260903-0003', '2026-09-03', 3, 1575000.00, 'DRAFT', 'Pembelian filter dan sparepart workshop', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembelian_sparepart_detail`
--

CREATE TABLE `pembelian_sparepart_detail` (
  `id` int(10) UNSIGNED NOT NULL,
  `pembelian_id` int(10) UNSIGNED NOT NULL,
  `sparepart_id` int(10) UNSIGNED NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `harga` decimal(18,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18,2) GENERATED ALWAYS AS (`qty` * `harga` - `diskon`) STORED,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pembelian_sparepart_detail`
--

INSERT INTO `pembelian_sparepart_detail` (`id`, `pembelian_id`, `sparepart_id`, `qty`, `harga`, `diskon`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 1.00, 85000000.00, 0.00, 'Hydraulic pump', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 1, 1, 4.00, 500000.00, 0.00, 'Oil filter', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 2, 3, 2.00, 2000000.00, 0.00, 'Seal kit', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(4, 3, 1, 3.00, 525000.00, 0.00, 'Oil filter', '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penjualan`
--

CREATE TABLE `penjualan` (
  `id` int(10) UNSIGNED NOT NULL,
  `nomor_penjualan` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'DRAFT',
  `keterangan` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `penjualan`
--

INSERT INTO `penjualan` (`id`, `nomor_penjualan`, `tanggal`, `customer_id`, `total`, `status`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PJ-20260910-0001', '2026-09-10', 1, 750000000.00, 'CONFIRMED', 'Penjualan unit AGM 100', 3, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'PJ-20260915-0002', '2026-09-15', 2, 625000000.00, 'DRAFT', 'Penjualan unit AGM 101', 3, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'PJ-20260920-0003', '2026-09-20', 3, 450000000.00, 'PAID', 'Penjualan unit AGM 099', 3, '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penjualan_detail`
--

CREATE TABLE `penjualan_detail` (
  `id` int(10) UNSIGNED NOT NULL,
  `penjualan_id` int(10) UNSIGNED NOT NULL,
  `alat_berat_id` int(10) UNSIGNED NOT NULL,
  `harga_jual` decimal(18,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(18,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18,2) GENERATED ALWAYS AS (`harga_jual` - `diskon`) STORED,
  `hpp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `laba` decimal(18,2) GENERATED ALWAYS AS (`harga_jual` - `diskon` - `hpp`) STORED,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `penjualan_detail`
--

INSERT INTO `penjualan_detail` (`id`, `penjualan_id`, `alat_berat_id`, `harga_jual`, `diskon`, `hpp`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 750000000.00, 0.00, 250000000.00, 'Unit CAT 320', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 2, 3, 625000000.00, 25000000.00, 300000000.00, 'Unit Komatsu PC200', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 3, 1, 450000000.00, 0.00, 292261640.00, 'Unit CAT 306', '2026-09-01 14:14:04', '2026-09-01 14:14:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `sparepart`
--

CREATE TABLE `sparepart` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `part_number` varchar(100) DEFAULT NULL,
  `merk` varchar(100) DEFAULT NULL,
  `stok` int(11) DEFAULT 0,
  `stok_minimum` int(11) DEFAULT 0,
  `harga_modal` double DEFAULT 0,
  `harga_jual` double DEFAULT 0,
  `satuan` varchar(30) NOT NULL DEFAULT 'PCS',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `sparepart`
--

INSERT INTO `sparepart` (`id`, `kode`, `nama`, `kategori`, `part_number`, `merk`, `stok`, `stok_minimum`, `harga_modal`, `harga_jual`, `satuan`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'SP001', 'Oil Filter', NULL, 'CAT-1R0739', 'CAT', 0, 0, 0, 0, 'PCS', 'Filter oli engine', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'SP002', 'Hydraulic Pump', NULL, 'HP-306-001', 'CAT', 0, 0, 0, 0, 'PCS', 'Pompa hydraulic excavator', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'SP003', 'Seal Kit Hydraulic', 'Filter', 'SK-306-001', 'CATa', 0, 0, 0, 0, 'Pcs', 'Seal kit hydraulic system', '2026-09-01 14:14:04', '2026-09-02 19:52:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `supplier`
--

CREATE TABLE `supplier` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `alamat` text DEFAULT NULL,
  `status` enum('Aktif','Tidak Aktif') DEFAULT 'Aktif',
  `telepon` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `negara` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `supplier`
--

INSERT INTO `supplier` (`id`, `kode`, `nama`, `alamat`, `status`, `telepon`, `email`, `contact_person`, `negara`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'SUP001', 'PT Caterpillar Indonesia', 'Jakarta', 'Aktif', '021-5551001', 'sales@cat-indonesia.example', 'Andi', NULL, 'Supplier sparepart alat berat', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'SUP002', 'PT United Equipment', 'Surabaya', 'Aktif', '031-5552002', 'sales@united-equipment.example', 'Budi', NULL, 'Supplier komponen dan sparepart', '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'SUP003', 'CV Sumber Teknik', 'Semarang.', 'Tidak Aktif', '024-5553003', 'info@sumber-teknik.example', 'Citra', 'indonesia', 'Supplier sparepart umum', '2026-09-01 14:14:04', '2026-09-02 18:39:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi_keuangan`
--

CREATE TABLE `transaksi_keuangan` (
  `id` int(10) UNSIGNED NOT NULL,
  `nomor_transaksi` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `kategori_id` int(10) UNSIGNED NOT NULL,
  `nominal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `metode_pembayaran` varchar(50) DEFAULT NULL,
  `referensi` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `transaksi_keuangan`
--

INSERT INTO `transaksi_keuangan` (`id`, `nomor_transaksi`, `tanggal`, `kategori_id`, `nominal`, `metode_pembayaran`, `referensi`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'TRX-20260901-0001', '2026-09-01', 3, 5000000.00, 'TRANSFER', 'INV-GDG-202609', 'Pembayaran sewa gudang September 2026', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(2, 'TRX-20260902-0002', '2026-09-02', 4, 50000000.00, 'TRANSFER', 'PAYROLL-202609', 'Pembayaran gaji karyawan September 2026', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04'),
(3, 'TRX-20260903-0003', '2026-09-03', 5, 3500000.00, 'TRANSFER', 'PLN-202609', 'Pembayaran listrik workshop', NULL, '2026-09-01 14:14:04', '2026-09-01 14:14:04');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_admin_karyawan` (`karyawan_id`),
  ADD KEY `idx_admin_role` (`role`),
  ADD KEY `idx_admin_status` (`status`);

--
-- Indeks untuk tabel `alat_berat`
--
ALTER TABLE `alat_berat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`),
  ADD UNIQUE KEY `nomor_rangka` (`nomor_rangka`);

--
-- Indeks untuk tabel `alat_berat_jasa`
--
ALTER TABLE `alat_berat_jasa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_abj_alat_berat` (`alat_berat_id`),
  ADD KEY `idx_abj_jenis_jasa` (`jenis_jasa`);

--
-- Indeks untuk tabel `alat_berat_perawatan`
--
ALTER TABLE `alat_berat_perawatan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_abp_alat_berat` (`alat_berat_id`),
  ADD KEY `idx_abp_tanggal` (`tanggal`);

--
-- Indeks untuk tabel `alat_berat_sparepart`
--
ALTER TABLE `alat_berat_sparepart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_abs_alat_berat` (`alat_berat_id`),
  ADD KEY `idx_abs_sparepart` (`sparepart_id`);

--
-- Indeks untuk tabel `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indeks untuk tabel `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nik` (`nik`);

--
-- Indeks untuk tabel `kategori_keuangan`
--
ALTER TABLE `kategori_keuangan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indeks untuk tabel `pembelian_sparepart`
--
ALTER TABLE `pembelian_sparepart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_pembelian` (`nomor_pembelian`),
  ADD KEY `idx_pembelian_tanggal` (`tanggal`),
  ADD KEY `idx_pembelian_supplier` (`supplier_id`),
  ADD KEY `idx_pembelian_created_by` (`created_by`);

--
-- Indeks untuk tabel `pembelian_sparepart_detail`
--
ALTER TABLE `pembelian_sparepart_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_psd_pembelian` (`pembelian_id`),
  ADD KEY `idx_psd_sparepart` (`sparepart_id`);

--
-- Indeks untuk tabel `penjualan`
--
ALTER TABLE `penjualan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_penjualan` (`nomor_penjualan`),
  ADD KEY `idx_penjualan_tanggal` (`tanggal`),
  ADD KEY `idx_penjualan_customer` (`customer_id`),
  ADD KEY `idx_penjualan_created_by` (`created_by`);

--
-- Indeks untuk tabel `penjualan_detail`
--
ALTER TABLE `penjualan_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pjd_penjualan` (`penjualan_id`),
  ADD KEY `idx_pjd_alat_berat` (`alat_berat_id`);

--
-- Indeks untuk tabel `sparepart`
--
ALTER TABLE `sparepart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indeks untuk tabel `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indeks untuk tabel `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_transaksi` (`nomor_transaksi`),
  ADD KEY `idx_tk_tanggal` (`tanggal`),
  ADD KEY `idx_tk_kategori` (`kategori_id`),
  ADD KEY `idx_tk_created_by` (`created_by`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `alat_berat`
--
ALTER TABLE `alat_berat`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `alat_berat_jasa`
--
ALTER TABLE `alat_berat_jasa`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `alat_berat_perawatan`
--
ALTER TABLE `alat_berat_perawatan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `alat_berat_sparepart`
--
ALTER TABLE `alat_berat_sparepart`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `customer`
--
ALTER TABLE `customer`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `kategori_keuangan`
--
ALTER TABLE `kategori_keuangan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `pembelian_sparepart`
--
ALTER TABLE `pembelian_sparepart`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pembelian_sparepart_detail`
--
ALTER TABLE `pembelian_sparepart_detail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `penjualan`
--
ALTER TABLE `penjualan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `penjualan_detail`
--
ALTER TABLE `penjualan_detail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `sparepart`
--
ALTER TABLE `sparepart`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `supplier`
--
ALTER TABLE `supplier`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_karyawan` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `alat_berat_jasa`
--
ALTER TABLE `alat_berat_jasa`
  ADD CONSTRAINT `fk_abj_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `alat_berat_perawatan`
--
ALTER TABLE `alat_berat_perawatan`
  ADD CONSTRAINT `fk_abp_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `alat_berat_sparepart`
--
ALTER TABLE `alat_berat_sparepart`
  ADD CONSTRAINT `fk_abs_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_abs_sparepart` FOREIGN KEY (`sparepart_id`) REFERENCES `sparepart` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembelian_sparepart`
--
ALTER TABLE `pembelian_sparepart`
  ADD CONSTRAINT `fk_pembelian_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembelian_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembelian_sparepart_detail`
--
ALTER TABLE `pembelian_sparepart_detail`
  ADD CONSTRAINT `fk_psd_pembelian` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian_sparepart` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_psd_sparepart` FOREIGN KEY (`sparepart_id`) REFERENCES `sparepart` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penjualan`
--
ALTER TABLE `penjualan`
  ADD CONSTRAINT `fk_penjualan_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penjualan_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penjualan_detail`
--
ALTER TABLE `penjualan_detail`
  ADD CONSTRAINT `fk_pjd_alat_berat` FOREIGN KEY (`alat_berat_id`) REFERENCES `alat_berat` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pjd_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  ADD CONSTRAINT `fk_transaksi_keuangan_admin` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transaksi_keuangan_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_keuangan` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
