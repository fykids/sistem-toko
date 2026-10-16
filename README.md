# Sistem Toko

Aplikasi pencatatan transaksi kendaraan berbasis Laravel. Aplikasi menyediakan daftar transaksi dan formulir untuk mencatat pembelian, penjualan, biaya, pajak, serta perbaikan kendaraan.

## Teknologi

- PHP 8.3 atau lebih baru yang didukung proyek, dan Composer 2
- Laravel 13
- Node.js 22 dan npm
- SQLite untuk pengembangan lokal (MySQL dapat dipilih melalui konfigurasi `.env`)

## Menyiapkan lingkungan Windows dengan Laragon

1. Pasang dan jalankan Laragon dengan PHP 8.3, Composer, Node.js 22, dan npm.
2. Pastikan ekstensi SQLite aktif pada PHP:

   ```powershell
   php -m | Select-String pdo_sqlite
   ```

3. Clone repositori dan masuk ke folder proyek:

   ```powershell
   git clone https://github.com/fykids/sistem-toko.git
   Set-Location sistem-toko
   ```

4. Siapkan dependensi, file lingkungan lokal, kunci aplikasi, database SQLite, dan aset frontend:

   ```powershell
   composer setup
   ```

   Perintah ini menggunakan `.env.example` sebagai dasar `.env` lokal bila file tersebut belum ada. Jangan bagikan `.env` atau kredensial lingkungan.

5. Jalankan aplikasi pada terminal pertama:

   ```powershell
   php artisan serve
   ```

6. Untuk mengembangkan aset frontend, jalankan pada terminal kedua:

   ```powershell
   npm run dev
   ```

   Buka `http://127.0.0.1:8000`.

## Pengujian dan build

Jalankan suite tes Laravel:

```powershell
php artisan test --compact
```

Build aset frontend:

```powershell
npm run build
```

GitHub Actions menjalankan tes dan build ini pada setiap Pull Request menuju `main` serta setiap push ke `main`.

## VS Code Dev Container

Buka folder proyek di VS Code dengan ekstensi **Dev Containers**, lalu pilih **Reopen in Container**. Container menggunakan PHP 8.3 dan Node.js 22; setup awal menjalankan `composer setup`. Port aplikasi `8000` dan Vite `5173` diteruskan ke host.

Di terminal container, jalankan `php artisan serve --host=0.0.0.0` untuk aplikasi. Untuk hot reload frontend, jalankan `npm run dev -- --host 0.0.0.0` pada terminal lain.

## Alur kontribusi

Gunakan branch fitur/perbaikan dan ajukan Pull Request ke `main`. Sertakan ringkasan perubahan dan tes yang dijalankan; detail alurnya ada di [CONTRIBUTING.md](CONTRIBUTING.md). Pull Request memakai [template](.github/PULL_REQUEST_TEMPLATE.md) dan harus lolos workflow CI.

Repositori ini bersifat publik. Data lokal (`.env`, database SQLite, `vendor`, dan `node_modules`) tidak untuk di-commit. Lihat dokumentasi [Laravel](https://laravel.com/docs) untuk referensi framework.
