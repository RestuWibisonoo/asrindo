# Asrindo Global Mandiri - ERP Warehouse & Rental
**Deskripsi:** Aplikasi Web Admin untuk manajemen operasional jual-beli dan penyewaan (rental) alat berat (excavator) serta suku cadangnya. Dokumen ini merupakan penyempurnaan dari alur awal untuk menyinkronkan kelengkapan, tata bahasa, dan logika bisnis.

## 1. Struktur Navigasi (Menu Sidebar)
- **Dashboard**
- **Transaksi**
  - Rental
  - Penjualan
  - Pembelian
- **Produk & Inventori**
  - Alat Berat
  - Suku Cadang
  - Perawatan (Maintenance)
- **Rekanan**
  - Pelanggan (Customer)
  - Supplier
- **Kas & Keuangan**
- **Laporan**
- **Pengaturan**

## 2. Modul Rekanan (CRM & SRM)
### 2.1 Pelanggan (Customer)
*Halaman Utama (List)*
- **Statistik Cepat:** Total pelanggan, Pelanggan Baru, Pelanggan Reguler, Blacklist.
- **Tabel Data:** Nama Lengkap (PIC/Individu), Nama Perusahaan, Jenis Pelanggan, Contact Person, Telepon, Status, Aksi (Detail).
- **Form Tambah (Modal):** Jenis Pelanggan (Perusahaan/Individu), Nama Lengkap, Nama Perusahaan (opsional jika individu), Contact Person, Telepon/HP, Email, Alamat, Keterangan. *(Secara default sistem meng-set status sebagai "Baru" dan DP Required 30%).*

*Halaman Detail*
Halaman ini menampilkan profil komprehensif dari pelanggan beserta tombol **Edit** dan **Hapus** untuk manajemen data.
- **Informasi Pelanggan:** Nama, Status, Jenis, Nama Perusahaan, Contact Person, Terdaftar Sejak, Keterangan.
- **Kontak:** Telepon, Email, Alamat Lengkap.
- **Kebijakan DP & Transaksi:** Status DP (Fleksibel/Strict), Persentase DP Wajib, Total Transaksi Rental, Transaksi Terakhir.
- **Form Edit (Modal):** Mengubah seluruh data, termasuk Status Pelanggan (Baru/Reguler/Blacklist) dan Kebijakan DP.

### 2.2 Supplier
*Halaman Utama (List)*
- **Statistik Cepat:** Total Supplier.
- **Tabel Data:** Nama Perusahaan, Nama Kontak, Negara, Telepon, Status (Aktif/Tidak Aktif), Aksi (Edit, Hapus).
- **Form Tambah & Edit (Modal):** Kode Supplier, Nama Perusahaan, Nama Kontak, Negara, Telepon, Email, Alamat Lengkap, Status (Aktif/Tidak Aktif), Keterangan Tambahan. *(Tidak ada halaman detail khusus untuk supplier, semuanya dikelola dari tabel).*

## 3. Modul Produk & Inventori
### 3.1 Alat Berat (Heavy Equipment)
*Halaman Utama (List)*
- **Statistik Cepat:** Total Unit, Total Nilai Inventori, Rata-rata Jam Operasional, Unit Tersedia.
- **Tabel Data:** Kode Alat, Tipe, Tahun, Lokasi, Status (Tersedia, Disewakan, Terjual, Perbaikan), Aksi (Detail).
- **Form Tambah (Modal):**
  - *Data Umum:* Kode Alat, Tipe Alat, No. Rangka, No. Mesin, Tahun Pembuatan, Kondisi (Baru, Bekas, Refurbished), Status, Lokasi.
  - *Data Harga Pembelian:* Kurs Beli (IDR/USD), Harga Beli Asal (USD/IDR), Harga Jual Estimasi.
  - *Harga Sewa (Rental):* Harga Sewa per Jam (All-in), Harga Sewa per Jam (Kosongan).
  - *Data Operasional:* Total Jam Operasional (Hour Meter), Jam Operasional Terakhir, Deskripsi.

