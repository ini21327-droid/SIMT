-- ==========================================================
-- AATMS Seed Data
-- Default Password semua akun: password123
-- Hash bcrypt murni (PHP password_hash('password123', PASSWORD_BCRYPT))
-- ==========================================================

USE `aatms_db`;

-- Users (1 Admin, 2 Dosen, 1 Kaprodi, 5 Mahasiswa)
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `nim_nidn`, `is_active`) VALUES
(1, 'admin', '$2y$10$.Sx0tPRUMVGdFUcEnkfQHe1SyD0lHGwTN7PNmvO.i6KE1Ra9oj9d.', 'Administrator Akademik', 'admin@kampus.ac.id', 'admin', 'ADM-001', 1),
(2, 'dosen1', '$2y$10$.Sx0tPRUMVGdFUcEnkfQHe1SyD0lHGwTN7PNmvO.i6KE1Ra9oj9d.', 'Dr. Budi Santoso, M.Kom.', 'budi.santoso@kampus.ac.id', 'dosen', '0412058801', 1),
(3, 'dosen2', '$2y$10$.Sx0tPRUMVGdFUcEnkfQHe1SyD0lHGwTN7PNmvO.i6KE1Ra9oj9d.', 'Siti Rahmawati, S.Kom., M.T.', 'siti.rahma@kampus.ac.id', 'dosen', '0423099002', 1),
(4, 'kaprodi', '$2y$10$.Sx0tPRUMVGdFUcEnkfQHe1SyD0lHGwTN7PNmvO.i6KE1Ra9oj9d.', 'Prof. Dr. Hendra Wijaya, M.Sc.', 'kaprodi.ti@kampus.ac.id', 'kaprodi', '0401017501', 1),
(5, '202401001', '$2y$10$.Sx0tPRUMVGdFUcEnkfQHe1SyD0lHGwTN7PNmvO.i6KE1Ra9oj9d.', 'Andi Pratama', 'andi.pratama@mhs.kampus.ac.id', 'mahasiswa', '202401001', 1),
(6, '202401002', '$2y$10$.Sx0tPRUMVGdFUcEnkfQHe1SyD0lHGwTN7PNmvO.i6KE1Ra9oj9d.', 'Dewi Lestari', 'dewi.lestari@mhs.kampus.ac.id', 'mahasiswa', '202401002', 1),
(7, '202401003', '$2y$10$.Sx0tPRUMVGdFUcEnkfQHe1SyD0lHGwTN7PNmvO.i6KE1Ra9oj9d.', 'Rizky Ramadhan', 'rizky.ramadhan@mhs.kampus.ac.id', 'mahasiswa', '202401003', 1),
(8, '202401004', '$2y$10$.Sx0tPRUMVGdFUcEnkfQHe1SyD0lHGwTN7PNmvO.i6KE1Ra9oj9d.', 'Nadia Putri', 'nadia.putri@mhs.kampus.ac.id', 'mahasiswa', '202401004', 1),
(9, '202401005', '$2y$10$.Sx0tPRUMVGdFUcEnkfQHe1SyD0lHGwTN7PNmvO.i6KE1Ra9oj9d.', 'Fajar Nugroho', 'fajar.nugroho@mhs.kampus.ac.id', 'mahasiswa', '202401005', 1);

-- Semesters
INSERT INTO `semesters` (`id`, `name`, `is_active`) VALUES
(1, 'Genap 2025/2026', 0),
(2, 'Ganjil 2026/2027', 1);

-- Courses
INSERT INTO `courses` (`id`, `course_code`, `course_name`, `sks`, `dosen_id`, `semester_id`) VALUES
(1, 'IF201', 'Pemrograman Web Lanjut', 3, 2, 2),
(2, 'IF202', 'Rekayasa Perangkat Lunak', 3, 2, 2),
(3, 'IF203', 'Basis Data Terdistribusi', 4, 3, 2);

-- Classes
INSERT INTO `classes` (`id`, `course_id`, `class_name`, `schedule_day`, `start_time`, `end_time`, `room`) VALUES
(1, 1, 'TI-3A', 'Senin', '08:00:00', '10:30:00', 'Lab Komputer 1'),
(2, 2, 'TI-3A', 'Rabu', '10:30:00', '13:00:00', 'Ruang R.302'),
(3, 3, 'TI-3B', 'Kamis', '13:00:00', '16:20:00', 'Lab Basis Data');

-- Class Enrollments
INSERT INTO `class_enrollments` (`class_id`, `student_id`) VALUES
(1, 5), (1, 6), (1, 7), (1, 8), (1, 9),
(2, 5), (2, 6), (2, 7), (2, 9),
(3, 5), (3, 8), (3, 9);

-- Attendance Sessions (Kampus Universitas Malikussaleh Reuleut, Aceh Utara: 5.23405600, 96.98813900)
INSERT INTO `attendance_sessions` (`id`, `class_id`, `dosen_id`, `session_code`, `meeting_number`, `topic`, `target_lat`, `target_lng`, `radius_meters`, `opened_at`, `closed_at`, `status`) VALUES
(1, 1, 2, 'SES-IF201-01', 1, 'Arsitektur MVC & Keamanan PDO pada PHP', 5.23405600, 96.98813900, 150, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 47 HOUR), 'closed'),
(2, 1, 2, 'SES-IF201-02', 2, 'Integrasi Geolocation API & Leaflet.js', 5.23405600, 96.98813900, 150, DATE_SUB(NOW(), INTERVAL 30 MINUTE), DATE_ADD(NOW(), INTERVAL 6 HOUR), 'open'),
(3, 2, 2, 'SES-IF202-01', 1, 'Software Requirements Specification (SRS)', 5.23438000, 96.98852000, 120, DATE_SUB(NOW(), INTERVAL 15 MINUTE), DATE_ADD(NOW(), INTERVAL 5 HOUR), 'open');

