# Project Blueprint: AATMS (Academic Attendance & Task Monitoring System)

> **Instruksi untuk AI Agent (Antigravity):**
> Gunakan dokumen ini sebagai *Single Source of Truth* untuk membangun aplikasi web **AATMS**. Ikuti struktur direktori, skema database, aturan bisnis, standar keamanan, dan urutan fase pengerjaan secara bertahap. Pastikan setiap fitur dapat dijalankan secara lokal (XAMPP/Laragon/Docker) tanpa dependensi *framework* berat di luar *tech stack* yang ditentukan.

---

## 1. Ringkasan Proyek
**AATMS (Academic Attendance & Task Monitoring System)** adalah sistem informasi akademik berbasis web untuk mengelola:
1. **Absensi perkuliahan berbasis lokasi GPS secara *real-time*** dengan validasi radius koordinat dan visualisasi peta.
2. **Pengumpulan, pemeriksaan, dan penilaian tugas mahasiswa** secara terintegrasi dengan penutupan *deadline* otomatis.

Semua entitas (Admin, Dosen, Mahasiswa, Kaprodi, Mata Kuliah, Kelas, Absensi, dan Tugas) terhubung dalam satu database relasional MySQL.

---

## 2. Tech Stack & Dependensi
- **Frontend:** HTML5, CSS3 (Responsif, bersih, modern; boleh menggunakan Tailwind CSS via CDN atau Bootstrap 5 via CDN), Vanilla JavaScript (ES6+).
- **Backend:** PHP 8.1+ (Native PHP dengan pola MVC sederhana atau prosedural modular menggunakan **PDO**).
- **Database:** MySQL / MariaDB.
- **Geolocation:** Browser HTML5 Geolocation API (`navigator.geolocation`).
- **Peta & Visualisasi:** Leaflet.js + OpenStreetMap Tiles (via CDN).
- **Ikon & Font:** FontAwesome / Lucide Icons + Google Fonts (`Inter`).

---

## 3. Struktur Direktori Proyek
Buat struktur folder yang rapi dan modular seperti berikut:

```text
aatms/
├── config/
│   ├── database.php          # Koneksi PDO MySQL
│   └── app.php               # Konstanta global (base URL, timezone Asia/Jakarta, upload limits)
├── database/
│   ├── schema.sql            # DDL pembuatan semua tabel & relasi
│   └── seed.sql              # Data dummy (Admin, Dosen, Mahasiswa, Kaprodi, Matkul, Kelas)
├── includes/
│   ├── auth.php              # Session check, RBAC middleware, CSRF helper
│   ├── functions.php         # Helper umum (kode otomatis, Haversine, sanitasi, format tanggal)
│   ├── header.php            # Layout header & navbar
│   ├── sidebar.php           # Sidebar dinamis berdasarkan role user
│   └── footer.php            # Layout footer & script JS umum
├── public/
│   ├── index.php             # Redirect ke login atau dashboard sesuai role
│   ├── login.php             # Halaman login
│   ├── logout.php            # Proses logout
│   ├── dashboard.php         # Dashboard ringkasan dinamis per role
│   ├── assets/
│   │   ├── css/style.css
│   │   └── js/
│   │       ├── gps-attendance.js   # Logika Geolocation API + Leaflet Map
│   │       └── app.js              # Interaksi UI & AJAX fetch
│   ├── admin/                # Modul CRUD Admin (users, semesters, courses, classes, enrollments)
│   ├── dosen/                # Modul Dosen (buka sesi absensi, kelola tugas, periksa & nilai)
│   ├── mahasiswa/            # Modul Mahasiswa (absen GPS, lihat & kumpul tugas, riwayat)
│   └── kaprodi/              # Modul Kaprodi (laporan rekap kehadiran & tugas, ekspor/cetak)
├── api/                      # Endpoint JSON untuk AJAX (lookup kode matkul/tugas, submit absen)
│   ├── lookup_code.php
│   ├── submit_attendance.php
│   └── grade_submission.php
└── uploads/
    └── tasks/                # Folder penyimpanan file tugas mahasiswa (dilindungi .htaccess)
```

---

## 4. Skema Database (MySQL)

Gunakan `utf8mb4_unicode_ci` dan engine `InnoDB` dengan *foreign key constraints*.