*Halaman Detail Unit*
- **Data Umum:** Menampilkan rincian spesifikasi unit sesuai form di atas.
- **Informasi Harga & Sewa:** Menampilkan rincian harga beli, harga jual, tarif sewa all-in & kosongan.
- **Riwayat Transaksi:** Catatan riwayat pembelian (nomor PO) dan penjualan unit.
- **Riwayat Perawatan (Maintenance Log):** Daftar perbaikan/perawatan yang pernah dilakukan beserta total biayanya.
- **Galeri Foto:** Fitur upload foto kondisi alat berat (Dokumentasi inspeksi).

### 3.2 Suku Cadang (Spare Parts)
*Halaman Utama (List)*
- **Statistik Cepat:** Total Jenis Item, Total Nilai Inventori (Stok * Harga Jual), Item Hampir Habis.
- **Tabel Data:** Kode Suku Cadang, Nama, Kategori, Stok Tersedia, Harga Jual, Lokasi Rak, Aksi (Detail).
- **Form Tambah (Modal):** Kode, Nama, Kategori, Satuan (Pcs, Set, dll), Stok Awal, Harga Beli (HPP), Harga Jual, Lokasi Penyimpanan, Keterangan.

*Halaman Detail Suku Cadang*
- **Informasi Utama:** Rincian data SKU suku cadang.
- **Nilai Inventori:** Nilai aset saat ini berdasarkan stok aktual.
- **Kartu Stok (Riwayat Transaksi):** Catatan log otomatis in/out barang (berkurang karena Penjualan atau Pemakaian Perawatan, dan bertambah dari Pembelian).

### 3.3 Perawatan (Maintenance)
*Halaman Utama (List)*
- **Statistik Cepat:** Total Perawatan Bulan Ini, Total Biaya Bulan Ini, Rata-rata Biaya Perawatan.
- **Tabel Data:** Tanggal, Kode/Tipe Alat Berat, Jenis Perawatan, Petugas/Mekanik, Total Biaya, Status, Aksi (Detail).
- **Form Tambah (Modal):** Pilih Alat Berat, Tanggal Perawatan, Jenis Perawatan (Preventif/Berkala, Korektif/Perbaikan, Darurat, Overhaul), Nama Mekanik, Keterangan Awal.

*Halaman Detail Perawatan*
- **Tombol Aksi Utama:** Edit informasi awal, Hapus tiket.
- **Informasi Perawatan:** Tanggal, Alat Berat, Mekanik, Jenis. 
- **Ringkasan Biaya:** Total Biaya Jasa/Finishing, Total Biaya Suku Cadang, Grand Total.
- **Item Jasa / Finishing:** Tabel rincian pekerjaan. Terdapat modal tambah (Nama Jasa, Harga, Qty, Total Biaya).
- **Penggunaan Suku Cadang:** Tabel rincian suku cadang yang terpakai. Modal tambah (Pilih Suku Cadang, Qty, Harga HPP). *Logika Bisnis: Menyimpan pemakaian di sini akan memotong stok secara otomatis.*

## 4. Modul Transaksi Utama
### 4.1 Penyewaan (Rental)
*Halaman Utama (List)*
- **Statistik Cepat:** Rental Aktif, Selesai Bulan Ini, Pendapatan Rental Bulan Ini.
- **Tabel Data:** Nomor Rental, Tanggal Mulai, Pelanggan, Tipe Alat Berat, Status Transaksi, Total Nilai, Aksi (Detail).
- **Form Tambah (Modal):** Pilih Pelanggan, Pilih Alat Berat, Paket (All-in/Kosongan), Tanggal Mulai Estimasi.

