# Legalkes Progress

Aplikasi internal untuk monitoring proyek perizinan, milestone, komunikasi klien, dokumen, dan aktivitas tim.

## Teknologi

PHP 8.2+, MySQL / MariaDB, JavaScript

## Menjalankan proyek

1. Salin `.env.example` menjadi `.env` dan isi konfigurasi database.
2. Impor `database/install.sql` untuk skema dan master layanan.
3. Atur environment ADMIN_EMAIL, ADMIN_PASSWORD (minimal 12 karakter), dan opsional ADMIN_NAME, lalu jalankan `php database/seed.php`.
4. Arahkan document root ke `public`, atau jalankan `php -S localhost:8000 -t public public/router.php`.
5. Folder `storage/logs`, `storage/uploads`, dan `storage/sessions` harus dapat ditulis PHP.
6. Untuk HTTPS, gunakan SESSION_SECURE=1.

Tes logika: `php tests/business.php`. Tes integrasi membutuhkan database pengujian tersendiri.

## Catatan proyek

Role Admin, Direktur, Solver; dokumen privat; laporan CSV; audit log.

## Isi repository

Repository berisi kode sumber dan aset presentasi. Konfigurasi produksi, database operasional, backup, data pelanggan, session, dan upload privat tidak disertakan.

## Pemilik

Muhammad Rizqi Maulana (rizqimaulana04).

Pengembangan dilakukan dengan bantuan Codex.
