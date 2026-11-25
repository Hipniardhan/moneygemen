# MoneyGemen

Aplikasi web (PWA) pencatat pemasukan & pengeluaran, sendiri atau bersama pasangan.
Laravel 12 + Blade + Livewire 3 + MySQL. Tampilan mobile-first, CSS biasa (`public/css/app.css`) —
**tidak perlu Node/Tailwind build**, jadi tidak ada langkah `npm` baik di laptop maupun di hosting.

## Jalankan di laptop

```bash
composer install            # tambahkan --ignore-platform-req=ext-gmp bila ext-gmp belum aktif
cp .env.example .env        # lalu atur DB_* (MySQL)
php artisan key:generate
php artisan migrate
php artisan push:keys       # kunci VAPID untuk notifikasi push
php artisan serve
php artisan test            # 66 tes
```

Catatan Windows: bila `push:keys` gagal "Unable to create the key", set `OPENSSL_CONF` ke
`...\php\extras\ssl\openssl.cnf` (masalah OpenSSL lokal, bukan kode).

## Deploy ke cPanel

1. **Subdomain**: buat subdomain (mis. `uang.domainmu.com`) dan ubah *Document Root*-nya ke `moneygemen/public`.
   Upload proyek ke folder **di luar** `public_html` (mis. `~/moneygemen`).
2. **PHP**: pilih PHP 8.2+ (MultiPHP Manager) dan aktifkan ekstensi BCMath, Ctype, cURL, Fileinfo, JSON,
   Mbstring, OpenSSL, PDO MySQL, Tokenizer, XML, **GMP**.
3. **Database**: buat database + user lewat *MySQL Databases*.
4. **Terminal/SSH**:
   ```bash
   cd ~/moneygemen
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && php artisan key:generate
   nano .env        # APP_ENV=production, APP_DEBUG=false, APP_URL=https://uang.domainmu.com, DB_*
   php artisan migrate --force
   php artisan push:keys
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   chmod -R 775 storage bootstrap/cache
   ```
5. **Cron (opsional, disarankan)** — membersihkan notifikasi >90 hari, undangan lama, dan akun demo:
   ```
   * * * * * cd /home/USER/moneygemen && php artisan schedule:run >> /dev/null 2>&1
   ```
6. Buka situs lewat **HTTPS** (wajib untuk PWA & push). Di Chrome HP muncul ajakan "Pasang aplikasi";
   di iPhone: Bagikan → Tambah ke Layar Utama.

Setelah mengubah `.env`, jalankan ulang `php artisan config:cache`.

## Desain

Palet krem + navy + emas, selaras dengan logo (token di bagian atas `public/css/app.css`, mode terang dan gelap):

| Peran | Terang | Gelap |
|---|---|---|
| Utama (tombol, tautan, kartu saldo) | navy `#14213d` | emas pucat `#e3c77a` (kartu saldo tetap biru tua) |
| Teks / latar / kartu | `#14213d` / `#f3e9dc` / `#fbf6ee` | `#f3e9dc` / `#0e1527` / `#16213a` |
| Aksen tunggal (hanya aksi utama: tombol +) | emas `#d9a826`, tepi navy | emas `#d9a826` |
| Pemasukan / pengeluaran (makna, tidak dihias) | `#166534` / `#a8431f` | `#4ade80` / `#fb923c` |

- Teks di atas emas wajib navy (7,3:1). Emas di atas krem hanya sekitar 2:1, jadi tombolnya diberi tepi navy 2px.
- Semua pasangan teks diukur terhadap WCAG AA (minimum 4,5:1): terendah 5,0:1 pada teks; batas kolom isian 4,1:1 (terang) dan 3,6:1 (gelap).
- Font: Plus Jakarta Sans (lisensi OFL, file di `public/fonts/`), dimuat dari server sendiri, jadi tidak ada permintaan ke pihak ketiga
  dan CSP tetap ketat. Ganti font cukup lewat `--font` di CSS.
- Logo sumber dan ikon: lihat `resources/branding/` dan `scripts/make-icons.php`.

## Keamanan

