# Ads Manager

Aplikasi Laravel untuk laporan campaign, impor XLSX, sinkronisasi Meta Ads, dan CRM lead/closing. Akses berdasarkan role dan cabang.

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

## Role, cabang, dan akses

Role yang tersedia: superadmin, direktur utama, direktur, komisaris, kacab, keuangan, akademik, marketing, operasional, staff, pengajar.

| Role | Laporan | Kelola campaign/lead/impor | Hubungkan/sinkronkan/putuskan Meta | Kelola pengguna |
| --- | --- | --- | --- | --- |
| superadmin | Semua cabang | Semua cabang | Tidak | Ya |
| direktur utama, direktur, komisaris | Semua cabang, hanya baca | Tidak | Tidak | Tidak |
| kacab, marketing | Cabang sendiri | Cabang sendiri | Cabang sendiri | Tidak |
| keuangan, akademik, operasional, staff, pengajar | Cabang sendiri, hanya baca | Tidak | Tidak | Tidak |

Cabang tersedia: Bandung, JakSel, dan JakPus. Role pusat tidak memerlukan cabang. Role cabang wajib ditetapkan ke satu cabang; pengguna lama tanpa cabang tidak dapat membaca data operasional sampai superadmin menetapkannya. Superadmin mengatur pengguna melalui Pengaturan > Anggota tim. Akun baru secara default menggunakan role staff; aplikasi tidak membuat kredensial bawaan.

Pemilih cabang tersedia untuk role pusat. Campaign, lead, impor, dan CSV mengikuti cakupan cabang. Server membatasi akses melalui ID, parameter branch, maupun header X-Branch. Pemindahan pengguna cabang melepas penugasan lead sebelumnya.

Kacab atau marketing menghubungkan satu akun iklan pada Pengaturan > Meta Ads cabangnya. Superadmin dan pimpinan tidak melihat tombol pengelolaan Meta dan ditolak oleh endpoint server jika mencoba langsung. Koneksi lama tanpa cabang dapat dihubungkan ulang oleh kacab/marketing dengan Ad Account ID dan token yang mereka masukkan sendiri. Tidak ada endpoint penetapan koneksi lintas cabang.

Data lama yang belum memiliki cabang hanya terlihat oleh role pusat. Superadmin dapat menetapkan cabang melalui Edit campaign; lead terkait ikut berpindah. Lead tanpa campaign dapat ditetapkan melalui Edit lead. Data tidak dipindahkan otomatis.

Scheduler server menjalankan koneksi cabang yang sudah dikonfigurasi, dan tetap mencoba cabang lain jika satu gagal. Ini pekerjaan otomatis server, bukan akses sinkronisasi melalui akun superadmin. Untuk cabang tertentu: `php artisan meta-ads:sync --branch=JakPus --since=2026-10-01 --until=2026-10-30`.

Migrasi role mengubah admin lama menjadi superadmin dan viewer lama menjadi staff, serta mengubah role default database menjadi staff. Tidak mengubah password atau token.

## Pengaturan profil

Semua role dapat mengubah nama, email, foto profil, dan kata sandi melalui Pengaturan. Klik avatar di sidebar untuk membuka pengaturan. Perubahan identitas langsung tampil di sidebar; peran hanya dapat diubah admin melalui pengelolaan pengguna.

Foto mendukung JPG, PNG, dan WebP, maksimal 2 MB dan dimensi 6000 x 6000 piksel. Foto disimpan pada disk local privat dan dilayani melalui route terautentikasi `/profile/avatar`; tidak memerlukan `storage:link`. Jalankan `php artisan migrate` setelah memperbarui project untuk menambahkan kolom avatar. Foto lama dihapus setelah penggantian berhasil.

## Impor XLSX

Maksimum upload 10 MB. Sheet wajib bernama `Campaign Meta Ads`, dengan kolom `Tanggal`, `Campaign`, dan `Platform`. Kolom metrik mengikuti workbook contoh. File identik ditolak setelah pernah diimpor; baris dengan kunci tanggal/nama/platform yang sama diperbarui.

Kesalahan file, XML, dan ekstensi zip dilaporkan sebagai respons 422. Catatan hasil impor menyimpan jumlah baris berhasil, dilewati, dan error per baris.

## Meta Ads

Kacab atau marketing memasukkan Ad Account ID dan access token melalui Pengaturan. Token disimpan terenkripsi dan tidak dikirim kembali dalam JSON. Sinkronisasi membaca seluruh halaman menggunakan cursor, lalu menyimpan hasil dalam transaksi. Kegagalan pada halaman berikutnya tidak menyimpan hasil parsial. `link_clicks` menggunakan action link_click, bukan seluruh klik.

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

## Omni Central Auth

Integrasi memakai OAuth 2 authorization code + PKCE melalui Laravel Passport di Omni Central Auth. Bukan alur `sso_data` terenkripsi atau OIDC ID token. Endpoint server: `/oauth/authorize`, `/oauth/token`, dan `/api/sso/user`.

Lingkungan lokal yang dikonfigurasi:
- Aplikasi: `http://iklan.test:8080`
- SSO: `http://sso.oca.test:8080`
- Callback terdaftar: `http://iklan.test:8080/auth/omni/callback`

Di halaman login, klik **Masuk dengan Omni Central Auth**, login di server SSO, dan setujui akses. Login lokal tetap tersedia. Logout Ads Manager mengakhiri sesi lokal; sesi pusat tetap mengikuti kebijakan server SSO.

Konfigurasi deployment ada di `.env.example`: `OMNI_ENABLED`, `OMNI_SERVER_URL`, `OMNI_CLIENT_ID`, `OMNI_CLIENT_SECRET`, `OMNI_REDIRECT_URI`. Client secret hanya berada di `.env` atau environment server dan tidak masuk Git. Daftarkan client OAuth tersendiri pada Omni untuk setiap lingkungan; callback harus sama persis. HTTPS wajib di luar lingkungan local/testing. Tidak memerlukan shared signing key Omni.

Identitas ditautkan berdasarkan pasangan server URL dan ID pengguna SSO. Akun SSO baru mendapatkan role **staff tanpa cabang** dan tidak dapat melihat data operasional sampai superadmin menetapkan cabang. Klaim role/cabang dari server tidak mengubah izin lokal. Nama, email, password, role, dan cabang akun lokal yang sudah ditautkan tetap dikelola lokal.

Email yang sama tidak otomatis menautkan akun lama. Administrator harus memastikan ID pengguna pada Omni, lalu menjalankan di Ads Manager:

```sh
php artisan app:link-omni-identity email-akun-lokal@example.com ID-PENGGUNA-OMNI
```

Command ini menolak tautan ganda dan tidak mengganti password atau hak akses. Jangan memakai ID akun iklan Meta sebagai ID Omni. Tabel identitas SSO yang sudah ada dipertahankan oleh migrasi; rollback migrasi integrasi ini juga mempertahankan tabel untuk melindungi tautan akun lama.

State dan verifier disimpan sementara di session, berlaku lima menit, lalu dihapus saat callback. Token OAuth hanya dipakai di server untuk membaca profil dan tidak disimpan pada session/database. Pengujian callback memakai HTTP fake; login dan persetujuan akun nyata dilakukan oleh pengguna sendiri.

Repository Git belum tersedia pada folder ini. Inisialisasi repository dan remote perlu dilakukan sesuai tujuan penyimpanan project.
