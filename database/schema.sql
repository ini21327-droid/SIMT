-- ==========================================================
-- AATMS (Academic Attendance & Task Monitoring System)
-- Database Schema (MySQL / MariaDB)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `aatms_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `aatms_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `task_submissions`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `attendances`;
DROP TABLE IF EXISTS `attendance_sessions`;
DROP TABLE IF EXISTS `class_enrollments`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `semesters`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- 4.1 Tabel users
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `role` ENUM('admin','dosen','mahasiswa','kaprodi') NOT NULL,
  `nim_nidn` VARCHAR(30) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.2 Tabel semesters
CREATE TABLE `semesters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.3 Tabel courses (Mata Kuliah)
CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_code` VARCHAR(20) NOT NULL UNIQUE,
  `course_name` VARCHAR(100) NOT NULL,
  `sks` TINYINT NOT NULL,
  `dosen_id` INT NOT NULL,
  `semester_id` INT NOT NULL,
  CONSTRAINT `fk_courses_dosen` FOREIGN KEY (`dosen_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_courses_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.4 Tabel classes (Kelas Perkuliahan)
CREATE TABLE `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `class_name` VARCHAR(50) NOT NULL,
  `schedule_day` VARCHAR(20) NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `room` VARCHAR(50) NOT NULL,
  CONSTRAINT `fk_classes_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.5 Tabel class_enrollments (Peserta Kelas)
CREATE TABLE `class_enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  UNIQUE KEY `uq_class_student` (`class_id`, `student_id`),
  CONSTRAINT `fk_enrollments_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.6 Tabel attendance_sessions (Sesi Absensi Dibuka Dosen)
CREATE TABLE `attendance_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `dosen_id` INT NOT NULL,
  `session_code` VARCHAR(20) NOT NULL UNIQUE,
  `meeting_number` INT NOT NULL,
  `topic` VARCHAR(255) NOT NULL,
  `target_lat` DECIMAL(10, 8) NOT NULL,
  `target_lng` DECIMAL(11, 8) NOT NULL,
  `radius_meters` INT NOT NULL DEFAULT 100,
  `opened_at` DATETIME NOT NULL,
  `closed_at` DATETIME NOT NULL,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  CONSTRAINT `fk_att_sessions_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_sessions_dosen` FOREIGN KEY (`dosen_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.7 Tabel attendances (Data Kehadiran Mahasiswa + Telemetri Perangkat & GPS)
CREATE TABLE `attendances` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `check_in_time` DATETIME NOT NULL,
  `student_lat` DECIMAL(10, 8) NOT NULL,
  `student_lng` DECIMAL(11, 8) NOT NULL,
  `distance_meters` DECIMAL(10, 2) NOT NULL,
  `gps_accuracy` DECIMAL(10, 2) DEFAULT NULL,
  `device_info` VARCHAR(180) DEFAULT NULL,
  `device_fingerprint` VARCHAR(64) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `status` ENUM('hadir','terlambat','izin','sakit','alpha') NOT NULL DEFAULT 'hadir',
  `location_valid` TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_session_student` (`session_id`, `student_id`),
  CONSTRAINT `fk_attendances_session` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendances_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.8 Tabel tasks (Manajemen Tugas)
CREATE TABLE `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `dosen_id` INT NOT NULL,
  `task_code` VARCHAR(25) NOT NULL UNIQUE,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `attachment_path` VARCHAR(255) DEFAULT NULL,
  `deadline` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_tasks_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_dosen` FOREIGN KEY (`dosen_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.9 Tabel task_submissions (Pengumpulan & Penilaian Tugas)
CREATE TABLE `task_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `text_answer` LONGTEXT DEFAULT NULL,
  `file_path` VARCHAR(255) DEFAULT NULL,
  `file_type` VARCHAR(50) DEFAULT NULL,
  `submitted_at` DATETIME NOT NULL,
  `score` DECIMAL(5, 2) DEFAULT NULL,
  `feedback` TEXT DEFAULT NULL,
  `review_status` ENUM('submitted','reviewed','graded') NOT NULL DEFAULT 'submitted',
  `graded_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_task_student` (`task_id`, `student_id`),
  CONSTRAINT `fk_submissions_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

