# QRIS Midtrans untuk RuangAjar

Integrasi memakai Midtrans Core API QRIS. Server Key hanya disimpan di environment server; tidak pernah dikirim ke browser.

1. Siapkan akun merchant Midtrans dan aktifkan QRIS. Untuk uji coba, gunakan Server Key **sandbox**.
2. Isi `MIDTRANS_SERVER_KEY`, `MIDTRANS_MERCHANT_ID`, dan `MIDTRANS_PRODUCTION=false` di `.env`, lalu jalankan `php artisan config:clear` atau bangun ulang cache konfigurasi. Jangan menyalin Server Key ke kode sumber atau browser.
3. Di dashboard Midtrans, atur HTTP notification URL ke `https://DOMAIN-ANDA/webhooks/midtrans`. URL ini harus dapat diakses lewat HTTPS publik; `127.0.0.1` tidak dapat menerima webhook dari Midtrans.
4. Atur durasi dan paket trial di **Admin → Pengaturan Sistem**. Atur harga, fitur, persentase diskon, serta akhir promo di **Admin → Paket & Limit**.
5. Buat akun guru uji, buka **Paket Saya**, pilih paket dan buat QRIS sandbox. Uji pembayaran hanya dengan simulator Midtrans, bukan aplikasi bank sungguhan. Gunakan tombol **Periksa status** untuk meminta status terbaru dari Midtrans saat webhook belum tersedia di localhost. Pastikan transaksi berubah dari pending menjadi lunas dan paket aktif tepat satu kali. Uji juga QR kedaluwarsa dan notifikasi yang dikirim ulang.
6. Setelah merchant produksi disetujui dan uji sandbox lolos, gunakan Server Key **produksi** dan set `MIDTRANS_PRODUCTION=true`. Konfirmasikan notification URL produksi dan lakukan satu transaksi nominal kecil sebelum menerima guru umum.

Jumlah tagihan dihitung ulang di server saat checkout. Promo hanya berlaku sebelum waktu berakhir yang diset admin. Status lunas transaksi QRIS hanya diterima dari notifikasi Midtrans dengan tanda tangan dan nominal yang cocok; tombol admin tidak dapat menandai transaksi gateway lunas secara manual.

Jika Midtrans menolak permintaan sebelum QR dibuat, transaksi tidak akan terlihat sebagai pembayaran berhasil di dashboard Midtrans. Buka **Admin → Pembayaran** untuk melihat kode dan pesan penolakan. Pesan `MERCHANT_QRIS_NOT_READY` / `Merchant pop id is not found` berasal dari Midtrans; periksa aktivasi metode GoPay/QRIS pada merchant Sandbox atau hubungi dukungan Midtrans. Kode aplikasi tidak dapat mengaktifkan merchant yang belum disiapkan oleh gateway. Pastikan dashboard Midtrans sedang berada pada lingkungan **Sandbox**, bukan **Production**, ketika menguji kunci `SB-Mid-...`.

Dokumentasi: [QRIS Core API](https://docs.midtrans.com/reference/qris), [autentikasi API](https://docs.midtrans.com/docs/api-authorization-headers), [verifikasi webhook](https://docs.midtrans.com/docs/https-notification-webhooks).
