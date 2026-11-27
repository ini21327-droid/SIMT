<?php
/**
 * Dashboard Dinamis Modern v2.0 (Admin, Dosen, Mahasiswa, Kaprodi)
 * Dilengkapi Hero Banner, KPI Cards, Live Radar Status, dan Grafik Analitik Chart.js
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDB();
syncExpiredAttendanceSessions($pdo);

$user = currentUser();
$role = $user['role'];
$userId = (int)$user['id'];
$pageTitle = 'Dashboard ' . ucfirst($role);

$activeSemester = $pdo->query("SELECT * FROM semesters WHERE is_active = 1 LIMIT 1")->fetch();

if ($role === 'admin' || $role === 'kaprodi') {
    $totalDosen = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'dosen'")->fetchColumn();
    $totalMhs = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'")->fetchColumn();
    $totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $totalOpenSessions = (int)$pdo->query("SELECT COUNT(*) FROM attendance_sessions WHERE status = 'open'")->fetchColumn();
    $totalTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();

    $recentSessions = $pdo->query("
        SELECT s.*, cl.class_name, c.course_code, c.course_name, u.full_name AS dosen_name,
               (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status IN ('hadir','terlambat')) AS present_count,
               (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = s.class_id) AS total_students
        FROM attendance_sessions s
        JOIN classes cl ON s.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        JOIN users u ON s.dosen_id = u.id
        ORDER BY s.opened_at DESC
        LIMIT 6
    ")->fetchAll();

    // Data grafik per kelas untuk Admin/Kaprodi
    $chartClasses = $pdo->query("
        SELECT CONCAT(c.course_code, ' (', cl.class_name, ')') AS label,
               (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = cl.id) AS mhs_count,
               (SELECT COUNT(*) FROM attendance_sessions s WHERE s.class_id = cl.id) AS sess_count,
               (SELECT COUNT(*) FROM attendances a JOIN attendance_sessions s ON a.session_id = s.id WHERE s.class_id = cl.id AND a.status IN ('hadir','terlambat')) AS att_count,
               (SELECT COUNT(*) FROM tasks t WHERE t.class_id = cl.id) AS task_count,
               (SELECT COUNT(*) FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE t.class_id = cl.id) AS sub_count
        FROM classes cl
        JOIN courses c ON cl.course_id = c.id
        ORDER BY c.course_code ASC
    ")->fetchAll();
} elseif ($role === 'dosen') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes cl JOIN courses c ON cl.course_id = c.id WHERE c.dosen_id = ?");
    $stmt->execute([$userId]);
    $myClassesCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_sessions WHERE dosen_id = ? AND status = 'open'");
    $stmt->execute([$userId]);
    $myOpenSessions = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE dosen_id = ?");
    $stmt->execute([$userId]);
    $myTasksCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM task_submissions ts
        JOIN tasks t ON ts.task_id = t.id
        WHERE t.dosen_id = ? AND ts.review_status = 'submitted'
    ");
    $stmt->execute([$userId]);
    $ungradedCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT s.*, cl.class_name, c.course_code, c.course_name,
               (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status IN ('hadir','terlambat')) AS present_count,
               (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = s.class_id) AS total_students
        FROM attendance_sessions s
        JOIN classes cl ON s.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        WHERE s.dosen_id = ?
        ORDER BY s.opened_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentSessions = $stmt->fetchAll();

    $cStmt = $pdo->prepare("
        SELECT CONCAT(c.course_code, ' (', cl.class_name, ')') AS label,
               (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = cl.id) AS mhs_count,
               (SELECT COUNT(*) FROM attendance_sessions s WHERE s.class_id = cl.id) AS sess_count,
               (SELECT COUNT(*) FROM attendances a JOIN attendance_sessions s ON a.session_id = s.id WHERE s.class_id = cl.id AND a.status IN ('hadir','terlambat')) AS att_count,
               (SELECT COUNT(*) FROM tasks t WHERE t.class_id = cl.id) AS task_count,
               (SELECT COUNT(*) FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE t.class_id = cl.id) AS sub_count
        FROM classes cl
        JOIN courses c ON cl.course_id = c.id
        WHERE c.dosen_id = ?
        ORDER BY c.course_code ASC
    ");
    $cStmt->execute([$userId]);
    $chartClasses = $cStmt->fetchAll();
} elseif ($role === 'mahasiswa') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE student_id = ?");
    $stmt->execute([$userId]);
    $enrolledClassesCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT s.*, cl.class_name, cl.room, c.course_code, c.course_name, u.full_name AS dosen_name,
               a.id AS attendance_id, a.status AS my_attendance_status, a.distance_meters
        FROM attendance_sessions s
        JOIN class_enrollments ce ON s.class_id = ce.class_id
        JOIN classes cl ON s.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        JOIN users u ON s.dosen_id = u.id
        LEFT JOIN attendances a ON a.session_id = s.id AND a.student_id = ?
        WHERE ce.student_id = ? AND s.status = 'open'
        ORDER BY s.opened_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    $activeSessionsForMhs = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT t.*, cl.class_name, c.course_code, c.course_name,
               ts.id AS submission_id, ts.review_status, ts.score
        FROM tasks t
        JOIN class_enrollments ce ON t.class_id = ce.class_id
        JOIN classes cl ON t.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        LEFT JOIN task_submissions ts ON ts.task_id = t.id AND ts.student_id = ?
        WHERE ce.student_id = ?
        ORDER BY t.deadline ASC
        LIMIT 6
    ");
    $stmt->execute([$userId, $userId]);
    $mhsTasks = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE student_id = ? AND status IN ('hadir','terlambat')");
    $stmt->execute([$userId]);
    $myTotalPresent = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM attendance_sessions s
        JOIN class_enrollments ce ON s.class_id = ce.class_id
        WHERE ce.student_id = ?
    ");
    $stmt->execute([$userId]);
    $totalSessionsAvailable = (int)$stmt->fetchColumn();
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Modern Hero Banner -->
<div class="hero-banner mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 position-relative" style="z-index: 2;">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="badge rounded-pill bg-sky text-white px-3 py-1">
                    <i class="fa-solid fa-sparkles me-1"></i>Semester <?= e($activeSemester['name'] ?? 'Aktif') ?>
                </span>
                <span class="badge rounded-pill bg-white bg-opacity-10 text-white border border-white border-opacity-25 px-3 py-1">
                    Role: <?= e(strtoupper($role)) ?>
                </span>
            </div>
            <h3 class="fw-extrabold mb-1">Halo, <?= e($user['full_name']) ?>!</h3>
            <p class="text-sky-light small mb-0 opacity-75">
                Pantau presensi berbasis lokasi GPS Haversine dan progres pengumpulan tugas akademik secara <em>real-time</em>.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($role === 'dosen'): ?>
                <a href="<?= e(BASE_URL) ?>/dosen/attendance.php" class="btn btn-sky">
                    <i class="fa-solid fa-satellite-dish me-2"></i>Buka Sesi Absensi GPS
                </a>
                <a href="<?= e(BASE_URL) ?>/dosen/tasks.php" class="btn btn-light fw-semibold text-dark">
                    <i class="fa-solid fa-plus me-1"></i>Buat Tugas
                </a>
            <?php elseif ($role === 'mahasiswa'): ?>
                <a href="<?= e(BASE_URL) ?>/mahasiswa/attendance.php" class="btn btn-sky">
                    <i class="fa-solid fa-location-crosshairs me-2"></i>Check-In GPS Sekarang
                </a>
                <a href="<?= e(BASE_URL) ?>/mahasiswa/tasks.php" class="btn btn-light fw-semibold text-dark">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i>Kumpulkan Tugas
                </a>
            <?php elseif ($role === 'admin'): ?>
                <a href="<?= e(BASE_URL) ?>/admin/courses.php" class="btn btn-sky">
                    <i class="fa-solid fa-book-open me-2"></i>Kelola Mata Kuliah
                </a>
                <a href="<?= e(BASE_URL) ?>/admin/users.php" class="btn btn-light fw-semibold text-dark">
                    <i class="fa-solid fa-user-plus me-1"></i>Pengguna
                </a>
            <?php elseif ($role === 'kaprodi'): ?>
                <a href="<?= e(BASE_URL) ?>/kaprodi/reports.php" class="btn btn-sky">
                    <i class="fa-solid fa-chart-line me-2"></i>Laporan &amp; Peta Kampus
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($role === 'admin' || $role === 'kaprodi'): ?>
    <!-- KPI Cards Admin / Kaprodi -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div>
                    <div class="text-muted small fw-semibold">Total Mahasiswa</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $totalMhs ?></div>
                    <span class="small text-sky fw-medium"><i class="fa-solid fa-user-graduate me-1"></i>Peserta Terdaftar</span>
                </div>
                <div class="stat-icon bg-sky-soft"><i class="fa-solid fa-users"></i></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card accent-emerald">
                <div>
                    <div class="text-muted small fw-semibold">Dosen Pengampu</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $totalDosen ?></div>
                    <span class="small text-success fw-medium"><i class="fa-solid fa-circle-check me-1"></i> Pengajar Aktif</span>
                </div>
                <div class="stat-icon" style="background:rgba(16,185,129,0.14); color:#10B981;"><i class="fa-solid fa-chalkboard-user"></i></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card accent-amber">
                <div>
                    <div class="text-muted small fw-semibold">Mata Kuliah Aktif</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $totalCourses ?></div>
                    <span class="small text-warning fw-medium"><i class="fa-solid fa-bolt me-1"></i>Kode Otomatis</span>
                </div>
                <div class="stat-icon" style="background:rgba(245,158,11,0.14); color:#D97706;"><i class="fa-solid fa-book-open"></i></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card accent-purple">
                <div>
                    <div class="text-muted small fw-semibold">Sesi GPS Live / Tugas</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $totalOpenSessions ?> <small class="fs-6 text-muted fw-normal">/ <?= $totalTasks ?> Tugas</small></div>
                    <span class="small text-success fw-medium"><span class="pulse-dot"></span>Geofencing Aktif</span>
                </div>
                <div class="stat-icon" style="background:rgba(139,92,246,0.14); color:#7C3AED;"><i class="fa-solid fa-satellite-dish"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card-custom p-3 p-md-4 h-100">
                <h6 class="fw-bold mb-1"><i class="fa-solid fa-chart-bar text-sky me-2"></i>Perbandingan Capaian Kelas (%)</h6>
                <p class="text-muted small mb-3">Tingkat kehadiran GPS vs pengumpulan tugas per kelas.</p>
                <div style="height: 260px;">
                    <canvas id="execClassChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card-custom p-3 p-md-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-satellite-dish text-sky me-2"></i>Sesi Absensi GPS Terbaru</h6>
                    <a href="<?= e(BASE_URL) ?>/kaprodi/reports.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Laporan Lengkap</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Kode Sesi</th>
                                <th>Mata Kuliah &amp; Kelas</th>
                                <th>Radius</th>
                                <th>Kehadiran</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSessions)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada sesi absensi.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentSessions as $s): ?>
                                    <tr>
                                        <td>
                                            <code class="fw-bold"><?= e($s['session_code']) ?></code>
                                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-muted" data-copy-code="<?= e($s['session_code']) ?>" data-copy-label="Kode Sesi" title="Salin Kode">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= e($s['course_code']) ?> - <?= e($s['course_name']) ?></div>
                                            <small class="text-muted">Kelas <?= e($s['class_name']) ?> &bull; <?= e($s['dosen_name']) ?></small>
                                        </td>
                                        <td><span class="badge bg-sky-soft"><?= (int)$s['radius_meters'] ?>m</span></td>
                                        <td><strong><?= (int)$s['present_count'] ?></strong> / <?= (int)$s['total_students'] ?></td>
                                        <td>
                                            <?php if ($s['status'] === 'open'): ?>
                                                <span class="badge badge-status-green"><span class="pulse-dot"></span>OPEN</span>
                                            <?php else: ?>
                                                <span class="badge badge-status-red">CLOSED</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($role === 'dosen'): ?>
    <!-- KPI Dosen -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div>
                    <div class="text-muted small fw-semibold">Kelas Diampu</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $myClassesCount ?></div>
                    <span class="small text-sky">Semester aktif</span>
                </div>
                <div class="stat-icon bg-sky-soft"><i class="fa-solid fa-chalkboard"></i></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card accent-emerald">
                <div>
                    <div class="text-muted small fw-semibold">Sesi GPS Aktif</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $myOpenSessions ?></div>
                    <span class="small text-success"><span class="pulse-dot"></span>Menerima Check-in</span>
                </div>
                <div class="stat-icon" style="background:rgba(16,185,129,0.14); color:#10B981;"><i class="fa-solid fa-location-dot"></i></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card accent-purple">
                <div>
                    <div class="text-muted small fw-semibold">Total Tugas</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $myTasksCount ?></div>
                    <span class="small text-muted">Auto-deadline aktif</span>
                </div>
                <div class="stat-icon" style="background:rgba(139,92,246,0.14); color:#7C3AED;"><i class="fa-solid fa-file-signature"></i></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card accent-amber">
                <div>
                    <div class="text-muted small fw-semibold">Menunggu Dinilai</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $ungradedCount ?></div>
                    <span class="small text-warning fw-medium">Submission masuk</span>
                </div>
                <div class="stat-icon" style="background:rgba(245,158,11,0.14); color:#D97706;"><i class="fa-solid fa-clipboard-check"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card-custom p-3 p-md-4 h-100">
                <h6 class="fw-bold mb-1"><i class="fa-solid fa-chart-column text-sky me-2"></i>Statistik Kelas Saya (%)</h6>
                <p class="text-muted small mb-3">Persentase kehadiran &amp; pengumpulan tugas mahasiswa.</p>
                <div style="height: 250px;">
                    <canvas id="execClassChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card-custom p-3 p-md-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-map-location-dot text-sky me-2"></i>Sesi Absensi Kelas Saya</h6>
                    <a href="<?= e(BASE_URL) ?>/dosen/attendance.php" class="btn btn-sm btn-sky">Kelola Peta</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Mata Kuliah &amp; Pertemuan</th>
                                <th>Hadir</th>
                                <th>Status</th>
                                <th class="text-end">Peta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSessions)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada sesi absensi yang dibuka.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentSessions as $s): ?>
                                    <tr>
                                        <td><code class="fw-bold"><?= e($s['session_code']) ?></code></td>
                                        <td>
                                            <div class="fw-semibold"><?= e($s['course_code']) ?> (<?= e($s['class_name']) ?>)</div>
                                            <small class="text-muted">Ke-<?= (int)$s['meeting_number'] ?>: <?= e($s['topic']) ?></small>
                                        </td>
                                        <td><strong><?= (int)$s['present_count'] ?></strong>/<?= (int)$s['total_students'] ?></td>
                                        <td>
                                            <?php if ($s['status'] === 'open'): ?>
                                                <span class="badge badge-status-green"><span class="pulse-dot"></span>OPEN</span>
                                            <?php else: ?>
                                                <span class="badge badge-status-red">CLOSED</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= e(BASE_URL) ?>/dosen/attendance.php?monitor=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                                                <i class="fa-solid fa-map-pin me-1"></i>Monitor
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($role === 'mahasiswa'): ?>
    <?php $attRate = $totalSessionsAvailable > 0 ? round(($myTotalPresent / $totalSessionsAvailable) * 100, 1) : 100; ?>
    <!-- KPI Mahasiswa -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="stat-card">
                <div>
                    <div class="text-muted small fw-semibold">Kelas Diambil</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $enrolledClassesCount ?></div>
                    <span class="small text-sky">KRS Semester Aktif</span>
                </div>
                <div class="stat-icon bg-sky-soft"><i class="fa-solid fa-book-bookmark"></i></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="stat-card accent-emerald">
                <div>
                    <div class="text-muted small fw-semibold">Sesi GPS Sedang Buka</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= count($activeSessionsForMhs) ?></div>
                    <span class="small text-success"><span class="pulse-dot"></span>Siap Check-in Lokasi</span>
                </div>
                <div class="stat-icon" style="background:rgba(16,185,129,0.14); color:#10B981;"><i class="fa-solid fa-location-crosshairs"></i></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="stat-card accent-amber">
                <div>
                    <div class="text-muted small fw-semibold">Capaian Kehadiran Saya</div>
                    <div class="fs-2 fw-extrabold stat-value mt-1"><?= $attRate ?>%</div>
                    <span class="small text-muted"><?= $myTotalPresent ?> dari <?= $totalSessionsAvailable ?> pertemuan</span>
                </div>
                <div class="stat-icon" style="background:rgba(245,158,11,0.14); color:#D97706;"><i class="fa-solid fa-user-check"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card-custom p-3 p-md-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><span class="pulse-dot"></span>Sesi Absensi GPS Sedang Dibuka</h6>
                    <a href="<?= e(BASE_URL) ?>/mahasiswa/attendance.php" class="btn btn-sm btn-sky">Buka Peta GPS</a>
                </div>
                <?php if (empty($activeSessionsForMhs)): ?>
                    <div class="text-center py-5 text-muted small">
                        <i class="fa-solid fa-satellite-dish fs-2 text-sky opacity-50 d-block mb-2"></i>
                        Tidak ada sesi absensi yang sedang terbuka saat ini.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($activeSessionsForMhs as $as): ?>
                            <div class="p-3 rounded-3 border bg-light d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                        <span class="badge bg-navy"><?= e($as['session_code']) ?></span>
                                        <span class="badge bg-sky-soft">Radius <?= (int)$as['radius_meters'] ?>m</span>
                                        <span class="badge badge-status-yellow" data-countdown="<?= e($as['closed_at']) ?>">Aktif</span>
                                    </div>
                                    <div class="fw-bold"><?= e($as['course_code']) ?> - <?= e($as['course_name']) ?> (<?= e($as['class_name']) ?>)</div>
                                    <div class="small text-muted">Pertemuan #<?= (int)$as['meeting_number'] ?>: <?= e($as['topic']) ?></div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <?php if ($as['attendance_id']): ?>
                                        <span class="badge badge-status-green"><i class="fa-solid fa-check me-1"></i>Hadir (<?= e((string)$as['distance_meters']) ?>m)</span>
                                    <?php else: ?>
                                        <a href="<?= e(BASE_URL) ?>/mahasiswa/attendance.php?session_id=<?= (int)$as['id'] ?>" class="btn btn-sm btn-sky">
                                            <i class="fa-solid fa-location-dot me-1"></i>Absen
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card-custom p-3 p-md-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-check text-sky me-2"></i>Tugas &amp; Deadline Terdekat</h6>
                    <a href="<?= e(BASE_URL) ?>/mahasiswa/tasks.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Semua Tugas</a>
                </div>
                <?php if (empty($mhsTasks)): ?>
                    <div class="text-center py-5 text-muted small">Belum ada tugas pada kelas Anda.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($mhsTasks as $t): ?>
                            <?php $cd = getDeadlineCountdown($t['deadline']); ?>
                            <div class="p-3 rounded-3 border bg-light d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="fw-bold"><?= e($t['task_code']) ?></code>
                                        <span class="badge <?= $cd['expired'] ? 'badge-status-red' : 'badge-status-green' ?>"
                                              <?= !$cd['expired'] ? 'data-countdown="' . e($t['deadline']) . '"' : '' ?>>
                                            <?= e($cd['label']) ?>
                                        </span>
                                    </div>
                                    <div class="fw-semibold mt-1"><?= e($t['title']) ?></div>
                                    <div class="small text-muted"><?= e($t['course_code']) ?> - <?= e($t['course_name']) ?></div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <?php if ($t['submission_id']): ?>
                                        <?php if ($t['review_status'] === 'graded'): ?>
                                            <span class="badge badge-status-green d-block mb-1">Nilai: <?= e((string)$t['score']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-status-yellow d-block mb-1">Terkumpul</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-status-red d-block mb-1">Belum Kumpul</span>
                                    <?php endif; ?>
                                    <a href="<?= e(BASE_URL) ?>/mahasiswa/tasks.php?task_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Buka</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($chartClasses)): ?>
<?php
$labels = [];
$attRates = [];
$subRates = [];
foreach ($chartClasses as $cc) {
    $labels[] = $cc['label'];
    $maxAtt = (int)$cc['mhs_count'] * (int)$cc['sess_count'];
    $maxSub = (int)$cc['mhs_count'] * (int)$cc['task_count'];
    $attRates[] = $maxAtt > 0 ? round(((int)$cc['att_count'] / $maxAtt) * 100, 1) : 0;
    $subRates[] = $maxSub > 0 ? round(((int)$cc['sub_count'] / $maxSub) * 100, 1) : 0;
}
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('execClassChart');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    {
                        label: 'Kehadiran GPS (%)',
                        data: <?= json_encode($attRates) ?>,
                        backgroundColor: 'rgba(14, 165, 233, 0.85)',
                        borderRadius: 8
                    },
                    {
                        label: 'Kumpul Tugas (%)',
                        data: <?= json_encode($subRates) ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.82)',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } }
                },
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
