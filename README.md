# RuangAjar

Platform perangkat pembelajaran guru berbasis Laravel 12, Livewire 3, Alpine.js, dan Tailwind CSS 4. Fondasi ini menerapkan alur hemat AI: data sekolah + data akademik + preset terstruktur + konten AI yang terbatas pada bagian dinamis.

## Yang sudah berfungsi

- Landing page, autentikasi email/password dan Google OAuth, OTP email, reset kata sandi, serta pengelolaan sesi.
- Dashboard guru responsif dengan paket, kuota, dokumen, dan navigasi mobile.
- Dashboard admin dengan metrik bisnis, subscription, dokumen, dan AI.
- Manajemen guru admin: tambah/edit, suspend/activate, reset kata sandi, detail penggunaan, assignment paket, dan perpanjangan masa aktif.
- Paket berlangganan dinamis dengan harga, durasi, pilihan fitur, generation limit, dan regeneration limit per fitur.
- Pembayaran manual dengan bukti privat, verifikasi, aktivasi subscription transaksional, dan proteksi aktivasi ganda.
- Halaman Paket Saya untuk guru dengan masa aktif, sisa kuota, riwayat langganan, dan pembayaran.
- Audit log administratif yang menyaring kata sandi dan data rahasia.
- Profil serta pratinjau kop sekolah.
- Studio pembuatan dengan generator khusus RPP, Modul Ajar, Silabus, LKPD, Soal, Kisi-Kisi, Prota, Promes, dan ATP.
- Wizard RPP empat langkah dengan dependent dropdown dan autosave draft sesi.
- Generator perangkat lain tiga langkah dengan formulir yang menyesuaikan kebutuhan setiap jenis dokumen.
- Kuota transaksional; kuota hanya bertambah setelah dokumen berhasil dibuat.
- Pembangun RPP hybrid dengan preset lokal saat provider AI belum dikonfigurasi.
- AI router OpenAI-compatible, failover, cooldown health, JSON validation, cache, dan usage log.
- Penyimpanan dokumen, snapshot versi, editor per bagian, otorisasi kepemilikan, dan PDF A4 untuk semua perangkat.
- Alur lanjutan Prota → Promes → ATP → RPP/Modul Ajar → LKPD → Soal → Kisi-Kisi, dengan pilihan dokumen sumber di wizard, pengisian konteks otomatis, tujuan/soal yang digunakan ulang, dan hubungan sumber yang tersimpan.
- Generator Soal menyimpan setiap butir beserta opsi, kunci, pembahasan, tingkat kesulitan, dan sumbernya ke Bank Soal.
- Bank Soal mendukung pencarian, filter kurikulum/kelas/mapel/kesulitan/bentuk, edit, duplikasi, dan pemilihan soal menjadi Paket Soal yang dapat dicetak.
- Skema inti akademik, paket, subscription, pembayaran, dokumen, bank soal, AI, dan audit.

## Menjalankan lokal

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Konfigurasi bawaan pengembangan menggunakan SQLite agar bisa langsung dicoba. Untuk produksi, gunakan MySQL dan sesuaikan `DB_*` pada `.env`.

## Persiapan Production (cPanel/VPS)

