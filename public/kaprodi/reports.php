<?php
/**
 * Modul Laporan Akademik, Visualisasi Grafik & Peta Kampus (Kaprodi, Admin, dan Dosen)
 * Fitur Lengkap Spesifikasi 5 & 6.5:
 * - Filter dinamis Semester, Mata Kuliah, dan Kelas (tervalidasi RBAC)
 * - Grafik Analitik Chart.js & Peta Sebaran Lokasi Sesi Absensi GPS (Leaflet)
 * - Statistik Persentase Kehadiran & Pengumpulan Tugas per Kelas / Mahasiswa
 * - Export CSV (UTF-8 BOM Excel) & Print View
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['kaprodi', 'admin', 'dosen']);

$pdo = getDB();
syncExpiredAttendanceSessions($pdo);

$user = currentUser();
$role = $user['role'];
$userId = (int)$user['id'];
$pageTitle = 'Laporan Akademik & Peta Monitoring';

$semesters = $pdo->query("SELECT * FROM semesters ORDER BY is_active DESC, id DESC")->fetchAll();
$activeSemId = 0;
foreach ($semesters as $s) {
    if ((int)$s['is_active'] === 1) {
        $activeSemId = (int)$s['id'];
        break;
    }
}

$filterSemester = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : $activeSemId;
$filterCourse   = (int)($_GET['course_id'] ?? 0);
$filterClass    = (int)($_GET['class_id'] ?? 0);

// Daftar Mata Kuliah sesuai role & semester
$courseParams = [];
$courseSql = "
    SELECT c.id, c.course_code, c.course_name, u.full_name AS dosen_name
    FROM courses c
    JOIN users u ON c.dosen_id = u.id
    WHERE 1=1
";
if ($filterSemester > 0) {
    $courseSql .= " AND c.semester_id = ?";
    $courseParams[] = $filterSemester;
}
if ($role === 'dosen') {
    $courseSql .= " AND c.dosen_id = ?";
    $courseParams[] = $userId;
}
$courseSql .= " ORDER BY c.course_code ASC";
$cStmt = $pdo->prepare($courseSql);
$cStmt->execute($courseParams);
$coursesList = $cStmt->fetchAll();

// Validasi apakah filterCourse ada di coursesList
$validCourseIds = array_map(fn($r) => (int)$r['id'], $coursesList);
if ($filterCourse > 0 && !in_array($filterCourse, $validCourseIds, true)) {
    $filterCourse = 0;
}

// Daftar Kelas sesuai filter
$classParams = [];
$classSql = "
    SELECT cl.id, cl.class_name, c.course_code, c.course_name, u.full_name AS dosen_name
    FROM classes cl
    JOIN courses c ON cl.course_id = c.id
    JOIN users u ON c.dosen_id = u.id
    WHERE 1=1
";
if ($filterSemester > 0) {
    $classSql .= " AND c.semester_id = ?";
    $classParams[] = $filterSemester;
}
if ($filterCourse > 0) {
    $classSql .= " AND c.id = ?";
    $classParams[] = $filterCourse;
}
if ($role === 'dosen') {
    $classSql .= " AND c.dosen_id = ?";
    $classParams[] = $userId;
}
$classSql .= " ORDER BY c.course_code ASC, cl.class_name ASC";
$clStmt = $pdo->prepare($classSql);
$clStmt->execute($classParams);
$classesList = $clStmt->fetchAll();

// Validasi RBAC & konsistensi filterClass terhadap daftar kelas yang diizinkan
$validClassIds = array_map(fn($r) => (int)$r['id'], $classesList);
if ($filterClass === 0 || !in_array($filterClass, $validClassIds, true)) {
    $filterClass = !empty($validClassIds) ? $validClassIds[0] : 0;
}

// Rekapitulasi per Kelas / Mata Kuliah
$summarySql = "
    SELECT cl.id AS class_id, cl.class_name, cl.room,
           c.course_code, c.course_name, c.sks,
           u.full_name AS dosen_name, s.name AS semester_name,
           (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = cl.id) AS total_students,
           (SELECT COUNT(*) FROM attendance_sessions asess WHERE asess.class_id = cl.id) AS total_sessions,
           (SELECT COUNT(*) FROM attendances a JOIN attendance_sessions asess ON a.session_id = asess.id WHERE asess.class_id = cl.id AND a.status IN ('hadir','terlambat')) AS total_present_records,
           (SELECT COUNT(*) FROM tasks t WHERE t.class_id = cl.id) AS total_tasks,
           (SELECT COUNT(*) FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE t.class_id = cl.id) AS total_submissions,
           (SELECT ROUND(AVG(ts.score), 2) FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE t.class_id = cl.id AND ts.score IS NOT NULL) AS avg_task_score
    FROM classes cl
    JOIN courses c ON cl.course_id = c.id
    JOIN users u ON c.dosen_id = u.id
    JOIN semesters s ON c.semester_id = s.id
    WHERE 1=1
";
$sumParams = [];
if ($filterSemester > 0) {
    $summarySql .= " AND c.semester_id = ?";
    $sumParams[] = $filterSemester;
}
if ($filterCourse > 0) {
    $summarySql .= " AND c.id = ?";
    $sumParams[] = $filterCourse;
}
if ($role === 'dosen') {
    $summarySql .= " AND c.dosen_id = ?";
    $sumParams[] = $userId;
}
$summarySql .= " ORDER BY c.course_code ASC, cl.class_name ASC";
$sumStmt = $pdo->prepare($summarySql);
$sumStmt->execute($sumParams);
$classSummaries = $sumStmt->fetchAll();

// Sesi GPS untuk Peta Monitoring Kampus
$mapSql = "
    SELECT s.id, s.session_code, s.meeting_number, s.topic, s.target_lat, s.target_lng, s.radius_meters, s.status,
           cl.class_name, c.course_code, c.course_name, u.full_name AS dosen_name,
           (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status IN ('hadir','terlambat')) AS present_count,
           (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = s.class_id) AS total_students
    FROM attendance_sessions s
    JOIN classes cl ON s.class_id = cl.id
    JOIN courses c ON cl.course_id = c.id
    JOIN users u ON s.dosen_id = u.id
    WHERE 1=1
";
$mapParams = [];
if ($filterSemester > 0) {
    $mapSql .= " AND c.semester_id = ?";
    $mapParams[] = $filterSemester;
}
if ($role === 'dosen') {
    $mapSql .= " AND c.dosen_id = ?";
    $mapParams[] = $userId;
}
$mapSql .= " ORDER BY s.opened_at DESC LIMIT 25";
$mapStmt = $pdo->prepare($mapSql);
$mapStmt->execute($mapParams);
$campusSessions = $mapStmt->fetchAll();

// Rekap Detail per Mahasiswa pada Kelas Terpilih ($filterClass)
$studentReports = [];
$selectedClassInfo = null;

if ($filterClass > 0) {
    $infoStmt = $pdo->prepare("
        SELECT cl.*, c.course_code, c.course_name, c.sks, u.full_name AS dosen_name, s.name AS semester_name,
               (SELECT COUNT(*) FROM attendance_sessions WHERE class_id = cl.id) AS total_sessions,
               (SELECT COUNT(*) FROM tasks WHERE class_id = cl.id) AS total_tasks
        FROM classes cl
        JOIN courses c ON cl.course_id = c.id
        JOIN users u ON c.dosen_id = u.id
        JOIN semesters s ON c.semester_id = s.id
        WHERE cl.id = ?
    ");
    $infoStmt->execute([$filterClass]);
    $selectedClassInfo = $infoStmt->fetch();

    if ($selectedClassInfo) {
        $mhsStmt = $pdo->prepare("
            SELECT u.id AS student_id, u.nim_nidn, u.full_name, u.email,
                   (SELECT COUNT(*) FROM attendances a JOIN attendance_sessions s ON a.session_id = s.id WHERE s.class_id = ? AND a.student_id = u.id AND a.status IN ('hadir','terlambat')) AS attended_count,
                   (SELECT COUNT(*) FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE t.class_id = ? AND ts.student_id = u.id) AS submitted_tasks_count,
                   (SELECT ROUND(AVG(ts.score), 2) FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE t.class_id = ? AND ts.student_id = u.id AND ts.score IS NOT NULL) AS avg_score
            FROM class_enrollments ce
            JOIN users u ON ce.student_id = u.id
            WHERE ce.class_id = ?
            ORDER BY u.nim_nidn ASC, u.full_name ASC
        ");
        $mhsStmt->execute([$filterClass, $filterClass, $filterClass, $filterClass]);
        $studentReports = $mhsStmt->fetchAll();
    }
}

// Handle Export CSV (Spesifikasi 6.5)
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $selectedClassInfo) {
    $filename = sprintf('Rekap_AATMS_%s_%s_%s.csv', $selectedClassInfo['course_code'], $selectedClassInfo['class_name'], date('Ymd_His'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, ['LAPORAN AKADEMIK KEHADIRAN & TUGAS MAHASISWA (AATMS)']);
    fputcsv($out, ['Mata Kuliah', $selectedClassInfo['course_code'] . ' - ' . $selectedClassInfo['course_name']]);
    fputcsv($out, ['Kelas', $selectedClassInfo['class_name']]);
    fputcsv($out, ['Dosen Pengampu', $selectedClassInfo['dosen_name']]);
    fputcsv($out, ['Semester', $selectedClassInfo['semester_name']]);
    fputcsv($out, ['Waktu Cetak', formatDateTime(date('Y-m-d H:i:s'))]);
    fputcsv($out, []);

    fputcsv($out, [
        'No', 'NIM', 'Nama Mahasiswa', 'Hadir Pertemuan', 'Total Pertemuan',
        'Persentase Kehadiran (%)', 'Tugas Terkumpul', 'Total Tugas', 'Rata-Rata Nilai Tugas'
    ]);

    $totSess = max(0, (int)$selectedClassInfo['total_sessions']);
    $totTasks = max(0, (int)$selectedClassInfo['total_tasks']);

    foreach ($studentReports as $idx => $sr) {
        $attPct = $totSess > 0 ? round(((int)$sr['attended_count'] / $totSess) * 100, 1) : 0;
        fputcsv($out, [
            $idx + 1,
            $sr['nim_nidn'] ?? '-',
            $sr['full_name'],
            (int)$sr['attended_count'],
            $totSess,
            $attPct . '%',
            (int)$sr['submitted_tasks_count'],
            $totTasks,
            $sr['avg_score'] !== null ? $sr['avg_score'] : '0.00'
        ]);
    }
    fclose($out);
    exit;
}

$extraScripts = [BASE_URL . '/assets/js/gps-attendance.js'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2 d-print-none">
    <div>
        <h4 class="fw-bold mb-1">Laporan Akademik &amp; Peta Monitoring Kampus</h4>
        <p class="text-muted small mb-0">Evaluasi persentase kehadiran GPS, capaian pengumpulan tugas, dan peta sebaran perkuliahan.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($selectedClassInfo): ?>
            <a href="?semester_id=<?= $filterSemester ?>&course_id=<?= $filterCourse ?>&class_id=<?= $filterClass ?>&export=csv" class="btn btn-success btn-sm fw-semibold">
                <i class="fa-solid fa-file-csv me-1"></i>Export CSV (Excel)
            </a>
        <?php endif; ?>
        <button type="button" onclick="window.print()" class="btn btn-navy btn-sm">
            <i class="fa-solid fa-print me-1"></i>Cetak Laporan (Print View)
        </button>
    </div>
</div>

<!-- Header Cetak -->
<div class="d-none d-print-block mb-4 border-bottom pb-3">
    <h4 class="fw-bold mb-1"><?= e(APP_FULL_NAME) ?></h4>
    <div class="small">Laporan Rekapitulasi Akademik Kehadiran GPS &amp; Tugas Mahasiswa &bull; Dicetak pada: <?= e(formatDateTime(date('Y-m-d H:i:s'))) ?></div>
</div>

<!-- Filter Bar -->
<div class="card-custom p-3 mb-4 d-print-none">
    <form method="GET" action="" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Semester Akademik</label>
            <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">Semua Semester</option>
                <?php foreach ($semesters as $sem): ?>
                    <option value="<?= (int)$sem['id'] ?>" <?= (int)$sem['id'] === $filterSemester ? 'selected' : '' ?>>
                        <?= e($sem['name']) ?><?= (int)$sem['is_active'] === 1 ? ' (Aktif)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Mata Kuliah</label>
            <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">Semua Mata Kuliah</option>
                <?php foreach ($coursesList as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $filterCourse ? 'selected' : '' ?>>
                        <?= e($c['course_code']) ?> - <?= e($c['course_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Kelas untuk Detail Mahasiswa</label>
            <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($classesList as $cl): ?>
                    <option value="<?= (int)$cl['id'] ?>" <?= (int)$cl['id'] === $filterClass ? 'selected' : '' ?>>
                        <?= e($cl['course_code']) ?> - <?= e($cl['course_name']) ?> (Kelas <?= e($cl['class_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <a href="reports.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
        </div>
    </form>
</div>

<!-- Visualisasi Analitik & Peta Sebaran Sesi GPS -->
<?php if (!empty($classSummaries) || !empty($campusSessions)): ?>
<div class="row g-4 mb-4 d-print-none">
    <div class="col-lg-6">
        <div class="card-custom p-3 p-md-4 h-100">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-chart-column text-sky me-2"></i>Grafik Capaian Kehadiran &amp; Tugas</h6>
            <p class="text-muted small mb-3">Perbandingan rata-rata kehadiran GPS, pengumpulan tugas, dan nilai per kelas.</p>
            <div style="height: 290px;">
                <canvas id="reportsChartCanvas"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom p-3 p-md-4 h-100">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-map-location-dot text-sky me-2"></i>Peta Monitoring Sesi Absensi GPS Kampus</h6>
            <p class="text-muted small mb-3">Titik lokasi kelas dan lingkaran radius toleransi dari sesi absensi yang tercatat.</p>
            <?php if (empty($campusSessions)): ?>
                <div class="text-center text-muted py-5 small">Belum ada titik koordinat sesi absensi pada filter ini.</div>
            <?php else: ?>
                <div id="campusOverviewMap" class="leaflet-map-box" style="height: 290px;"></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tabel 1: Ringkasan Statistik per Mata Kuliah & Kelas -->
<div class="card-custom p-3 p-md-4 mb-4">
    <h6 class="fw-bold mb-3"><i class="fa-solid fa-table-list text-sky me-2"></i>Rekapitulasi Per Mata Kuliah &amp; Kelas</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Kode &amp; Mata Kuliah</th>
                    <th>Kelas</th>
                    <th>Dosen Pengampu</th>
                    <th>Peserta</th>
                    <th>Sesi GPS</th>
                    <th>Kehadiran</th>
                    <th>Tugas</th>
                    <th>Kumpul Tugas</th>
                    <th>Rata-rata Nilai</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classSummaries)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data kelas pada filter ini.</td></tr>
                <?php else: ?>
                    <?php
                    $chartLabels = [];
                    $chartAtt = [];
                    $chartSub = [];
                    $chartAvg = [];
                    ?>
                    <?php foreach ($classSummaries as $cs): ?>
                        <?php
                        $mhsCount = (int)$cs['total_students'];
                        $sessCount = (int)$cs['total_sessions'];
                        $taskCount = (int)$cs['total_tasks'];

                        $maxPossibleAtt = $mhsCount * $sessCount;
                        $attPercent = $maxPossibleAtt > 0 ? round(((int)$cs['total_present_records'] / $maxPossibleAtt) * 100, 1) : 0;

                        $maxPossibleSub = $mhsCount * $taskCount;
                        $subPercent = $maxPossibleSub > 0 ? round(((int)$cs['total_submissions'] / $maxPossibleSub) * 100, 1) : 0;

                        $chartLabels[] = $cs['course_code'] . ' (' . $cs['class_name'] . ')';
                        $chartAtt[] = $attPercent;
                        $chartSub[] = $subPercent;
                        $chartAvg[] = $cs['avg_task_score'] !== null ? (float)$cs['avg_task_score'] : 0;
                        ?>
                        <tr class="<?= (int)$cs['class_id'] === $filterClass ? 'table-active' : '' ?>">
                            <td>
                                <span class="badge bg-sky me-1"><?= e($cs['course_code']) ?></span>
                                <span class="fw-semibold"><?= e($cs['course_name']) ?></span>
                            </td>
                            <td>
                                <a href="?semester_id=<?= $filterSemester ?>&course_id=<?= $filterCourse ?>&class_id=<?= (int)$cs['class_id'] ?>" class="badge bg-navy text-decoration-none">
                                    <?= e($cs['class_name']) ?>
                                </a>
                            </td>
                            <td><?= e($cs['dosen_name']) ?></td>
                            <td><?= $mhsCount ?> Mhs</td>
                            <td><?= $sessCount ?> Sesi</td>
                            <td>
                                <span class="badge <?= $attPercent >= 75 ? 'badge-status-green' : ($attPercent >= 50 ? 'badge-status-yellow' : 'badge-status-red') ?>">
                                    <?= $attPercent ?>%
                                </span>
                            </td>
                            <td><?= $taskCount ?> Tugas</td>
                            <td>
                                <span class="badge <?= $subPercent >= 75 ? 'badge-status-green' : 'badge-status-yellow' ?>">
                                    <?= $subPercent ?>% (<?= (int)$cs['total_submissions'] ?>/<?= $maxPossibleSub ?>)
                                </span>
                            </td>
                            <td>
                                <strong><?= $cs['avg_task_score'] !== null ? e((string)$cs['avg_task_score']) : '-' ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tabel 2: Rekapitulasi Detail Per Mahasiswa pada Kelas Terpilih -->
<?php if ($selectedClassInfo): ?>
<div class="card-custom p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h6 class="fw-bold mb-0">
                <i class="fa-solid fa-user-graduate text-sky me-2"></i>Detail Kehadiran &amp; Nilai Mahasiswa —
                <?= e($selectedClassInfo['course_code']) ?> <?= e($selectedClassInfo['course_name']) ?> (Kelas <?= e($selectedClassInfo['class_name']) ?>)
            </h6>
            <small class="text-muted">
                Dosen: <?= e($selectedClassInfo['dosen_name']) ?> &bull;
                Total Pertemuan: <strong><?= (int)$selectedClassInfo['total_sessions'] ?></strong> &bull;
                Total Tugas: <strong><?= (int)$selectedClassInfo['total_tasks'] ?></strong>
            </small>
        </div>
        <div class="d-print-none">
            <input type="text" class="form-control form-control-sm" placeholder="Cari NIM / Nama mahasiswa..." data-table-search="#studentReportTable">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="studentReportTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>NIM</th>
                    <th>Nama Mahasiswa</th>
                    <th>Kehadiran Pertemuan</th>
                    <th>Persentase Kehadiran</th>
                    <th>Pengumpulan Tugas</th>
                    <th>Rata-rata Nilai Tugas</th>
                    <th>Status Akademik</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($studentReports)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada mahasiswa terdaftar di kelas ini.</td></tr>
                <?php else: ?>
                    <?php
                    $totSessions = (int)$selectedClassInfo['total_sessions'];
                    $totTasks = (int)$selectedClassInfo['total_tasks'];
                    ?>
                    <?php foreach ($studentReports as $idx => $sr): ?>
                        <?php
                        $attended = (int)$sr['attended_count'];
                        $pctAtt = $totSessions > 0 ? round(($attended / $totSessions) * 100, 1) : 0;
                        $subTasks = (int)$sr['submitted_tasks_count'];
                        ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><code><?= e($sr['nim_nidn'] ?? '-') ?></code></td>
                            <td class="fw-semibold"><?= e($sr['full_name']) ?></td>
                            <td><?= $attended ?> / <?= $totSessions ?> Pertemuan</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 7px; max-width: 90px;">
                                        <div class="progress-bar <?= $pctAtt >= 75 ? 'bg-success' : ($pctAtt >= 50 ? 'bg-warning' : 'bg-danger') ?>"
                                             style="width: <?= min(100, $pctAtt) ?>%"></div>
                                    </div>
                                    <span class="fw-semibold small"><?= $pctAtt ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= ($totTasks > 0 && $subTasks === $totTasks) ? 'badge-status-green' : ($subTasks > 0 ? 'badge-status-yellow' : 'badge-status-red') ?>">
                                    <?= $subTasks ?> / <?= $totTasks ?> Tugas
                                </span>
                            </td>
                            <td>
                                <?php if ($sr['avg_score'] !== null): ?>
                                    <span class="fw-bold text-slate-800"><?= e((string)$sr['avg_score']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pctAtt >= 75): ?>
                                    <span class="badge badge-status-green">Memenuhi Syarat</span>
                                <?php else: ?>
                                    <span class="badge badge-status-red">Kurang Kehadiran (&lt;75%)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    <?php if (!empty($campusSessions)): ?>
    AATMS_GPS.initCampusOverviewMap({
        mapId: 'campusOverviewMap',
        sessions: <?= json_encode($campusSessions) ?>
    });
    <?php endif; ?>

    <?php if (!empty($classSummaries)): ?>
    const ctx = document.getElementById('reportsChartCanvas');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels ?? []) ?>,
                datasets: [
                    {
                        label: 'Kehadiran GPS (%)',
                        data: <?= json_encode($chartAtt ?? []) ?>,
                        backgroundColor: 'rgba(14, 165, 233, 0.85)',
                        borderRadius: 6
                    },
                    {
                        label: 'Kumpul Tugas (%)',
                        data: <?= json_encode($chartSub ?? []) ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderRadius: 6
                    },
                    {
                        label: 'Rata-rata Nilai',
                        data: <?= json_encode($chartAvg ?? []) ?>,
                        backgroundColor: 'rgba(139, 92, 246, 0.82)',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