### 4.1 Tabel `users`
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | `INT PK AI` | ID unik pengguna |
| `username` | `VARCHAR(50) UNIQUE` | Username / NIM / NIDN |
| `password_hash` | `VARCHAR(255)` | Hash via `password_hash(..., PASSWORD_DEFAULT)` |
| `full_name` | `VARCHAR(100)` | Nama lengkap |
| `email` | `VARCHAR(100) UNIQUE` | Email pengguna |
| `role` | `ENUM('admin','dosen','mahasiswa','kaprodi')` | Hak akses pengguna |
| `nim_nidn` | `VARCHAR(30) NULL` | NIM (mahasiswa) atau NIDN (dosen) |
| `is_active` | `TINYINT(1) DEFAULT 1` | Status aktif akun |
| `created_at` | `TIMESTAMP` | Waktu pembuatan |

### 4.2 Tabel `semesters`
- `id` (`INT PK AI`), `name` (`VARCHAR(50)`, contoh: `Ganjil 2026/2027`), `is_active` (`TINYINT(1) DEFAULT 0`).

### 4.3 Tabel `courses` (Mata Kuliah)
- `id` (`INT PK AI`)
- `course_code` (`VARCHAR(20) UNIQUE`, contoh: `IF201` — digunakan untuk pemanggilan data otomatis)
- `course_name` (`VARCHAR(100)`)
- `sks` (`TINYINT`)
- `dosen_id` (`INT FK -> users.id`)
- `semester_id` (`INT FK -> semesters.id`)

### 4.4 Tabel `classes` (Kelas Perkuliahan)
- `id` (`INT PK AI`)
- `course_id` (`INT FK -> courses.id`)
- `class_name` (`VARCHAR(50)`, contoh: `TI-3A`)
- `schedule_day` (`VARCHAR(20)`)
- `start_time` (`TIME`), `end_time` (`TIME`)
- `room` (`VARCHAR(50)`)

### 4.5 Tabel `class_enrollments` (Peserta Kelas)
- `id` (`INT PK AI`)
- `class_id` (`INT FK -> classes.id`)
- `student_id` (`INT FK -> users.id`)
- *Unique constraint:* `(class_id, student_id)`

### 4.6 Tabel `attendance_sessions` (Sesi Absensi Dibuka Dosen)
- `id` (`INT PK AI`)
- `class_id` (`INT FK -> classes.id`)
- `dosen_id` (`INT FK -> users.id`)
- `session_code` (`VARCHAR(20) UNIQUE`, auto-generated)
- `meeting_number` (`INT`, pertemuan ke-1, 2, dst.)
- `topic` (`VARCHAR(255)`)
- `target_lat` (`DECIMAL(10, 8)`, koordinat lokasi kelas/dosen)
- `target_lng` (`DECIMAL(11, 8)`)
- `radius_meters` (`INT DEFAULT 100`, batas toleransi jarak absensi)
- `opened_at` (`DATETIME`)
- `closed_at` (`DATETIME`, batas waktu sesi absensi)
- `status` (`ENUM('open','closed') DEFAULT 'open'`)

### 4.7 Tabel `attendances` (Data Kehadiran Mahasiswa)
- `id` (`INT PK AI`)
- `session_id` (`INT FK -> attendance_sessions.id`)
- `student_id` (`INT FK -> users.id`)
- `check_in_time` (`DATETIME`)
- `student_lat` (`DECIMAL(10, 8)`)
- `student_lng` (`DECIMAL(11, 8)`)
- `distance_meters` (`DECIMAL(10, 2)`, hasil perhitungan jarak ke titik target)
- `status` (`ENUM('hadir','terlambat','izin','sakit','alpha') DEFAULT 'hadir'`)
- `location_valid` (`TINYINT(1)`, `1` jika dalam radius, `0` jika di luar)
- *Unique constraint:* `(session_id, student_id)`

### 4.8 Tabel `tasks` (Manajemen Tugas)
- `id` (`INT PK AI`)
- `class_id` (`INT FK -> classes.id`)
- `dosen_id` (`INT FK -> users.id`)
- `task_code` (`VARCHAR(25) UNIQUE`, contoh: `TGS-IF201-01` — untuk pemanggilan otomatis)
- `title` (`VARCHAR(150)`)
- `description` (`TEXT`)
- `attachment_path` (`VARCHAR(255) NULL`, file soal opsional dari dosen)
- `deadline` (`DATETIME`, penutupan otomatis berdasarkan waktu server)
- `created_at` (`TIMESTAMP`)

