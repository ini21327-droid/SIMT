<?php
/**
 * Modul Mahasiswa: Riwayat Kehadiran GPS & Rekap Nilai Tugas
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('mahasiswa');

$pdo = getDB();
$user = currentUser();
$studentId = (int)$user['id'];
$pageTitle = 'Riwayat Kehadiran & Nilai Tugas';

// Riwayat absensi mahasiswa
$attStmt = $pdo->prepare("
    SELECT s.session_code, s.meeting_number, s.topic, s.opened_at, s.radius_meters,
           cl.class_name, c.course_code, c.course_name,
           a.check_in_time, a.distance_meters, a.gps_accuracy, a.device_info, a.device_fingerprint, a.status, a.location_valid
    FROM attendance_sessions s
    JOIN class_enrollments ce ON s.class_id = ce.class_id
    JOIN classes cl ON s.class_id = cl.id
    JOIN courses c ON cl.course_id = c.id
    LEFT JOIN attendances a ON a.session_id = s.id AND a.student_id = ?
    WHERE ce.student_id = ?
    ORDER BY s.opened_at DESC
");
$attStmt->execute([$studentId, $studentId]);
$attendanceHistory = $attStmt->fetchAll();

// Riwayat nilai tugas mahasiswa
$taskStmt = $pdo->prepare("
    SELECT t.task_code, t.title, t.deadline,
           cl.class_name, c.course_code, c.course_name,
           ts.submitted_at, ts.score, ts.feedback, ts.review_status
    FROM tasks t
    JOIN class_enrollments ce ON t.class_id = ce.class_id
    JOIN classes cl ON t.class_id = cl.id
    JOIN courses c ON cl.course_id = c.id
    LEFT JOIN task_submissions ts ON ts.task_id = t.id AND ts.student_id = ?
    WHERE ce.student_id = ?
    ORDER BY t.deadline DESC
");
$taskStmt->execute([$studentId, $studentId]);
$taskHistory = $taskStmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-4">
    <!-- Tabel Riwayat Absensi GPS -->
    <div class="col-12">
        <div class="card-custom p-3 p-md-4">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-location-dot text-sky me-2"></i>Riwayat Kehadiran Perkuliahan (GPS)</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode Sesi</th>
                            <th>Mata Kuliah &amp; Kelas</th>
                            <th>Pertemuan &amp; Topik</th>
                            <th>Perangkat Terdeteksi</th>
                            <th>Waktu Check-In</th>
                            <th>Jarak GPS</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendanceHistory)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat sesi absensi.</td></tr>
                        <?php else: ?>
                            <?php foreach ($attendanceHistory as $ah): ?>
                                <tr>
                                    <td><code><?= e($ah['session_code']) ?></code></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($ah['course_code']) ?> - <?= e($ah['course_name']) ?></div>
                                        <small class="text-muted">Kelas <?= e($ah['class_name']) ?></small>
                                    </td>
                                    <td>
                                        <div>Pertemuan #<?= (int)$ah['meeting_number'] ?></div>
                                        <small class="text-muted"><?= e($ah['topic']) ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($ah['device_info'])): ?>
                                            <div class="small fw-medium"><?= e($ah['device_info']) ?></div>
                                            <code class="small text-muted"><?= e($ah['device_fingerprint'] ?? '') ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= !empty($ah['check_in_time']) ? e(formatDateTime($ah['check_in_time'])) : '-' ?></td>
                                    <td>
                                        <?php if ($ah['distance_meters'] !== null): ?>
                                            <span class="badge bg-light text-dark border"><?= e((string)$ah['distance_meters']) ?> m</span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $st = $ah['status'] ?? null;
                                        if ($st === 'hadir') {
                                            echo '<span class="badge badge-status-green">HADIR</span>';
                                        } elseif ($st === 'terlambat') {
                                            echo '<span class="badge badge-status-yellow">TERLAMBAT</span>';
                                        } elseif ($st === 'izin' || $st === 'sakit') {
                                            echo '<span class="badge badge-status-blue">' . e(strtoupper($st)) . '</span>';
                                        } else {
                                            echo '<span class="badge badge-status-red">ALPHA / BELUM ABSEN</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat Pengumpulan & Nilai Tugas -->
    <div class="col-12">
        <div class="card-custom p-3 p-md-4">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-clipboard-check text-sky me-2"></i>Rekap Pengumpulan & Nilai Tugas</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode Tugas</th>
                            <th>Judul Tugas & Mata Kuliah</th>
                            <th>Waktu Kumpul</th>
                            <th>Status Review</th>
                            <th>Nilai</th>
                            <th>Feedback Dosen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($taskHistory)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data tugas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($taskHistory as $th): ?>
                                <tr>
                                    <td><code><?= e($th['task_code']) ?></code></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($th['title']) ?></div>
                                        <small class="text-muted"><?= e($th['course_code']) ?> - <?= e($th['course_name']) ?> (<?= e($th['class_name']) ?>)</small>
                                    </td>
                                    <td class="small"><?= !empty($th['submitted_at']) ? e(formatDateTime($th['submitted_at'])) : '<span class="text-danger">Belum mengumpulkan</span>' ?></td>
                                    <td>
                                        <?php if (empty($th['submitted_at'])): ?>
                                            <span class="badge badge-status-red">BELUM MENGUMPULKAN</span>
                                        <?php elseif ($th['review_status'] === 'graded'): ?>
                                            <span class="badge badge-status-green">GRADED</span>
                                        <?php else: ?>
                                            <span class="badge badge-status-yellow">SUBMITTED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($th['score'] !== null): ?>
                                            <span class="fw-bold fs-6 text-success"><?= e((string)$th['score']) ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= e($th['feedback'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

