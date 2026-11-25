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
- Ikon dibuat ulang dengan `php scripts/make-icons.php`

## Perilaku penting

- **Batas percobaan**: login 5×/menit per email+IP; daftar 5×/10 menit per IP; kode undangan 5 salah/5 menit.
- **Gabung pasangan**: kategori bernama sama (per tipe, tidak peka huruf besar/kecil) disatukan; transaksi dipindah.
- **Putus**: yang memutus mendapat household baru berisi salinan kategori + transaksi yang ia catat sendiri;
  sisanya tetap di household lama.
- **Kategori terpakai** tidak bisa dihapus (database juga memakai `RESTRICT`).
- **Push** dikirim sinkron dan kegagalannya tidak membatalkan penyimpanan transaksi (hanya dicatat di log).
  Push dilewati bila ekstensi GMP/VAPID belum ada; notifikasi in-app tetap bekerja.
- Akun demo: satu akun bersama yang **hanya-baca** (tidak bisa menambah/ubah/hapus data, ganti nama, pasangan, atau push). Data contoh disegarkan otomatis tiap hari; tidak ada akun baru per kunjungan.