### 4.9 Tabel `task_submissions` (Pengumpulan & Penilaian Tugas)
- `id` (`INT PK AI`)
- `task_id` (`INT FK -> tasks.id`)
- `student_id` (`INT FK -> users.id`)
- `text_answer` (`LONGTEXT NULL`, untuk jawaban teks langsung)
- `file_path` (`VARCHAR(255) NULL`, untuk PDF/Word/Foto)
- `file_type` (`VARCHAR(50) NULL`)
- `submitted_at` (`DATETIME`)
- `score` (`DECIMAL(5, 2) NULL`)
- `feedback` (`TEXT NULL`, komentar dosen)
- `review_status` (`ENUM('submitted','reviewed','graded') DEFAULT 'submitted'`)
- `graded_at` (`DATETIME NULL`)
- *Unique constraint:* `(task_id, student_id)`

---

## 5. Matriks Hak Akses (RBAC)

| Modul / Fitur | Admin | Dosen | Mahasiswa | Kaprodi |
| :--- | :---: | :---: | :---: | :---: |
| CRUD Users (Dosen, Mahasiswa, Kaprodi) | Ya | Tidak | Tidak | Tidak |
| CRUD Semester, Mata Kuliah, Kelas, Enrollment | Ya | Lihat (milik sendiri) | Lihat (yang diambil) | Lihat Semua |
| Buka / Tutup Sesi Absensi GPS | Tidak | Ya | Tidak | Tidak |
| Lakukan Absensi GPS | Tidak | Tidak | Ya | Tidak |
| Monitoring Absensi (Peta & Daftar) | Ya | Ya (kelas sendiri) | Riwayat sendiri | Ya (Semua) |
| Buat & Kelola Tugas + Deadline | Tidak | Ya | Tidak | Tidak |
| Kumpulkan Tugas (PDF/Word/Foto/Teks) | Tidak | Tidak | Ya | Tidak |
| Monitoring, Periksa, & Nilai Tugas | Tidak | Ya | Lihat nilai sendiri | Rekap saja |
| Laporan Akademik & Cetak Rekap | Ya | Ya (kelas sendiri) | Tidak | Ya (Semua) |

---

## 6. Spesifikasi Logika Bisnis Inti

### 6.1 Pemanggilan Data Otomatis Berbasis Kode
- Sediakan endpoint `api/lookup_code.php` yang menerima parameter `type` (`course` atau `task`) dan `code`.
- Ketika pengguna memasukkan **Kode Mata Kuliah** (misal `IF201`), sistem otomatis menampilkan nama mata kuliah, SKS, nama dosen pengampu, dan kelas terkait.
- Ketika memasukkan **Kode Tugas** (misal `TGS-IF201-01`), sistem otomatis memuat detail tugas, mata kuliah, dan sisa waktu *deadline*.

### 6.2 Absensi GPS & Validasi Radius (Haversine Formula)
1. Dosen membuka sesi absensi dan menentukan titik lokasi (otomatis ambil GPS dosen atau klik pin di peta **Leaflet + OpenStreetMap**) beserta radius toleransi (default: 100 meter) dan durasi sesi.
2. Saat mahasiswa membuka halaman absensi, JavaScript memanggil `navigator.geolocation.getCurrentPosition()` dengan `enableHighAccuracy: true`, lalu menampilkan posisi mahasiswa dan lingkaran radius kelas di peta Leaflet.
3. Saat mahasiswa menekan **Absen Sekarang**, koordinat dikirim ke server (`api/submit_attendance.php`).
4. **Validasi wajib dilakukan di sisi server (PHP)** menggunakan rumus Haversine dengan jari-jari bumi $R = 6371000\text{ meter}$:

$$a = \sin^2\left(\frac{\Delta\phi}{2}\right) + \cos(\phi_1)\cos(\phi_2)\sin^2\left(\frac{\Delta\lambda}{2}\right)$$

$$d = 2R \cdot \text{atan2}\left(\sqrt{a}, \sqrt{1-a}\right)$$

5. Jika $d \le \text{radius\_meters}$ dan waktu server $\le \text{closed\_at}$, absensi diterima dan dicatat dengan *timestamp* otomatis. Jika di luar radius, tolak absensi dengan pesan jarak aktual dalam meter.

### 6.3 Manajemen Tugas & Penutupan Deadline Otomatis
- Penutupan *deadline* wajib divalidasi di **backend PHP** menggunakan waktu server (`Asia/Jakarta`), bukan hanya menyembunyikan tombol di frontend.
- Jika `CURRENT_TIMESTAMP > task.deadline`, form pengumpulan otomatis terkunci dengan status **"Deadline Berakhir"** dan endpoint menolak *request* baru.
- **Format yang didukung:**
  - Jawaban teks langsung (`textarea` / *rich text*).
  - Unggah file: `.pdf`, `.doc`, `.docx`, `.jpg`, `.jpeg`, `.png` (maksimal 10 MB).
  - Mahasiswa boleh mengisi teks saja, file saja, atau keduanya.