*Halaman Detail Rental*
Halaman ini adalah pusat komando / Control Panel untuk satu proyek penyewaan (SPK).
- **Tombol Aksi Utama:** Edit Kontrak, Update Status (Draft, Proses/Berjalan, Selesai, Dibatalkan), Generate Invoice.
- **Informasi Kontrak:** Nomor, Status, Tipe Paket (All-in / Kosongan), Tanggal Mulai, Tanggal Selesai Aktual, Estimasi Hari, Total Jam Aktual.
- **Data Entitas:** Detail Alat Berat (Tipe, No Rangka) & Detail Pelanggan.
- **Daftar Tarif (Pricing):** Harga Alat/Jam, Biaya Operator/Jam, BBM/Jam. (Fleksibel, bisa diedit via modal Edit Kontrak).
- **Biaya Tambahan:** Biaya Crew, Mobilitas (Mobilisasi/Demobilisasi), Broker/Komisi, Lain-lain.
- **Ringkasan Keuangan:** Subtotal Sewa (Jam Aktual * Tarif), Biaya Tambahan, PPN (11%), Grand Total.
- **Status Pembayaran:** Total Terbayar (DP + Pelunasan), Sisa Tagihan (Piutang).
- **Detail Harian (Time Sheet):** Pencatatan absen harian alat. Tabel: Tanggal, Jam Mulai, Jam Selesai, Total Jam Harian, Harga Harian, Status (Aktif/Libur/Rusak), Keterangan. Terdapat Modal (Tambah/Edit).
- **Riwayat Pembayaran:** Pencatatan termin / angsuran uang masuk. Modal Tambah: Jenis (DP/Pelunasan), Tanggal, Jumlah, Metode (Transfer/Tunai), Bank Tujuan, Keterangan.
- **Riwayat Invoice:** Daftar tagihan formal yang sudah digenerate.

### 4.2 Penjualan (Sales)
*Halaman Utama (List)*
- **Statistik Cepat:** Total Penjualan Bulan Ini, Nilai Penjualan.
- **Tabel Data:** Nomor Invoice/Nota, Tanggal, Pelanggan, Status (Draft, Proses Kirim, Selesai, Batal), Grand Total, Aksi (Detail).
- **Form Tambah (Modal):** Pilih Pelanggan, Tanggal Penjualan, Jatuh Tempo, PPN (%), Alamat Pengiriman, Keterangan Awal.

*Halaman Detail Penjualan*
- **Tombol Aksi Utama:** Edit Info Penjualan, Update Status Transaksi, Generate Invoice.
- **Informasi Utama:** Nomor, Status Transaksi, Dual Pricing (jika relevan), Tanggal, Jatuh Tempo, Tanggal Pengiriman, Tanggal Diterima, Alamat.
- **Data Pelanggan:** Nama, Kontak, Alamat Kirim.
- **Ringkasan Total & Pembayaran:** Subtotal Item, Total Biaya Tambahan, PPN, Grand Total Real. Status Pembayaran (Terbayar, Sisa Piutang). Ringkasan Total Pengeluaran Ekstra.
- **Daftar Item Terjual:** Tabel rincian produk (bisa Alat Berat atau Suku Cadang). Modal Tambah: Pilih Jenis (Alat/Sparepart), Nama Barang, Qty, Satuan, Harga Satuan, Diskon Nominal/%.
- **Biaya Tambahan Penjualan:** (Dibebankan ke pelanggan). Jenis Biaya (Towing, Asuransi, Handling), Tipe (Fixed/Persentase), Nominal.
- **Pengeluaran Internal:** Biaya operasional yang dibayar perusahaan untuk melancarkan penjualan (misal: Komisi Makelar, Uang Jalan Supir). Ini penting agar sistem bisa menghitung margin Laba Bersih yang akurat.
- **Riwayat Pembayaran (Uang Masuk):** Pencatatan penerimaan uang dari pelanggan. 

### 4.3 Pembelian (Purchase Order / PO)
*Halaman Utama (List)*
- **Statistik Cepat:** PO Aktif (Sedang Berjalan), Total PO Bulan Ini, Total Nilai Pembelian Bulan Ini.
- **Tabel Data:** Nomor PO, Tanggal, Supplier, Status Pembelian (Draft, Proses, Selesai, Dibatalkan), Total Nilai, Aksi (Detail).
- **Form Tambah (Modal):** Pilih Supplier, Tanggal PO, Estimasi Kedatangan, Keterangan Awal.