1. Arahkan document root domain ke folder `public/`, aktifkan HTTPS, lalu buat `.env` server dari `.env.production.example`. Jangan unggah `.env` atau arsip backup ke folder publik. Saat memindahkan data lama, pertahankan `APP_KEY` lama di tempat aman: kunci baru dapat membuat data terenkripsi dan backup lama tidak terbaca. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://ruangajar.id`, `DB_CONNECTION=mysql`, dan kredensial MySQL khusus aplikasi. Jangan jalankan `migrate:fresh` atau `db:seed` di server hidup; seeder bawaan berisi akun demo dan sengaja ditolak pada Production.
2. Isi SMTP dari penyedia email dengan alamat pengirim/domain yang sudah diverifikasi. Gunakan `MAIL_MAILER=smtp`, host, port, username, password, dan `MAIL_FROM_ADDRESS`. Jangan gunakan `log`, `array`, atau failover yang berakhir di `log` untuk akun sungguhan. Setelah deploy dan `php artisan config:cache`, jalankan `php artisan deployment:test-email alamat-anda@domain.tld`, lalu pastikan pesan benar-benar masuk. Setelah itu uji pendaftaran dan reset sandi menggunakan alamat email milik operator.
3. Jalankan `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build` di lingkungan build, lalu `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, dan `php artisan view:cache`. Pastikan PHP memiliki ekstensi ZIP dan folder `storage/` serta `bootstrap/cache/` dapat ditulis oleh proses web/CLI. Jalankan `php artisan deployment:check`; semua butir wajib `[OK]` sebelum menerima pembayaran nyata. Untuk Midtrans nyata, ganti **seluruh** kredensial dengan key Production dan set `MIDTRANS_PRODUCTION=true` hanya setelah merchant disetujui. Uji webhook publik `https://ruangajar.id/webhooks/midtrans` dengan transaksi kecil yang terverifikasi; jangan menandai paket lunas berdasarkan redirect browser.
4. Di cPanel tambahkan satu cron **setiap menit** dengan PHP CLI yang sesuai hosting: `<PHP_CLI> <PATH_APLIKASI>/artisan schedule:run`. Gunakan jalur absolut, misalnya `/usr/local/bin/php /home/AKUN/ruangajar/artisan schedule:run`, dan arahkan output ke log yang dipantau/dirotasi. Dengan `QUEUE_WORKER_VIA_SCHEDULER=true`, penjadwal menjalankan pekerja database singkat untuk antrean `mail,pdf,maintenance,default` setiap menit. Periksa `php artisan schedule:list`, `php artisan queue:failed`, dan status backup di Admin setelah beberapa menit. `DB_QUEUE_RETRY_AFTER` harus lebih besar dari timeout worker 1800 detik.
5. Bila VPS menyediakan Supervisor/systemd, set `QUEUE_WORKER_VIA_SCHEDULER=false` dan jaga pekerja persisten dengan `php artisan queue:work database --queue=mail,pdf,maintenance,default --sleep=1 --tries=3 --timeout=1800`. Tetap jalankan cron penjadwal untuk backup, pengingat, pembersihan, dan sinkronisasi paket. Setelah deploy kode baru jalankan `php artisan queue:restart`.

**Uji backup dan restore MySQL sebelum go-live:** buat backup dari Admin atau `php artisan backup:create`, biarkan worker menyelesaikannya, lalu verifikasi dengan `php artisan backup:restore ID` (tanpa `--execute`). Untuk uji pemulihan penuh, siapkan **instalasi staging terpisah** dengan database MySQL kosong/terisolasi, akses web dibatasi, dan `APP_KEY` yang sama dengan pembuat arsip. Unggah arsip ke staging dan pulihkan di sana; periksa login, jumlah guru/dokumen, serta berkas privat. Jangan mengarahkan instalasi Production ke database uji atau melakukan restore uji pada database utama. Di hosting, pastikan `mysqldump`, `mysql`, dan `proc_open` tersedia bagi PHP CLI; backup MySQL memerlukan izin SELECT dan ekspor, restore memerlukan izin impor/DDL. Simpan salinan backup di luar server dengan akses terbatas.

## Pekerjaan latar belakang

Gunakan `QUEUE_CONNECTION=database` dan jalankan penjadwal Laravel secara terus-menerus saat pengembangan:

```bash
php artisan schedule:work
```

Di server produksi, jalankan `php artisan schedule:run` setiap menit melalui cron. Penjadwal memproses antrean email, PDF besar, backup, dan pekerjaan umum setiap menit; menyelaraskan status paket setiap lima menit; membersihkan OTP, cache AI, cooldown model, dan berkas sementara setiap jam; serta tetap menjalankan backup, pengingat paket, dan agregasi analitik harian. Untuk beban lebih tinggi, jalankan pekerja antrean khusus yang dipantau process manager dengan `php artisan queue:work database --queue=mail,pdf,maintenance,default --tries=3 --timeout=1800`; pekerja terjadwal tetap aman karena berhenti saat antrean kosong. Pastikan `DB_QUEUE_RETRY_AFTER` lebih besar daripada timeout pekerja (bawaan: 2100 detik).

### Memulihkan backup

