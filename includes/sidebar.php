<?php
/**
 * Sidebar Dinamis Modern Berdasarkan Role Pengguna (RBAC)
 */
$user = currentUser();
$role = $user['role'] ?? '';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

function isNavActive(string $needle, string $currentUri): string
{
    return (strpos($currentUri, $needle) !== false) ? 'active' : '';
}
?>
<aside class="sidebar d-print-none" id="appSidebar">
    <div class="sidebar-brand">
        <a href="<?= e(BASE_URL) ?>/dashboard.php" class="d-flex align-items-center gap-3 text-decoration-none text-white">
            <div class="brand-icon">
                <i class="fa-solid fa-satellite-dish"></i>
            </div>
            <div>
                <div class="fw-extrabold tracking-tight fs-5 lh-1 d-flex align-items-center gap-1">
                    <?= e(APP_NAME) ?>
                    <span class="badge rounded-pill bg-sky" style="font-size: 0.58rem; padding: 0.2em 0.55em;">v2</span>
                </div>
                <small class="text-sky-light" style="font-size: 0.7rem;">Smart GPS & Task Portal</small>
            </div>
        </a>
        <button class="btn btn-sm text-white d-lg-none ms-auto" id="sidebarClose" type="button" aria-label="Close Sidebar">
            <i class="fa-solid fa-xmark fs-5"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar-circle bg-gradient-sky text-white fw-bold shadow-sm">
                <?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?>
            </div>
            <div class="overflow-hidden">
                <div class="fw-semibold text-white text-truncate small"><?= e($user['full_name'] ?? '') ?></div>
                <div class="text-slate-400 d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                    <span class="live-dot-sky"></span>
                    <span><?= e(strtoupper($role)) ?></span>
                    <?= !empty($user['nim_nidn']) ? ' &bull; ' . e($user['nim_nidn']) : '' ?>
                </div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">NAVIGASI UTAMA</div>
        <a href="<?= e(BASE_URL) ?>/dashboard.php" class="nav-link-item <?= isNavActive('/dashboard.php', $currentUri) ?>">
            <i class="fa-solid fa-grid-2 fa-chart-pie"></i>
            <span>Dashboard Utama</span>
        </a>
        <a href="<?= e(BASE_URL) ?>/schedule.php" class="nav-link-item <?= isNavActive('/schedule.php', $currentUri) ?>">
            <i class="fa-solid fa-calendar-week"></i>
            <span>Jadwal & Kelas</span>
        </a>

        <?php if ($role === 'admin'): ?>
            <div class="nav-section-title">MANAJEMEN MASTER DATA</div>
            <a href="<?= e(BASE_URL) ?>/admin/users.php" class="nav-link-item <?= isNavActive('/admin/users.php', $currentUri) ?>">
                <i class="fa-solid fa-users-gear"></i>
                <span>Kelola Pengguna</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/semesters.php" class="nav-link-item <?= isNavActive('/admin/semesters.php', $currentUri) ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Semester Akademik</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/courses.php" class="nav-link-item <?= isNavActive('/admin/courses.php', $currentUri) ?>">
                <i class="fa-solid fa-book-open"></i>
                <span>Mata Kuliah (Kode)</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/classes.php" class="nav-link-item <?= isNavActive('/admin/classes.php', $currentUri) ?>">
                <i class="fa-solid fa-chalkboard-user"></i>
                <span>Kelas & Ruangan</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/enrollments.php" class="nav-link-item <?= isNavActive('/admin/enrollments.php', $currentUri) ?>">
                <i class="fa-solid fa-user-check"></i>
                <span>Peserta Kelas (KRS)</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/maps_settings.php" class="nav-link-item <?= isNavActive('/admin/maps_settings.php', $currentUri) ?>">
                <i class="fa-brands fa-google"></i>
                <span>Google Maps &amp; Perangkat</span>
            </a>
            <div class="nav-section-title">ANALITIK & LAPORAN</div>
            <a href="<?= e(BASE_URL) ?>/kaprodi/reports.php" class="nav-link-item <?= isNavActive('/kaprodi/reports.php', $currentUri) ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Monitoring & Rekap</span>
            </a>
        <?php endif; ?>

        <?php if ($role === 'dosen'): ?>
            <div class="nav-section-title">PERKULIAHAN & ABSENSI GPS</div>
            <a href="<?= e(BASE_URL) ?>/dosen/attendance.php" class="nav-link-item <?= isNavActive('/dosen/attendance.php', $currentUri) ?>">
                <i class="fa-solid fa-map-location-dot"></i>
                <span>Sesi Absensi GPS</span>
            </a>
            <div class="nav-section-title">TUGAS & PENILAIAN</div>
            <a href="<?= e(BASE_URL) ?>/dosen/tasks.php" class="nav-link-item <?= isNavActive('/dosen/tasks.php', $currentUri) || isNavActive('/dosen/task_detail.php', $currentUri) ? 'active' : '' ?>">
                <i class="fa-solid fa-list-check"></i>
                <span>Kelola & Nilai Tugas</span>
            </a>
            <div class="nav-section-title">LAPORAN AKADEMIK</div>
            <a href="<?= e(BASE_URL) ?>/kaprodi/reports.php" class="nav-link-item <?= isNavActive('/kaprodi/reports.php', $currentUri) ?>">
                <i class="fa-solid fa-file-waveform"></i>
                <span>Rekap Kelas Saya</span>
            </a>
        <?php endif; ?>

        <?php if ($role === 'mahasiswa'): ?>
            <div class="nav-section-title">AKTIVITAS MAHASISWA</div>
            <a href="<?= e(BASE_URL) ?>/mahasiswa/attendance.php" class="nav-link-item <?= isNavActive('/mahasiswa/attendance.php', $currentUri) ?>">
                <i class="fa-solid fa-location-crosshairs"></i>
                <span>Absensi GPS</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/mahasiswa/tasks.php" class="nav-link-item <?= isNavActive('/mahasiswa/tasks.php', $currentUri) ?>">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span>Tugas & Pengumpulan</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/mahasiswa/history.php" class="nav-link-item <?= isNavActive('/mahasiswa/history.php', $currentUri) ?>">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Riwayat & Nilai</span>
            </a>
        <?php endif; ?>

        <?php if ($role === 'kaprodi'): ?>
            <div class="nav-section-title">MONITORING PROGRAM STUDI</div>
            <a href="<?= e(BASE_URL) ?>/kaprodi/reports.php" class="nav-link-item <?= isNavActive('/kaprodi/reports.php', $currentUri) ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Laporan & Peta Kampus</span>
            </a>
        <?php endif; ?>

        <div class="nav-section-title">PENGATURAN AKUN</div>
        <a href="<?= e(BASE_URL) ?>/profile.php" class="nav-link-item <?= isNavActive('/profile.php', $currentUri) ?>">
            <i class="fa-solid fa-user-shield"></i>
            <span>Profil & Password</span>
        </a>
        <a href="<?= e(BASE_URL) ?>/logout.php" class="nav-link-item text-danger-soft">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Keluar Sistem</span>
        </a>
    </nav>
</aside>
