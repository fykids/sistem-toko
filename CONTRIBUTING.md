# Panduan kontribusi

## Menyiapkan proyek

Ikuti langkah Windows/Laragon atau Dev Container di [README.md](README.md). Gunakan PHP 8.3, Composer 2, dan Node.js 22 agar lingkungan lokal konsisten dengan CI.

## Branch dan Pull Request

- Buat branch dari `main` dengan awalan `feat/` untuk fitur atau `fix/` untuk perbaikan, misalnya `feat/export-transactions`.
- Hindari commit langsung ke `main`; ajukan Pull Request agar perubahan ditinjau.
- Jelaskan masalah yang diselesaikan, dampak perubahan, dan langkah verifikasi di deskripsi Pull Request.
- Tambahkan screenshot bila perubahan memengaruhi antarmuka.
- Pastikan workflow CI lulus dan tanggapi masukan review sebelum merge.

## Pemeriksaan sebelum mengajukan Pull Request

```powershell
php artisan test --compact
npm run build
```

Jika perubahan mengubah skema database, sertakan migration yang kompatibel dan jelaskan dampaknya pada data yang sudah ada.

## Keamanan

- Jangan commit `.env`, kredensial, token, file konfigurasi lokal, atau data database.
- Laporkan kerentanan keamanan secara privat kepada pemilik repositori, bukan melalui issue publik.