Admin dapat membuka **Admin → Backup**, mengunggah arsip ZIP yang sebelumnya diunduh, lalu memeriksa rinciannya dan mengetik frasa konfirmasi untuk memulihkan langsung dari web. Backup yang masih ada di daftar juga dapat dipulihkan lewat tombol **Pulihkan** tanpa mengunggah ulang. Sistem memeriksa integritas arsip, membuat backup pengaman sebelum impor, serta menahan permintaan web lain selama proses berlangsung. Setelah selesai admin diminta masuk kembali. Pastikan `APP_KEY` lama tersedia secara aman di luar arsip. Karena batas upload dan waktu proses PHP berbeda-beda di cPanel, arsip besar mungkin memerlukan penyesuaian `upload_max_filesize`, `post_max_size`, dan batas waktu PHP/hosting.

Perintah terminal di bawah tetap tersedia sebagai jalur pemulihan darurat bila panel web tidak dapat dibuka atau proses web terhenti:

```bash
php artisan backup:restore 123
php artisan down
php artisan backup:restore 123 --execute --confirm=PULIHKAN-123
# Setelah memeriksa login, dokumen, dan berkas:
php artisan up
```

Ganti `123` dengan ID yang ditampilkan di halaman admin. Perintah pemeriksaan tidak mengubah data. Saat eksekusi, sistem membuat backup pengaman baru sebelum impor; jalur arsip pengaman ditampilkan di terminal. Jika restore gagal sesudah impor dimulai, biarkan aplikasi dalam mode pemeliharaan dan pulihkan dari arsip pengaman atau hubungi pengelola server. Server MySQL/MariaDB memerlukan program `mysqldump` dan `mysql` serta akun database dengan izin impor yang memadai. Jangan menjalankan restore pada server aktif tanpa memahami bahwa perubahan setelah tanggal backup akan hilang.

## Email dan Google Login

Email verifikasi dan tautan reset hanya dikirim bila `MAIL_MAILER` memakai transport pengiriman sungguhan. Mode `log`, `array`, dan `failover` yang bisa jatuh ke `log` sengaja tidak dianggap siap, sehingga aplikasi tidak menyatakan email terkirim padahal hanya tersimpan di log.

Isi `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, dan `MAIL_FROM_ADDRESS` sesuai layanan email Anda. Gunakan alamat pengirim yang sudah diverifikasi di layanan tersebut. Setelah mengubah `.env`, jalankan `php artisan config:clear`. Uji pendaftaran dan reset kata sandi dengan kotak masuk yang Anda kendalikan; jangan memakai alamat pengguna nyata untuk pengujian.

Untuk Google Login, buat OAuth client bertipe **Web application** di Google Cloud Console. Masukkan URL callback yang persis sama ke **Authorized redirect URIs**: `https://ruangajar.id/auth/google/callback` untuk production, atau misalnya `http://127.0.0.1:8017/auth/google/callback` untuk server lokal pada port 8017. Isi `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, dan `GOOGLE_REDIRECT_URI` di `.env`; skema (`http`/`https`), domain, port, path, dan garis miring harus sama persis. Setelah mengubah konfigurasi jalankan `php artisan optimize:clear`, lalu `php artisan config:cache` di production. Tombol Google baru muncul setelah client ID dan secret tersedia.

Pengguna baru dari Google memperoleh akun guru dengan email terverifikasi. Jika email sudah terdaftar, pengguna perlu masuk dengan kata sandi terlebih dahulu dan menghubungkan akun Google dari halaman **Perangkat Masuk** atau **Verifikasi Email**; aplikasi tidak mengaitkan akun berdasarkan kecocokan email saja. Token OAuth tidak disimpan.

## Akun demo

- Guru: `guru@ruangajar.id` / `password`
- Admin: `admin@ruangajar.id` / `password`

## Pengujian

```bash
php artisan test
npm run build
```

Test mencakup autentikasi, pembatasan role admin, CRUD guru dan paket, pembayaran dan aktivasi subscription, audit log, seluruh halaman generator, alur Livewire, pembuatan RPP dan Soal, Bank Soal, pencatatan kuota, versioning, editor, hak akses dokumen, dan respons PDF.

## Provider AI

Tambahkan record terenkripsi pada `ai_providers`, model pada `ai_models`, lalu prioritas fitur pada `ai_routes`. Tanpa route aktif, seluruh perangkat tetap dapat dibuat dari preset terstruktur. Base URL provider perlu menyediakan endpoint OpenAI-compatible `/chat/completions` dengan keluaran JSON.