### 6.4 Monitoring & Pemeriksaan Tugas oleh Dosen
- Tampilkan 2 tab/filter pada halaman detail tugas dosen: **Sudah Mengumpulkan** dan **Belum Mengumpulkan** (dihitung dari `class_enrollments` LEFT JOIN `task_submissions`).
- Dosen dapat membuka preview PDF/gambar secara langsung di modal/tab baru atau mengunduh file Word, membaca jawaban teks, lalu mengisi **Nilai (0–100)**, **Komentar**, dan mengubah status menjadi `graded`.

### 6.5 Laporan Akademik (Kaprodi & Dosen)
- Menampilkan persentase kehadiran per mahasiswa dan per mata kuliah.
- Menampilkan statistik pengumpulan tugas (total tugas, jumlah terkumpul, rata-rata nilai).
- Menyediakan fitur filter berdasarkan **Semester**, **Mata Kuliah**, dan **Kelas**, serta tombol **Export CSV** dan **Print View (`window.print()`)**.

---

## 7. Standar Keamanan & Kualitas Kode
1. **SQL Injection Prevention:** Semua query database WAJIB menggunakan **PDO Prepared Statements**.
2. **XSS Protection:** Semua output HTML dari database WAJIB di-escape menggunakan `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`.
3. **Authentication & Session:** Gunakan `password_hash()` dan `password_verify()`, `session_regenerate_id(true)` saat login, serta proteksi CSRF token pada semua form `POST`.
4. **File Upload Security:** Validasi ekstensi dan MIME type (`finfo_file`), ganti nama file menjadi acak (`uniqid() . '_' . bin2hex(random_bytes(4))`), dan cegah eksekusi skrip PHP di folder `uploads/` menggunakan `.htaccess`.

---

## 8. Panduan UI/UX
- Gunakan desain dashboard modern dengan palet warna profesional (**Deep Navy `#1E293B`**, **Teal/Blue Accent `#0EA5E9`**, latar belakang **Light Gray `#F8FAFC`**).
- Gunakan *status badge* berwarna jelas:
  - Hijau: `Hadir`, `Open`, `Graded`
  - Kuning: `Terlambat`, `Submitted`
  - Merah: `Alpha`, `Closed`, `Belum Mengumpulkan`
- Pastikan halaman absensi GPS dan pengumpulan tugas **mobile-friendly** karena mahasiswa umumnya mengaksesnya melalui smartphone.

---

## 9. Rencana Eksekusi Bertahap (Workflow untuk Antigravity)

Kerjakan proyek ini secara berurutan per fase dan pastikan setiap fase bebas *error* sebelum lanjut ke fase berikutnya:

- **Fase 1: Inisialisasi & Autentikasi**
  - Buat `database/schema.sql` dan `database/seed.sql` (sertakan akun default untuk 4 role agar mudah diuji).
  - Buat `config/database.php`, `config/app.php`, `includes/auth.php`, `includes/functions.php`.
  - Buat halaman `login.php`, `logout.php`, dan kerangka layout `dashboard.php`.
- **Fase 2: Manajemen Data Master (Admin) & Lookup Kode**
  - Buat CRUD Users, Semesters, Courses (dengan kode unik), Classes, dan Enrollments.
  - Buat endpoint `api/lookup_code.php` untuk pencarian cepat kode mata kuliah & tugas.
- **Fase 3: Modul Absensi GPS & Peta Leaflet**
  - Buat fitur buka/tutup sesi absensi untuk Dosen (dengan peta Leaflet untuk set lokasi & radius).
  - Buat halaman absensi Mahasiswa dengan Geolocation API + validasi Haversine server-side.
  - Buat halaman Monitoring Absensi untuk Dosen (tabel + titik lokasi mahasiswa di peta).
- **Fase 4: Modul Tugas, Pengumpulan, Pemeriksaan & Penilaian**
  - Buat fitur manajemen tugas untuk Dosen (auto-generate `task_code` & `deadline`).
  - Buat halaman pengumpulan multi-format untuk Mahasiswa dengan penguncian *deadline* otomatis.
  - Buat halaman monitoring (sudah/belum kumpul) dan antarmuka pemeriksaan + penilaian tugas untuk Dosen.
- **Fase 5: Laporan Akademik (Kaprodi) & Finalisasi**
  - Buat dashboard laporan & rekapitulasi kehadiran/tugas untuk Kaprodi beserta fitur Export CSV dan Print.
  - Lakukan pengujian alur *end-to-end* untuk ke-4 peran pengguna dan rapikan responsivitas CSS.