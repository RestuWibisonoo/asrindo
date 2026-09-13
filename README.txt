ASRINDO ADMIN LOGIN STARTER

Struktur:
admin/index.php
admin/login.php
config/koneksi.php
assets/css/admin.css
assets/images/logo.png

Letakkan logo ASRINDO Anda sebagai:
assets/images/logo.png

Edit config/koneksi.php sesuai MySQL Anda:
DB_HOST, DB_NAME, DB_USER, DB_PASS

Login membaca tabel admin dan menggunakan password_verify().
Setelah berhasil login diarahkan ke admin/dashboard.php (akan dibuat pada tahap berikutnya).