Sudah diterapkan di kode:
- **Header keamanan** (`app/Http/Middleware/SecurityHeaders.php`): CSP, `X-Frame-Options: DENY`, `nosniff`,
  `Referrer-Policy`, `Permissions-Policy`, HSTS (hanya di HTTPS), `Cache-Control: no-store` untuk halaman setelah login,
  dan `X-Powered-By` dihapus. Bila nanti memakai skrip/gambar dari domain lain, izinkan dulu di `CSP` pada file itu.
- **HTTPS dipaksa** lewat `public/.htaccess` dan `URL::forceScheme` di produksi. File tersembunyi (`.env`, `.git`) diblokir.
- **Sesi**: ID diganti saat login/daftar; ganti kata sandi atau `user:reset-password` otomatis mengeluarkan perangkat lain.
- **Batas percobaan**: login per email+IP (5/menit) dan per IP (20/5 menit); daftar, kode undangan, ganti sandi, ekspor,
  `/demo`, dan langganan push juga dibatasi.
- **Isolasi data**: semua query dibatasi `household_id`; kategori dan transaksi milik household lain tidak bisa diakses
  (ada tes yang memastikannya). Properti publik Livewire yang bisa dipalsukan klien divalidasi/dibatasi di server.
- **Push**: endpoint langganan hanya diterima dari layanan push resmi (FCM, Mozilla, Apple, Windows) agar server tidak
  bisa dipaksa mengirim request ke alamat internal (SSRF).
- **CSV**: sel yang diawali `= + - @` dinetralkan (CSV injection).
- Akun demo bersifat hanya-baca dan domain emailnya (`@demo.moneygemen`) tidak bisa didaftarkan.

Wajib di server produksi: `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, dan jangan pernah
mengunggah `.env`/`vendor` ke repo. Jalankan `composer audit` berkala untuk memeriksa kerentanan dependensi.

## Perintah admin

| Perintah | Fungsi |
|---|---|
| `php artisan user:reset-password email@x.com [sandiBaru]` | Reset kata sandi (tanpa argumen sandi → dibuat acak & ditampilkan) |
| `php artisan moneygemen:cleanup` | Hapus notifikasi >90 hari, undangan kedaluwarsa, akun demo >1 hari |
| `php artisan push:keys [--force]` | Buat kunci VAPID (`--force` membatalkan semua langganan push lama) |
| `php artisan migrate:status` | Cek status skema |

## Peta fitur → kode

- Skema 7 tabel: `database/migrations/`
- Akun, demo, undangan, gabung & pisah data: `app/Services/HouseholdService.php`
- Notifikasi (in-app + web push): `app/Services/PartnerNotifier.php`, `app/Livewire/NotificationBell.php`
- Halaman: `app/Livewire/{Dashboard,Transactions,TransactionForm,Categories,Partner,Profile,Report}.php`, `app/Livewire/Auth/`
- Laporan bulanan: `app/Livewire/Report.php`; ekspor CSV: `app/Http/Controllers/ExportController.php`
- PWA: `public/manifest.webmanifest`, `public/sw.js`, `public/js/app.js`, `resources/views/offline.blade.php`
- Logo sumber: `resources/branding/logo-source.jpg`. Semua ikon (PWA, favicon, badge, logo halaman masuk) dibuat ulang dengan `php scripts/make-icons.php`

## Perilaku penting

- **Batas percobaan**: login 5×/menit per email+IP; daftar 5×/10 menit per IP; kode undangan 5 salah/5 menit.
- **Gabung pasangan**: kategori bernama sama (per tipe, tidak peka huruf besar/kecil) disatukan; transaksi dipindah.
- **Putus**: yang memutus mendapat household baru berisi salinan kategori + transaksi yang ia catat sendiri;
  sisanya tetap di household lama.
- **Kategori terpakai** tidak bisa dihapus (database juga memakai `RESTRICT`).
- **Push** dikirim sinkron dan kegagalannya tidak membatalkan penyimpanan transaksi (hanya dicatat di log).
  Push dilewati bila ekstensi GMP/VAPID belum ada; notifikasi in-app tetap bekerja.
- Akun demo: satu akun bersama yang **hanya-baca** (tidak bisa menambah/ubah/hapus data, ganti nama, pasangan, atau push). Data contoh disegarkan otomatis tiap hari; tidak ada akun baru per kunjungan.