-- Attendances (Contoh kehadiran mahasiswa di sekitar Kampus Unimal Reuleut + Info Perangkat)
INSERT INTO `attendances` (`session_id`, `student_id`, `check_in_time`, `student_lat`, `student_lng`, `distance_meters`, `gps_accuracy`, `device_info`, `device_fingerprint`, `ip_address`, `status`, `location_valid`) VALUES
(1, 5, DATE_SUB(NOW(), INTERVAL 2 DAY), 5.23415000, 96.98819000, 11.88, 6.40, 'Smartphone Android 14 • Chrome Mobile', 'DEV-A91F02C4', '10.10.12.45', 'hadir', 1),
(1, 6, DATE_SUB(NOW(), INTERVAL 2 DAY), 5.23426000, 96.98831000, 29.56, 8.10, 'iPhone iOS 17.4 • Mobile Safari', 'DEV-B72E19D8', '10.10.12.78', 'hadir', 1),
(1, 7, DATE_SUB(NOW(), INTERVAL 2 DAY), 5.23401000, 96.98808000, 8.29, 5.20, 'Smartphone Android 13 • Chrome Mobile', 'DEV-C45A88F1', '10.10.12.90', 'terlambat', 1),
(1, 8, DATE_SUB(NOW(), INTERVAL 2 DAY), 5.23421000, 96.98825000, 21.08, 7.50, 'Laptop Windows 11 • Microsoft Edge', 'DEV-D18C33E9', '10.10.12.112', 'hadir', 1),
(2, 5, DATE_SUB(NOW(), INTERVAL 10 MINUTE), 5.23413000, 96.98818000, 9.40, 4.80, 'Smartphone Android 14 • Chrome Mobile', 'DEV-A91F02C4', '10.10.12.45', 'hadir', 1),
(2, 6, DATE_SUB(NOW(), INTERVAL 6 MINUTE), 5.23423000, 96.98829000, 25.56, 9.10, 'iPhone iOS 17.4 • Mobile Safari', 'DEV-B72E19D8', '10.10.12.78', 'hadir', 1);

-- Tasks (Contoh tugas aktif dan tugas yang sudah lewat deadline)
INSERT INTO `tasks` (`id`, `class_id`, `dosen_id`, `task_code`, `title`, `description`, `attachment_path`, `deadline`) VALUES
(1, 1, 2, 'TGS-IF201-01', 'Implementasi CRUD dengan PDO & Prepared Statements', 'Buatlah aplikasi CRUD sederhana dengan koneksi PDO MySQL, lengkap dengan validasi CSRF token dan sanitasi output htmlspecialchars(). Kumpulkan kode sumber atau laporan PDF.', NULL, DATE_ADD(NOW(), INTERVAL 5 DAY)),
(2, 1, 2, 'TGS-IF201-02', 'Analisis Keamanan Aplikasi Web (OWASP Top 10)', 'Tuliskan ringkasan analisis mengenai pencegahan SQL Injection dan Cross-Site Scripting (XSS) pada aplikasi PHP Native.', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 2, 2, 'TGS-IF202-01', 'Perancangan Use Case & ERD Sistem Akademik', 'Buatlah dokumen perancangan Use Case Diagram dan Entity Relationship Diagram (ERD) untuk Sistem Monitoring Akademik.', NULL, DATE_ADD(NOW(), INTERVAL 7 DAY));

-- Task Submissions
INSERT INTO `task_submissions` (`task_id`, `student_id`, `text_answer`, `file_path`, `file_type`, `submitted_at`, `score`, `feedback`, `review_status`, `graded_at`) VALUES
(1, 5, 'Berikut adalah implementasi CRUD PDO beserta proteksi CSRF token menggunakan hash_equals() dan prepared statement untuk seluruh transaksi input.', NULL, 'text/plain', DATE_SUB(NOW(), INTERVAL 2 HOUR), 92.50, 'Implementasi kode sangat rapi, keamanan PDO dan CSRF sudah tepat.', 'graded', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1, 6, 'Saya melampirkan penjelasan implementasi PDO MySQL dengan arsitektur modular dan pemisahan layer autentikasi RBAC.', NULL, 'text/plain', DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, NULL, 'submitted', NULL),
(2, 5, 'Analisis OWASP Top 10: SQL Injection dicegah dengan PDO prepared statements, sedangkan XSS dicegah dengan htmlspecialchars ENT_QUOTES UTF-8.', NULL, 'text/plain', DATE_SUB(NOW(), INTERVAL 2 DAY), 88.00, 'Analisis jelas dan tepat sasaran.', 'graded', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 7, 'Ringkasan mitigasi kerentanan Broken Access Control menggunakan middleware RBAC berbasis session_regenerate_id.', NULL, 'text/plain', DATE_SUB(NOW(), INTERVAL 2 DAY), 85.00, 'Penjelasan RBAC sangat baik.', 'graded', DATE_SUB(NOW(), INTERVAL 1 DAY));
