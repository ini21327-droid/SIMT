# AATMS — Academic Attendance & Task Monitoring System

Sistem informasi akademik berbasis web untuk **Absensi Perkuliahan Berbasis GPS Real-Time (Google Maps + Leaflet + Rumus Haversine)**, **Deteksi Perangkat (Device Telemetry & Anti-Titip Absen)**, dan **Manajemen Pengumpulan serta Penilaian Tugas Mahasiswa** dengan penutupan *deadline* otomatis.

## Fitur Utama
- **Multi-Role RBAC:** Administrator, Dosen, Mahasiswa, dan Ketua Program Studi (Kaprodi).
- **Absensi GPS & Google Maps Hybrid:** Validasi jarak koordinat di sisi server menggunakan rumus Haversine ($R = 6371000\text{ m}$) dengan titik default **Kampus Universitas Malikussaleh (Unimal) Reuleut**.
- **Deteksi Perangkat & Live GPS Radar:** Mendeteksi tipe perangkat (Android/iPhone/Laptop), OS, browser, akurasi sensor GPS, *device fingerprint* (`DEV-XXXXXXXX`), serta deteksi penggunaan perangkat ganda (*anti-titip absen*).
- **Pengumpulan Tugas Multi-Format:** Mendukung jawaban teks maupun unggah berkas (`.pdf`, `.doc`, `.docx`, `.jpg`, `.png` maks. 10 MB) dengan penguncian *deadline* otomatis berbasis waktu server (`Asia/Jakarta`).
- **Pemeriksaan & Penilaian Instan (AJAX):** Tab Sudah/Belum Mengumpulkan, *preview* PDF/gambar langsung, dan penilaian 0–100 + komentar dosen.
- **Laporan Eksekutif & Analitik:** Grafik Chart.js, peta monitoring kampus, Export CSV (Excel), dan Print View.
- **UI Modern v2.0:** Mendukung *Dark/Light Mode*, *Smart Code Lookup* (`Ctrl + K`), *countdown timer*, dan desain responsif *mobile-friendly*.

## Tech Stack
- **Backend:** PHP 8.1+ (Native Modular + PDO MySQL)
- **Database:** MySQL / MariaDB (`InnoDB`, `utf8mb4_unicode_ci`)
- **Frontend:** HTML5, CSS3, Bootstrap 5.3, Chart.js 4, Leaflet.js + Google Maps Layers

## Cara Instalasi Lokal (XAMPP / Laragon)
1. Salin seluruh folder proyek ini ke `htdocs/aatms` (XAMPP) atau `www/aatms` (Laragon).
2. Jalankan layanan **Apache** dan **MySQL/MariaDB**.
3. Buka `http://localhost/aatms` di browser — database `aatms_db`, tabel, dan akun demo akan terpasang secara otomatis dari `database/schema.sql` dan `database/seed.sql`.
4. (Opsional) Masuk sebagai **Admin** -> menu **Google Maps & Perangkat** untuk memasukkan Google Maps API Key Anda.

## Akun Demo Default (Password: `password123`)
| Role | Username / NIM |
| :--- | :--- |
| **Admin** | `admin` |
| **Dosen** | `dosen1` |
| **Mahasiswa** | `202401001` |
| **Kaprodi** | `kaprodi` |