*Halaman Detail Pembelian (PO)*
- **Tombol Aksi Utama:** Edit PO, Update Status, Hapus Transaksi.
- **Informasi PO:** Nomor, Status Transaksi, Tanggal Beli, Estimasi Datang, Kedatangan Aktual, Detail Supplier. 
- **Ringkasan Tagihan (Multi-Currency):** 
  - Karena alat berat biasa diimpor, sistem mendukung rekap USD dan IDR.
  - Menampilkan: Subtotal (IDR/USD), Biaya Bea Cukai (IDR/USD), Biaya Pengiriman Luar (IDR/USD), Grand Total. 
  - Terdapat tombol *Setup Kurs/Biaya* untuk memasukkan Kurs Berlaku, Bea Cukai, Ekspedisi.
- **Ringkasan Pembayaran:** Total Pelunasan, Sisa Hutang ke Supplier.
- **Daftar Item Dibeli:** Tabel barang pesanan (Alat Berat / Suku Cadang). 
  - Modal Tambah: Jenis Item, Nama, Qty, Harga Satuan Valas (USD), Harga Satuan Lokal (IDR), Subtotal.
- **Manajemen Barang Kurang / Cacat (Defect Log):** 
  - Tabel untuk memantau klaim / retur. 
  - Modal Tambah/Edit: Nama Barang, Qty Kurang, Status Komplain (Open/Baru Lapor, In Progress/Sedang Dikirim Pengganti, Closed/Selesai), Tanggal Lapor, Tanggal Pengiriman Pengganti.
- **Riwayat Pembayaran (Hutang ke Supplier):** Pencatatan pengeluaran uang bayar supplier. Modal Pembayaran: Tanggal, Jenis (DP/Pelunasan), Kurs Saat Bayar, Jumlah Asing (USD), Jumlah Rupiah (IDR), Rekening Tujuan.

## 5. Modul Dashboard & Analitik (Overview)
Halaman awal saat user login akan memberikan panel eksekutif:
- **Widget Keuangan:** Total Laba/Rugi (Bulan Ini), Total Piutang Belum Dibayar, Total Hutang Supplier.
- **Aktivitas Operasional:** Jumlah Alat Berat Tersewa (vs Tersedia), Jumlah PO Aktif, Tiket Perawatan Berjalan.
- **Grafik Tren:** Tren Penyewaan dan Penjualan (Bulanan).
- **Shortcut/Peringatan:** Notifikasi jika ada tagihan/invoice yang jatuh tempo.

---
### Catatan Optimalisasi (Sinkronisasi Sistem):
1. **Multi-Currency yang Konsisten:** Modul Pembelian dibuat lebih robust menangani IDR dan USD. Input bisa dalam valas, dan sistem akan mengkalikan dengan field *Kurs Pembayaran*.
2. **Keterkaitan (Relasi) Stok Terpusat:** Penjualan suku cadang (di modul Penjualan) atau pemakaian suku cadang (di modul Perawatan) akan **secara otomatis mengurangi** kuantitas stok di modul Gudang/Suku Cadang. Sebaliknya, saat PO Pembelian diselesaikan, stok akan otomatis bertambah.
3. **Tracking Laba Real-Time:** Penambahan fitur "Pengeluaran Ekstra" di Penjualan dan Rental memungkinkan perhitungan margin bersih (Laba Bersih = Pendapatan Kotor - HPP Barang - Biaya Tambahan Operasional).
4. **Sentralisasi Kontrol:** Konsep halaman "Detail" yang berfungsi layaknya Dashboard Mini per-Transaksi (Rental/Penjualan/PO) sangat mempermudah admin untuk mengontrol tagihan, pembayaran, jadwal, dan dokumen dalam 1 layar tanpa pindah-pindah menu.
