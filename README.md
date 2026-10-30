# Ads Manager

Aplikasi Laravel untuk laporan campaign, impor XLSX, sinkronisasi Meta Ads, dan CRM lead/closing. Peran pengguna: admin, marketing, viewer.

## Menjalankan lokal

1. Gunakan PHP 8.3+ dengan PDO driver database, SimpleXML, dan zip. Periksa PHP CLI dengan `php --ini` dan `php -m`; PHP web server dapat memakai konfigurasi berbeda.
2. Jalankan `composer install` dan `npm ci --ignore-scripts`.
3. Salin `.env.example` ke `.env` jika belum tersedia. Atur database dan `APP_URL`, lalu jalankan `php artisan key:generate` hanya untuk instalasi baru. Jangan mengganti APP_KEY instalasi berjalan karena token Meta terenkripsi menggunakannya.
4. Jalankan `php artisan migrate`, kemudian `php artisan app:create-admin`. Command meminta password secara tersembunyi, minimum 12 karakter dengan huruf dan angka.
5. Jalankan `npm run build` dan `php artisan serve`, atau gunakan Laragon dengan document root direktori `public`.

`php artisan db:seed` hanya mengisi contoh pada lingkungan local/testing. Seeder tidak mengubah password pengguna yang sudah ada. Akun contoh baru memakai password acak; buat akun login melalui command admin. Akun lama yang pernah memakai password bawaan perlu menggantinya melalui Pengaturan.

## Dashboard dan sumber konversi

KPI, grafik harian, funnel, tabel overview, dan ekspor CSV menggunakan filter periode/platform yang sama. Periode awal adalah bulan berjalan. Workspace kosong menampilkan angka nol.

Pilihan **Laporan campaign** memakai angka campaign manual/Excel/Meta. Pilihan **Catatan CRM** memakai jumlah lead, jumlah closing, dan nilai closing yang terhubung ke campaign. Dua sumber ini tidak dijumlahkan agar transaksi yang sama tidak dihitung dua kali. Budget, impression, klik, dan chat tetap berasal dari campaign.

Periode mengikuti tanggal campaign (analisis cohort campaign), bukan tanggal transaksi closing. Lead tanpa campaign tetap tampil di CRM tetapi tidak termasuk KPI campaign. Semua catatan closing dihitung beserta nilainya; status closing adalah label operasional, bukan filter pembayaran. Reach merupakan akumulasi per campaign/hari, bukan audience unik seluruh periode.

Klik tanda centang pada baris lead untuk mencatat/memperbarui closing. Pemindahan lead ke campaign lain juga memindahkan atribusi closing. Menghapus lead menghapus closing terkait.

## Pengaturan profil

Semua peran dapat mengubah nama, email, foto profil, dan kata sandi melalui Pengaturan. Klik avatar di sidebar untuk membuka pengaturan. Perubahan identitas langsung tampil di sidebar; peran hanya dapat diubah admin melalui pengelolaan pengguna.

Foto mendukung JPG, PNG, dan WebP, maksimal 2 MB dan dimensi 6000 x 6000 piksel. Foto disimpan pada disk local privat dan dilayani melalui route terautentikasi `/profile/avatar`; tidak memerlukan `storage:link`. Jalankan `php artisan migrate` setelah memperbarui project untuk menambahkan kolom avatar. Foto lama dihapus setelah penggantian berhasil.

## Impor XLSX

Maksimum upload 10 MB. Sheet wajib bernama `Campaign Meta Ads`, dengan kolom `Tanggal`, `Campaign`, dan `Platform`. Kolom metrik mengikuti workbook contoh. File identik ditolak setelah pernah diimpor; baris dengan kunci tanggal/nama/platform yang sama diperbarui.

Kesalahan file, XML, dan ekstensi zip dilaporkan sebagai respons 422. Catatan hasil impor menyimpan jumlah baris berhasil, dilewati, dan error per baris.

## Meta Ads

Admin memasukkan Ad Account ID dan access token melalui Pengaturan. Token disimpan terenkripsi dan tidak dikirim kembali dalam JSON. Sinkronisasi membaca seluruh halaman menggunakan cursor, lalu menyimpan hasil dalam transaksi. Kegagalan pada halaman berikutnya tidak menyimpan hasil parsial. `link_clicks` menggunakan action link_click, bukan seluruh klik.

Sinkronisasi manual: `php artisan meta-ads:sync --since=2026-10-01 --until=2026-10-30`.
Scheduler menjalankan sinkronisasi pukul 02:00 sesuai APP_TIMEZONE (default Asia/Jakarta). Di server, jalankan `php artisan schedule:run` tiap menit melalui scheduler OS.

## Pengujian

```sh
php artisan test
node --test tests/frontend/dashboard.test.cjs
php vendor/bin/pint --test
npm run build
composer audit --locked
npm audit
```

Test backend memakai SQLite in-memory sesuai phpunit.xml. Test Meta menggunakan HTTP fake; akses API Meta sebenarnya perlu token dan izin akun yang valid. Test frontend memverifikasi inisialisasi halaman login, workspace kosong, render KPI/grafik/funnel, dan escaping data.

## Deployment dan batas fitur

Gunakan APP_ENV=production, APP_DEBUG=false, APP_URL HTTPS, SESSION_SECURE_COOKIE=true, database dan kredensial server yang sesuai. Lindungi APP_KEY dan backup database. Jangan menjalankan seeder contoh pada produksi. Folder public adalah satu-satunya document root.

OIDC/SSO belum tersedia: file kerangka terkait belum terhubung ke route. Aktivasi memerlukan provider, issuer, client ID, callback URL, dan kebijakan pemetaan akun; jangan menganggap login SSO atau nonaktif akun sudah diterapkan. Test contoh OIDC telah diganti dengan test autentikasi lokal yang benar.

Repository Git belum tersedia pada folder ini. Inisialisasi repository dan remote perlu dilakukan sesuai tujuan penyimpanan project.
